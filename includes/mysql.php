<?php
/*
 * Import the following variables from .creds/vote.php: 
 * $dbname,$server,$username,$password
 */
require_once('/var/www/.creds/vote.php');
// Create Connection
$conn = new mysqli($server,$username,$password,$dbname);
if ($conn->connect_error) {
  echo("DB Unavailable");
  $conn->close();
}

function get_sql($sql,$where_params) {
  global $conn;
  try {
    $stmt = $conn->prepare($sql);
    if (count($where_params) > 0) {
      // Dynamically build bind statement
      # https://stackoverflow.com/a/38857987
      $type = "";
      $out = [];
      foreach ($where_params as $param) {
        $type .= $param[0];
        $out[] = &$param[1];
      }
      array_unshift($out,$type);
      call_user_func_array([$stmt,'bind_param'],$out);
    }
    if($stmt->execute()) {
      $result = $stmt->get_result();
      if ($result->num_rows > 0) { 
        return $result;
      }
    }
  } catch (Exception $e) {
    return false;
  }
  return false;
}

function set_sql($sql,$where_params) {
  global $conn;
  try {
    $stmt = $conn->prepare($sql);
    if (count($where_params) > 0) {
      // Dynamically build bind statement
      # https://stackoverflow.com/a/38857987
      $type = "";
      $out = [];
      foreach ($where_params as $param) {
        $type .= $param[0];
        $out[] = &$param[1];
      }
      array_unshift($out,$type);
      call_user_func_array([$stmt,'bind_param'],$out);
    }
    if($stmt->execute()) {
      return true;
    }
  } catch (Exception $e) {
    return false;
  }
  return false;
}
?>