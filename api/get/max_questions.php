<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/api/api_config.php');
if (sanitize_input($_SERVER["REQUEST_METHOD"]) === "POST") {
  if (sanitize_input($_SERVER["CONTENT_TYPE"]) === "application/json") {
    $_POST = json_decode(file_get_contents('php://input'), true);
  } 
  $sql = 'SELECT max(`order`) AS max_questions FROM `question`';
  $r = get_sql($sql,array());
  if (!empty($r)) {
    if ($r->num_rows === 1) {
      print(json_encode($r->fetch_assoc()));
    }
  }
}
?>