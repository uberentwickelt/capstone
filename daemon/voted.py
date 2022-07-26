import base64
#import cryptography
from cryptography.exceptions import InvalidSignature, UnsupportedAlgorithm
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding, rsa
#from cryptography import x509
#from cryptography.x509.oid import NameOID
from PyKCS11 import *
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from os.path import exists
from time import sleep
import binascii, json, os, requests, threading
#import datetime
try:
  from os import scandir
except ImportError:
  from scandir import scandir
pkcs11 = PyKCS11Lib()

keyBits = 2048
#confDir = '/etc/voted/'
confDir = './'
uriBase = "https://{server name}"
apiBase = uriBase + "/api"
# Log Levels:
LOG_NONE = 0
INFO = 1
DEBUG = 2
logLevel = DEBUG

class Card:
  serial = ""
  label  = ""
  minPin = 0
  maxPin = 0
  def __init__(self,serial,label,minPin,maxPin):
    self.serial = serial
    self.label  = label
    self.minPin = minPin
    self.maxPin = maxPin
  def toString(self):
    print(" Serial: "+self.serial)
    print("  Label: "+self.label)
    print("Min Pin: "+str(self.minPin))
    print("Max Pin: "+str(self.maxPin))

class Backoff:
  backoff = 2
  maxBackoff = 120
  def __init__(self):
    return
  def increment(self):
    return_value = self.backoff
    if (self.backoff <= self.maxBackoff):
      self.backoff = self.backoff + self.backoff
    return return_value

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
        print(e)
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
        print(e)
      return False
  if (r.status_code == 500 and logLevel >= DEBUG):
    print('Error in request, check weblogs')
  return False

def authenticate(reader):
  print(reader)

def cardPresent(reader):
  info = pkcs11.getSlotInfo(reader)
  if info.flags & PyKCS11.CKF_TOKEN_PRESENT:
    return True
  if (logLevel >= DEBUG):
    print("No Card")
  return False

def generateSystemKey():
  # https://github.com/pyca/cryptography
  # https://cryptography.io/en/latest/x509/tutorial/#creating-a-self-signed-certificate
  # Generate and store system key
  try:
    key = rsa.generate_private_key(
        public_exponent=65537,
        key_size=keyBits,
    )
    public = key.public_key().public_bytes(
      encoding=serialization.Encoding.PEM,
      format=serialization.PublicFormat.SubjectPublicKeyInfo
    )
    private = key.private_bytes(
      encoding=serialization.Encoding.PEM,
      format=serialization.PrivateFormat.TraditionalOpenSSL,
      encryption_algorithm=serialization.NoEncryption()
    )
    # Write key to disk
    for ky in [["private",private],["public",public]]:
      with open(confDir+ky[0]+"key.pem", "wb") as f:
        f.write(ky[1])
    return True
  except:
    return False

def getCard(reader):
  card = pkcs11.getTokenInfo(reader)
  if (cardPresent(reader)):
    return Card(card.serialNumber,card.label,card.ulMinPinLen,card.ulMaxPinLen)
  if (logLevel >= DEBUG):
    print("Class - Card Invalid")
  return False

def getPublicKey(reader,pin):
  session = pkcs11.openSession(reader, PyKCS11.CKF_SERIAL_SESSION | PyKCS11.CKF_RW_SESSION)
  try:
    session.login(str(pin))
  except PyKCS11Error as e:
    session.closeSession()
    if (e.value == PyKCS11.CKF_PIN_INCORRECT):
      return 'CKF_PIN_INCORRECT'
    elif (e.value == PyKCS11.CKF_PIN_LOCKED):
      return 'CKF_PIN_LOCKED'
    else:
      return 'ERROR'
  # find public key and print modulus
  pubKey = session.findObjects([(PyKCS11.CKA_CLASS, PyKCS11.CKO_PUBLIC_KEY)])[0]
  modulus = session.getAttributeValue(pubKey, [PyKCS11.CKA_MODULUS])[0]
  session.logout()
  session.closeSession()
  return binascii.hexlify(bytearray(modulus))

