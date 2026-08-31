<?php
session_start();
include("conexion.php");

if (!isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] != 1) {
    header("Location: login.php");
    exit();
}

// 1. OPERACIÓN CRUD: BAJA ASEGURADA
if (isset($_GET['eliminar'])) {
    $id_usuario = intval($_GET['eliminar']);
    
    $conn->begin_transaction();
    try {
        $stmt1 = $conn->prepare("DELETE FROM cuenta WHERE usuario_fk = ?");
        $stmt1->bind_param("i", $id_usuario);
        $stmt1->execute();
        $stmt1->close();

        $stmt2 = $conn->prepare("DELETE FROM usuario WHERE usuario_pk = ?");
        $stmt2->bind_param("i", $id_usuario);
        $stmt2->execute();
        $stmt2->close();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
    header("Location: index.php");
    exit();
}

// 2. OPERACIÓN CRUD: ALTA
if (isset($_POST['registrar'])) {
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $apellidos = $conn->real_escape_string($_POST['apellidos']);
    $correo = $conn->real_escape_string($_POST['correo']);
    $telefono = $conn->real_escape_string($_POST['telefono']);
    $direccion = $conn->real_escape_string($_POST['direccion']);
    $contrasena = $_POST['contrasena'];
    $rol = intval($_POST['rol']);
    
    $num_cuenta = $conn->real_escape_string($_POST['num_cuenta']);
    $saldo = floatval($_POST['saldo']);
    $tipo_cuenta = $_POST['tipo_cuenta'];

    $sql_user = "INSERT INTO usuario (nombre, apellidos, correo, telefono, direccion, contrasena, fecha_registro, rol_fk) 
                 VALUES ('$nombre', '$apellidos', '$correo', '$telefono', '$direccion', '$contrasena', CURDATE(), $rol)";
    
    if ($conn->query($sql_user)) {
        $ultimo_id = $conn->insert_id; 
        
        $sql_cuenta = "INSERT INTO cuenta (num_cuenta, saldo, tipo_cuenta, fecha_creacion, usuario_fk) 
                       VALUES ('$num_cuenta', $saldo, '$tipo_cuenta', CURDATE(), $ultimo_id)";
        $conn->query($sql_cuenta);
    }
    header("Location: index.php");
    exit();
}

$sql_consulta = "SELECT u.usuario_pk, u.nombre, u.apellidos, u.correo, r.nombre_rol, c.num_cuenta, c.saldo, c.tipo_cuenta
                 FROM usuario u
                 LEFT JOIN rol r ON u.rol_fk = r.rol_pk
                 LEFT JOIN cuenta c ON c.usuario_fk = u.usuario_pk";
$resultado = $conn->query($sql_consulta);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>BANJOCO - Administrador</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

    <nav class="navbar" style="background: #072146; color: white; padding: 15px; display: flex; justify-content: space-between;">
        <div><span style="font-size:22px; font-weight:bold;">BANJOCO</span> <span style="font-size:12px; background:#48ae64; padding:3px 6px; border-radius:3px;">Módulo Administrativo</span></div>
        <div style="font-size:14px;">
            Admin: <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></strong> | <a href="logout.php" style="color: #ff6b6b; font-weight: bold; text-decoration: none;">Salir</a>
        </div>
    </nav>

    <div style="display: grid; grid-template-columns: 350px 1fr; max-width: 1400px; margin: 30px auto; gap: 25px; padding: 0 20px;">
        <div style="background: white; padding: 20px; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.1); height:fit-content;">
            <h3>Altas de Cuentahabientes</h3>
            <form action="index.php" method="POST" onsubmit="return validarFormulario()">
                <div><label style="font-size:12px; font-weight:bold;">Nombre(s)</label><input type="text" id="nombre" name="nombre" style="width:100%; padding:8px; margin:5px 0 12px;" required></div>
                <div><label style="font-size:12px; font-weight:bold;">Apellidos</label><input type="text" id="apellidos" name="apellidos" style="width:100%; padding:8px; margin:5px 0 12px;" required></div>
                <div><label style="font-size:12px; font-weight:bold;">Correo</label><input type="email" id="correo" name="correo" style="width:100%; padding:8px; margin:5px 0 12px;" required></div>
                <div><label style="font-size:12px; font-weight:bold;">Teléfono</label><input type="text" id="telefono" name="telefono" style="width:100%; padding:8px; margin:5px 0 12px;"></div>
                <div><label style="font-size:12px; font-weight:bold;">Dirección</label><input type="text" id="direccion" name="direccion" style="width:100%; padding:8px; margin:5px 0 12px;"></div>
                <div><label style="font-size:12px; font-weight:bold;">Contraseña (NIP)</label><input type="password" id="contrasena" name="contrasena" style="width:100%; padding:8px; margin:5px 0 12px;" required></div>
                <div>
                    <label style="font-size:12px; font-weight:bold;">Rol</label>
                    <select name="rol" style="width:100%; padding:8px; margin:5px 0 12px; background:#fff;">
                        <option value="2">CLIENTE</option>
                        <option value="1">ADMINISTRADOR</option>
                    </select>
                </div>

                <hr style="border:0; border-top:1px dashed #ccc; margin:15px 0;">
                <h4>Datos de la Cuenta</h4>
                <div><label style="font-size:12px; font-weight:bold;">Número de Cuenta Único</label><input type="text" id="num_cuenta" name="num_cuenta" style="width:100%; padding:8px; margin:5px 0 12px;" required></div>
                <div>
                    <label style="font-size:12px; font-weight:bold;">Tipo de Cuenta</label>
                    <select name="tipo_cuenta" style="width:100%; padding:8px; margin:5px 0 12px; background:#fff;">
                        <option value="DEBITO">DÉBITO</option>
                        <option value="AHORRO">AHORRO</option>
                    </select>
                </div>
                <div><label style="font-size:12px; font-weight:bold;">Saldo Inicial ($)</label><input type="number" id="saldo" name="saldo" step="0.01" value="0.00" style="width:100%; padding:8px; margin:5px 0 12px;" required></div>

                <button type="submit" name="registrar" style="background:#48ae64; color:white; border:none; padding:12px; width:100%; font-weight:bold; cursor:pointer; border-radius:4px; margin-top:10px;">Registrar Todo</button>
            </form>
        </div>

        <div style="background: white; padding: 20px; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.1); overflow-x:auto;">
            <h3>Clientes en el Sistema</h3>
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="background:#072146; color:white;">
                        <th style="padding:10px; text-align:left;">ID</th>
                        <th style="padding:10px; text-align:left;">Nombre / Correo</th>
                        <th style="padding:10px; text-align:left;">Rol</th>
                        <th style="padding:10px; text-align:left;">No. Cuenta</th>
                        <th style="padding:10px; text-align:left;">Tipo</th>
                        <th style="padding:10px; text-align:left;">Saldo</th>
                        <th style="padding:10px; text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $resultado->fetch_assoc()): ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px;"><?php echo $row['usuario_pk']; ?></td>
                        <td style="padding:10px;"><strong><?php echo htmlspecialchars($row['nombre'] . " " . $row['apellidos']); ?></strong><br><span style="font-size:11px; color:#666;"><?php echo htmlspecialchars($row['correo']); ?></span></td>
                        <td style="padding:10px;"><?php echo htmlspecialchars($row['nombre_rol']); ?></td>
                        <td style="padding:10px;"><code><?php echo htmlspecialchars($row['num_cuenta'] ?? 'Sin Cuenta'); ?></code></td>
                        <td style="padding:10px;"><?php echo htmlspecialchars($row['tipo_cuenta'] ?? '-'); ?></td>
                        <td style="padding:10px; font-weight:bold; color:#072146;">$<?php echo number_format($row['saldo'] ?? 0, 2); ?></td>
                        <td style="padding:10px; text-align:center;">
                            <a href="reporte.php?id=<?php echo $row['usuario_pk']; ?>" target="_blank" style="background:#1973b8; color:white; padding:5px 10px; text-decoration:none; border-radius:4px; font-size:12px; font-weight:bold; display:inline-block; margin-right:5px;">📄 Estado</a>
                            <a href="index.php?eliminar=<?php echo $row['usuario_pk']; ?>" onclick="return confirm('¿Seguro que deseas dar de baja este usuario?')" style="background:#ff4d4d; color:white; padding:5px 10px; text-decoration:none; border-radius:4px; font-size:12px; font-weight:bold; display:inline-block;">Eliminar</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="app.js"></script>
</body>
</html>