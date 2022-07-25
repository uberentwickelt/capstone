<?php
  include_once($_SERVER['DOCUMENT_ROOT'].'/includes/mysql.php');
  include_once($_SERVER['DOCUMENT_ROOT'].'/includes/common-functions.php');
  global $conn;
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" charset="utf-8" content="width=device-width, initial-scale=1.0" />
    <link href="/favicon.ico" rel="icon" type="image/x-icon" />
    <title>Vote</title>
    <link rel="stylesheet" href="/lib/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/lib/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="/lib/bootstrap-datepicker/css/bootstrap-datepicker.min.css">
    <link rel="stylesheet" href="/css/main.css">
  </head>
  <body>
    <?php include($_SERVER['DOCUMENT_ROOT'].'/includes/nav.php'); ?>