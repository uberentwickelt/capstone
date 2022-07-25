<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/api/api_config.php');
$response = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (valid_session()) {
    print('<h3>Questions</h3>');
    display_table('select bin_to_uuid(id) as id,display as "Question Number",name as "Question",election_id,question_id,answer as "Answer" from question order by display asc',array());
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