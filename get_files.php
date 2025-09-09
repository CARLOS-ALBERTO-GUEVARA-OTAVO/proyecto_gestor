<?php
session_start();
require __DIR__ . '/vendor/autoload.php';

/**
 * Parsea una excepción de la API de Google para obtener un mensaje más legible.
 * @param Exception $e La excepción capturada.
 * @param string $context El contexto de la operación ('view' o 'delete') para personalizar el mensaje.
 * @return string El mensaje de error procesado.
 */
function parseGoogleException(Exception $e, $context = 'view') {
    $errorMessage = $e->getMessage();
    $decodedError = json_decode($errorMessage, true);

    if (json_last_error() === JSON_ERROR_NONE && isset($decodedError['error']['message'])) {
        $finalMessage = $decodedError['error']['message'];
        if (isset($decodedError['error']['errors'][0]['reason']) && $decodedError['error']['errors'][0]['reason'] === 'insufficientFilePermissions') {
            $permissionNeeded = ($context === 'delete') ? '"Editor"' : '"Lector"';
            $finalMessage .= ' (Asegúrate de que la cuenta de servicio tenga permisos de ' . $permissionNeeded . ' en la carpeta Y en el archivo específico. A veces los archivos no heredan los permisos correctamente).';
        }
        return $finalMessage;
    }
    return $errorMessage;
}

// --- Helper function for logging ---
function log_action($conn, $action, $fileId, $fileName, $details = '') {
    // Asumimos que no hay sesión de usuario en este contexto público.
    // Si hubiera un sistema de login, aquí se obtendría el ID de usuario de la sesión.
    $userId = null; 
    $userId = $_SESSION['usuario_id'] ?? null;
    $stmt = $conn->prepare("INSERT INTO historial_acciones (usuario_id, accion, id_archivo, nombre_archivo, detalles) VALUES (?, ?, ?, ?, ?)");    
    $stmt->bind_param("issss", $userId, $action, $fileId, $fileName, $details);
    $stmt->execute();
    $stmt->close();
}

// --- Database Connection ---
$conn = new mysqli('localhost', 'root', '123456', 'proyecto_gestion', '3309');

// Establecemos que la respuesta será en formato JSON
header('Content-Type: application/json');

// --- Handle POST requests (e.g., delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => '❌ Error de conexión a la base de datos.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;
    $fileId = $input['fileId'] ?? null;

    // --- Security Check ---
    // Solo usuarios logueados pueden eliminar. ¡Podrías añadir más checks, ej. por rol!
    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(403); // Forbidden
        echo json_encode(['status' => 'error', 'message' => '❌ No tienes permiso para realizar esta acción. Inicia sesión.']);
        exit;
    }

    if ($action === 'delete' && $fileId) {
        $fileName = 'Desconocido'; // Valor por defecto por si falla la obtención del nombre
        try {
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/flotax-map-3949a96314d9.json');
            $client = new Google_Client();
            $client->setAuthConfig(__DIR__ . '/flotax-map-3949a96314d9.json');
            $client->addScope(Google_Service_Drive::DRIVE);
            $token = $client->fetchAccessTokenWithAssertion();
            if (isset($token['error'])) {
                throw new Exception('Error al autenticar con Google: ' . $token['error_description']);
            }
            $service = new Google_Service_Drive($client);

            $fileToDelete = $service->files->get($fileId, ['fields' => 'name']);
            $fileName = $fileToDelete->getName();

            // Movemos el archivo a la papelera
            $service->files->update($fileId, new Google_Service_Drive_DriveFile(['trashed' => true]));

            if ($conn->ping()) log_action($conn, 'movido a papelera', $fileId, $fileName);

            echo json_encode(['status' => 'success', 'message' => "✅ Archivo '" . htmlspecialchars($fileName) . "' movido a la papelera."]);
        } catch (Exception $e) {
            http_response_code(500);
            $friendlyError = parseGoogleException($e, 'delete');
            // Registramos el error detallado en el historial
            if ($conn->ping()) log_action($conn, 'error al eliminar', $fileId, $fileName, $friendlyError);
            echo json_encode(['status' => 'error', 'message' => '❌ Error al eliminar: ' . $friendlyError]);
        }
        $conn->close();
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Acción no válida.']);
    $conn->close();
    exit;
}

// --- Handle GET requests (list files) ---
$response = [
    'status' => 'error',
    'message' => 'Ocurrió un error desconocido.',
    'folderName' => 'Google Drive',
    'fileListHtml' => '',
    'backLinkHtml' => ''
];