def getReaders():
  # The following getSlotList with parameter will force the software to assume
  #   a card is in the slot even if it is not.
  # Pkcs11 treats the word 'Token' as the card
  #slot = pkcs11.getSlotList(tokenPresent=True)
  slot = pkcs11.getSlotList()
  if (len(slot) > 0):
    return slot
  if (logLevel >= DEBUG):
    print("No Reader")
  return False

def getSession(backoff,systemKey):
  public, private = systemKey["public"], systemKey["private"]
  pub = base64.b64encode(public).decode("utf-8")
  session = requests.session()
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
        getSession(backoff,systemKey)
    elif "mid" in get_challenge and "display" in get_challenge:
      wait_value = backoff.increment()
      print('Machine Display ID ('+get_challenge["display"]+') not activated. Waiting '+str(wait_value)+' seconds before checking for activation again.')
      sleep(wait_value)
      getSession(backoff,systemKey)
    else:
      wait_value = backoff.increment()
      print('Machine not found. Waiting '+str(wait_value)+' seconds before trying again.')
      sleep(wait_value)
      getSession(backoff,systemKey)
  else:
    wait_value = backoff.increment()
    if (wait_value > backoff.maxBackoff):
      print('Attempts to get session exceeded. Returning False.')
      return False
    print('Failed to get initial phase. Waiting '+str(wait_value)+' seconds before trying again.')
    sleep(wait_value)
    getSession(backoff,systemKey)

  print('Attempts to get session exceeded. Returning False.')
  return False

def getSystemKey():
  keys = {"private":"","public":""}
  # Check to make sure both files exist
  existing_files = 0
  for key in keys:
    if (exists(confDir+key+"key.pem")):
      existing_files += 1

  # If one or more of the files do not exist, make sure the other does not exist
  if (existing_files < 2):
    # If no files exist, generate new keys and save them.
    # Then start the loop over to skip the rest of the if statement
    if (existing_files == 0):
      generateSystemKey()
      getSystemKey()
    
    # If one of the files exists, remove it, then start the function over.
    for key in keys:
      if (exists(confDir+key+"key.pem")):
        os.remove(confDir+key+"key.pem")
    getSystemKey()
 
  # A key should now exist, load it into memory
  for key in keys:
    # Just to be extra careful, check the file exists
    if (exists(confDir+key+"key.pem")):
      with open(confDir+key+"key.pem",'rb') as f:
        keys[key] = f.read()
  return keys

def handle_card():
  print("")

def monitor_card(reader):
  print(reader)

def monitor_session():
  print("")

def scanFiles(dir,file):
  try:
    objs = scandir(path=dir)
  except (PermissionError,OSError):
    return ""
  
  for obj in objs:
    if obj.is_file():
      if (obj.name == file):
        return str(obj.path)
    if obj.is_dir():
      if not obj.is_symlink():
        test = scanFiles(obj.path,file)
        if (len(test) > 0):
          return str(test)
  return ""

def setPKCSenv():
  ENV = "PYKCS11LIB"
  if ENV not in os.environ:  
    pkcsModule = ""
    pkcsModuleFile = 'opensc-pkcs11.so'
    for i in ['/lib','/usr']:
      pkcsModule = scanFiles(i,pkcsModuleFile)
      if (len(pkcsModule) > 0):
        break
    # If scanFiles returned nothing, print error then exit
    if (len(pkcsModule) == 0):
      print("Could not source PKCS11 Library '"+pkcsModuleFile+"'")
      quit()
    os.environ[ENV] = pkcsModule

  # Load pkcs11 module
  pkcs11.load()

