<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/lib/composer/vendor/autoload.php');
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Math\BigInteger;

$current_uri = preg_replace('/\.php$/', '',sanitize_input(htmlspecialchars($_SERVER['PHP_SELF'])));
$user_details_success = "select bin_to_uuid(id) as id,username,display_name,password from `login` where lower(username) = lower(?)";

/*
 * SQL Requests must include a datatype matching below:
 * https://www.php.net/manual/en/mysqli-stmt.bind-param.php
 * $type may be one of the following
 * i => corresponding variable has type integer
 * d => corresponding variable has type double
 * s => corresponding variable has type string
 * b => corresponding variable is a blob and will be sent in packets
 */

if (!isset($_SESSION)) {
  session_start();
}

function add_user($username,$display_name,$password) {
  global $conn;
  // Get and sanitize
  $username     = sanitize_input($username);
  $password     = sanitize_input($password);
  $display_name = sanitize_post($display_name);

  // Check if password matches pwd verify (probably should put some validation to prevent single char passwords and stuff)
  /*
  if ($password != $pwd_verify) {
    global $title;
    //include($_SERVER['DOCUMENT_ROOT'].'/includes/open.php');
    //include('requirements.php'); ?>
    <h3>Passwords do not match <a class="btn" href="/login">Signup?</a></h3><?php
    $conn->close();
    die();
  }
  */

  // Check if username exists in db
  $existing_users = $conn->prepare("select count(username) as username from `login` where lower(username) = lower(?)");
  $existing_users->bind_param("s",$username);
  $existing_users->execute();
  $existing_users = $existing_users->get_result()->fetch_assoc()["username"];
  if ($existing_users === 0) {
    // Go ahead and attempt to add the user
    // Prepare the hashed password
    $password = password_hash($password,PASSWORD_ARGON2ID);
    // Create user in database with provided details
    $stmt = $conn->prepare("insert into `login` (username,display_name,password) values (?,?,?)");
    $stmt->bind_param("sss",$username,$display_name,$password);
    if ($stmt->execute()) {
      return true;
    }
  }
  return false;
}

function console($msg) {
  print('<script>console.log("'.sanitize_post($msg).'")</script>');
}

function display_table($sql,$where_params) {
  global $conn;
  try {
    $stmt = $conn->prepare($sql);
    if (count($where_params) > 0) {
      /*
       * https://www.php.net/manual/en/mysqli-stmt.bind-param.php
       * $type may be one of the following
       * i => corresponding variable has type integer
       * d => corresponding variable has type double
       * s => corresponding variable has type string
       * b => corresponding variable is a blob and will be sent in packets
       */
      $type = "";
      $out = "";
      // Dynamically build bind statement
      foreach ($where_params as $key => $value) {
        $type = $type.$key;
        if ($key === array_key_first($where_params)) {
          $out = $value;
        } else {
          $out = $out.",".$value;
        }
      }
      $stmt->bind_param($type,$out);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) { 
      $row = $result->fetch_assoc(); ?>
  
    <table class="table">
      <thead>
        <tr>
          <?php
        foreach(array_keys($row) as $col) {
          if ($col === 'id') {
            print('<th scope="col"><i class="bi bi-pencil-square"></i></th>');
          } else {
            print('<th scope="col">'.$col.'</th>');
          }
      }
      ?>
        </tr>
      <thead>
      <tbody>  
  
    <?php
      do {
        print('<tr>');
        foreach(array_keys($row) as $col) {
          if ($col === 'id') {
            print('<td><i id="'.$row[$col].'" class="bi bi-pencil-fill"></i></td>');
          } else {
            print('<td>'.$row[$col].'</td>');
          }
        }
        print('</tr>');
      } while($row = $result->fetch_assoc());
    ?>
      </tbody>
    </table>
    <?php
    } else {
      print('<h4>No records found.</h4>');
    }
  } catch (Exception $e) {
    print('Error');
  }
}

function get_citizen_challenge($cid) {
  // get a new or existing citizen challenge
  $cid = sanitize_input($cid);
  $sql = 'SELECT get_citizen_challenge(uuid_to_bin(?)) AS challenge';
  $r = get_sql($sql,array(array('s',$cid)));
  if (!empty($r)) {
    if ($r->num_rows === 1) {
      return $r->fetch_assoc()['challenge'];
    }
  }
  return false;
}

