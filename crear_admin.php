<?php
require 'db.php';

$nombre = "Administrador";
$email = "admin@correo.com";
$password = "admin123"; // contraseña real para loguearse
$rol_id = 1;
$estado = 1;

// Generar el hash
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Insertar en la BD
$stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol_id, estado_id) 
                       VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$nombre, $email, $password_hash, $rol_id, $estado]);

echo "✅ Administrador creado con éxito.<br>";
echo "Email: $email<br>";
echo "Contraseña: $password<br>";
?>
