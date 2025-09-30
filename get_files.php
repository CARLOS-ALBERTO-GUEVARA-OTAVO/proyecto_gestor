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

/**
 * Obtiene la ruta completa de una carpeta (breadcrumb) de forma recursiva.
 * Utiliza una caché para evitar llamadas repetidas a la API para la misma carpeta.
 * @param Google_Service_Drive $service El objeto de servicio de Drive.
 * @param string $folderId El ID de la carpeta desde la que empezar.
 * @param string $rootFolderId El ID de la carpeta raíz para detener la recursión.
 * @param array &$cache Array pasado por referencia para almacenar en caché las rutas ya calculadas.
 * @return array Un array de fragmentos de ruta, cada uno con 'id', 'name'.
 */
function getFolderPathRecursive(Google_Service_Drive $service, $folderId, $rootFolderId, &$cache) {
    // Si ya calculamos esta ruta, la devolvemos desde la caché.
    if (isset($cache[$folderId])) {
        return $cache[$folderId];
    }

    // Si llegamos a la carpeta raíz (o por encima de ella), detenemos la recursión.
    if ($folderId === $rootFolderId) {
        return [];
    }

    try {
        $folder = $service->files->get($folderId, ['fields' => 'id, name, parents']);
        $path = [];

        // Recursivamente obtenemos la ruta del padre.
        if (!empty($folder->getParents())) {
            $parentId = $folder->getParents()[0];
            // Si el padre no es la carpeta raíz, continuamos la recursión.
            if ($parentId !== $rootFolderId) {
                 $path = getFolderPathRecursive($service, $parentId, $rootFolderId, $cache);
            }
        }

        // Añadimos la carpeta actual al final de la ruta.
        $path[] = [
            'id' => $folder->getId(),
            'name' => $folder->getName()
        ];

        $cache[$folderId] = $path; // Guardamos el resultado en la caché.
        return $path;

    } catch (Exception $e) {
        // Si no hay permisos para una carpeta intermedia, devolvemos un marcador.
        return [['id' => null, 'name' => 'Ruta no accesible']];
    }
}

// --- Database Connection ---
$conn = new mysqli('localhost', 'root', '', 'proyecto_gestion', '3306');

// Establecemos que la respuesta será en formato JSON
header('Content-Type: application/json');

$searchQuery = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// ID de la carpeta raíz. Se define aquí para que esté disponible tanto para la búsqueda como para el listado.
$defaultFolderId = "1w1X74_EI9LDVhkTrrgA89etnvofGhYSN";

