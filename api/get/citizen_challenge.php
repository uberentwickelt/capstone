<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/api/api_config.php');
if (sanitize_input($_SERVER["REQUEST_METHOD"]) === "POST") {
  if (sanitize_input($_SERVER["CONTENT_TYPE"]) === "application/json") {
    $_POST = json_decode(file_get_contents('php://input'), true);
  }
  if (isset($_POST['serial'],$_POST['cid'])) {
    if (valid_session()) {
      $serial = sanitize_input($_POST['serial']);
      $cid = sanitize_input($_POST['cid']);
      $challenge = get_citizen_challenge($cid);
      if ((bool) $challenge) {
        print(json_encode(array('challenge'=>$challenge)));
      }
    }
  }
}