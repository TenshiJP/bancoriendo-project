<?php
session_start();
require "config.php";

$user = $_SESSION["login_user"] ?? "";
$pass = $_SESSION["login_pass"] ?? "";
$otp  = $_POST["otp"] ?? "";

$operation_id = $_SESSION["otp_operation_id"] ?? "";

if ($user === "" || $pass === "" || $otp === "" || $operation_id === "") {
  $_SESSION["msg_err"] = "Falta usuario/password/OTP u operación.";
  header("Location: login.php");
  exit;
}

// 1) Validar credenciales locales
$stmt = $conn->prepare("SELECT id, username, password FROM usuarios WHERE username = ?");
$stmt->execute([$user]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!($row && $pass === $row["password"])) {
  $_SESSION["msg_err"] = "Credenciales inválidas.";
  header("Location: login.php");
  exit;
}

// 2) Validar OTP en servidor Tokens (IMPORTANTE: MISMO operation_id)
$url = "http://10.67.157.213:8088/otp/validate";
$otp  = $_POST["otp"] ?? "";
$otp  = preg_replace('/\D+/', '', $otp); // deja solo números

if (strlen($otp) !== 6) {
    $_SESSION["msg_err"] = "El OTP debe tener exactamente 6 dígitos.";
    header("Location: login.php");
    exit;
}

$payload = json_encode([
  "user_id" => $user,
  "operation_id" => $operation_id,
  "otp" => $otp,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
  CURLOPT_POSTFIELDS => $payload,
  CURLOPT_TIMEOUT => 10,
]);

$resp = curl_exec($ch);
$err  = curl_error($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false) {
  $_SESSION["msg_err"] = "Error validando OTP: $err";
  header("Location: login.php");
  exit;
}

if ($http < 200 || $http >= 300) {
  $_SESSION["msg_err"] = "OTP inválido o error. HTTP=$http. Resp=$resp";
  header("Location: login.php");
  exit;
}

// ✅ OK: crear sesión
$_SESSION["uid"]  = $row["id"];
$_SESSION["user"] = $row["username"];

// Limpieza de variables del login/otp
unset($_SESSION["login_user"], $_SESSION["login_pass"], $_SESSION["otp_operation_id"],$_SESSION["otp_code"]);

header("Location: dashboard.php");
exit;

