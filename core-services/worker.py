from .db import init_db
from .mq import start_consumer
from .main import on_mq_message

def main():
  init_db()
  start_consumer(on_mq_message)

if __name__ == "__main__":
  main()
