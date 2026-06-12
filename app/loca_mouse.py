import pyautogui
import time

while True:
    print(pyautogui.position(), end="\r")
    time.sleep(0.1)