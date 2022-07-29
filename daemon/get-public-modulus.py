#!/usr/bin/env python3
# https://pkcs11wrap.sourceforge.io/api/samples.html#dump-all-the-token-objects
from __future__ import print_function

from PyKCS11 import *
import binascii
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

# get 1st slot
slot = pkcs11.getSlotList(tokenPresent=True)[0]

session = pkcs11.openSession(slot, PyKCS11.CKF_SERIAL_SESSION | PyKCS11.CKF_RW_SESSION)
#session.login("1234")
session.login("000000")
#session.login("00000000000000000000000000000000")

# key ID in hex (has to be tuple, that's why trailing comma)
#keyID = (0x22,)
keyID = (4,)

# find public key and print modulus
pubKey = session.findObjects([(PyKCS11.CKA_CLASS, PyKCS11.CKO_PUBLIC_KEY)])[0]
modulus = session.getAttributeValue(pubKey, [PyKCS11.CKA_MODULUS])[0]
#print("\nmodulus: {}".format(binascii.hexlify(bytearray(modulus))))
#print("\nmod_len: "+str(len(modulus)))
#print("\nserial#: "+pkcs11.getTokenInfo(slot).serialNumber)

print("\nINSERT INTO `citizen` (`card`,`public_key`,`display_name`) values ('"+str(pkcs11.getTokenInfo(slot).serialNumber)+"','"+str(binascii.hexlify(bytearray(modulus)).decode(sys.stdout.encoding))+"','');\n")

# logout
session.logout()
session.closeSession()