// mq.js
const amqp = require("amqplib");

function now() {
  return new Date().toISOString();
}

/**
 * Crea conexión+canal, asegura colas, consume REP_QUEUE y expone publish().
 * @param {object} cfg
 * @param {(data: any, meta: { corr?: string, raw: string, props: any }) => void} onReply
 */
async function createMq(cfg, onReply) {
  const MQ_URL = `amqp://${cfg.MQ_USER}:${cfg.MQ_PASS}@${cfg.MQ_HOST}:5672`;

  console.log(`[FINIX][MQ] ${now()} Conectando a ${cfg.MQ_HOST}...`);
  const conn = await amqp.connect(MQ_URL, { heartbeat: cfg.AMQP_HEARTBEAT });

  conn.on("error", (e) => console.error(`[FINIX][MQ] ${now()} conn error: ${e.message}`));
  conn.on("close", () => console.error(`[FINIX][MQ] ${now()} conn CLOSED`));

  const ch = await conn.createChannel();
  ch.on("error", (e) => console.error(`[FINIX][MQ] ${now()} channel error: ${e.message}`));
  ch.on("close", () => console.error(`[FINIX][MQ] ${now()} channel CLOSED`));
  await ch.prefetch(1);
  const EX_NAME = "finix.exchange"; 
  await ch.assertExchange(EX_NAME, 'direct', { durable: true });

  await ch.assertQueue(cfg.REQ_QUEUE, { durable: true });
  await ch.assertQueue(cfg.REP_QUEUE, { durable: true });

  await ch.bindQueue(cfg.REP_QUEUE, EX_NAME, cfg.REP_QUEUE); //
//  console.log(`[FINIX][MQ] Exchange y Bindings listos en ${EX_NAME}`);

  const ok = await ch.checkQueue(cfg.REP_QUEUE);
  console.log(
    `[FINIX][MQ] ${now()} checkQueue ${cfg.REP_QUEUE}: messages=${ok.messageCount} consumers=${ok.consumerCount}`
  );

  //console.log(`[FINIX][MQ] ${now()} Conectado. Colas: ${cfg.REQ_QUEUE}, ${cfg.REP_QUEUE}`);

  const consumeResult = await ch.consume(
    cfg.REP_QUEUE,
    (msg) => {
//      console.log(`[FINIX][MQ] ¡ALGO LLEGÓ A LA COLA!`);
      if (!msg) return;
      const rawBuf = msg.content;
      const rawStr = rawBuf.toString("utf8");
      let propCorr = msg.properties?.correlationId;

      console.log(
        `[FINIX][MQ] ${now()} << RX ${cfg.REP_QUEUE} bytes=${rawBuf.length} corr(prop)=${propCorr || 'N/A'}`
      );

      try {
        let data;
        try {
          data = JSON.parse(rawStr);
          if (!propCorr && data.correlationId) {
            propCorr = data.correlationId;
          }
        } catch (e) {
          // Si no es JSON, lo tratamos como raw
          data = { _raw: rawStr };
        }

        // Enviar al  callback onReply
        onReply(data, { corr: propCorr, raw: rawStr, props: msg.properties });

      } catch (e) {
        console.error(`[FINIX][MQ] ${now()} Error en onReply: ${e.message}`);
      } finally {
        // Confirmar recepción del mensaje
        ch.ack(msg);
      }
    },
    { noAck: false }
  );

  console.log(`[FINIX][MQ] ${now()} consumerTag=${consumeResult.consumerTag} activo.`);

  /**
   * Envía la solicitud original a la cola core.request
   */
  function publishRequest(corr, payloadObj) {
    const payloadStr = JSON.stringify(payloadObj);
    console.log(`[FINIX][MQ] ${now()} >> PUB ${cfg.REQ_QUEUE} corr=${corr}`);

    ch.sendToQueue(cfg.REQ_QUEUE, Buffer.from(payloadStr), {
      persistent: true,
      correlationId: corr,
      contentType: "application/json",
      deliveryMode: 2,
      replyTo: cfg.REP_QUEUE
    });
  }

  async function close() {
    try { await ch.close(); } catch {}
    try { await conn.close(); } catch {}
  }

  return { publishRequest, close };
}

module.exports = { createMq };