try {
    if ($conn->connect_error) {
        // No es un error fatal para el listado, pero podríamos querer registrarlo o mostrar una advertencia.
        $response['message'] = '⚠️ Advertencia: No se pudo conectar a la base de datos para el historial.';
    }
    // 1. Configuración y autenticación del cliente
    putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/flotax-map-3949a96314d9.json');
    $client = new Google_Client();
    $client->setAuthConfig(__DIR__ . '/flotax-map-3949a96314d9.json');
    $client->addScope(Google_Service_Drive::DRIVE);

    $token = $client->fetchAccessTokenWithAssertion();
    if (isset($token['error'])) {
        throw new Exception('Error al autenticar: ' . $token['error_description']);
    }

    // 2. Inicializar servicio de Drive
    $service = new Google_Service_Drive($client);

    // 3. Obtener información de la carpeta y archivos
    $defaultFolderId = "1w1X74_EI9LDVhkTrrgA89etnvofGhYSN";
    $folderId = filter_input(INPUT_GET, 'folderId', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: $defaultFolderId;

    $folder = $service->files->get($folderId, ['fields' => 'name, parents']);
    $response['folderName'] = $folder->getName();

    // Generamos un enlace para "Volver" si no estamos en la carpeta raíz
    if ($folderId !== $defaultFolderId && !empty($folder->getParents())) {
        $parentFolderId = $folder->getParents()[0];
        // Añadimos un atributo data-folderid para que JavaScript lo pueda identificar
        $response['backLinkHtml'] = sprintf(
            '<a href="?folderId=%s" class="back-link" data-folderid="%s">← Volver a la carpeta anterior</a>',
            htmlspecialchars($parentFolderId),
            htmlspecialchars($parentFolderId)
        );
    }

    // Parámetros para listar archivos
    $optParams = [
        'q' => sprintf("'%s' in parents and trashed = false", $folderId),
        'pageSize' => 20,
        'fields' => 'files(id, name, iconLink, webViewLink, mimeType)'
    ];

    $results = $service->files->listFiles($optParams);
    $archivos = $results->getFiles();

    // 4. Generar HTML para la lista de archivos
    $fileListHtml = '';
    if (empty($archivos)) {
        $fileListHtml = "<p class='no-files'>No se encontraron archivos en la carpeta.</p>";
    } else {
        $fileListHtml .= '<ul class="file-list">';
        foreach ($archivos as $file) {
            $isFolder = $file->getMimeType() === 'application/vnd.google-apps.folder';
            $fileId = $file->getId();
            $fileNameEscaped = htmlspecialchars($file->getName());

            // El botón de eliminar solo se muestra si hay una sesión de usuario activa.
            $deleteButton = '';
            if (isset($_SESSION['usuario_id'])) {
                $deleteButton = sprintf(
                    '<button class="delete-btn" data-fileid="%s" data-filename="%s" title="Mover a la papelera">🗑️</button>',
                    htmlspecialchars($fileId),
                    $fileNameEscaped
                );
            }
            
            if ($isFolder) {
                // Si es una CARPETA, preparamos el enlace para AJAX
                $link = sprintf('?folderId=%s', htmlspecialchars($fileId));
                $target = '';
                $dataAttribute = sprintf('data-folderid="%s"', htmlspecialchars($fileId)); // Atributo clave para JS
            } else {
                // Si es un ARCHIVO, el enlace va a Google Drive en una nueva pestaña
                $link = htmlspecialchars($file->getWebViewLink());
                $target = 'target="_blank" rel="noopener noreferrer"';
                $dataAttribute = '';
            }
            
            $fileListHtml .= sprintf(
                '<li class="file-item"><a href="%s" %s %s><img src="%s" alt="icon" class="file-icon"> <span>%s</span></a>%s</li>',
                $link,
                $target,
                $dataAttribute,
                htmlspecialchars($file->getIconLink()),
                $fileNameEscaped,
                $deleteButton
            );
        }
        $fileListHtml .= '</ul>';
    }
    
    $response['status'] = 'success';
    $response['fileListHtml'] = $fileListHtml;
    $response['message'] = '✅ Conexión establecida con Google Drive.';

} catch (Exception $e) {
    $response['message'] = "❌ Error: " . parseGoogleException($e, 'view');
    $response['fileListHtml'] = "<p class='no-files'>No se pudo cargar el contenido. Revisa los permisos de la carpeta en Google Drive.</p>";
}

// Devolvemos la respuesta completa como un objeto JSON
echo json_encode($response);
$conn->close();
