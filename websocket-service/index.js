// index.js
const cfg = require("./config");
const { createMq } = require("./mq");
const { startWs } = require("./ws");

function now() {
  return new Date().toISOString();
}

(async function main() {
  console.log(`[FINIX] ${now()} Iniciando FINIX...`);

  // Arrancar WS primero (necesita publishFn, la asignamos luego)
  let mq = null;

  // publishFn placeholder; cuando MQ esté listo, se reemplaza
  const publishFn = (corr, reqObj) => {
    if (!mq) {
      console.error(`[FINIX] ${now()} MQ no listo aún, no puedo publicar corr=${corr}`);
      return;
    }
    mq.publishRequest(corr, reqObj);
  };

  const ws = startWs(cfg, publishFn);

  // Conectar MQ y setear consumer que manda a WS
  mq = await createMq(cfg, (data, meta) => {
    ws.onReplyFromMq(data, meta);
  });

  console.log(`[FINIX] ${now()} Listo. WS + MQ activos.`);
})().catch((e) => {
  console.error(`[FINIX] ${now()} Fatal: ${e.message}`);
  process.exit(1);
});
