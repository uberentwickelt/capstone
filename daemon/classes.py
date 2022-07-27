import asyncio
from websockets import WebSocketServerProtocol
from queue import Queue

class Backoff:
  backoff = 2
  maxBackoff = 120
  def __init__(self):
    return
  def increment(self):
    return_value = self.backoff
    if (self.backoff <= self.maxBackoff):
      self.backoff = self.backoff + self.backoff
    return return_value

# https://betterprogramming.pub/how-to-create-a-websocket-in-python-b68d65dbd549
class Server:
  clients = set()
  fromMainThread:Queue = Queue()
  toMainThread:Queue   = Queue()

  def __init__(self,fromMainThread,toMainThread):
    self.fromMainThread = fromMainThread
    self.toMainThread   = toMainThread

  # Get messages from main thread
  async def get_messages(self):
    if self.clients and not self.fromMainThread.empty:
      await asyncio.wait([client.send(self.fromMainThread.get()) for client in self.clients])
      #await self.send_to_clients(self.fromMainThread.get())

  async def distribute(self, ws:WebSocketServerProtocol) -> None:
    #async for message in self.get_messages():
    #  await self.send_to_clients(message)
    async for message in ws:
      await self.get_messages()
    #async for message in ws:
    #  await self.send_to_clients(message)

  async def handler(self, ws:WebSocketServerProtocol, uri:str) -> None:
    await self.register(ws)
    try:
      await self.distribute(ws)
      #for client in self.clients:
      #  await client.send('This is a test')
    finally:
      await self.unregister(ws)

  async def register(self, ws:WebSocketServerProtocol) -> None:
    print('added client')
    self.clients.add(ws)
 
  async def send_to_clients(self, message: str) -> None:
    if self.clients:
      await asyncio.wait([client.send(message) for client in self.clients])

  async def get_from_client(self, message:str) -> None:
    if self.clients:
      await asyncio.wait([client.recv(message) for client in self.clients])

  async def unregister(self, ws:WebSocketServerProtocol) -> None:
    print('removed client')
    self.clients.remove(ws)