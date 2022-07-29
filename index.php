<meta id="meta-title" name="title" content="Voting Booth">
<div id="sign-in" class="container vote-vertical-upper-center">
  <div class="row h-100 translate-middle text-center mx-auto my-auto d-flex justify-content-center">
    <div clas="col my-auto translate-middle">
      <img class="mb-4" src="/img/vote-logo.png" alt="" width="144" height="144">
      <h1 class="h3 mb-3 fw-normal">Please insert card</h1>
      <br>
      <svg id="testWS" xmlns="http://www.w3.org/2000/svg" width="96" height="96" fill="grey" class="bi bi-person-badge bi-10x" viewBox="0 0 16 16">
        <path d="M6.5 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1h-3zM11 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
        <path d="M4.5 0A2.5 2.5 0 0 0 2 2.5V14a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2.5A2.5 2.5 0 0 0 11.5 0h-7zM3 2.5A1.5 1.5 0 0 1 4.5 1h7A1.5 1.5 0 0 1 13 2.5v10.795a4.2 4.2 0 0 0-.776-.492C11.392 12.387 10.063 12 8 12s-3.392.387-4.224.803a4.2 4.2 0 0 0-.776.492V2.5z"/>
      </svg>
      <p class="mt-5 mb-3 text-muted">If you do not have your Drivers License, use your state ID</p>
      <div id='ws-test'></div>
    </div>
  </div>
</div>
<div id="waiting-on-card-validation" class="container vote-vertical-upper-center d-none">
  <div class="row h-100 translate-middle text-center mx-auto my-auto d-flex justify-content-center">
    <div clas="col my-auto translate-middle">
      <!-- or spinner-border -->
      <div class="spinner-grow m-5 text-info" style="width: 5rem; height: 5rem;" role="status">
        <span class="sr-only">Loading...</span>
      </div>
      <h1 class="h3 mb-3 fw-normal">Authorizing Card <span class="dot dot1">.</span><span class="dot dot2">.</span><span class="dot dot3">.</span></h1>
    </div>
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