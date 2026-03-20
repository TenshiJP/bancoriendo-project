<?php
$DB_HOST = "10.67.157.16";
$DB_PORT = "3306";
$DB_NAME = "infraestructura";
$DB_USER = "appuser";           // o usuario real de MySQL
$DB_PASS = "P@ss12345*";

try {
    $dsn = "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4";
    $conn = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Error conectando a BD: " . $e->getMessage());
}

