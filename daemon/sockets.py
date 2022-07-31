# Named sockets to not get confused with the python module socket
import asyncio, functools, websockets
from websockets import WebSocketServerProtocol
from websockets.exceptions import ConnectionClosed,ConnectionClosedError
from queue import Queue
#from asyncio import Queue

# https://betterprogramming.pub/how-to-create-a-websocket-in-python-b68d65dbd549
# https://github.com/aaugustin/websockets/issues/152
class Server:
  clients = set()
  fromMainThread:Queue = Queue()
  toMainThread:Queue   = Queue()

  def __init__(self,fromMainThread,toMainThread):
    self.fromMainThread = fromMainThread
    self.toMainThread   = toMainThread 

  #async def consumer(self,message) -> None:
  #  #if message != False:
  #  print('message from browser: '+str(message))
  #  self.toMainThread.put(message)

  async def consumer_handler(self,ws) -> None:
    while True:
      #if ws in self.clients:
      print('ws in clients')
      async for message in ws:
        print('message from browser: '+str(message))
        self.toMainThread.put(message)
          #await self.consumer(message)
          #print('message from browser: '+str(message))
          #self.toMainThread.put(message)

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

  async def handler(self,ws:WebSocketServerProtocol) -> None:
    # Thank you: https://github.com/aaugustin/websockets/issues/152
    await self.register(ws)
    
    consumer_task = asyncio.create_task(self.consumer_handler(ws))
    producer_task = asyncio.create_task(self.producer_handler(ws))

    done, pending = await asyncio.gather(
      *[consumer_task, producer_task],
      return_exceptions=True
    )
    #if type(pending) != ConnectionClosedError:
    #  for task in pending:
    #    task.cancel()
    #    await self.unregister(ws)
    #    raise
    if type(pending) == ConnectionClosedError or type(done) == ConnectionClosedError:
      await self.unregister(ws)
    if type(done) != ConnectionClosedError:
      for task in done:
        task.cancel()
        await self.unregister(ws)
        raise

  async def producer(self):
    while True:
      if self.fromMainThread.not_empty:
        return self.fromMainThread.get()

  async def producer_handler(self,ws) -> None:
    while True:
      if ws in self.clients:
        message = await self.producer()
        await ws.send(message)
        #await asyncio.wait([client.send(self.fromMainThread.get()) for client in self.clients])
    #if ws in self.clients:
    #  message = await self.producer()
    #  if message != False:
    #    print('producer handler message: '+str(message))
    #    await ws.send(message)

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
    toMainThread.put(message)

async def producer(fromMainThread):
  while True:
    message = fromMainThread.get()
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

def threadWS(toWSThread,fromWSThread):
  # Thank you ZachL: https://stackoverflow.com/a/72220058
  try:
    loop = asyncio.get_event_loop()
  except RuntimeError as e:
    if str(e).startswith('There is no current event loop in thread'):
      l2 = asyncio.new_event_loop()
      asyncio.set_event_loop(l2)
      loop = asyncio.get_event_loop()
    else:
      raise
  finally:
    start_server = websockets.serve(Server(toWSThread,fromWSThread).handler,'localhost',1776)
    #start_server = websockets.serve(functools.partial(handler,fromMainThread=toWSThread,toMainThread=fromWSThread),'localhost', 1776)
    loop.run_until_complete(start_server)
    loop.run_forever()