<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Registra una acción del usuario en la base de datos.
 *
 * @param mysqli $conn La conexión a la base de datos.
 * @param string $action La acción realizada (ej. 'vista de archivo').
 * @param string|null $fileId El ID del archivo de Google Drive.
 * @param string|null $fileName El nombre del archivo.
 * @param string $details Detalles adicionales sobre la acción.
 * @param int|null $userId El ID del usuario. Si es null, se intenta obtener de la sesión.
 * @return void
 */
function log_action(mysqli $conn, string $action, ?string $fileId, ?string $fileName, string $details = '', ?int $userId = null)
{
    // Si no se pasa un userId, lo tomamos de la sesión.
    if ($userId === null) {
        $userId = $_SESSION['usuario_id'] ?? null;
    }

    // Preparamos la consulta.
    $stmt = $conn->prepare("INSERT INTO historial_acciones (usuario_id, accion, id_archivo, nombre_archivo, detalles) VALUES (?, ?, ?, ?, ?)");
    if ($stmt === false) {
        error_log("Error al preparar la consulta en log_action: " . $conn->error);
        return;
    }

    // MEJORA: Manejo robusto de un `usuario_id` que puede ser NULL.
    // Si $userId es null, no podemos usar el tipo "i" (integer) en bind_param.
    // Lo vinculamos como nulo directamente.
    if ($userId === null) {
        $stmt->bind_param("sssss", $userId, $action, $fileId, $fileName, $details);
    } else {
        $stmt->bind_param("issss", $userId, $action, $fileId, $fileName, $details);
    }

    if (!$stmt->execute()) {
        error_log("Error al ejecutar la consulta en log_action: " . $stmt->error);
    }

    $stmt->close();
}