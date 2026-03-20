import os

# RabbitMQ
MQ_HOST = os.getenv("MQ_HOST", "192.168.245.134")
MQ_USER = os.getenv("MQ_USER", "fnx")
MQ_PASS = os.getenv("MQ_PASS", "pass")
MQ_VHOST = os.getenv("MQ_VHOST", "/")

REQ_QUEUE = os.getenv("REQ_QUEUE", "core.processing")
REP_QUEUE = os.getenv("REP_QUEUE", "core.reply")

# Postgres
PG_HOST = os.getenv("PG_HOST", "localhost")
PG_PORT = int(os.getenv("PG_PORT", "5432"))
PG_DB   = os.getenv("PG_DB", "coredb")
PG_USER = os.getenv("PG_USER", "coreuser")
PG_PASS = os.getenv("PG_PASS", "corepass")
