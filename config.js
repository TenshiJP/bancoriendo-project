// config.js
module.exports = {
  MQ_HOST: "192.168.245.134",
  MQ_USER: "fnx",
  MQ_PASS: "pass",
  REQ_QUEUE: "core.request",
  REP_QUEUE: "core.reply",

  WS_HOST: "0.0.0.0",
  WS_PORT: 8081,

  TIMEOUT_MS: 15000,
  AMQP_HEARTBEAT: 10,
};

