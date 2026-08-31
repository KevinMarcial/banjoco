<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include("conexion.php"); // Puente a la base de datos de MAMP

// SEGURIDAD: Si no es un Cliente (Rol 2), lo expulsa al login
if (!isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] != 2) {
    header("Location: login.html");
    exit();
}

$id_cliente = intval($_SESSION['usuario_id']);

// ====================================================================
// BLOQUE 1: PROCESAR OPERACIONES ESTÁNDAR (Depósitos, Retiros, etc.)
// ====================================================================
if (isset($_POST['ejecutar_operacion'])) {
    $tipo = $conn->real_escape_string($_POST['tipo_transaccion']); 
    $monto = floatval(str_replace(',', '.', $_POST['monto']));
    $descripcion = $conn->real_escape_string($_POST['descripcion']);

    $tipos_permitidos = ["DEPOSITO", "RETIRO", "PRESTAMO", "APARTADO", "RECARGA"];
    if (!in_array($tipo, $tipos_permitidos)) {
        echo "<script>alert('❌ Error: Tipo de transacción no válido.'); window.location.href='cliente_panel.php';</script>";
        exit();
    }

    if ($monto > 0) {
        $conn->begin_transaction(); 
        try {
            $res_c_aux = $conn->query("SELECT cuenta_pk, saldo FROM cuenta WHERE usuario_fk = $id_cliente FOR UPDATE");
            if (!$res_c_aux) {
                throw new Exception("Error en la consulta de cuenta: " . $conn->error);
            }
            $c_aux = $res_c_aux->fetch_assoc();
            $id_cuenta = $c_aux['cuenta_pk'] ?? 0;
            $saldo_actual = $c_aux['saldo'] ?? 0;

            if ($id_cuenta <= 0) {
                throw new Exception("No se encontró una cuenta asociada a tu usuario.");
            }

            if ($tipo == "DEPOSITO" || $tipo == "PRESTAMO") {
                $upd = $conn->query("UPDATE cuenta SET saldo = saldo + $monto WHERE cuenta_pk = $id_cuenta");
            } else {
                if ($saldo_actual >= $monto) {
                    $upd = $conn->query("UPDATE cuenta SET saldo = saldo - $monto WHERE cuenta_pk = $id_cuenta");
                } else {
                    throw new Exception("Saldo insuficiente para esta operación.");
                }
            }
            if (!$upd) {
                throw new Exception("Error al actualizar el saldo: " . $conn->error);
            }

            $ins_t = $conn->query("INSERT INTO transaccion (tipo_transaccion, monto, fecha, hora, descripcion, cuenta_fk) 
                          VALUES ('$tipo', $monto, CURDATE(), CURTIME(), '$descripcion', $id_cuenta)");
            if (!$ins_t) {
                throw new Exception("Error al registrar la transacción: " . $conn->error);
            }

            if ($tipo == "PRESTAMO") {
                $conn->query("INSERT INTO prestamo (monto, interes, plazo, fecha_inicio, estado, usuario_fk) VALUES ($monto, 10.0, 12, CURDATE(), 'ACTIVO', $id_cliente)");
            } else if ($tipo == "APARTADO") {
                $conn->query("INSERT INTO apartado (nombre_apartado, monto_guardado, fecha, usuario_fk) VALUES ('$descripcion', $monto, CURDATE(), $id_cliente)");
            } else if ($tipo == "RECARGA") {
                $conn->query("INSERT INTO recarga_tiempo_aire (numero_telefono, compania, monto, fecha, usuario_fk) VALUES ('5500000000', 'TELCEL', $monto, CURDATE(), $id_cliente)");
            }

            $conn->commit(); 
            echo "<script>alert('✅ Operación ejecutada con éxito.'); window.location.href='cliente_panel.php';</script>";
            exit();
        } catch (Exception $e) {
            $conn->rollback(); 
            echo "<script>alert('❌ Error: " . addslashes($e->getMessage()) . "'); window.location.href='cliente_panel.php';</script>";
            exit();
        }
    } else {
        echo "<script>alert('❌ Error: El monto debe ser mayor a cero.'); window.location.href='cliente_panel.php';</script>";
        exit();
    }
}

// ====================================================================
// BLOQUE 2: PROCESAR TRANSFERENCIAS ENTRE CUENTAS DE BANJOCO
// ====================================================================
if (isset($_POST['enviar_transferencia'])) {
    $cuenta_destino = $conn->real_escape_string($_POST['cuenta_destino']);
    $monto_transferir = floatval(str_replace(',', '.', $_POST['monto_transferir']));
    $concepto_trans = $conn->real_escape_string($_POST['concepto_transferencia']);

    if ($monto_transferir > 0) {
        $sql_destino = "SELECT cuenta_pk, num_cuenta FROM cuenta WHERE num_cuenta = '$cuenta_destino'";
        $res_destino = $conn->query($sql_destino);

        if ($res_destino && $res_destino->num_rows === 1) {
            $row_dest = $res_destino->fetch_assoc();
            $id_cuenta_destino = intval($row_dest['cuenta_pk']);

            $conn->begin_transaction();
            try {
                $res_c_emisor = $conn->query("SELECT cuenta_pk, num_cuenta, saldo FROM cuenta WHERE usuario_fk = $id_cliente FOR UPDATE");
                if (!$res_c_emisor || $res_c_emisor->num_rows === 0) {
                    throw new Exception("No se encontró la cuenta de origen asociada a tu usuario.");
                }
                $c_emisor = $res_c_emisor->fetch_assoc();
                $id_cuenta_emisor = intval($c_emisor['cuenta_pk']);
                $num_cuenta_emisor = $c_emisor['num_cuenta'];
                $saldo_emisor = floatval($c_emisor['saldo']);

                $res_c_dest_lock = $conn->query("SELECT cuenta_pk, saldo FROM cuenta WHERE cuenta_pk = $id_cuenta_destino FOR UPDATE");
                if (!$res_c_dest_lock || $res_c_dest_lock->num_rows === 0) {
                    throw new Exception("La cuenta destino ya no está disponible.");
                }

                if ($id_cuenta_destino == $id_cuenta_emisor) {
                    throw new Exception("No puedes transferir dinero a tu propia cuenta.");
                }

                if ($saldo_emisor >= $monto_transferir) {
                    $upd_emisor = $conn->query("UPDATE cuenta SET saldo = saldo - $monto_transferir WHERE cuenta_pk = $id_cuenta_emisor");
                    if (!$upd_emisor) throw new Exception("Error al descontar saldo: " . $conn->error);
                } else {
                    throw new Exception("Saldo insuficiente para enviar la transferencia.");
                }

                $upd_dest = $conn->query("UPDATE cuenta SET saldo = saldo + $monto_transferir WHERE cuenta_pk = $id_cuenta_destino");
                if (!$upd_dest) throw new Exception("Error al abonar saldo destino: " . $conn->error);

                // Textos cortos para evitar problemas de truncamiento en base de datos
                $ins_env = $conn->query("INSERT INTO transaccion (tipo_transaccion, monto, fecha, hora, descripcion, cuenta_fk) 
                              VALUES ('T_ENVIADA', $monto_transferir, CURDATE(), CURTIME(), 'A la cuenta: $cuenta_destino - $concepto_trans', $id_cuenta_emisor)");
                if (!$ins_env) throw new Exception("Error al registrar transacción enviada: " . $conn->error);

                $ins_rec = $conn->query("INSERT INTO transaccion (tipo_transaccion, monto, fecha, hora, descripcion, cuenta_fk) 
                              VALUES ('T_RECIBIDA', $monto_transferir, CURDATE(), CURTIME(), 'Desde la cuenta: $num_cuenta_emisor - $concepto_trans', $id_cuenta_destino)");
                if (!$ins_rec) throw new Exception("Error al registrar transacción recibida: " . $conn->error);

                $conn->commit(); 
                echo "<script>alert('✅ Transferencia realizada con éxito.'); window.location.href='cliente_panel.php';</script>";
                exit();

            } catch (Exception $e) {
                $conn->rollback(); 
                echo "<script>alert('❌ Error: " . addslashes($e->getMessage()) . "'); window.location.href='cliente_panel.php';</script>";
                exit();
            }
        } else {
            echo "<script>alert('❌ Error: El número de cuenta destino no existe en el sistema.'); window.location.href='cliente_panel.php';</script>";
            exit();
        }
    } else {
        echo "<script>alert('❌ Error: El monto a transferir debe ser mayor a cero.'); window.location.href='cliente_panel.php';</script>";
        exit();
    }
}

// ====================================================================
// PASO 3: OBTENER DATOS DE LA CUENTA Y DEL USUARIO
// ====================================================================
$sql_cuenta = "SELECT * FROM cuenta WHERE usuario_fk = $id_cliente";
$res_cuenta = $conn->query($sql_cuenta);
$cuenta = $res_cuenta ? $res_cuenta->fetch_assoc() : null;

$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'KEVIN MARCIAL';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BANJOCO - Panel Personal</title>
    <!-- Aquí vinculas tu archivo CSS externo correctamente -->
    <link rel="stylesheet" href="estilos.css"> 
</head>
<body>

    <nav class="navbar">
        <div class="nav-contenedor">
            <div class="logo-area">
                <span class="logo">BANJOCO</span>
                <span class="badge-banca">Banca Digital</span>
            </div>
            <div style="font-size: 13px;">
                Bienvenido(a): <strong><?php echo htmlspecialchars(strtoupper($nombre_usuario)); ?></strong> | 
                <a href="logout.php" style="color: #ff6b6b; font-weight: bold; text-decoration: none; margin-left: 10px;">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="panel-contenedor">
        
        <!-- COLUMNA IZQUIERDA: Tarjeta de Saldo Digital Estilizada -->
        <div>
            <div class="tarjeta-saldo-top">
                <div class="tarjeta-header-row">
                    <span class="banco-chip-text">BANJOCO DIGITAL</span>
                    <span class="badge-debito">DÉBITO</span>
                </div>
                
                <div class="chip-tarjeta"></div>

                <p class="etiqueta-saldo">Saldo Disponible</p>
                <h2 class="monto-saldo">$<?php echo number_format($cuenta['saldo'] ?? 0, 2, '.', ','); ?> <span style="font-size: 14px; font-weight: normal; color: #94a3b8;">MXN</span></h2>
                
                <div class="info-titular-tarjeta">
                    <div>
                        <p class="etiqueta-titular">Titular de la cuenta</p>
                        <p class="nombre-titular"><?php echo htmlspecialchars(strtoupper($nombre_usuario)); ?></p>
                    </div>
                    <div>
                        <!-- CORREGIDO: Imprime num_cuenta de la base de datos -->
                        <span class="num-cuenta-tarjeta">N° <?php echo htmlspecialchars($cuenta['num_cuenta'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>

           <a href="reporte.php" target="_blank" class="btn-estado-cuenta">
    🖨️ Imprimir Estado de Cuenta
</a>
            </a>
        </div>

        <!-- COLUMNA DERECHA: Los Dos Formularios / Cajeros -->
        <div class="grid-cajeros">
            
            <!-- Formulario 1: Transferir Dinero -->
            <div class="tarjeta-formulario">
                <h3>💸 Transferir Dinero</h3>
                
                <form action="cliente_panel.php" method="POST">
                    <div class="grupo-input">
                        <label>Número de Cuenta Destino</label>
                        <input type="text" name="cuenta_destino" placeholder="Ej. 12345" required>
                    </div>
                    
                    <div class="grupo-input">
                        <label>Monto a Enviar ($)</label>
                        <input type="text" name="monto_transferir" placeholder="0.00" required>
                    </div>

                    <div class="grupo-input">
                        <label>Concepto de Transferencia</label>
                        <input type="text" name="concepto_transferencia" placeholder="Ej. Pago de proyecto, comida, etc." required>
                    </div>

                    <button type="submit" name="enviar_transferencia" class="btn-accion btn-transferir">Confirmar Envío</button>
                </form>
            </div>

            <!-- Formulario 2: Cajero Express y Servicios -->
            <div class="tarjeta-formulario">
                <h3>🏛️ Cajero Express y Servicios</h3>
                
                <form action="cliente_panel.php" method="POST">
                    <div class="grupo-input">
                        <label>Selecciona la Operación</label>
                        <select name="tipo_transaccion">
                            <option value="DEPOSITO">📥 Realizar un Depósito (+)</option>
                            <option value="RETIRO">📤 Retirar Efectivo Cajero (-)</option>
                            <option value="PRESTAMO">🏛️ Solicitar Crédito / Préstamo (+)</option>
                            <option value="APARTADO">🐷 Enviar Dinero a mi Apartado (-)</option>
                            <option value="RECARGA">📱 Comprar Tiempo Aire Celular (-)</option>
                        </select>
                    </div>
                    
                    <div class="grupo-input">
                        <label>Monto de la Operación ($)</label>
                        <input type="text" name="monto" placeholder="0.00" required>
                    </div>

                    <div class="grupo-input">
                        <label>Referencia / Nota Breve</label>
                        <input type="text" name="descripcion" placeholder="Ej. Depósito semanal, Retiro cena" required>
                    </div>

                    <button type="submit" name="ejecutar_operacion" class="btn-accion btn-cajero">Ejecutar Movimiento</button>
                </form>
            </div>

        </div>

    </div>

</body>
</html>