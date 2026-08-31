<?php
session_start();
include("conexion.php");

$error_msg = "";

if (isset($_POST['ingresar'])) {
    $correo = trim($_POST['correo']);
    $contrasena = $_POST['contrasena'];

    $stmt = $conn->prepare("SELECT usuario_pk, nombre, apellidos, contrasena, rol_fk FROM usuario WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();

        if ($contrasena === $usuario['contrasena']) {
            $_SESSION['usuario_id'] = $usuario['usuario_pk'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'] . " " . $usuario['apellidos'];
            $_SESSION['usuario_rol'] = $usuario['rol_fk']; 

            if ($usuario['rol_fk'] == 1) {
                header("Location: index.php");
            } else if ($usuario['rol_fk'] == 2) {
                header("Location: cliente_panel.php");
            }
            exit();
        } else {
            $error_msg = "Contraseña incorrecta.";
        }
    } else {
        $error_msg = "El correo electrónico no está registrado.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>BANJOCO - Iniciar Sesión</title>
    <link rel="stylesheet" href="estilos.css">
    <style>
        body {
            margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #072146 0%, #0d3873 100%);
            height: 100vh; display: flex; justify-content: center; align-items: center;
        }
        .login-box {
            background: white; padding: 40px; border-radius: 8px;
            box-shadow: 0 12px 36px rgba(0,0,0,0.3); width: 100%; max-width: 400px;
        }
        .login-box h2 { text-align: center; color: #072146; margin-bottom: 30px; letter-spacing: 1px; }
        .grupo-input { margin-bottom: 20px; }
        .grupo-input label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: bold; color: #444; }
        .grupo-input input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn-login { background: #48ae64; color: white; border: none; padding: 14px; width: 100%; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; transition: background 0.3s; }
        .btn-login:hover { background: #3b9352; }
        .alert-error { background: #ffe3e3; color: #e53935; padding: 10px; border-radius: 4px; font-size: 13px; margin-bottom: 20px; text-align: center; border: 1px solid #fac8c8; }
    </style>
</head>
<body>

<div class="login-box">
    <h2>BANJOCO</h2>
    <?php if(!empty($error_msg)): ?>
        <div class="alert-error"><?php echo $error_msg; ?></div>
    <?php endif; ?>
    <form action="login.php" method="POST">
        <div class="grupo-input">
            <label>Correo Electrónico</label>
            <input type="email" name="correo" placeholder="ejemplo@banco.com" required>
        </div>
        <div class="grupo-input">
            <label>Contraseña / NIP</label>
            <input type="password" name="contrasena" placeholder="••••" required>
        </div>
        <button type="submit" name="ingresar" class="btn-login">Ingresar al Sistema</button>
    </form>
</div>

</body>
</html>