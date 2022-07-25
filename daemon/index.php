<?php
include($_SERVER['DOCUMENT_ROOT'].'/includes/common-functions.php');
$file = "./voted.py";
if (isset($_GET['checksum'])) {
  $checksum = sanitize_input($_GET['checksum']);
  if ($checksum === 'sha256' || $checksum === 'sha512') {
    echo(hash_file($checksum,$file));
  }  
} else {
  $contents = file_get_contents($file);
  if ($contents !== false) {
    echo($contents);
  }
}
?>