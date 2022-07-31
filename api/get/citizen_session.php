<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/api/api_config.php');
if (sanitize_input($_SERVER["REQUEST_METHOD"]) === "POST") {
  if (sanitize_input($_SERVER["CONTENT_TYPE"]) === "application/json") {
    $_POST = json_decode(file_get_contents('php://input'), true);
  }
  if (isset($_POST['serial'],$_POST['cid'],$_POST['signature'])) {
    if (valid_session()) {
      $serial = sanitize_input($_POST['serial']);
      $cid = sanitize_input($_POST['cid']);
      $signature = sanitize_input($_POST['signature']);
      if (verify_citizen_signature($cid,$signature)) {
        print(json_encode(array('user_session'=>'yes')));
        // get_citizen_session($cid)
        // $_SESSION['whatever'] = whatever;
      } else {
        print(json_encode(array('user_session'=>'no')));
      }
    }
  }
}