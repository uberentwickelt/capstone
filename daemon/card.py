import binascii,base64,binascii,os,PyKCS11
from Crypto.PublicKey.RSA import construct
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding
from PyKCS11 import *
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
  return False

def getCard(reader):
  if (cardPresent(reader)):
    card = pkcs11.getTokenInfo(reader)
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
    session.login(str(pin))
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
  #mecha= Mechanism(PyKCS11.CKM_SHA1_RSA_PKCS, None)
  slength = get_card_salt_length(session)
  ism = PyKCS11.Mechanism(mechanism=PyKCS11.RSA_PSS_Mechanism(
    mecha=PyKCS11.CKM_SHA256_RSA_PKCS_PSS,
    hashAlg=PyKCS11.CKM_SHA256,
    mgf=PyKCS11.CKG_MGF1_SHA256,
    sLen=slength))
  signature = session.sign(key=privKey,data=binascii.unhexlify(toSign).encode('utf-8'),mecha=ism)
  
  # logout
  session.logout()
  session.closeSession()
  if validateSignature(session,message,signature,ism):
    return binascii.hexlify(bytearray(signature))
  else:
    return 'Unable to validate signature'

def validateSignature(session,message,signature,ism):
  message = binascii.hexlify(bytearray(message,'utf-8'))
  pubKey = session.findObjects([(PyKCS11.CKA_CLASS, PyKCS11.CKO_PUBLIC_KEY)])[0]  
  result = session.verify(key=pubKey,data=binascii.unhexlify(message).encode('utf-8'),signature=signature,mecha=ism)
  return result

def get_card_salt_length(session) -> bytes:
  # find public key and print modulus
  pubKey = session.findObjects([(PyKCS11.CKA_CLASS, PyKCS11.CKO_PUBLIC_KEY)])[0]
  modulus = session.getAttributeValue(pubKey, [PyKCS11.CKA_MODULUS])[0]
  n = int(binascii.hexlify(bytearray(modulus)),16)
  e = int.from_bytes(session.getAttributeValue(pubKey,[PyKCS11.CKA_PUBLIC_EXPONENT])[0],byteorder='big')
  p = construct((n,e)).exportKey()
  pad = padding.calculate_max_pss_salt_length(serialization.load_pem_public_key(p), hashes.SHA256())
  print(str(pad).encode())
  #b = pad.to_bytes(length=pad.bit_length(),byteorder='big')
  #print(str(test))
  #result = long_to_bytes(pad)
  #print(str(result))
  return str(pad).encode()

# https://stackoverflow.com/questions/8730927/convert-python-long-int-to-fixed-size-byte-array
def long_to_bytes (val, endianness='big'):
  """
  Use :ref:`string formatting` and :func:`~binascii.unhexlify` to
  convert ``val``, a :func:`long`, to a byte :func:`str`.

  :param long val: The value to pack

  :param str endianness: The endianness of the result. ``'big'`` for
    big-endian, ``'little'`` for little-endian.

  If you want byte- and word-ordering to differ, you're on your own.

  Using :ref:`string formatting` lets us use Python's C innards.
  """

  # one (1) hex digit per four (4) bits
  width = val.bit_length()

  # unhexlify wants an even multiple of eight (8) bits, but we don't
  # want more digits than we need (hence the ternary-ish 'or')
  width += 8 - ((width % 8) or 8)

  # format width specifier: four (4) bits per hex digit
  fmt = '%%0%dx' % (width // 4)

  # prepend zero (0) to the width, to zero-pad the output
  s = binascii.unhexlify(fmt % val)
  if endianness == 'little':
      # see http://stackoverflow.com/a/931095/309233
      s = s[::-1]
  return s