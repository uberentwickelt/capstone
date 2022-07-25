<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/api/api_config.php');
$response = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (valid_session()) {
    if (isset($_POST["type"])) {
      $type = sanitize_input($_POST["type"]);
      switch($type) {
        case 'activation':
          print('<h3>New Systems</h3>');
          display_table('select bin_to_uuid(id) as id,friendly_id,active,last_ip,last_date from machine where active=?',array("i"=>0));
          break;
        case 'enrolled':
          print('<h3>Enrolled Systems</h3>');
          display_table('select bin_to_uuid(id) as id,friendly_id,active,last_ip,last_date from machine where active=?',array("i"=>1));
          break;
        case 'deactivated':
          print('<h3>Deactivated Systems</h3>');
          display_table('select bin_to_uuid(id) as id,friendly_id,active,last_ip,last_date from audit_machine where active=?',array("i"=>0));
          break;
        default:
          $response = 'invalid request';
          break;
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