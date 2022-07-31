<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/api/api_config.php');
if (sanitize_input($_SERVER["REQUEST_METHOD"]) === "POST") {
  if (sanitize_input($_SERVER["CONTENT_TYPE"]) === "application/json") {
    $_POST = json_decode(file_get_contents('php://input'), true);
  }
  //error_log('post request made to pin.php');
  if (isset($_POST['serial'])) {
    if (valid_session()) {
      $serial = sanitize_input($_POST['serial']);
      $sql = 'SELECT count(*) AS result FROM citizen WHERE card = ?';
      $r = get_sql($sql,array(array('s',$serial)));
      if (!empty($r)) {
        //error_log('Made it past first query');
        if ($r->num_rows === 1) {
          //error_log('first query 1 rows');
          if ($r->fetch_assoc()['result'] === 1) {
            //error_log('first query result = 1');
            $s2 = 'SELECT bin_to_uuid(id) AS cid, card_pin FROM citizen WHERE card = ?';
            $r2 = get_sql($s2,array(array('s',$serial)));
            if (!empty($r2)) {
              //error_log('Made it past second query');
              if ($r2->num_rows === 1) {
                //error_log('second query one rows');
                $row = $r2->fetch_assoc();
                if (empty($row['card_pin'])) {
                  //error_log('second query row card pin empty');
                  print(json_encode(array('card_status'=>'Default Pin','cid'=>$row['cid'])));    
                } else {
                  //error_log('second query card pin not empty');
                  print(json_encode(array('card_status'=>'Card Enrolled','cid'=>$row['cid'],'pin'=>$row['card_pin'])));
                }
              } else { json_encode('Error Occurred'); }
            } else {
              print(json_encode(array('card_status'=>'Card Not Enrolled')));
            }
          } else {
            print(json_encode(array('card_status'=>'Card Not Enrolled')));
          }
        } else { error_log('response is not 1 rows'); json_encode('Error Occurred'); }
      } else { error_log('response is empty'); json_encode('Error Occurred'); }
    } else { error_log('not valid session'); json_encode('Error Occurred'); }
  } else { error_log('post[serial] not set'); json_encode('Error Occurred'); }
} else { error_log('not a post request'); json_encode('Error Occurred'); }
?>