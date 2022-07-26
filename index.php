<meta id="meta-title" name="title" content="Voting Booth">
<meta id="sid" name="sid" content="">
<meta id="mid" name="mid" content="">
<meta id="did" name="did" content="">
<div id="sign-in" class="container">
  <div class="row h-100 translate-middle text-center mx-auto my-auto">
    <!--<div clas="col my-auto">-->
      <img class="mb-4" src="/img/vote-logo.png" alt="" width="144" height="144">
      <h1 class="h3 mb-3 fw-normal">Please insert card</h1>
      <p class="mt-5 mb-3 text-muted">If you do not have your Drivers License, use your state ID</p>
    <!--</div>-->
  </div>
</div>
<?php
if (isset($_SESSION['sid'],$_SESSION['bid'],$_SESSION['did'])) {
  $sid = sanitize_input($_SESSION['sid']);
  $mid = sanitize_input($_SESSION['bid']);
  if (validate_session('BROWSER',$sid,$mid)) {
    // Logged in
    ?>
<div id="logged-in" class="container d-none">
  </div>
<div class="container">
  <div class="row translate-middle d-flex flex-wrap justify-content-between align-items-center">
    <div class="col align-items-center float-left text-center">SID:</div>
    <div class="col align-items-center float-right text-center">MID/BID:</div>
  </div>
  <div class="row translate-middle d-flex flex-wrap justify-content-between align-items-center">
    <div id="sid-data" class="col align-items-center float-left text-center"><?php print(sanitize_input($_SESSION['sid']));?></div>
    <div id="mid-data" class="col align-items-center float-right text-center"><?php print(sanitize_input($_SESSION['bid']));?></div>
  </div>
</div>
    <?php
  } else {
    // Not logged in
    ?>
<div class="container">
  <div class="row">
    <div class="col">Not logged in</div>
  </div>
</div>
    <?php
  }
} else {
  if (isset($_GET['sid'],$_GET['mid'])) {
    // Try to get a browser session with machine session id as authentication
    $sid = sanitize_input($_GET['sid']);
    $mid = sanitize_input($_GET['mid']);
    $session = get_browser_session($mid);
    if ((bool) $session) {
      // Got a browser session, set session variables and reload page
      $_SESSION['sid'] = $session['sid'];
      $_SESSION['bid'] = $session['bid'];
      $_SESSION['did'] = $session['did'];
      echo('<meta http-equiv="Refresh" content="0;/">');
    }
  }
}

?>

<!--
  <form class="form-signin my-auto text-center">
      <img class="mb-4" src="/img/vote-logo.png" alt="" width="144" height="144">
      <h1 class="h3 mb-3 fw-normal">Please insert card</h1>
      <p class="mt-5 mb-3 text-muted">If you do not have your Drivers License, use your state ID</p>
    </form>
-->
<!--<div class="form-floating">
        <input type="email" class="form-control" id="floatingInput" placeholder="name@example.com">
        <label for="floatingInput">Drivers Licese ID/State ID/Voting ID</label>
      </div>
      <div class="form-floating">
        <input type="password" class="form-control" id="floatingPassword" placeholder="Password">
        <label for="floatingPassword">Pin</label>
      </div>
      <button class="w-100 btn btn-lg btn-primary" type="submit">Begin</button>
    -->