function get_citizen_publicKey($cid) {
  // Get citizen public key from uuid
  global $conn;
  $cid = sanitize_input($cid);
  $r = get_sql('select public_key from citizen where id = uuid_to_bin(?)',array(array('s',$cid)));
  if (!empty($r)) {
    if ($r->num_rows === 1) {
      $row = $r->fetch_assoc();
      return $row['public_key'];
    }
  }
  return false;
}

function get_ip() {
  $ip = '';
  if (isset($_SERVER['HTTP_CLIENT_IP']))
    $ip = sanitize_input($_SERVER['HTTP_CLIENT_IP']);
  else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
    $ip = sanitize_input($_SERVER['HTTP_X_FORWARDED_FOR']);
  else if(isset($_SERVER['HTTP_X_FORWARDED']))
    $ip = sanitize_input($_SERVER['HTTP_X_FORWARDED']);
  else if(isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
    $ip = sanitize_input($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']);
  else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
    $ip = sanitize_input($_SERVER['HTTP_FORWARDED_FOR']);
  else if(isset($_SERVER['HTTP_FORWARDED']))
    $ip = sanitize_input($_SERVER['HTTP_FORWARDED']);
  if(isset($_SERVER['REMOTE_ADDR']))
    $ip = sanitize_input($_SERVER['REMOTE_ADDR']);
  else
    $ip = 'UNKNOWN';
  return $ip;
}

function get_machine_activation_status($mid) {
  $mid = sanitize_input($mid);
  $sql = 'SELECT active FROM machine WHERE id = uuid_to_bin(?)';
  $r = get_sql($sql,array(array('s',$mid)));
  if (!empty($r)) {
    if ($r->num_rows === 1) {
      if ($r->fetch_assoc()['active'] == 1) {
        return true;
      }
    }
  }
  return false;
}

function get_machine_challenge($mid) {
  // get a new or existing machine challenge
  $mid = sanitize_input($mid);
  $sql = 'SELECT get_machine_challenge(uuid_to_bin(?)) AS challenge';
  $r = get_sql($sql,array(array('s',$mid)));
  if (!empty($r)) {
    if ($r->num_rows === 1) {
      return $r->fetch_assoc()['challenge'];
    }
  }
  return false;
}

function get_machine_id($publicKey) {
  // Get machine uuid from public key
  global $conn;
  $publicKey = sanitize_input($publicKey);
  $sql = 'SELECT bin_to_uuid(`id`) AS `mid`,`friendly_id` AS `display` FROM `machine` WHERE `pubkey` = ?';
  $r = get_sql($sql,array(array('s',$publicKey)));
  if (!empty($r)) {
    if ($r->num_rows === 1) {
      $row = $r->fetch_assoc();
      return array('mid'=>$row['mid'],'display'=>$row['display']);
    }
  } else {
    // Insert new machine into the system
    // This step probably needs to be protected somehow as it could lead to abuse/DoS
    $s2 = 'INSERT INTO machine (pubkey,last_ip) VALUES (?,?)';
    $ip = get_ip();
    $r2 = set_sql($s2,array(array('s',strval($publicKey)),array('s',strval($ip))));
    if ((bool) $r2) {
      return get_machine_id($publicKey);
    }
  }
  return false;
}

function get_machine_publicKey($mid) {
  // Get machine public key from uuid
  global $conn;
  $mid = sanitize_input($mid);
  $r = get_sql('select pubkey from machine where id = uuid_to_bin(?)',array(array('s',$mid)));
  if (!empty($r)) {
    if ($r->num_rows === 1) {
      $row = $r->fetch_assoc();
      return $row['pubkey'];
    }
  }
  return false;
}

function get_browser_session($bid) {
  // Browser ID is machine ID, representing the browser in the machine
  $uuid = get_uuid();
  $ip = get_ip();
  $bid = sanitize_input($bid);
  $agent = get_user_agent();
  $sql = 'SELECT bin_to_uuid(session.id) AS sid,bin_to_uuid(session.browser_id) AS bid,machine.friendly_id AS did FROM session JOIN machine ON machine.id = session.browser_id WHERE session.browser_id = uuid_to_bin(?) AND sysdate() < session.expire';
  $r = get_sql($sql,array(array('s',$bid)));
  if (!empty($r)) {
    if ($r->num_rows === 1) {
      return $r->fetch_assoc();
    }
    // Delete all sessions if there are more than one within the timeframe
    if ($r->num_rows > 1) {
      $s2 = 'DELETE FROM session WHERE browser_id = uuid_to_bin(?)';
      $r2 = set_sql($sql,array(array('s',$bid)));
      if ((bool) $r2) {
        // Deleted all sessions for machine, call self to get a new session.
        get_machine_session($bid);
      }  
    }
  } else {
    // Has zero rows or an error
    $s2 = 'INSERT INTO session (id,browser_id,ip_address,user_agent) VALUES (uuid_to_bin(?),uuid_to_bin(?),?,?)';
    $r2 = set_sql($s2,array(array('s',$uuid),array('s',$bid),array('s',$ip),array('s',$agent)));
    if ((bool) $r2) {
      // Inserted new session successfully, call self to get the session.
      get_machine_session($bid);
    }
  }
  return false;
}

/*function get_citizen_session($mid) {
  $uuid = get_uuid();
  $ip = get_ip();
  $mid = sanitize_input($mid);
  $agent = get_user_agent();
  $sql = 'SELECT bin_to_uuid(session.id) AS sid,bin_to_uuid(session.machine_id) AS mid,machine.friendly_id AS did FROM session JOIN machine ON machine.id = session.machine_id WHERE session.machine_id = uuid_to_bin(?) AND sysdate() < session.expire';
  $r = get_sql($sql,array(array('s',$mid)));
  if (!empty($r)) {
    if ($r->num_rows === 1) {
      return $r->fetch_assoc();
    }
    // Delete all sessions if there are more than one within the timeframe
    if ($r->num_rows > 1) {
      $s2 = 'DELETE FROM session WHERE machine_id = uuid_to_bin(?)';
      $r2 = set_sql($sql,array(array('s',$mid)));
      if ((bool) $r2) {
        // Deleted all sessions for machine, call self to get a new session.
        get_machine_session($mid);
      }  
    }
  } else {
    // Has zero rows or an error
    $s2 = 'INSERT INTO session (id,machine_id,ip_address,user_agent) VALUES (uuid_to_bin(?),uuid_to_bin(?),?,?)';
    $r2 = set_sql($s2,array(array('s',$uuid),array('s',$mid),array('s',$ip),array('s',$agent)));
    if ((bool) $r2) {
      // Inserted new session successfully, call self to get the session.
      get_machine_session($mid);
    }
  }
  return false;
}
*/

function get_machine_session($mid) {
  $uuid = get_uuid();
  $ip = get_ip();
  $mid = sanitize_input($mid);
  $agent = get_user_agent();
  $sql = 'SELECT bin_to_uuid(session.id) AS sid,bin_to_uuid(session.machine_id) AS mid,machine.friendly_id AS did FROM session JOIN machine ON machine.id = session.machine_id WHERE session.machine_id = uuid_to_bin(?) AND sysdate() < session.expire';
  $r = get_sql($sql,array(array('s',$mid)));
  if (!empty($r)) {
    if ($r->num_rows === 1) {
      return $r->fetch_assoc();
    }
    // Delete all sessions if there are more than one within the timeframe
    if ($r->num_rows > 1) {
      $s2 = 'DELETE FROM session WHERE machine_id = uuid_to_bin(?)';
      $r2 = set_sql($sql,array(array('s',$mid)));
      if ((bool) $r2) {
        // Deleted all sessions for machine, call self to get a new session.
        get_machine_session($mid);
      }  
    }
  } else {
    // Has zero rows or an error
    $s2 = 'INSERT INTO session (id,machine_id,ip_address,user_agent) VALUES (uuid_to_bin(?),uuid_to_bin(?),?,?)';
    $r2 = set_sql($s2,array(array('s',$uuid),array('s',$mid),array('s',$ip),array('s',$agent)));
    if ((bool) $r2) {
      // Inserted new session successfully, call self to get the session.
      get_machine_session($mid);
    }
  }
  return false;
}

function get_user_agent() {
  return sanitize_input($_SERVER['HTTP_USER_AGENT']);
}

function get_uuid() {
  global $conn;
  return $conn->query('select uuid()')->fetch_row()[0];
}

function login($username,$password) {
  global $conn;
  $username = sanitize_input($username);
  $password = sanitize_input($password);
  global $user_details_success;
  $verify = $conn->prepare($user_details_success);
  $verify->bind_param("s",$username);
  $verify->execute();
  $result = $verify->get_result();
  $row    = $result->fetch_assoc();
  if (($result->num_rows == 1) && password_verify($password,$row['password'])) {
    if (session_create($row['id'],$row['username'],$row['display_name'])) {
      return true;
    }
  }
  // Login failed
  return false;
}

function logout($refresh,$from) {
  if (isset($_SESSION['sid'],$_SESSION['uid'])) {
    // Kill the session in the dabase too
    $sid = sanitize_input($_SESSION['sid']);
    $uid = sanitize_input($_SESSION['uid']);
    session_delete($sid,$uid);
  }
  session_unset();
  session_destroy();
  if ($refresh === true) {
    echo('<meta http-equiv="Refresh" content="0;' . sanitize_input($from) . '">');
  }
}

function sanitize_post($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}

function sanitize_input($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  foreach(array(FILTER_SANITIZE_STRING,FILTER_SANITIZE_URL) as $filter) {
    $data = filter_var($data,$filter);
  }
  foreach(array('%','_') as $wildcard) {
    $data = str_replace($wildcard,'',$data);
  }
  return $data;
}

function sanitize_int($data) {
  return filter_var($data,FILTER_SANITIZE_NUMBER_INT);
}

function session_create($uid,$username,$display_name) {
  // Create a new session in the db
  global $conn;

  $uid = sanitize_input($uid);
  $username = sanitize_input($username);
  $display_name = sanitize_post($display_name);

  // First get a uuid
  $uuid = get_uuid();

  // Do some browser fingerprinting
  $ip    = get_ip();
  $agent = sanitize_input($_SERVER['HTTP_USER_AGENT']);

  $sql = 'insert into session (id,user_id,ip_address,user_agent) values (uuid_to_bin(?),uuid_to_bin(?),?,?)';
  try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss",$uuid,$uid,$ip,$agent);
    if ($stmt->execute()) {
      $_SESSION["sid"] = $uuid;
      $_SESSION["uid"] = $uid;
      $_SESSION["username"] = $username;
      $_SESSION["display_name"] = $display_name;
      return true;
    }
  } catch (Exception $e) {
    //return false;
  }
  return false;
}

function session_delete($sid,$uid) {
  // Delete session from db
  global $conn;

  $sid = sanitize_input($sid);
  $uid = sanitize_input($uid);
  if (strlen($sid) === 0 || strlen($uid) === 0) {
    return false;
  }

  $sql = 'delete from `session` where `id`=uuid_to_bin(?) and `user_id`=uuid_to_bin(?)';
  try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss",$sid,$uid);
    if ($stmt->execute()) {
      return true;
    }
  } catch (Exception $e) {
    return false;
  }
  return false;
}

