function wsReconnect() {
  setTimeout(function() {
    wsConnect();
  }, 1000);
}

function wsConnect() {
  const socket = new WebSocket('ws://localhost:1776');
  socket.addEventListener('open', function (event) {
      socket.send('Connection Established');
  });
  socket.addEventListener('message', function (event) {
    if (isJson(event.data)) {
      console.log(event.data);
      // Treat event.data as json
      if ('Logged in' in event.data) {
        // Add a dummy form to the page with cid value then redirect to there
        var form = $('<form action="/pages/questions" method="post">' +
          '<input type="hidden" name="cid" value="' + event.data['Logged in'] + '" />' +
          '</form>');
        $('body').append(form);
        form.submit();
      }
    } else {
      // Treat as a string if not json
      if (event.data.startsWith('Logged in: ')) {
        user_card_id = event.data.split(': ')[1];
        console.log('Card ID: '+user_card_id);
        // Add a dummy form to the page with cid value then redirect to there
        // https://stackoverflow.com/questions/8389646/send-post-data-on-redirect-with-javascript-jquery
        var form = $('<form action="/pages/questions" method="post">' +
          '<input type="hidden" id="card_id" name="card_id" value="' + user_card_id + '" />' +
          '</form>');
        $('body').append(form);
        form.submit();
      } else {
        switch(event.data) {
          case 'Connected':
            console.log(event.data);
            $('#connect-in-waiting').addClass('d-none');
            $('#sign-in').removeClass('d-none');
            break;
          case 'No Card':
            console.log(event.data)
            // $('#sign-in').removeClass('d-none');
            break;
          case 'Card Compatible':
            console.log(event.data)
            $('#sign-in').addClass('d-none');
            $('#waiting-on-card-validation').removeClass('d-none');
          default:
            console.log(event.data);
            $('#ws-test').append(event.data+'<br>');
            break;
        }
      }
    }
  });
  socket.addEventListener('close', function (event) {
    console.log('Socket closed. Reconnecting.');
    wsReconnect();
  });
  socket.addEventListener('error', function (event) {
    console.log('Socket error encountered. Reconnecting.');
    wsReconnect();
  });
  $('#testWS').on('click',()=>{
    console.log('test clicked thing');
    socket.send('Test message from client');
  });
}

$(document).ready((async () => { 
  wsConnect();
  /*
  const contactServer = () => {
    socket.send("Initialize");
  }
  */
})());