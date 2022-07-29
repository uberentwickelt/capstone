<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/api/api_config.php');
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
            $s2 = 'SELECT bin_to_uuid(id) AS cid, card_pin FROM citizen WHERE card = ?';
            $r2 = get_sql($sql,array(array('s',$serial)));
            if (!empty($r2)) {
              if ($r2->num_rows === 1) {
                $row = $r2->fetch_assoc();
                if (empty($row['card_pin'])) {
                  print(json_encode(array('card_status'=>'Default Pin','cid'=>$row['cid'])));    
                } else {
                  print(json_encode(array('card_status'=>'Card Enrolled','cid'=>$row['cid'],'pin'=>$row['card_pin'])));
                }
              }
            } else {
              print(json_encode(array('card_status'=>'Card Not Enrolled')));
            }
          } else {
            print(json_encode(array('card_status'=>'Card Not Enrolled')));
          }
        }
      }
    }
  }
}