import base64
from card import *
from classes import *
from machine_code import *
from time import sleep
from browser import launch
from queue import Queue
import binascii, json, os, requests, threading, sys
import asyncio,websockets

keyBits = 2048
#confDir = '/etc/voted/'
confDir = './'
uriBase = "https://{server name}"
apiBase = uriBase + "/api"
kiosk   = False
# Log Levels:
LOG_NONE = 0
INFO = 1
DEBUG = 2
logLevel = DEBUG

def apiGet(session,uri,data):
  uri=apiBase+uri
  headers={'Content-Type':'application/json'}
  s = session
  r = s.get(uri,headers=headers,json=data)
  if (r.status_code == 200):
    try:
      return r.json()
    except requests.JSONDecodeError as e:
      if logLevel == DEBUG:
        print('JSON decode error: '+e)
      return False
  return False

def apiPost(session,uri,data):
  uri=apiBase+uri
  headers={'Content-Type':'application/json'}
  s = session
  r = s.post(uri,headers=headers,json=data)
  if (r.status_code == 200):
    try:
      return r.json()
    except requests.JSONDecodeError as e:
      if logLevel >= DEBUG:
        print('JSON decode error: '+e)
      return False
  if (r.status_code == 500 and logLevel >= DEBUG):
    print('Error in request, check weblogs')
  return False

def authenticate(reader):
  print(reader)

def getSession(session,backoff,systemKey):
  public, private = systemKey["public"], systemKey["private"]
  pub = base64.b64encode(public).decode("utf-8")
  get_challenge = apiPost(session,'/get/session',{
    'type':'machine',
    'publicKey':pub
  })
  if (get_challenge != False):
    if "mid" in get_challenge and "display" in get_challenge and "challenge" in get_challenge:
      # Machine exists with login challenge
      # sign challenge and send id,display,pubkey, and challenge signature
      # Base64 encode the signature so it can be converted to a string and json serialized.
      # Work will need to be done on the php side to base64 decode before validating the challenge
      signature = base64.b64encode(signMachineMessage(systemKey,get_challenge['challenge'])).decode('utf-8')
      if (signature != False):
        # Signature is valid/successful
        # Get the salt length of the signature to pass with our request
        # https://github.com/pyca/cryptography/issues/3008
        saltLength = padding.calculate_max_pss_salt_length(serialization.load_pem_public_key(public), hashes.SHA256())
        get_session = apiPost(session,'/get/session',{
          'type':'machine',
          'mid':get_challenge['mid'],
          'response':signature,
          'saltLength':saltLength
        })
        if (get_session != False):
          if "sid" in get_session and "mid" in get_session and "did" in get_session:
            # Got a valid session! Yay!
            return get_session
        wait_value = backoff.increment()
        print('Failed to get session. Waiting '+str(wait_value)+' seconds before trying again.')
        sleep(wait_value)
        getSession(session,backoff,systemKey)
    elif "mid" in get_challenge and "display" in get_challenge:
      wait_value = backoff.increment()
      print('Machine Display ID ('+get_challenge["display"]+') not activated. Waiting '+str(wait_value)+' seconds before checking for activation again.')
      sleep(wait_value)
      getSession(session,backoff,systemKey)
    else:
      wait_value = backoff.increment()
      print('Machine not found. Waiting '+str(wait_value)+' seconds before trying again.')
      sleep(wait_value)
      getSession(session,backoff,systemKey)
  else:
    wait_value = backoff.increment()
    if (wait_value > backoff.maxBackoff):
      print('Attempts to get session exceeded. Returning False.')
      return False
    print('Failed to get initial phase. Waiting '+str(wait_value)+' seconds before trying again.')
    sleep(wait_value)
    getSession(session,backoff,systemKey)

  print('Attempts to get session exceeded. Returning False.')
  return False

def monitor_card(reader):
  print(reader)

def monitor_session():
  print("monitor_session")

def threadWS(toWSThread,fromWSThread):
  # Thank you ZachL: https://stackoverflow.com/a/72220058
  try:
    loop = asyncio.get_event_loop()
  except RuntimeError as e:
    if str(e).startswith('There is no current event loop in thread'):
      l2 = asyncio.new_event_loop()
      asyncio.set_event_loop(l2)
      loop = asyncio.get_event_loop()
    else:
      raise
  finally:
    start_server = websockets.serve(Server(toWSThread,fromWSThread).handler,'localhost',1776)
    loop.run_until_complete(start_server)
    loop.run_forever()

