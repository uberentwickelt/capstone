function getMeta(metaName) {
  const metas = document.getElementsByTagName('meta');
  for (let i = 0; i < metas.length; i++) {
    if (metas[i].getAttribute('name') === metaName) {
      return metas[i].getAttribute('content');
    }
  }
  return '';
}

function onError(event) {
  console.log('WS -> Error ocurred: '+event.data);
}

function onOpen(event) {
  console.log('WS -> Connection Established');
}

function onMessage(event) {
  console.log('WS -> Message Event: '+event.data);
}

$(document).ready((async () => {
  const socket = new WebSocket('ws://localhost:1776');
  socket.addEventListener('open', function (event) {
      socket.send('Connection Established');
  });

  socket.addEventListener('message', function (event) {
    console.log(event.data);
    $('#ws-test').append(event.data+'<br>');
    if (event.data === 'logged_in') {
      console.log('got logged in message');
    }
  });

  const contactServer = () => {
    socket.send("Initialize");
  }
})());