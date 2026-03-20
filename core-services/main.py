import json
import random
import time
from fastapi import FastAPI
from .db import init_db, insert_received, update_status
from .mq import start_consumer, publish_reply
from .config import REQ_QUEUE, REP_QUEUE

app = FastAPI(title="CORE Service")

@app.on_event("startup")
def startup():
  init_db()

def process_business(req: dict) -> str:
    try:
        amount = float(req.get("amount", 0))
        currency = req.get("currency", "GTQ")

        # validaciones
        if amount <= 0:
            return "DECLINED_INVALID_AMOUNT"
        
        if amount > 100000:
            return "DECLINED_EXCEEDS_LIMIT"
        
        if currency != "GTQ":
            return "DECLINED_UNSUPPORTED_CURRENCY"

        if amount <= 500:
            return "APPROVED"
            
        return "APPROVED" 
        
    except (ValueError, TypeError):
        return "ERROR_DATA_FORMAT"

def on_mq_message(ch, method, properties, body: bytes):
  raw = body.decode("utf-8", errors="replace")
  corr = getattr(properties, "correlation_id", None)

  try:
    req = json.loads(raw)
  except Exception:
    # Si llega basura, ACK y respondemos error (si hay corr)
    if corr:
      publish_reply(ch, corr, {
        "type": "txn_response",
        "correlationId": corr,
        "status": "ERROR",
        "message": "JSON inválido en core.request"
      })
    ch.basic_ack(delivery_tag=method.delivery_tag)
    return

  corr = corr or req.get("correlationId") or req.get("correlation_id")
  if not corr:
    # Sin corr no hay trazabilidad: ACK y fuera
    ch.basic_ack(delivery_tag=method.delivery_tag)
    return

  # BD: RECEIVED
  insert_received(corr, req)

  # delay 
  time.sleep(random.uniform(0.05, 0.25))
  result = process_business(req)

  # BD: update
  update_status(corr, result)

  # Publish core.reply
  resp = {
    "type": "txn_response",
    "correlationId": corr,
    "status": result,
    "message": f"Procesado por CORE",
    "finixTs": req.get("finixTs"), 
    "brokerTs": req.get("brokerTs"), 
    "coreTs": time.strftime("%Y-%m-%dT%H:%M:%S%z") 
  }
  publish_reply(ch, corr, resp)

  # 5) ACK request
  ch.basic_ack(delivery_tag=method.delivery_tag)

@app.get("/health")
def health():
  return {"ok": True, "service": "core", "queues": {"in": REQ_QUEUE, "out": REP_QUEUE}}

@app.post("/run-consumer")
def run_consumer_note():
  return {"ok": True, "message": "Ejecuta: python3 -m app.worker"}

# Entrada ejecutable para consumer (ver app/worker.py)
