<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/api/api_config.php');
$result = 'False';
if (sanitize_input($_SERVER["REQUEST_METHOD"]) === "POST") {
  if (sanitize_input($_SERVER["CONTENT_TYPE"]) === "application/json") {
    $_POST = json_decode(file_get_contents('php://input'), true);
  }
  if (isset($_POST['cid'],$_POST['signature'])) {
    $cid = sanitize_input($_POST['cid']);
    $signature = sanitize_input($_POST['signature']);
    $order = sanitize_int($_POST['order']);
    $question = sanitize_post($_POST['question']);
    $sql = 'UPDATE `question_result` SET `signed_date` = sysdate(), `signature` = ? WHERE `citizen` = uuid_to_bin(?) AND `order` = ? AND `question` = ?';
    $r = set_sql($sql,array(
      array('s',$signature),
      array('s',$cid),
      array('i',$order),
      array('s',$question)
    ));
    if ((bool) $r) {
      $result = 'True';
    }
  }
}
print(json_encode(array('result'=>$result)));
?>