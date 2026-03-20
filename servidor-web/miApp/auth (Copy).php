<?php
session_start();
require "config.php";

$user = $_POST["user"] ?? "";
$pass = $_POST["pass"] ?? "";

$stmt = $conn->prepare(
    "SELECT id, username, password 
     FROM usuarios 
     WHERE username = ?"
);
$stmt->execute([$user]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && $pass === $row["password"]) {
    $_SESSION["uid"]  = $row["id"];
    $_SESSION["user"] = $row["username"];
    header("Location: dashboard.php");
    exit;
}

header("Location: login.php?err=1");
exit;

