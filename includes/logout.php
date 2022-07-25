<?php 
include_once($_SERVER['DOCUMENT_ROOT'].'/includes/mysql.php');
include_once($_SERVER['DOCUMENT_ROOT'].'/includes/common-functions.php');
$from = "/admin";
if (isset($_GET)) {
  if (isset($_GET["from"])) {
    $from = sanitize_input($_GET["from"]);
  }
}
echo(logout(true,$from));
?>