<?php
include_once($_SERVER['DOCUMENT_ROOT'].'/api/api_config.php');
$response = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (valid_session()) { ?>
    <div class="row align-items-center py-2">
      <div class="col-md-6 align-items-center">
        <h3>Elections</h3>
      </div>
      <div class="col-md-6">
        <!-- Button trigger modal -->
        <button type="button" class="btn btn-primary float-right" data-toggle="modal" data-target="#addElection">
          Add Election
        </button>
      </div>
    </div>
    <!-- Modal -->
    <div class="modal fade" id="addElection" tabindex="-1" role="dialog" aria-labelledby="addElectionLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addElectionLabel">Add Election</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div id="addElectionMsg" class="alert-warning" role="alert"></div>
            <form class="needs-validation text-center" method="post" action="none" novalidate>
              <div class="form-floating">
                <input type="text" class="form-control" id="electionName" name="electionName" placeholder="Election Name">
              </div>
              <div class="form-floating">
                <input type="text" class="form-control" id="electionYear" name="electionYear" placeholder="Election Year">
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button id="addElectionSave" type="button" class="btn btn-primary">Save changes</button>
          </div>
        </div>
      </div>
    </div>
    <!--<div aria-live="polite" aria-automatic="true" style="position:relative;min-height:200px;">
      <--<div id="addElectionToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true" >--
      <div id="addElectionToast" class="toast" style="position:absolute;top:0;right:0;">
        <div class="toast-header">
          <--<img src="..." class="rounded mr-2" alt="...">--
          <strong class="mr-auto">Elections</strong>
          <small>now</small>
          <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="toast-body">
          Election added successfully.
        </div>
      </div>
    </div>-->
  <?php
    display_table('select bin_to_uuid(id) as id,name,year from election order by year,name',array());
  } else {
    $response = 'invalid session';
  }
} else {
  $response = 'invalid request';
}
if (count($response) > 0) {
  print($response);
}
?>