<?php
// Credenciales de acceso a la base de datos
$host = "localhost";
$usuario = "root";
$password = "root"; 
$base = "banjoco";   
$puerto = 3306; // Cambiar a 8889 en MAMP si es necesario

$conn = new mysqli($host, $usuario, $password, $base, $puerto);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4"); 
?>