<?php session_start(); ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Login</title>
  <style>
    body { font-family: Arial, sans-serif; }
    .box { width: 360px; }
    label { display:block; margin-top:12px; }
    input { width: 100%; padding: 8px; }
    .msg { margin-top: 12px; padding: 10px; background:#f6f6f6; border:1px solid #ddd; }
    .err { color: #b00020; }
    .ok  { color: #0b6b0b; }
    .muted { color:#666; font-size: 12px; }
    button { margin-top: 14px; padding: 10px 14px; cursor:pointer; }
  </style>
</head>
<body>
  <div class="box">
    <h2>Login</h2>

    <?php
      // Mensaje para mostrar “estado token”
      $token_msg = "";

      if (isset($_GET["err"])) {
        $token_msg = "<span class='err'>Credenciales inválidas o token incorrecto.</span>";
      }

      // Cuando tu auth.php pida el OTP, puede redirigir así:
      // login.php?otp=sent&user=user01&op=op001&msg=Revisa%20tu%20token
      if (isset($_GET["otp"]) && $_GET["otp"] === "sent") {
        $m = $_GET["msg"] ?? "Se solicitó el token. Ingresa el OTP.";
        $token_msg = "<span class='ok'>" . htmlspecialchars($m) . "</span>";
      }

      $pref_user = $_GET["user"] ?? "";
      $pref_op   = $_GET["op"] ?? "";
    ?>

    <?php if ($token_msg !== ""): ?>
      <div class="msg">
        <b>Token/Estado:</b><br>
        <?= $token_msg ?>
      </div>
    <?php else: ?>
      <div class="msg">
        <b>Token/Estado:</b><br>
        <span class="muted">Aún no se ha solicitado OTP.</span>
      </div>
    <?php endif; ?>

    <form method="POST" action="auth.php">
      <label>Usuario:</label>
      <input name="user" required value="<?= htmlspecialchars($pref_user) ?>">

      <label>Password:</label>
      <input type="password" name="pass">

      <!-- operation_id: tu auth.php lo va a manejar.
           Si no existe, auth.php lo genera y lo guarda para el paso 2 -->
      <input type="hidden" name="operation_id" value="<?= htmlspecialchars($pref_op) ?>">

      <label>OTP (Token):</label>
      <input name="otp" inputmode="numeric" autocomplete="one-time-code"
             placeholder="Ingresa el OTP (ej: 980517)">

      <button type="submit">Ingresar</button>

      <div class="muted" style="margin-top:10px;">
        Flujo esperado: 1) ingresas user/pass → se solicita OTP → 2) ingresas OTP → acceso.
      </div>
    </form>
  </div>
</body>
</html>

