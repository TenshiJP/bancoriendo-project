<?php
session_start();

$user = $_SESSION["login_user"] ?? "";
$pass = $_SESSION["login_pass"] ?? "";

$otp_requested = isset($_SESSION["otp_operation_id"]);
$operation_id  = $_SESSION["otp_operation_id"] ?? "";

$otp_code = $_SESSION["otp_code"] ?? ""; // <-- el OTP que devolvió Tokens (solo para mostrar)

$msg_ok  = $_SESSION["msg_ok"]  ?? "";
$msg_err = $_SESSION["msg_err"] ?? "";

unset($_SESSION["msg_ok"], $_SESSION["msg_err"]);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Login</title>
</head>
<body>
  <h2>Login</h2>

  <?php if ($msg_err): ?>
    <p style="color:red"><?= htmlspecialchars($msg_err) ?></p>
  <?php endif; ?>

  <?php if ($msg_ok): ?>
    <p style="color:green"><?= htmlspecialchars($msg_ok) ?></p>
  <?php endif; ?>

  <!-- Paso 1: Solicitar OTP -->
  <form method="POST" action="otp_request.php" style="margin-bottom:15px;">
    <label>Usuario:</label><br>
    <input name="user" required value="<?= htmlspecialchars($user) ?>"><br><br>

    <label>Password:</label><br>
    <input type="password" name="pass" required value="<?= htmlspecialchars($pass) ?>"><br><br>

    <button type="submit">Solicitar OTP</button>
  </form>

  <?php if ($otp_requested): ?>
    <!-- Paso 2: Validar OTP y entrar -->
    <?php if ($otp_code !== ""): ?>
      <p><b>Token (OTP):</b> <?= htmlspecialchars($otp_code) ?></p>
    <?php endif; ?>

    <form method="POST" action="auth.php">
      <label>Token (OTP):</label><br>
      <input name="otp" required placeholder="Ingresa el código" maxlength="6" inputmode="numeric"><br><br>

      <button type="submit">Ingresar</button>
    </form>
  <?php endif; ?>

</body>
</html>

