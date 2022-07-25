<meta id="meta-title" name="title" content="Add User">
<?php if (!isset($_POST['submit'])) { ?>
<div class="container">
  <div class="container">
    <form class="needs-validation form-signin text-center" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" novalidate>
      <h1 class="h3 mb-3 fw-normal">Create User</h1>
      <div class="form-floating">
        <input type="text" class="form-control" id="username" name="username" placeholder="Username">
      </div>
      <div class="form-floating">
        <input type="text" class="form-control" id="display_name" name="display_name" placeholder="Display Name">
      </div>
      <div class="form-floating">
        <input type="password" class="form-control" id="password" name="password" placeholder="Password">
      </div>
      <div class="form-floating">
        <input type="password" class="form-control" id="password-verify" name="password-verify" placeholder="Verify Password">
      </div>
      <button class="w-100 btn btn-lg btn-primary" type="submit" name="submit">Create User</button>
    </form>
  </div>
</div>
<?php } else { 
  if (isset($_POST["submit"]) && isset($_POST["username"]) && isset($_POST["display_name"]) && isset($_POST["password"]) && isset($_POST["password-verify"])) { 
    $username = sanitize_input($_POST["username"]);
    $display_name = sanitize_post($_POST["display_name"]);
    $password = sanitize_input($_POST["password"]);
    $pv = sanitize_input($_POST["password-verify"]);
    if ($password === $pv) {
      if (add_user($username,$display_name,$password)) { ?>
        User added successfully
        <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">Add another?</a>
      <?php } else { ?>
        User add error occured. Either the user already exists or another issue is present<br>
        <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">Try Again</a>
      <?php }
    } else { ?>
      Passwords do not match<br>
      <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">Try Again</a>
<?php }
  } else { ?>
    Invalid Request<br>
    <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">Try Again</a>
<?php } } ?>