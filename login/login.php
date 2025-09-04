<?php
session_start();
if (isset($_SESSION['usuario_id'])) {
    header("Location: ../administrador/dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 400px; margin: 80px auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,.1);}
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
        button { width: 100%; padding: 10px; background: #007BFF; border: none; color: #fff; font-weight: bold; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .error { color: red; margin: 10px 0; }

        
        .input-ok {
            border: 2px solid green !important;
            background-color: #eaffea;
        }
        .input-error {
            border: 2px solid red !important;
            background-color: #ffeaea;
        }
        .error {
            color: red;
            font-size: 14px;
            margin-top: 5px;
        }

    </style>
</head>
<body>
<div class="container">
    <h2>Iniciar Sesión</h2>

    <?php if (isset($_SESSION["error"])): ?>
        <div class="error"><?= $_SESSION["error"]; unset($_SESSION["error"]); ?></div>
    <?php endif; ?>

    <form id="loginForm" method="POST" action="procesar_login.php">
        <input type="email" id="email" name="email" placeholder="Correo electrónico" required>
        <input type="password" id="password" name="password" placeholder="Contraseña" required minlength="6">
        <button type="submit">Entrar</button>
    </form>
    <div id="mensajeError" class="error"></div>
</div>

<script src="../validaciones/val_login.js"></script>
</body>
</html>
