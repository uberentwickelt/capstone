<meta name="title" content="Admin">
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST["submit"]) && isset($_POST["username"]) && isset($_POST["password"])) {
    $username = sanitize_input($_POST["username"]);
    $password = sanitize_input($_POST["password"]);
    if (!login($username,$password)) {
      logout(false,"");
    } else {
      header("Refresh:0");
    }
  } else {
    header("Refresh:0");
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if (!isset($_SESSION["sid"]) || !isset($_SESSION["uid"])) { 
    $_SESSION["sid"] = "";
    $_SESSION["uid"] = "";
  ?>
  <div id="admin-login-form" class="container">
    <form class="needs-validation form-signin text-center" method="post" action="<?php print($current_uri);?>" novalidate>
      <h1 class="h3 mb-3 fw-normal">Admin Login</h1>
      <div class="form-floating">
        <input type="email" class="form-control" id="username" name="username" placeholder="Username">
      </div>
      <div class="form-floating"></div>
      <div class="form-floating">
        <input type="password" class="form-control" id="password" name="password" placeholder="Password">
      </div>
      <button class="w-100 btn btn-lg btn-primary" type="submit" name="submit">Login</button>
    </form>
  </div>
  <?php } else {
    // check if session id is valid
    if (valid_session()) { ?>
    <div class="container">
      <h1>Welcome, <?php print(sanitize_post($_SESSION["display_name"])); ?></h1>
    </div>
    <div class="container">
      <ul class="nav nav-tabs justify-content-center" id="adminTabs" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" id="elections-tab" data-toggle="tab" href="#elections" role="tab" aria-controls="elections" aria-selected="true">Elections</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" id="machines-dropdown" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false" aria-selected="false">Machines</a>
          <div class="dropdown-menu">
            <a class="dropdown-item" id="activation-tab" data-toggle="tab" href="#activation" role="tab" aria-controls="activation" aria-selected="false">Awaiting Activation</a>
            <a class="dropdown-item" id="enrolled-tab" data-toggle="tab" href="#enrolled" role="tab" aria-controls="enrolled" aria-selected="false">Enrolled</a>
            <a class="dropdown-item" id="deactivated-tab" data-toggle="tab" href="#deactivated" role="tab" aria-controls="deactivated" aria-selected="false">Deactivated</a>
          </div>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="races-tab" data-toggle="tab" href="#races" role="tab" aria-controls="races" aria-selected="false">Races</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="questions-tab" data-toggle="tab" href="#questions" role="tab" aria-controls="questions" aria-selected="false">Questions</a>
        </li>
        <a class="nav-link d-none" id="electionView-tab" data-toggle="tab" href="#electionView" role="tab" aria-controls="electionView" aria-selected="false"></a>
      </ul>
      <div class="tab-content" id="adminTabsContent">
        <div class="tab-pane fade show active" id="elections" role="tabpanel" aria-labelledby="elections-tab"></div>
        <div class="tab-pane fade" id="activation" role="tabpanel" aria-labelledby="activation-tab"></div>
        <div class="tab-pane fade" id="enrolled" role="tabpanel" aria-labelledby="enrolled-tab"></div>
        <div class="tab-pane fade" id="deactivated" role="tabpanel" aria-labelledby="deactivated-tab"></div>
        <div class="tab-pane fade" id="races" role="tabpanel" aria-labelledby="races-tab"></div>
        <div class="tab-pane fade" id="questions" role="tabpanel" aria-labelledby="questions-tab"></div>
        <div class="tab-pane fade" id="electionView" role="tabpanel"></div>
      </div>
    </div>
</div>
    </div>
    <?php } else {
      logout(true,"");
    }
  }
}
?>