def signMachineMessage(systemKey,message):
  # https://www.cryptoexamples.com/python_cryptography_string_signature_rsa.html
  signature = False
  public, private = systemKey["public"], systemKey["private"]
  private = serialization.load_pem_private_key(private,password=None)
  public = serialization.load_pem_public_key(public)
  try:
    # SIGN DATA/STRING
    signature = private.sign(
      data=message.encode('utf-8'),
      padding=padding.PSS(
        mgf=padding.MGF1(hashes.SHA256()),
        salt_length=padding.PSS.MAX_LENGTH
      ),
      algorithm=hashes.SHA256()
    )
    # VERIFY JUST CREATED SIGNATURE USING PUBLIC KEY
    try:
      public.verify(
        signature=signature,
        data=message.encode('utf-8'),
        padding=padding.PSS(
          mgf=padding.MGF1(hashes.SHA256()),
          salt_length=padding.PSS.MAX_LENGTH
        ),
        algorithm=hashes.SHA256()
      )
    except InvalidSignature:
      signature = False
  except UnsupportedAlgorithm as e:
    signature = False
  return signature

def signMessage(slot,pin,message):
  session = pkcs11.openSession(slot, PyKCS11.CKF_SERIAL_SESSION | PyKCS11.CKF_RW_SESSION)
  try:
    session.login(pin)
  except PyKCS11Error as e:
    session.closeSession()
    if (e.value == PyKCS11.CKF_PIN_INCORRECT):
      return 'CKF_PIN_INCORRECT'
    elif (e.value == PyKCS11.CKF_PIN_LOCKED):
      return 'CKF_PIN_LOCKED'
    else:
      return 'ERROR'
  # Key ID must be a tuple with trailing comma
  # Might search without keyID
  # original waas (0x22,)
  #keyID = (4,)
  toSign = binascii.hexlify(bytearray(message,'utf-8'))
  #privKey = session.findObjects([(CKA_CLASS, CKO_PRIVATE_KEY), (CKA_ID, keyID)])[0]
  privKey = session.findObjects([(PyKCS11.CKA_CLASS, PyKCS11.CKO_PRIVATE_KEY)])[0]
  signature = session.sign(privKey, binascii.unhexlify(toSign), Mechanism(PyKCS11.CKM_SHA1_RSA_PKCS, None))
  # logout
  session.logout()
  session.closeSession()
  return binascii.hexlify(bytearray(signature))

def validateSignature(reader,pin,message,signature):
  session = pkcs11.openSession(slot, PyKCS11.CKF_SERIAL_SESSION | PyKCS11.CKF_RW_SESSION)
  try:
    session.login(pin)
  except PyKCS11Error as e:
    session.closeSession()
    if (e.value == PyKCS11.CKF_PIN_INCORRECT):
      return 'CKF_PIN_INCORRECT'
    elif (e.value == PyKCS11.CKF_PIN_LOCKED):
      return 'CKF_PIN_LOCKED'
    else:
      return 'ERROR'

  pubKey = session.findObjects([(PyKCS11.CKA_CLASS, PyKCS11.CKO_PUBLIC_KEY)])[0]
  result = session.verify(pubKey, message, signature, Mechanism(PyKCS11.CKM_SHA1_RSA_PKCS, None))
  # logout
  session.logout()
  session.closeSession()
  return result

def main():
  setPKCSenv()
  backoff = Backoff()
  run = True
  while(run):
    # Check if system key exists
    systemKey = getSystemKey()
    if (systemKey == False):
      sleep(backoff.increment())
      continue

    # Connect to api and get session logic here
    session = getSession(Backoff(),systemKey)
    if (session == False):
      sleep(backoff.increment())
      continue

    print('got a session: ')
    print(session)

    #ff_options = webdriver.FirefoxOptions()
    #ff_options.add_argument("--kiosk")
    #driver = Firefox(executable_path='./geckodriver',options=ff_options)
    driver = webdriver.Firefox(executable_path='./geckodriver')
    driver.get(uriBase+'/?sid='+session['sid']+'&mid='+session['mid'])
    quit()

    # Check if card readers/card exist on system
    readers = getReaders()
    if (readers == False):
      sleep(backoff.increment())
      continue

    # Set first reader as reader
    reader = readers[0]

    # Ensure a card is in the reader
    if (cardPresent(reader) == False):
      sleep(backoff.increment())
      continue

    # If Card is not valid, restart loop:
    card = getCard(reader)
    if (card == False):
      sleep(backoff.increment())
      continue
    
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