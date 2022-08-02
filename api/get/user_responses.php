<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/api/api_config.php');
if (sanitize_input($_SERVER["REQUEST_METHOD"]) === "POST") {
  if (sanitize_input($_SERVER["CONTENT_TYPE"]) === "application/json") {
    $_POST = json_decode(file_get_contents('php://input'), true);
  }
  if (isset($_POST['cid'],$_POST['type'])) {
    $cid = sanitize_input($_POST['cid']);
    $type = sanitize_input($_POST['type']);
    if ($type === 'total') {
      $sql = 'SELECT count(*) AS total_questions FROM `question_result` WHERE `citizen` = uuid_to_bin(?)';
    } else {
      $sql = 'SELECT `order`,`question`,`answer` FROM `question_result` WHERE `citizen` = uuid_to_bin(?) ORDER BY `order`';
    }
    $r = get_sql($sql,array(array('s',$cid)));
    if (!empty($r)) {
      if ($r->num_rows > 0) {
        $row = array($r->fetch_assoc());
        $result = $row;
        while ($row = $r->fetch_assoc()) {
          array_push($result,$row);
        }
        print(json_encode($result));
      }
    }
  }
}
?>