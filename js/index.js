function getMeta(metaName) {
  const metas = document.getElementsByTagName('meta');
  for (let i = 0; i < metas.length; i++) {
    if (metas[i].getAttribute('name') === metaName) {
      return metas[i].getAttribute('content');
    }
  }
  return '';
}

$(document).ready((async () => {
  const socket = new WebSocket('ws://localhost:1776');
  socket.addEventListener('open', function (event) {
      socket.send('Connection Established');
  });

  socket.addEventListener('message', function (event) {
    if (isJson(event.data)) {
      // Treat event.data as json

    } else {
      // Treat as a string if not json
      switch(event.data) {
        case 'No Card':
          $('#sign-in').removeClass('d-none');
          break;
        case 'Card Compatible':
          $('#sign-in').addClass('d-none');
          $('#waiting-on-card-validation').removeClass('d-none');
        default:
          console.log(event.data);
          $('#ws-test').append(event.data+'<br>');
          break;
      }
    }
  });

  $('#testWS').on('click',()=>{
    console.log('test clicked thing');
    socket.send('Test message from client');
  });

  const contactServer = () => {
    socket.send("Initialize");
  }
})());