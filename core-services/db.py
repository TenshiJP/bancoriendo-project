import psycopg2
from psycopg2.extras import RealDictCursor
from .config import PG_HOST, PG_PORT, PG_DB, PG_USER, PG_PASS

DDL = """
CREATE TABLE IF NOT EXISTS core_txn (
  id SERIAL PRIMARY KEY,
  correlation_id TEXT UNIQUE NOT NULL,
  req_payload JSONB NOT NULL,
  status TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_core_txn_status ON core_txn(status);
"""

def get_conn():
  return psycopg2.connect(
    host=PG_HOST, port=PG_PORT, dbname=PG_DB, user=PG_USER, password=PG_PASS
  )

def init_db():
  with get_conn() as conn:
    with conn.cursor() as cur:
      cur.execute(DDL)
    conn.commit()

def insert_received(corr: str, req_payload: dict):
  sql = """
  INSERT INTO core_txn (correlation_id, req_payload, status)
  VALUES (%s, %s::jsonb, 'RECEIVED')
  ON CONFLICT (correlation_id) DO NOTHING;
  """
  with get_conn() as conn:
    with conn.cursor() as cur:
      cur.execute(sql, (corr, _json(req_payload)))
    conn.commit()

def update_status(corr: str, status: str):
  sql = """
  UPDATE core_txn
     SET status=%s,
         updated_at=NOW()
   WHERE correlation_id=%s;
  """
  with get_conn() as conn:
    with conn.cursor() as cur:
      cur.execute(sql, (status, corr))
    conn.commit()

def _json(d: dict) -> str:
  # Evitamos depender de json adapter extra; psycopg2 castea con ::jsonb
  import json
  return json.dumps(d, ensure_ascii=False)
