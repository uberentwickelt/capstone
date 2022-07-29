from cryptography.exceptions import InvalidSignature, UnsupportedAlgorithm
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding, rsa
#import cryptography
#from cryptography import x509
#from cryptography.x509.oid import NameOID
from os.path import exists
import os

keyBits = 2048
#confDir = '/etc/voted/'
confDir = './'

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