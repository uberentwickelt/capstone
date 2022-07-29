from selenium import webdriver
from selenium.webdriver.firefox.service import Service
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from webdriver_manager.firefox import GeckoDriverManager

def launch(page,kiosk):
  ff_options = None
  if kiosk:
    ff_options = webdriver.FirefoxOptions()
    ff_options.add_argument("--kiosk")
  driver = webdriver.Firefox(service=Service(GeckoDriverManager().install()),options=ff_options)
  driver.get(page)