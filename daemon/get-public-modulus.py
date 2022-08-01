#!/usr/bin/env python3
# https://pkcs11wrap.sourceforge.io/api/samples.html
from __future__ import print_function
from Crypto.PublicKey.RSA import construct
from PyKCS11 import *
import binascii,base64
from time import sleep
try:
  from os import scandir
except ImportError:
  from scandir import scandir

pkcs11 = PyKCS11Lib()

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

setPKCSenv()

def getReaders():
  # The following getSlotList with parameter will force the software to assume
  #   a card is in the slot even if it is not.
  # Pkcs11 treats the word 'Token' as the card
  #slot = pkcs11.getSlotList(tokenPresent=True)
  slot = pkcs11.getSlotList()
  if (len(slot) > 0):
    return slot
  return False

def cardPresent(reader):
  try:
    info = pkcs11.getSlotInfo(reader)
    if info.flags & PyKCS11.CKF_TOKEN_PRESENT:
      return True
  except PyKCS11.PyKCS11Error as e:
    if e == PyKCS11.CKR_DEVICE_ERROR:
      print('Error loading card')
      raise
  return False

terminate_due_to_incorrect_pin = False
while not terminate_due_to_incorrect_pin:
  reader = getReaders()
  if not reader:
    print('Waiting for Card Reader')
  while not reader:
    reader = getReaders()
    sleep(1)

  reader = reader[0]

  present = cardPresent(reader)
  if not present:
    print('Waiting for Card')
  while not present:
    present = cardPresent(reader)
    sleep(1)

  # get 1st slot
  #slot = pkcs11.getSlotList(tokenPresent=True)[0]

  slot = reader

  session = pkcs11.openSession(slot, PyKCS11.CKF_SERIAL_SESSION | PyKCS11.CKF_RW_SESSION)

  try:
    session.login("000000")

    # key ID in hex (has to be tuple, that's why trailing comma)
    #keyID = (0x22,)
    #keyID = (4,)

    # find public key and print modulus
    pubKey = session.findObjects([(PyKCS11.CKA_CLASS, PyKCS11.CKO_PUBLIC_KEY)])[0]
    modulus = session.getAttributeValue(pubKey, [PyKCS11.CKA_MODULUS])[0]
    n = int(binascii.hexlify(bytearray(modulus)),16)
    e = int.from_bytes(session.getAttributeValue(pubKey,[PyKCS11.CKA_PUBLIC_EXPONENT])[0],byteorder='big')
    p = base64.b64encode(construct((n,e)).exportKey()).decode(sys.stdout.encoding)

    #print('modulus: '+str(modulus))
    #print('exponent: '+str(e))
    #print('mod bits: '+str(session.getAttributeValue(pubKey,[PyKCS11.CKA_MODULUS_BITS])[0]))
    #print("\nmodulus: {}".format(binascii.hexlify(bytearray(modulus))))
    #print("\nmod_len: "+str(len(modulus)))
    #print("\nserial#: "+pkcs11.getTokenInfo(slot).serialNumber)

    # Converting the modulus to something useable was possible with help from the following posts:
    #  https://stackoverflow.com/questions/40094108/i-have-a-rsa-public-key-exponent-and-modulus-how-can-i-encrypt-a-string-using-p
    #  https://stackoverflow.com/questions/18039401/how-can-i-transform-between-the-two-styles-of-public-key-format-one-begin-rsa
    serialNumber = str(pkcs11.getTokenInfo(slot).serialNumber)
    displayName = ""
    if serialNumber == '9511dda8798b062c':
      displayName = "Voter C"
    elif serialNumber == '84b80dd5b0514f86':
      displayName = "Voter D"
    elif serialNumber == 'a5c26bbd5ce0a61e':
      displayName = "Voter E"      
    elif serialNumber == '9fba854bcf771248':
      displayName = "Voter F"
    else:
      displayName = ""
    print("\nINSERT INTO `citizen` (`card`,`public_key`,`display_name`) values ('"+str(serialNumber)+"','"+str(p)+"','"+str(displayName)+"');\n")
  except PyKCS11Error as e:
    if str(e).startswith('CKR_PIN_INCORRECT'):
      print('Incorrect Pin')
      terminate_due_to_incorrect_pin = True
  finally:
    # logout
    try:
      session.logout()
      session.closeSession()
    except PyKCS11Error as f:
      # Ignore if user is not logged in when trying to log out
      if str(f).startswith('CKR_USER_NOT_LOGGED_IN'):
        pass

  if terminate_due_to_incorrect_pin:
    quit()

  present = cardPresent(reader)
  if present:
    print('Remove card before trying new card')
  while present:
    present = cardPresent(reader)
    sleep(1)
  
  print('Restarting loop')
  sleep(1)