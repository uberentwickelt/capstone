<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/api/api_config.php');

$return_val = 'false';
if (sanitize_input($_SERVER["REQUEST_METHOD"]) === "POST") {
  if (sanitize_input($_SERVER["CONTENT_TYPE"]) === "application/json") {
    $_POST = json_decode(file_get_contents('php://input'), true);
  }

  if (isset($_POST['type'])) {
    $type = sanitize_input($_POST['type']);
    if ($type === 'machine') {
      // Stage 1, initial introduction
      if (isset($_POST['publicKey'])) {
        $publicKey = sanitize_input($_POST['publicKey']);
        $machine_id = get_machine_id($publicKey);
        if ((bool) $machine_id) {
          $muid = $machine_id['mid'];
          $mdid = $machine_id['display'];
          // Check if machine is activated here
          if (get_machine_activation_status($muid)) {
            $challenge = get_machine_challenge($muid);
            if ((bool) $challenge) {
              $return_val = array('mid'=>$muid,'display'=>$mdid,'challenge'=>$challenge);
            }
          } else {
            $return_val = array('mid'=>$muid,'display'=>$mdid);
          }
        }
      }
      // Stage 2, challenge response -> issue session
      if (isset($_POST['mid'],$_POST['response'],$_POST['saltLength'])) {
        $mid = sanitize_input($_POST['mid']);
        $response = sanitize_input($_POST['response']);
        $saltLength = sanitize_int($_POST['saltLength']);
        if (verify_machine_signature($mid,$response,$saltLength)) {
          $session = get_machine_session($mid);
          if ((bool) $session) {
            $_SESSION['sid'] = $session['sid'];
            $_SESSION['mid'] = $session['mid'];
            $_SESSION['did'] = $session['did'];
            $return_val = $session;
          }
        }
      }
    }
    /*
    if ($type === 'user') {
      // do something for user session
      
    }
    */
  } else {
    if (isset($_POST['sid'],$_POST['uid'])) {
      $sid = sanitize_input($_POST['sid']);
      $uid = sanitize_input($_POST['uid']);
      if (session_validate($sid,$uid)) {
        $return_val = array("sid"=>$sid,"uid"=>$uid);
      }
    }
  }
}
print(json_encode($return_val));
?>