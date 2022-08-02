<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/api/api_config.php');
//if (sanitize_input($_SERVER["REQUEST_METHOD"]) === "POST") {
//  if (sanitize_input($_SERVER["CONTENT_TYPE"]) === "application/json") {
//    $_POST = json_decode(file_get_contents('php://input'), true);
//  }
if (isset($_POST['card_id'])) {
  $card_id = sanitize_input($_POST['card_id']);
  $login_with_card = 'SELECT bin_to_uuid(`id`) AS `cid`,`display_name` FROM `citizen` WHERE `card` = ?';
  $card_logged_in = get_sql($login_with_card,array(
    array('s',$card_id)
  ));
  if (!empty($card_logged_in)) {
    if ($card_logged_in->num_rows === 1) {
      $row = $card_logged_in->fetch_assoc();
      $_SESSION['cid'] = $row['cid'];
      $_SESSION['display_name'] = $row['display_name'];
    }
  }
}
$number_of_questions = 'SELECT MAX(`order`) AS `result` FROM `question`';
$questions = get_sql($number_of_questions,array());
if (!empty($questions)) {
  if ($questions->num_rows === 1) {
    $max_questions = $questions->fetch_assoc()['result'];
    $order = 1;
    $_SESSION['done_voting'] = False;
    if (isset($_SESSION['cid'],$_POST['submit'],$_POST['order'],$_POST['question'],$_POST['answer'])) {
      // Post request happened, insert the data in the db then move on and display the page
      $cid = sanitize_input($_SESSION['cid']);
      $order = sanitize_int($_POST['order']);
      $question = sanitize_post($_POST['question']);
      $answer = sanitize_post($_POST['answer']);
      
      $si = 'INSERT INTO `question_result` (`citizen`,`order`,`question`,`answer`) VALUES (uuid_to_bin(?),?,?,?)';
      $ri = set_sql($si,array(
        array('s',$cid),
        array('i',$order),
        array('s',$question),
        array('s',$answer)
      ));
      if ((bool) $ri) {
        // Submit success
        if ($order < $max_questions) {
          $order += 1;
        } else {
          //print('End of voting questions please logout');
          $_SESSION['done_voting'] = True;
        }
      } else {
        $su = 'UPDATE `question_result` SET `answer`=? WHERE `citizen`=uuid_to_bin(?) AND `order`=? AND `question`=?';
        $ru = set_sql($su,array(
          array('s',$answer),
          array('s',$cid),
          array('i',$order),
          array('s',$question)
        ));
        if ((bool) $ru) {
          if ($order < $max_questions) {
            $order += 1;
          } else {
            //print('End of voting questions please logout');
            $_SESSION['done_voting'] = True;
          }  
        } else {
          print('Some error occurred');
        }
      }
      //print('Submit occured, enter the data: '.$order.' | '.$question.' | '.$answer);
    }

    if (isset($_SESSION['done_voting'])) {
      $done_voting = False;
      if ((bool) $_SESSION['done_voting']) {
        $done_voting = True;
      }
    }

    if ($order <= $max_questions && !$done_voting) {
      $sql = 'SELECT bin_to_uuid(id) AS `id`,`order`,`question`,`answer` FROM `question` WHERE `order` = ? ORDER BY `order`';
      $r = get_sql($sql,array(array('i',$order)));
      if (!empty($r)) {
        if ($r->num_rows > 0) {
          $current_row = 1;
          print('<div class="container vote-vertical-upper-center">');
          print('<div class="row translate-middle text-left mx-auto my-auto justify-content-center">');
          print('<form method="post" action="'.$current_uri.'">');
          while ($row = $r->fetch_assoc()) {
            if ($current_row === 1) {
              if (isset($_SESSION['display_name'])) {
                $display_name = sanitize_post($_SESSION['display_name']);
                print('<h5>Ballot for: '.$display_name.'</h5><br>');
              }
              $question = $row['question'];
              print('<h3>Who should be '.$row['question'].'</h3>');
            }
            print('<div class="form-check"><input class="form-check-input" type="radio" name="answer" id="'.$row['id'].'" value="'.$row['answer'].'" required><label class="form-check-label" for="'.$row['id'].'">'.$row['answer'].'</label></div>');
            $current_row += 1;
          }
          print('<input type="hidden" id="question" name="question" value="'.$question.'"/>');
          print('<input type="hidden" id="order" name="order" value="'.$order.'"/>');
          print('<input type="submit" id="submit" name="submit" value="Next"/>');
          print('</form></div>');
          print('<div class="row translate-middle text-left mx-auto my-auto justify-content-center">');
          $prev = $current_uri.'?order='.($order - 1);
          $next = ($order + 1);
          if ($order <= 2) {
            $prev = $current_uri;
          }
          print('</div>');
          print('</div>');
        }
      }
    } else {
      print('<div class="container vote-vertical-upper-center">');
      print('<div class="row translate-middle text-left mx-auto my-auto justify-content-center">');
      if (isset($_SESSION['display_name'])) {
        $display_name = sanitize_post($_SESSION['display_name']);
        print('<h3>Summary for: '.$display_name.'</h3><br>');
      }
      if (isset($_SESSION['cid'])) {
        $cid = sanitize_input($_SESSION['cid']);
        $summary_sql = 'SELECT CASE WHEN `signed_date` IS NULL THEN CONCAT(\'<i id="\',`question`,\'" class="bi bi-x-circle text-danger"></i>\') ELSE CONCAT(\'<i id="\',`question`,\'" class="bi bi-check-circle text-success"></i>\') END AS Signed, `question` AS Question,`answer` AS Answer FROM `question_result` WHERE `citizen` = uuid_to_bin(?) ORDER BY `order`';
        display_table($summary_sql,array(array('s',$cid)));
      } else {
        print('<p>An error was encountered while loading your results.</p>');
      }
      print('</div>');
      print('</div>');
    }
  }
}
?>