function validate_session($type,$sid,$oid) {
  // Run query to determine if the session is valid in db. If it is, return true, else return false
  $sid = sanitize_input($sid);
  $oid = sanitize_input($oid);

  // Don't even bother the DB if the length of either of these is too small/not uuid
  if (strlen($sid) === 0 || strlen($oid) === 0) {
    return false;
  }

  if ($type === 'machine' || $type === 'MACHINE') {
    $col = 'machine_id';
  } elseif ($type === 'user' || $type === 'USER') {
    $col = 'user_id';
  } elseif ($type === 'browser' || $type === 'BROWSER') {
    $col = 'browser_id';
  } else {
    return false;
  }

  // Setup Statements
  #$sql  = 'SELECT count(id) AS result, uuid_to_bin(id) AS sid, uuid_to_bin(user_id) AS uid, uuid_to_bin(machine_id) AS mid, uuid_to_bin(browser_id) AS bid FROM `session` WHERE id = uuid_to_bin(?) AND '.$col.' = uuid_to_bin(?) AND sysdate() < `expire`';
  $sql  = 'SELECT count(id) AS result FROM `session` WHERE id = uuid_to_bin(?) AND '.$col.' = uuid_to_bin(?) AND sysdate() < `expire`';
  #$s2   = 'UPDATE `session` SET `expire`=ADDDATE(sysdate(),INTERVAL (SELECT `value` FROM `params` WHERE `KEY`=\'SESSION_INACTIVITY_LENGTH\') MINUTE),ip_address=?,user_agent=? WHERE id = uuid_to_bin(?) AND '.$col.' = uuid_to_bin(?)';
  $s2   = 'UPDATE `session` SET `expire`=ADDDATE(sysdate(),INTERVAL (SELECT `value` FROM `params` WHERE `KEY`=\'SESSION_INACTIVITY_LENGTH\') MINUTE) WHERE id = uuid_to_bin(?) AND '.$col.' = uuid_to_bin(?)';

  // Setup arguments
  $args = array(array('s',$sid),array('s',$oid));

  $r = get_sql($sql,$args);
  if (!empty($r)) {
    if ($r->num_rows === 1) {
      $result = $r->fetch_assoc();
      if ($result['result'] === 1) {
        #$r2 = set_sql($s2,array(array('s',get_ip()),array('s',get_user_agent()),array('s',$sid),array('s',$oid)));
        $r2 = set_sql($s2,$args);
        if ((bool) $r2) {
          return true;
        }
      }
    }
  }
  return false;
}

