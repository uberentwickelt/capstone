<footer class="translate-middle py-1 my-1 d-flex flex-wrap justify-content-between align-items-center">
  <div class="container border-top">
    <div class="col-md-4 align-items-center float-left text-center">
      <span class="mb-3 mb-md-0 text-muted">Vote <?php echo(getdate()['year']); ?>!</span>
    </div>
    <?php if (isset($_SESSION['did'])) { ?>
    <div class="col-md-4 align-items-center float-right text-center">
      <span class="mb-3 mb-md-0 text-muted">Machine ID: <?php echo(sanitize_input($_SESSION['did'])); ?></span>
    </div>
    <?php } ?>
  </div>
</footer>