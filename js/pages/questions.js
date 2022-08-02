$(document).ready((async () => {
  const socket = new WebSocket('ws://localhost:1776');
  socket.addEventListener('open', function (event) {
      socket.send('Connection Established');
  });

  socket.addEventListener('message', function (event) {
     if (event.data.startsWith('Signed: ')) {
      console.log(event.data);
      signed_id = event.data.split(': ')[1];
      $('#'+signed_id).removeClass('bi-x-circle');
      $('#'+signed_id).removeClass('text-danger');
      $('#'+signed_id).addClass('bi-check-circle');
      $('#'+signed_id).addClass('text-success');
    }
  });
})());