function valid_session() {
  // file_put_contents('phplog.txt',var_export($_SESSION,true),FILE_APPEND);
  if (isset($_SESSION['sid'],$_SESSION['uid'])) {
    // User Session
    $sid = sanitize_input($_SESSION['sid']);
    $uid = sanitize_input($_SESSION['uid']);
    return validate_session('USER',$sid,$uid);
  } elseif (isset($_SESSION['sid'],$_SESSION['mid'])) {
    // Machine session
    $sid = sanitize_input($_SESSION['sid']);
    $mid = sanitize_input($_SESSION['mid']);
    return validate_session('MACHINE',$sid,$mid);
  } elseif (isset($_SESSION['sid'],$_SESSION['bid'])) {
    $sid = sanitize_input($_SESSION['sid']);
    $bid = sanitize_input($_SESSION['bid']);
    return validate_session('BROWSER',$sid,$bid);
  }
  return false;  
}

function verify_citizen_signature($cid,$response) {
  $cid = sanitize_input($cid);
  $response = sanitize_input($response);
  $challenge = get_citizen_challenge($cid);
  if (isset($challenge,$response)) {
    $publicKey = get_citizen_publicKey($cid); 
    if (isset($publicKey)) {
      $publicKey = openssl_get_publickey(base64_decode($publicKey,true));  
      $publicKeyDetails = openssl_pkey_get_details($publicKey)['rsa'];  
      $pubkey = PublicKeyLoader::load([
        'e' => new BigInteger(bin2hex($publicKeyDetails['e']),16),
        'n' => new BigInteger(bin2hex($publicKeyDetails['n']),16),
      ]);
      error_log($challenge);
      // $ok = $pubkey->withhash('sha384')->withMGFHash('sha384')-withSaltLength('222')->verify($challenge,base64_decode($response,true));
      $ok = $pubkey->withHash('sha1')->verify($challenge,base64_decode($response,true));
      // $ok = openssl_verify($challenge,base64_decode($response,true),$publicKey,OPENSSL_ALGO_SHA1);
      if ($ok) {
        error_log('session validated');
        return true;
      }
    }
  }
  error_log('session validation failed');
  return false;
}

function verify_machine_signature($mid,$response,$saltLength) {
  # https://phpseclib.com/docs/rsa
  # https://www.php.net/manual/en/function.openssl-pkey-get-details.php
  # https://www.php.net/manual/en/function.openssl-pkey-get-public.php
  # Get challenge for machine
  $mid = sanitize_input($mid);
  $response = sanitize_input($response);
  $challenge = get_machine_challenge($mid);
  if (isset($challenge)) {
    $publicKey = get_machine_publicKey($mid);
    if (isset($publicKey,$saltLength)) {
      $saltLength = sanitize_int($saltLength);
      $publicKey = openssl_get_publickey(base64_decode($publicKey,true));
      $publicKeyDetails = openssl_pkey_get_details($publicKey)['rsa'];
      $pubkey = PublicKeyLoader::load([
        'e' => new BigInteger(bin2hex($publicKeyDetails['e']),16),
        'n' => new BigInteger(bin2hex($publicKeyDetails['n']),16),
      ]);
      $ok = $pubkey->withHash('sha256')->withMGFHash('sha256')->withSaltLength($saltLength)->verify($challenge, base64_decode($response,true));
      if ($ok) {
        return true;
      }
    }
  }
  return false;
}
?>