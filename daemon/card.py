from PyKCS11 import *
import binascii,os
try:
  from os import scandir
except ImportError:
  from scandir import scandir
pkcs11 = PyKCS11Lib()

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

def cardPresent(reader):
  info = pkcs11.getSlotInfo(reader)
  if info.flags & PyKCS11.CKF_TOKEN_PRESENT:
    return True
  if (logLevel >= DEBUG):
    return "No Card"
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

def handle_card():
  print("handle_card")

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