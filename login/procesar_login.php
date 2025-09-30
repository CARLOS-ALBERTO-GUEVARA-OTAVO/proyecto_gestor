<?php
session_start();
require '../bd/db.php'; // Usamos la conexión PDO

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = 'Por favor, completa todos los campos.';
        header('Location: login.php');
        exit;
    }

    try {
        // 1. Buscamos al usuario por su email y traemos la información de su estado.
        $sql = "SELECT u.id, u.nombre, u.password_hash, u.rol_id, u.estado_id, es.estado, es.descripcion, r.nombre as rol_nombre, c.nombre as cargo_nombre
                FROM usuarios u
                JOIN estados_usuario es ON u.estado_id = es.id
                LEFT JOIN roles r ON u.rol_id = r.id
                LEFT JOIN cargos c ON u.cargo_id = c.id
                WHERE u.email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // 2. Verificamos si el usuario existe y si la contraseña es correcta
        if ($user && password_verify($password, $user['password_hash'])) {
            // 3. Verificamos si el estado del usuario es 'Activo' (ID 1)
            if ($user['estado_id'] == 1) {
                // Las credenciales son correctas y el usuario está activo, creamos la sesión.
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $user['nombre'];
                $_SESSION['usuario_rol_id'] = $user['rol_id'];
                $_SESSION['usuario_rol_nombre'] = $user['rol_nombre'];
                $_SESSION['usuario_cargo_nombre'] = $user['cargo_nombre'];

                // Redirigir según el rol del usuario
                if ($user['rol_id'] == 1) { // Rol de Administrador
                    header('Location: ../administrador/dashboard.php');
                } else {
                    header('Location: ../index.php');
                }
                exit;
            } else {
                // El usuario existe pero no está activo. Mostramos un mensaje específico.
                $_SESSION['error'] = "Acceso denegado. Su cuenta está en estado '{$user['estado']}'. Razón: {$user['descripcion']}";
                header('Location: login.php');
                exit;
            }
        } else {
            // Credenciales incorrectas (email no encontrado o contraseña errónea)
            $_SESSION['error'] = 'Correo o contraseña incorrectos.';
            header('Location: login.php');
            exit;
        }
    } catch (PDOException $e) {
        // En caso de un error de base de datos
        $_SESSION['error'] = 'Error del sistema. Por favor, intenta más tarde.';
        // podrías loggear el error real: error_log($e->getMessage());
        header('Location: login.php');
        exit;
    }
} else {
    // Si alguien intenta acceder directamente a este archivo
    header('Location: login.php');
    exit;
}