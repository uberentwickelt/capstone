<?php 
  include($_SERVER['DOCUMENT_ROOT'].'/api/api_config.php');  global $conn;

  echo json_encode(array($conn->query('select uuid()')->fetch_row()[0]));
?>