<div class="container border-top">
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
      <a class="navbar-brand" href="/">Vote</a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse justify-content-end" id="navbarSupportedContent">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" href="/admin">Admin</a>
          </li>
          <?php if (valid_session()) { ?>
            <li class="nav-item">
              <a class="nav-link" href="/pages/create_account">Create Account</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#" onclick="logout()">Logout</a>
            </li>
          <?php } ?>
        </ul>
      </div>
    </div>
  </nav>
</div>