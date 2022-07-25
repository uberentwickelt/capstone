<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/api/api_config.php');
$response = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (valid_session()) {
    if (isset($_POST['electionName']) && isset($_POST['electionYear'])) {
      global $conn;
      $name = sanitize_input($_POST['electionName']);
      $year = sanitize_post($_POST['electionYear']);
      $sql = 'insert into election (name,year) values (?,?)';
      try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss",$name,$year);
        if ($stmt->execute()) {
          $response = 'SUCCESS';
        } else {
          $response = 'FAILED TO ADD ELECTION';
        }
      } catch (Exception $e) {
        $response = 'ERROR IN REQUEST';
      }
    }
  } else {
    $response = 'invalid session';
  }
} else {
  $response = 'invalid request';
}
if (count($response) > 0) {
print($response);
}
?>