// --- Handle GET requests (list files) ---
$response = [
    'status' => 'error',
    'message' => 'Ocurrió un error desconocido.',
    'folderName' => 'Google Drive',
    'fileListHtml' => '',
    'breadcrumbHtml' => ''
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

    // --- LÓGICA DE BÚSQUEDA ---
    if ($searchQuery) {
        $response['folderName'] = "Resultados de la búsqueda";

        // Parámetros para buscar archivos por nombre
        $optParams = [
            'q' => sprintf("name contains '%s' and trashed = false", addslashes($searchQuery)),
            'pageSize' => 25, // Aumentamos un poco el límite para búsquedas
            'fields' => 'files(id, name, iconLink, webViewLink, mimeType, parents, createdTime, modifiedTime)' // Pedimos los parents
        ];

        $results = $service->files->listFiles($optParams);
        $archivos = $results->getFiles();

        // Caché para las rutas de las carpetas, para no repetir llamadas a la API
        $folderPathCache = [];

        $fileListHtml = '';
        if (empty($archivos)) {
            $fileListHtml = "<p class='no-files'>No se encontraron archivos que coincidan con '<strong>" . htmlspecialchars($searchQuery) . "</strong>'.</p>";
        } else {
            $fileListHtml .= '<ul class="file-list search-results">';
            foreach ($archivos as $file) {
                // Para la búsqueda, no mostraremos carpetas en los resultados, solo archivos.
                if ($file->getMimeType() === 'application/vnd.google-apps.folder') {
                    continue;
                }

                // --- Formateo de Fechas con Zona Horaria (igual que en la vista de carpeta) ---
                // ¡IMPORTANTE! Cambia 'America/Bogota' a tu zona horaria si es diferente.
                $local_tz = new DateTimeZone('America/Bogota');

                $createdDate = new DateTime($file->getCreatedTime());
                $createdDate->setTimezone($local_tz);
                $formattedCreated = $createdDate->format('d/m/Y H:i');

                $modifiedDate = new DateTime($file->getModifiedTime());
                $modifiedDate->setTimezone($local_tz);
                $formattedModified = $modifiedDate->format('d/m/Y H:i');


                $fullPath = 'Ubicación desconocida';                

                // Obtenemos la ruta completa de la carpeta padre (si tiene una)
                if (!empty($file->getParents())) {
                    $parentId = $file->getParents()[0];
                    // getFolderPathRecursive devuelve un array de partes de la ruta
                    $pathParts = getFolderPathRecursive($service, $parentId, $defaultFolderId, $folderPathCache);
                    
                    if (empty($pathParts)) {
                        // Si la ruta está vacía, el archivo está en la carpeta raíz.
                        // Creamos un enlace a la carpeta raíz.
                        $fullPath = sprintf(
                            '<a href="?folderId=%s" data-folderid="%s">Carpeta Principal</a>',
                            htmlspecialchars($defaultFolderId),
                            htmlspecialchars($defaultFolderId)
                        );
                    } else {
                        $fullPath = implode(' / ', array_map(fn($part) => sprintf('<a href="?folderId=%s" data-folderid="%s">%s</a>', htmlspecialchars($part['id']), htmlspecialchars($part['id']), htmlspecialchars($part['name'])), $pathParts)
                        );
                    }
                }

                $fileListHtml .= sprintf(
                    '<li class="file-item">' .
                        '<a href="%s" target="_blank" rel="noopener noreferrer"><img src="%s" alt="icon" class="file-icon"> <span>%s</span></a>'.
                        '<div class="file-dates">' .
                            '<div class="file-location"><span>En carpeta:</span> %s</div>' .
                            '<span class="date-modified" title="Última modificación">Modificado: %s</span>' .
                        '</div>' .
                    '</li>',
                    htmlspecialchars($file->getWebViewLink()),
                    htmlspecialchars($file->getIconLink()),
                    htmlspecialchars($file->getName()),
                    $fullPath, // Ruta de la carpeta
                    $formattedModified // Fecha de modificación
                );
            }
            $fileListHtml .= '</ul>';
        }
        $response['status'] = 'success';
        $response['fileListHtml'] = $fileListHtml;
        $response['message'] = '✅ Búsqueda completada.';
        echo json_encode($response);
        $conn->close();
        exit; // Terminamos el script aquí para no ejecutar la lógica de listar carpetas.
    }

    // 3. Obtener información de la carpeta y archivos
    $folderId = filter_input(INPUT_GET, 'folderId', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: $defaultFolderId; // Usa el ID de la URL o el por defecto.

    $folder = $service->files->get($folderId, ['fields' => 'name, parents']);
    $response['folderName'] = $folder->getName();

    // Registramos la acción de visualización de la carpeta en el historial si hay un usuario logueado.
    if ($conn->ping() && isset($_SESSION['usuario_id'])) {
        log_action($conn, 'vista de carpeta', $folderId, $folder->getName());
    }

    // --- Generación de la ruta de navegación (Breadcrumbs) ---
    $breadcrumbHtml = sprintf('<a href="?folderId=%s" data-folderid="%s">Carpeta Principal</a>', htmlspecialchars($defaultFolderId), htmlspecialchars($defaultFolderId));
    $breadcrumbCache = [];

    if ($folderId !== $defaultFolderId) {
        $pathParts = getFolderPathRecursive($service, $folderId, $defaultFolderId, $breadcrumbCache);
        
        foreach ($pathParts as $index => $part) {
            $breadcrumbHtml .= '<span class="separator">/</span>';
            $isLast = $index === count($pathParts) - 1;

            if ($isLast) {
                // El último elemento es la carpeta actual, no es un enlace.
                $breadcrumbHtml .= sprintf('<span class="current-folder">%s</span>', htmlspecialchars($part['name']));
            } else {
                // Los elementos intermedios son enlaces.
                $breadcrumbHtml .= sprintf(
                    '<a href="?folderId=%s" data-folderid="%s">%s</a>',
                    htmlspecialchars($part['id']),
                    htmlspecialchars($part['id']),
                    htmlspecialchars($part['name'])
                );
            }
        }
    }
    $response['breadcrumbHtml'] = $breadcrumbHtml;

    // Parámetros para listar archivos
    $optParams = [
        'q' => sprintf("'%s' in parents and trashed = false", $folderId),
        'pageSize' => 20,
        'fields' => 'files(id, name, iconLink, webViewLink, mimeType, createdTime, modifiedTime)'
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

            // --- Formateo de Fechas con Zona Horaria ---
            // La API de Google devuelve fechas en formato UTC (ej: 2023-10-27T10:00:00.000Z).
            // Para mostrarlas correctamente en la hora local del usuario, hacemos una conversión.
            // ¡IMPORTANTE! Cambia 'America/Bogota' a tu zona horaria.
            // Lista de zonas horarias: https://www.php.net/manual/es/timezones.php
            $local_tz = new DateTimeZone('America/Bogota');

            $createdDate = new DateTime($file->getCreatedTime());
            $createdDate->setTimezone($local_tz);
            $formattedCreated = $createdDate->format('d/m/Y H:i');

            $modifiedDate = new DateTime($file->getModifiedTime());
            $modifiedDate->setTimezone($local_tz);
            $formattedModified = $modifiedDate->format('d/m/Y H:i');
            
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
                '<li class="file-item">' .
                    '<a href="%s" %s %s><img src="%s" alt="icon" class="file-icon"> <span>%s</span></a>' .
                    '<div class="file-dates">' .
                        '<span class="date-modified" title="Última modificación">Modificado: %s</span>' .
                        '<span class="date-created" title="Fecha de creación">Creado: %s</span>' .
                    '</div>' .
                '</li>',
                $link,
                $target,
                $dataAttribute,
                htmlspecialchars($file->getIconLink()),
                $fileNameEscaped,
                $formattedModified,
                $formattedCreated
            );
        }
        $fileListHtml .= '</ul>';
    }
    
    $response['status'] = 'success';
    $response['fileListHtml'] = $fileListHtml; // Ya se asignó antes
    $response['message'] = '✅ Carpeta cargada correctamente.';

} catch (Exception $e) {
    $response['message'] = "❌ Error: " . parseGoogleException($e, 'view');
    $response['fileListHtml'] = "<p class='no-files'>No se pudo cargar el contenido. Revisa los permisos de la carpeta en Google Drive.</p>";
}

// Devolvemos la respuesta completa como un objeto JSON
echo json_encode($response);
$conn->close();
