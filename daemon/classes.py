import asyncio
from websockets import WebSocketServerProtocol
from websockets.exceptions import ConnectionClosed,ConnectionClosedError
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
# https://github.com/aaugustin/websockets/issues/152
class Server:
  clients = set()
  fromMainThread:Queue = Queue()
  toMainThread:Queue   = Queue()

  def __init__(self,fromMainThread,toMainThread):
    self.fromMainThread = fromMainThread
    self.toMainThread   = toMainThread 
  
  async def consumer(self,message,uri) -> None:
    print('consumer hit: '+message)
    #if len(message) > 0:
    #  self.toMainThread.put(message)

  async def consumer_handler(self,ws,uri) -> None:
    while True:
      if ws in self.clients:
        async for message in ws:
          print('this is a consumer test')
        #message = await ws.recv()
        #await self.consumer(message,uri)

        #async for message in ws:
        #  await self.consumer(message,uri)

  async def handler(self,ws:WebSocketServerProtocol,uri:str) -> None:
    # Thank you: https://github.com/aaugustin/websockets/issues/152
    await self.register(ws)
    
    consumer_task = asyncio.create_task(self.consumer_handler(ws,uri))
    producer_task = asyncio.create_task(self.producer_handler(ws))

    done, pending = await asyncio.gather(
      *[consumer_task, producer_task],
      return_exceptions=True
    )
    if type(pending) != ConnectionClosedError:
      for task in pending:
        task.cancel()
        await self.unregister(ws)
    if type(pending) == ConnectionClosedError or type(done) == ConnectionClosedError:
      await self.unregister(ws)
    if type(done) != ConnectionClosedError:
      for task in done:
        task.cancel()
        await self.unregister(ws)

  async def producer(self):
    if self.fromMainThread.not_empty:
      return self.fromMainThread.get()
    return False

  async def producer_handler(self,ws) -> None:
    while True:
      if ws in self.clients:
        message = await self.producer()
        if message != False:
          await ws.send(message)

  async def register(self, ws:WebSocketServerProtocol) -> None:
    if ws not in self.clients:
      self.clients.add(ws)
      print('added client')

  async def unregister(self, ws:WebSocketServerProtocol) -> None:
    if ws in self.clients:
      self.clients.remove(ws)
      print('removed client')