def main():
  backoff = Backoff()
  requests_session = requests.session()
  while True:
    # Check if system key exists
    systemKey = getSystemKey()
    if (systemKey == False):
      sleep(backoff.increment())
      continue
    # Connect to api and get a session
    session = getSession(requests_session,Backoff(),systemKey)
    if (session == False):
      sleep(backoff.increment())
      continue
    else:
      # Got a valid session, leave while loop and go back to main function
      break

  # Start websocket server and queues
  toWSThread = Queue()
  fromWSThread = Queue()
  thread_ws = threading.Thread(target=threadWS,args=(toWSThread,fromWSThread,),daemon=True)
  thread_ws.start()
  # Start web browser with login session
  launch(uriBase+'/?sid='+session['sid']+'&mid='+session['mid'],kiosk)

  #Setup PKCS environment
  setPKCSenv()

  # Check if card readers/card exist on system
  readers = getReaders()
  while readers is False:
    timeout = backoff.increment()
    print('No card reader found. Waiting '+timeout+' seconds for a reader.')
    toWSThread.put('No Card Reader')
    sleep(timeout)
    readers = getReaders()

  # Set first reader as reader
  reader = readers[0]

  # Wait for card if run is true
  run = True
  while run:
    # Ensure a card is in the reader
    # Wait for a card before continuing with the rest of the loop
    # Only wait 2 seconds between checks, not full backoff
    present = cardPresent(reader)
    if (present == False):
      toWSThread.put('No Card')
      if not fromWSThread.empty:
        print(fromWSThread.get())
      print('No Card')
      sleep(2)
      continue

    # If Card is not valid, restart loop:
    card = getCard(reader)
    if (card == False):
      toWSThread.put('Invalid Card')
      if not fromWSThread.empty:
        print(fromWSThread.get())
      sleep(backoff.increment())
      continue
  
    # Check info about the card
    # Determine what type of card it is
    default_pin = False
    if (card.label == 'PIV_II'):
      # Is likely YubiCo
      default_pin = 123456
      if (card.serial == 00000000):
        toWSThread.put('Card Not Initialized')
        print('Card Not Initializled')
        sleep(backoff.increment())
        continue
    elif card.label.startswith('PIVKey'):
      # Is Likely Smart Card
      default_pin = 000000

    # Card is compatiable with the system (more or less)
    # Try to authenticate - Update web interface to show
    # That the card was inserted and authentication is
    # being attempted
    toWSThread.put('Card Compatible')
    
    # Check to see if the card is enrolled in the database as a person
    pin = apiPost(requests_session,'/get/pin',{
    'serial':str(card.serial)
    })
    #pin != False and
    if "card_status" in pin:
      # card data request succeeded
      # process the data
      if pin['card_status'] == 'Card Enrolled' and 'cid' in pin:
        print(pin['card_status'])
        toWSThread.put(pin['card_status'])
        challenge = apiPost(requests_session,'/get/citizen_challenge',{
          'cid':pin['cid'],
          'serial':card.serial
        })
        if challenge != False and 'challenge' in challenge:
          print('got a challenge: '+challenge)
      elif pin['card_status'] == 'Default Pin' and 'cid' in pin:
        print(pin['card_status'])
        toWSThread.put(pin['card_status'])
        pin['pin'] = default_pin
      elif pin['card_status'] == 'Card Not Enrolled':
        print(pin['card_status'])
        toWSThread.put(pin['card_status'])
      else:
        print('Some other error occured while attempting to get card pin data')
    else:
      # card request did not complete successfully
      print('Some other error occured while attempting to get card pin data')
        
    #if fromWSThread.not_empty:
    #  print(fromWSThread.get())
    sleep(10)
    quit()

    # Valid session has been established with api
    # Card is present in found reader
    # Card class is valid

    # Start a thread to monitor if the card gets ejected
    thread_monitor_card = threading.Thread(target=monitor_card, args=(reader,))
    thread_monitor_card.start()
    # Start a thread to monitor if the session expires
    thread_monitor_session = threading.Thread(target=monitor_session)
    thread_monitor_session.start()
    # Start a thread to handle using the card
    thread_handle_card = threading.Thread(target=handle_card)
    thread_handle_card.start()


    # Wait for threads to finish before continuing
    thread_monitor_card.join()
    thread_monitor_session.join()
    thread_handle_card.join()

    sleep(backoff)

if (__name__ == "__main__"):
  main()