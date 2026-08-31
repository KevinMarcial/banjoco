<?php
session_start();
include("conexion.php");

if(!isset($_SESSION['usuario_id'])){
    die("Acceso denegado. Por favor inicia sesión.");
}

// Lógica de Identificación Cruzada Inteligente
if ($_SESSION['usuario_rol'] == 1 && isset($_GET['id'])) {
    $id = intval($_GET['id']); // Admin consultando un cliente
} else {
    $id = intval($_SESSION['usuario_id']); // Cliente consultando su propio estado
}

// Búsqueda preparada contra ataques de escalado de privilegios
$stmt = $conn->prepare("SELECT u.*, c.* FROM usuario u LEFT JOIN cuenta c ON c.usuario_fk = u.usuario_pk WHERE u.usuario_pk = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$data){ 
    die("Registro financiero no localizado en el core bancario."); 
}

$id_cuenta_aux = $data['cuenta_pk'] ?? 0;
$movimientos = $conn->query("SELECT * FROM transaccion WHERE cuenta_fk = $id_cuenta_aux ORDER BY transaccion_pk DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>BANJOCO - Estado de Cuenta Oficial</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #333; padding: 30px; background:#f4f6f9;}
        .factura-box { max-width: 850px; margin: auto; border: 1px solid #dcdcdc; padding: 40px; background: #fff; border-radius: 6px; box-shadow: 0 4px 16px rgba(0,0,0,0.05); }
        .encabezado { display: flex; justify-content: space-between; border-bottom: 3px solid #072146; padding-bottom: 15px; margin-bottom: 25px;}
        .logo-banco { font-size: 32px; font-weight: bold; color: #072146; letter-spacing: 1px; }
        .tabla-conceptos { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
        .tabla-conceptos th { background-color: #072146; color: white; padding: 12px; text-align: left;}
        .tabla-conceptos td { padding: 12px; border-bottom: 1px solid #eef2f5; }
        .btn-imprimir { background: #48ae64; color: white; border: none; padding: 14px 28px; font-weight: bold; cursor: pointer; margin-bottom: 25px; border-radius: 4px; font-size: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        
        @media print {
            .btn-imprimir { display: none; }
            body { padding: 0; background: #fff; }
            .factura-box { border: none; box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>

    <center><button class="btn-imprimir" onclick="window.print()">🖨️ Ejecutar Impresión / Descargar PDF Oficial</button></center>

    <div class="factura-box">
        <div class="encabezado">
            <div class="logo-banco">BANJOCO</div>
            <div style="text-align: right; font-size: 13px; color: #555;">
                <strong>ESTADO DE CUENTA INTEGRAL</strong><br>
                Generado: <?php echo date("d/m/Y H:i:s"); ?><br>
                Auditoría Ref: BJC-00<?php echo $data['usuario_pk']; ?>
            </div>
        </div>

        <div style="margin-bottom: 25px; font-size: 14px; background: #f8fafc; padding: 15px; border-radius: 4px; border-left: 4px solid #072146;">
            <h4 style="color:#072146; margin:0 0 8px 0; font-size: 15px;">INFORMACIÓN DEL TITULAR</h4>
            <strong>Cliente:</strong> <?php echo htmlspecialchars($data['nombre'] . " " . $data['apellidos']); ?><br>
            <strong>Correo de Contacto:</strong> <?php echo htmlspecialchars($data['correo']); ?>
        </div>

        <table class="tabla-conceptos">
            <thead>
                <tr>
                    <th>Número de Cuenta Core</th>
                    <th>Subtipo Producto</th>
                    <th style="text-align: right;">Saldo Neto Disponible</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code><?php echo htmlspecialchars($data['num_cuenta'] ?? 'SIN CUENTA ASIGNADA'); ?></code></td>
                    <td><?php echo htmlspecialchars($data['tipo_cuenta'] ?? 'N/A'); ?></td>
                    <td style="text-align: right; font-weight: bold; font-size:16px; color: #072146;">$<?php echo number_format($data['saldo'] ?? 0, 2); ?> MXN</td>
                </tr>
            </tbody>
        </table>

        <h4 style="color:#072146; margin-top: 35px; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px;">HISTORIAL DE MOVIMIENTOS RECIENTES</h4>
        <table class="tabla-conceptos">
            <thead>
                <tr>
                    <th>Fecha / Registro</th>
                    <th>Operación Bancaria</th>
                    <th>Descripción Conceptual</th>
                    <th style="text-align: right;">Monto Efectivo</th>
                </tr>
            </thead>
            <tbody>
                <?php if($movimientos && $movimientos->num_rows > 0): ?>
                    <?php while($m = $movimientos->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $m['fecha'] . ' ' . $m['hora']; ?></td>
                        <td><span style="font-weight: bold; font-size: 11px; padding: 3px 6px; border-radius: 3px; background: #f0f4f8; color: #072146;"><?php echo $m['tipo_transaccion']; ?></span></td>
                        <td><?php echo htmlspecialchars($m['descripcion']); ?></td>
                        <td style="text-align: right; font-weight: bold; color: <?php echo (strpos($m['tipo_transaccion'], 'DEPOSITO')!==false || strpos($m['tipo_transaccion'], 'RECIBIDA')!==false || strpos($m['tipo_transaccion'], 'PRESTAMO')!==false) ? '#48ae64' : '#e53935'; ?>">
                            <?php echo (strpos($m['tipo_transaccion'], 'DEPOSITO')!==false || strpos($m['tipo_transaccion'], 'RECIBIDA')!==false || strpos($m['tipo_transaccion'], 'PRESTAMO')!==false) ? '+' : '-'; ?>$<?php echo number_format($m['monto'], 2); ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; color:#888; padding: 20px;">No se registran operaciones en el período seleccionado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>


<?php
$url_reporte = "http://192.168.0.103/banjoco/reporte.php?id=" . $data['usuario_pk'];
?>
<img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=<?php echo urlencode($url_reporte); ?>" 
     alt="QR Bancario Validador" 
     style="border: 4px solid white; border-radius: 4px;">

<script>
        // Dispara la impresión nativa del sistema en cuanto el documento cargue por completo
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
