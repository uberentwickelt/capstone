$('#adminTabs a').on('click', function (e) {
  if (e.target.id !== 'machines-dropdown') {
    const tab = $(this).attr('aria-controls');
    //console.log(e);
    //console.log($(this).attr('aria-controls'));
    switch(tab) {
      case 'activation':
      case 'enrolled':
      case 'deactivated':
        $.post('/api/get/machine',{type:tab}).done((data) => { $('#'+tab).html(data); });
        break;
      case 'elections':
        $.post('/api/get/election',{type:tab}).done((data) => { 
          $('#'+tab).html(data);
          // Add election when clicking save
          $('#addElectionSave').on('click', function (e) {
            $.post('/api/set/election',{electionName:$('#electionName').val(),electionYear:$('#electionYear').val()}, (data) => {
              if(data === 'SUCCESS') {
                //$('.toast').toast({animation:true,autohide:true,delay:1000});
                //$('#addElectionToast').toast('show');
                $('#addElection').modal('hide');
              } else {
                $('#addElectionMsg').html('An error occured adding election');
              }
            });
          });
          $('.bi').closest('td').each((index)=>{
            $($('.bi').closest('td')[index]).on('click',(e)=>{
              const rowId = e.currentTarget.children[0].id;
              $.post('/api/get/race',{electionID:rowId}).done((data)=>{
                $('#electionView').html(data);
                $('#electionView-tab').click();//tab('show');
              });
            });
          });
        });
        break;
      case 'races':
        $.post('/api/get/race',{type:tab}).done((data) => { $('#'+tab).html(data); });
        break;
      case 'questions':
        $.post('/api/get/question',{type:tab}).done((data) => { $('#'+tab).html(data); });
        break;
      default:
        break;
    }
    e.preventDefault();
    $(this).tab('show');
  }
});
// Automatically load the elections tab
$('#elections-tab').click();