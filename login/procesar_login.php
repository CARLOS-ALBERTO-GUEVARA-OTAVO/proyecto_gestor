<?php
session_start();
require '../bd/db.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $pdo->prepare("SELECT id, nombre, email, password_hash, rol_id, estado_id
                           FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        if ($usuario["estado_id"] != 1) {
        $_SESSION["error"] = "Tu cuenta está inactiva o suspendida.";
        header("Location: login.php");
    exit;
        }

        if (password_verify($password, $usuario["password_hash"])) {
            $_SESSION["usuario_id"] = $usuario["id"];
            $_SESSION["usuario_nombre"] = $usuario["nombre"];
            $_SESSION["usuario_rol"] = $usuario["rol_id"];

            header("Location: ../administrador/dashboard.php");
            exit;
        } else {
            $_SESSION["error"] = "Contraseña incorrecta.";
            header("Location: login.php");
            exit;
        }
    } else {
        $_SESSION["error"] = "Usuario no encontrado.";
        header("Location: login.php");
        exit;
    }
}
?>


