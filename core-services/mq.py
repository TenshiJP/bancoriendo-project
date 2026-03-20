import json
import pika
from .config import MQ_HOST, MQ_USER, MQ_PASS, MQ_VHOST, REQ_QUEUE, REP_QUEUE

# Exchange para las respuestas (Servicio -> FINIX)
EX_NAME = "finix.exchange"

def _conn_params():
    creds = pika.PlainCredentials(MQ_USER, MQ_PASS)
    return pika.ConnectionParameters(
        host=MQ_HOST,
        virtual_host=MQ_VHOST,
        credentials=creds,
        heartbeat=30,
        blocked_connection_timeout=30
    )

def publish_reply(ch, corr, resp_dict):
    ch.basic_publish(
        exchange=EX_NAME,
        routing_key=REP_QUEUE,
        properties=pika.BasicProperties(
            correlation_id=corr,
            delivery_mode=2  # Persistente
        ),
        body=json.dumps(resp_dict, ensure_ascii=False)
    )
    print(f"[CORE][MQ] >> TX: {corr}")

def start_consumer(on_message_callback):
    connection = pika.BlockingConnection(_conn_params())
    channel = connection.channel()
    channel.exchange_declare(exchange=EX_NAME, exchange_type='direct', durable=True)
    channel.queue_declare(queue=REQ_QUEUE, durable=True) # core.processing
    channel.queue_declare(queue=REP_QUEUE, durable=True) # core.reply

    # Binding para MQ
    channel.queue_bind(exchange=EX_NAME, queue=REQ_QUEUE, routing_key=REQ_QUEUE)
    channel.queue_bind(exchange=EX_NAME, queue=REP_QUEUE, routing_key=REP_QUEUE)

    channel.basic_qos(prefetch_count=1)

    def _wrapped_callback(ch, method, properties, body: bytes):
        print(f"\n[CORE][MQ] << RX: {body.decode()}")
        on_message_callback(ch, method, properties, body)

    channel.basic_consume(queue=REQ_QUEUE, on_message_callback=_wrapped_callback)

    print(f"[CORE][MQ] Esperando en {REQ_QUEUE} vía {EX_NAME}...")
    channel.start_consuming()

