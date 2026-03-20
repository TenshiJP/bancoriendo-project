<?php
session_start();

if (!isset($_SESSION["uid"])) {
    header("Location: login.php");
    exit;
}

require "config.php";

// 1) Obtener ventas
$stmt = $conn->query("
    SELECT cliente, producto, cantidad, precio, total, fecha
    FROM ventas
    ORDER BY fecha DESC
");
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2) Si presionan el botón, guardar TXT en NFS
$mensaje_export = "";


// Para el websocket
$ws_mensaje   = "";
$ws_enviado   = "";
$ws_respuesta = "";

$amount   = isset($_POST['amount']) ? (float) $_POST['amount'] : 0.0;
$currency = isset($_POST['currency']) ? strtoupper(trim($_POST['currency'])) : 'GTQ';

// Validaciones básicas
if ($amount <= 0) {
    $ws_mensaje = "❌ El monto debe ser mayor a 0.";
} elseif (!in_array($currency, ['GTQ','USD','EUR'], true)) {
    $ws_mensaje = "❌ Moneda inválida.";
} else {
    $payload = [
        "type"     => "txn",
        "amount"   => $amount,
        "currency" => $currency
    ];

    $ws_enviado = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $cmd = "/usr/local/bin/ws_send.sh " . escapeshellarg($ws_enviado) . " 2>&1";
    $out = shell_exec($cmd);

    if ($out === null || trim($out) === "") {
        $ws_mensaje   = "❌ No hubo salida del WebSocket.";
        $ws_respuesta = "CMD:\n$cmd\n\nOUT:\nNULL";
    } else {
        $ws_mensaje   = "✅ WebSocket ejecutado correctamente.";
        $ws_respuesta = trim($out);
    }
}




//Para FileServer
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["export_txt"])) {

    $dir = "/mnt/evidencias/uploads";
    if (!is_dir($dir)) {
        $mensaje_export = "❌ La carpeta de evidencias no existe: $dir";
    } else {

        // Nombre del archivo con fecha/hora
        $filename = "ventas_" . date("Ymd_His") . ".txt";
        $ruta = $dir . "/" . $filename;

        // Construir contenido
        $lineas = [];
        $lineas[] = "REPORTE DE VENTAS";
        $lineas[] = "Generado por: " . ($_SESSION["user"] ?? "desconocido");
        $lineas[] = "Fecha: " . date("Y-m-d H:i:s");
        $lineas[] = str_repeat("=", 60);
        $lineas[] = "Cliente | Producto | Cantidad | Precio | Total | Fecha";
        $lineas[] = str_repeat("-", 60);

        if (count($ventas) === 0) {
            $lineas[] = "No hay ventas registradas.";
        } else {
            foreach ($ventas as $v) {
                $cliente  = $v["cliente"];
                $producto = $v["producto"];
                $cantidad = (int)$v["cantidad"];
                $precio   = number_format((float)$v["precio"], 2, ".", "");
                $total    = number_format((float)$v["total"], 2, ".", "");
                $fecha    = $v["fecha"];

                $lineas[] = "{$cliente} | {$producto} | {$cantidad} | Q {$precio} | Q {$total} | {$fecha}";
            }
        }

        $contenido = implode(PHP_EOL, $lineas) . PHP_EOL;

        // Guardar archivo
        $ok = @file_put_contents($ruta, $contenido);

        if ($ok === false) {
            $mensaje_export = "❌ No se pudo guardar el TXT. Revisa permisos en: $dir";
        } else {
            $mensaje_export = "✅ TXT guardado en FileServer: $ruta";
        }
    }
}

// (Opcional) Mostrar contenido de un txt específico
$archivo = "/mnt/evidencias/uploads/prueba_ok.txt";
$contenido_txt = "";
if (file_exists($archivo)) {
    $contenido_txt = file_get_contents($archivo);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Dashboard</title>
  <style>
    body { font-family: Arial, sans-serif; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background-color: #f4f4f4; }
    tr:nth-child(even) { background-color: #fafafa; }
    .msg { margin: 15px 0; padding: 10px; background: #f6f6f6; border: 1px solid #ddd; }
    .btn { padding: 10px 14px; cursor: pointer; }
    pre { background: #111; color: #eee; padding: 10px; overflow:auto; }
  </style>
</head>
<body>

  <h2>Bienvenido, <?= htmlspecialchars($_SESSION["user"]) ?></h2>
  <p>Ya estás autenticado en la aplicación ✅</p>

  <!-- Mensaje de exportación -->
  <?php if ($mensaje_export !== ""): ?>
    <div class="msg"><?= htmlspecialchars($mensaje_export) ?></div>
  <?php endif; ?>

  <!-- Botón exportar -->
  <form method="POST" style="margin: 10px 0;">
    <button class="btn" type="submit" name="export_txt" value="1">📄 Guardar listado de ventas en TXT (FileServer)</button>
  </form>

  <!-- (Opcional) mostrar un txt -->
  <h3>Contenido del archivo: prueba_ok.txt</h3>
  <?php if ($contenido_txt !== ""): ?>
    <pre><?= htmlspecialchars($contenido_txt) ?></pre>
  <?php else: ?>
    <p>(Vacío o no existe)</p>
  <?php endif; ?>
  
  
<form method="POST" style="margin: 10px 0;">
  <label>Monto:</label><br>
  <input type="number" name="amount" step="0.01" min="0" required
         value="<?= htmlspecialchars($_POST['amount'] ?? '100.00') ?>">
  <br><br>

  <label>Moneda:</label><br>
  <select name="currency" required>
    <?php
      $cur = $_POST['currency'] ?? 'GTQ';
      foreach (['GTQ'] as $opt) {
        $sel = ($cur === $opt) ? 'selected' : '';
        echo "<option value=\"$opt\" $sel>$opt</option>";
      }
    ?>
  </select>

  <br><br>
  <button class="btn" type="submit" name="send_ws" value="1">🔌 Enviar JSON a CORE (WebSocket)</button>
</form>


<?php if ($ws_mensaje !== ""): ?>
  <div class="msg"><?= htmlspecialchars($ws_mensaje) ?></div>

  <h3>JSON enviado</h3>
  <pre><?= htmlspecialchars($ws_enviado) ?></pre>

  <h3>Respuesta recibida (raw)</h3>
  <pre><?= htmlspecialchars($ws_respuesta) ?></pre>
<?php endif; ?>


  <h3>Listado de Ventas</h3>

  <?php if (count($ventas) > 0): ?>
    <table>
      <thead>
        <tr>
          <th>Cliente</th>
          <th>Producto</th>
          <th>Cantidad</th>
          <th>Precio</th>
          <th>Total</th>
          <th>Fecha</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ventas as $v): ?>
          <tr>
            <td><?= htmlspecialchars($v["cliente"]) ?></td>
            <td><?= htmlspecialchars($v["producto"]) ?></td>
            <td><?= (int)$v["cantidad"] ?></td>
            <td>Q <?= number_format((float)$v["precio"], 2) ?></td>
            <td>Q <?= number_format((float)$v["total"], 2) ?></td>
            <td><?= htmlspecialchars($v["fecha"]) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>No hay ventas registradas.</p>
  <?php endif; ?>

  <br>
  <a href="logout.php">Cerrar sesión</a>

</body>
</html>

