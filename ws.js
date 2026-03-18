// ws.js
const WebSocket = require("ws");

function now() {
  return new Date().toISOString();
}

function newCorrelationId() {
  return `txn_${Date.now()}_${Math.floor(Math.random() * 1000000)}`;
}

/**
 * Maneja WS + pending map + timers
 * @param {object} cfg
 * @param {(corr: string, reqObj: any) => void} publishFn  // publica a core.request
 */
function startWs(cfg, publishFn) {
  const pending = new Map(); // corr -> ws
  const timers = new Map();  // corr -> timeoutId

  function clearTimer(corr) {
    const t = timers.get(corr);
    if (t) clearTimeout(t);
    timers.delete(corr);
  }

  function setTimer(corr, ws) {
    clearTimer(corr);

    const t = setTimeout(() => {
      if (pending.has(corr)) {
        pending.delete(corr);

        if (ws.readyState === WebSocket.OPEN) {
          ws.send(JSON.stringify({
            type: "txn_response",
            correlationId: corr,
            status: "TIMEOUT",
            message: "No hubo respuesta del CORE a tiempo"
          }));
        }

        console.warn(`[FINIX][WS] ${now()} TIMEOUT corr=${corr} pending=${pending.size}`);
      }
      timers.delete(corr);
    }, cfg.TIMEOUT_MS);

    timers.set(corr, t);
  }

  function onReplyFromMq(data, meta) {
    // Determinar correlationId:
    // - primero JSON.correlationId
    // - si no, properties.correlationId
    const corr = (data && data.correlationId) || meta?.corr;

    if (!corr) {
      console.warn(`[FINIX][WS] ${now()} RX sin correlationId. data=${JSON.stringify(data).slice(0,200)}`);
      return;
    }

    const ws = pending.get(corr);
    if (!ws || ws.readyState !== WebSocket.OPEN) {
      console.warn(`[FINIX][WS] ${now()} RX corr=${corr} pero no hay WS pendiente (o cerrado). pending=${pending.size}`);
      // Igual limpiamos timer si existiera
      clearTimer(corr);
      pending.delete(corr);
      return;
    }

    // Cancelar timeout y responder
    clearTimer(corr);
    pending.delete(corr);

    // Si viene {_raw:"..."} intentamos devolver string
    const out = (data && data._raw) ? data._raw : JSON.stringify(data);
    ws.send(out);

    console.log(`[FINIX][WS] ${now()} >> WS OK corr=${corr} pending=${pending.size}`);
  }

  const wss = new WebSocket.Server({ host: cfg.WS_HOST, port: cfg.WS_PORT });
  console.log(`[FINIX][WS] ${now()} WS escuchando en ${cfg.WS_HOST}:${cfg.WS_PORT}`);

  wss.on("connection", (ws) => {
    console.log(`[FINIX][WS] ${now()} Cliente WS conectado`);

    ws.on("message", (raw) => {
      console.log(`[FINIX][DEBUG] ${now()} Raw recibido:`, raw.toString());
      let req;
      try {
        req = JSON.parse(raw.toString());
      } catch {
        console.error(`[FINIX][ERROR] ${now()} Error parseando JSON:`, err.message);
        ws.send(JSON.stringify({ type: "error", message: "JSON inválido" }));
        return;
      }

      const corr = req.correlationId || newCorrelationId();
      req.correlationId = corr;
      req.finixTs = now();

      pending.set(corr, ws);
      setTimer(corr, ws);

      // Publicar a MQ
      publishFn(corr, req);

      // ACK inmediato para Postman/PHP
      ws.send(JSON.stringify({
        type: "ack",
        correlationId: corr,
        status: "ENVIADO_A_CORE"
      }));
    });

    ws.on("close", () => {
      // limpiar pendientes de este ws
      for (const [corr, sock] of pending.entries()) {
        if (sock === ws) {
          pending.delete(corr);
          clearTimer(corr);
        }
      }
      console.log(`[FINIX][WS] ${now()} WS cerrado. pending=${pending.size}`);
    });
  });

  return { onReplyFromMq };
}

module.exports = { startWs };
