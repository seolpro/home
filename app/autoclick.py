import pyautogui
import time

# 클릭할 좌표 (예시)
X = 1231
Y = 760

INTERVAL = 20  # 3분 = 180초

print("자동 클릭 시작")
print(f"좌표: ({X}, {Y})")
print(f"주기: {INTERVAL}초")

while True:
    pyautogui.click(X, Y)
    print(f"클릭 완료 : {time.strftime('%Y-%m-%d %H:%M:%S')}")
    time.sleep(INTERVAL)