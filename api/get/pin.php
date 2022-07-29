<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/api/api_config.php');

$return_val = 'false';
if (sanitize_input($_SERVER["REQUEST_METHOD"]) === "POST") {
  if (sanitize_input($_SERVER["CONTENT_TYPE"]) === "application/json") {
    $_POST = json_decode(file_get_contents('php://input'), true);
  }
  if (isset($_POST['serial'])) {
    if (valid_session()) {
      $serial = sanitize_input($_POST['serial']);
      $sql = 'SELECT count(*) AS result FROM citizen WHERE card = ?';
      $r = get_sql($sql,array(array('s',$serial)));
      if (!empty($r)) {
        if ($r->num_rows === 1) {
          if ($r->fetch_assoc()['result'] === 1) {
            $s2 = 'SELECT card_pin FROM citizen WHERE card = ?';
            $r2 = get_sql($sql,array(array('s',$serial)));
            if (!empty($r2)) {
              if ($r2->num_rows === 1) {
                $return_val = array('card_status'$r2->fetch_assoc()['card_pin'];
              }
            } else {
              $return_val = 'Default Pin';
            }
          } else {
            $return_val = 'Card Not Enrolled';
          }
        }
      }
    }
  }
  return $return_val;
}