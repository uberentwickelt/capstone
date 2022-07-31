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