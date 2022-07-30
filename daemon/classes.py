import asyncio
from websockets import WebSocketServerProtocol
from websockets.exceptions import ConnectionClosed,ConnectionClosedError
#from queue import Queue
from asyncio import Queue

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
  
  #async def consumer(self,message,uri) -> None:
  #  print('consumer hit: '+message)
  #  #if len(message) > 0:
  #  #  self.toMainThread.put(message)

  async def consumer_handler(self,ws,uri) -> None:
    while True:
      if ws in self.clients:
        async for message in ws:
          print('message from browser: '+str(message))
          await self.toMainThread.put(message)

  #async def consumer_handler(self,ws,uri) -> None:
  #  while True:
  #    if ws in self.clients:
  #      async for message in ws:
  #        print('this is a consumer test')
  #      #message = await ws.recv()
  #      #await self.consumer(message,uri)
#
  #      #async for message in ws:
  #      #  await self.consumer(message,uri)

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
        raise
    if type(pending) == ConnectionClosedError or type(done) == ConnectionClosedError:
      await self.unregister(ws)
    if type(done) != ConnectionClosedError:
      for task in done:
        task.cancel()
        await self.unregister(ws)
        raise

  async def producer(self):
    if await self.fromMainThread.not_empty:
      return await self.fromMainThread.get()
    return False

  async def producer_handler(self,ws) -> None:
    if ws in self.clients:
      message = await self.producer()
      if message != False:
        print('producer handler message: '+str(message))
        await ws.send(message)

  async def register(self, ws:WebSocketServerProtocol) -> None:
    if ws not in self.clients:
      self.clients.add(ws)
      print('added client')

  async def unregister(self, ws:WebSocketServerProtocol) -> None:
    if ws in self.clients:
      self.clients.remove(ws)
      print('removed client')

async def consumer_handler(ws,toMainThread:Queue) -> None:
  async for message in ws:
    print('message from browser: '+str(message))
    await toMainThread.put(message)

async def producer(fromMainThread):
  while True:
    message = await fromMainThread.get()
    print('message to browser: '+str(message))
    return message

async def producer_handler(ws,fromMainThread:Queue) -> None:
  await ws.send(await producer(fromMainThread))

async def handler(ws,uri,fromMainThread:Queue,toMainThread:Queue) -> None:
  producer_task = asyncio.ensure_future(producer_handler(ws,fromMainThread))
  consumer_task = asyncio.ensure_future(consumer_handler(ws,toMainThread))
  #producer_task = asyncio.create_task(producer_handler(ws,fromMainThread))
  #consumer_task = asyncio.create_task(consumer_handler(ws,toMainThread))

  #done, pending = await asyncio.wait([consumer_task,producer_task],return_when=asyncio.FIRST_COMPLETED)
  done, pending = await asyncio.gather(*[consumer_task,producer_task],return_exceptions=True)
  if type(done) != ConnectionClosedError and type(done) != RuntimeError and type(done) != TypeError:
    for task in done:
      task.cancel()
      raise
  if type(pending) != ConnectionClosedError and type(pending) != RuntimeError and type(pending) != TypeError:
    for task in pending:
      task.cancel()
      raise
  ##if type(done) != ConnectionClosedError:
  ##  for task in done:
  ##    task.cancel()
  ##if type(pending) != ConnectionClosedError and type(pending) != RuntimeError and type(pending) != TypeError:
  ##  for task in pending:
  ##    task.cancel()
  #for task in pending:
  #  task.cancel()
  #if type(pending) != ConnectionClosedError:
  #  for task in pending:
  #    task.cancel()
  #if type(pending) == ConnectionClosedError or type(done) == ConnectionClosedError:
  #  pass
  #if type(done) != ConnectionClosedError:
  #  for task in done:
  #    task.cancel()
  #    pass