<?php
session_start();

$user = $_POST["user"] ?? "";
$pass = $_POST["pass"] ?? "";

$_SESSION["login_user"] = $user;
$_SESSION["login_pass"] = $pass;

$operation_id = "op_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $user) . "_" . time();
$_SESSION["otp_operation_id"] = $operation_id;

$url = "http://10.67.157.213:8088/otp/request";
$payload = json_encode([
  "user_id" => $user,
  "operation_id" => $operation_id,
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
  $_SESSION["msg_err"] = "Error solicitando OTP: $err";
  unset($_SESSION["otp_operation_id"]);
  header("Location: login.php");
  exit;
}

if ($http < 200 || $http >= 300) {
  $_SESSION["msg_err"] = "Error solicitando OTP. HTTP=$http. Resp=$resp";
  unset($_SESSION["otp_operation_id"]);
  header("Location: login.php");
  exit;
}

$data = json_decode($resp, true);
$otp  = $data["otp"] ?? null;

if (!$otp) {
  $_SESSION["msg_err"] = "Tokens respondió sin OTP. Resp=$resp";
  header("Location: login.php");
  exit;
}

// ✅ Guardar SOLO el código OTP para mostrarlo
$_SESSION["otp_code"] = $otp;
$_SESSION["msg_ok"] = "OTP solicitado. Ingresa el código para continuar.";

header("Location: login.php");
exit;

