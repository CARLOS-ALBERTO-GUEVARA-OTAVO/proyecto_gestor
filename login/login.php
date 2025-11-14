<?php
session_start();
// Si el usuario ya ha iniciado sesión, redirigirlo a su página correspondiente.
if (isset($_SESSION['usuario_id'])) {
    // Si el rol es Administrador (ID 1), va al dashboard.
    if (isset($_SESSION['usuario_rol_id']) && $_SESSION['usuario_rol_id'] == 1) {
        header("Location: ../administrador/dashboard.php");
    } else { // Cualquier otro rol, va al visor de archivos.
        header("Location: ../index.php");
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #198754; /* Un verde oscuro y elegante */
            --primary-hover: #146c43; /* Un tono más oscuro para el hover */
            --error-color: #dc3545;
            --success-color: #198754; /* Mantenemos el verde para éxito */
            --text-color: #333;
        }

        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            /* Fondo de la página */
            background-image: url('../css/fondo_1_.png');
            background-size: cover;
            background-position: center;
        }

        .container {
            max-width: 400px;
            width: 90%;
            padding: 40px;
            background: rgba(255, 255, 255, 0.9); /* Un poco más opaco para mejorar legibilidad */ 
            backdrop-filter: blur(10px);
            border-radius: 12px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
            text-align: center;
        }

        h2 {
            margin-top: 0;
            margin-bottom: 24px;
            color: var(--text-color);
            font-size: 2rem;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box; /* Importante para que el padding no afecte el ancho total */
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.25); /* Sombra de foco con el nuevo color verde */
        }

        button {
            width: 100%;
            padding: 12px;
            background: var(--primary-color);
            border: none;
            color: #fff;
            font-weight: bold;
            font-size: 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        button:hover {
            background: var(--primary-hover);
        }

        .input-ok {
            border-color: var(--success-color) !important;
        }

        .input-error {
            border-color: var(--error-color) !important;
        }

        .error {
            color: var(--error-color);
            font-weight: 500;
            margin: 15px 0;
            padding: 10px;
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            border-radius: 8px;
        }

        .contact-info {
            margin-top: 24px;
            font-size: 0.875rem; /* 14px */
            color: #4a5568; /* Un gris oscuro para buena legibilidad */
            line-height: 1.5;
            border-top: 1px solid #e2e8f0; /* Separador sutil */
            padding-top: 16px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Iniciar Sesión</h2>

    <?php if (isset($_SESSION["error"])) : ?>
        <div class="error"><?= htmlspecialchars($_SESSION["error"]); unset($_SESSION["error"]); ?></div>
    <?php endif; ?>

    <form id="loginForm" method="POST" action="procesar_login.php">
        <div class="form-group">
            <input type="email" id="email" name="email" placeholder="Correo electrónico" required>
        </div>
        <div class="form-group">
            <input type="password" id="password" name="password" placeholder="Contraseña" required minlength="6">
        </div>
        <div id="mensajeError" class="error" style="display: none;"></div>
        <button type="submit">Iniciar Sesión</button>
    </form> <!-- El formulario termina aquí -->

    <!-- Movemos la información de contacto fuera del formulario -->
    <div class="contact-info">
        <p style="margin-bottom: 10px;">
            ¿Olvidaste tus credenciales? Contacta al <strong>Líder de Gestión del Conocimiento y TICS</strong>.
        </p>
        <a href="https://mail.google.com/mail/?view=cm&fs=1&to=aprendiz.gct@biofix.com.co&su=Reporte+de+Problema+-+Gestor+Documental&body=Descripción%20del%20problema:%0A%0A"
           target="_blank" 
           style="display: inline-block; padding: 8px 12px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 6px; font-size: 0.9rem;">
            Reportar un Problema
        </a>
    </div>
</div>

<script src="../validaciones/val_login.js"></script>
</body>
</html>
