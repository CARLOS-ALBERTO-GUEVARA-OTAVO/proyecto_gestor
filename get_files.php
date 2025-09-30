<?php
session_start();

/**
 * Manejador de errores personalizado para asegurar respuestas JSON.
 * Captura errores de PHP (warnings, notices) que no son excepciones
 * y los convierte en una respuesta JSON de error coherente.
 */
set_error_handler(function ($severity, $message, $file, $line) {
    // Ignorar errores si error_reporting está desactivado
    if (!(error_reporting() & $severity)) {
        return false;
    }

    // Limpiamos cualquier salida que se haya podido generar antes del error.
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500); // Internal Server Error
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => "Error interno del servidor en $file en la línea $line: $message",
        'fileListHtml' => '<p class="no-files">Ocurrió un error inesperado en el servidor. Contacta al administrador.</p>'
    ]);
    exit; // Detenemos la ejecución para no enviar más datos.
});

/**
 * Manejador de cierre para capturar errores fatales (como Parse Errors).
 * Esto asegura que si el script muere inesperadamente, todavía intentará
 * enviar una respuesta JSON válida en lugar de HTML de error.
 */
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        if (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Error fatal del servidor: ' . $error['message'] . " en " . $error['file'] . " línea " . $error['line'],
            'fileListHtml' => '<p class="no-files">Ocurrió un error fatal en el servidor. Contacta al administrador.</p>'
        ]);
    }
});

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

/**
 * Verifica si un usuario tiene permiso para acceder a una carpeta específica.
 * Lo hace de forma recursiva, subiendo por el árbol de directorios de Drive
 * hasta encontrar una de las carpetas base permitidas para el usuario.
 *
 * @param Google_Service_Drive $service El objeto de servicio de Drive.
 * @param string $folderId El ID de la carpeta que se quiere verificar.
 * @param array $allowedRootFolders La lista de IDs de las carpetas raíz permitidas para el usuario.
 * @param array &$cache Caché para evitar llamadas repetidas a la API.
 * @return bool True si la carpeta es permitida, false en caso contrario.
 */
function isFolderAllowed(Google_Service_Drive $service, $folderId, array $allowedRootFolders, &$cache) {
    // Si la carpeta actual es una de las raíces permitidas, el acceso es válido.
    if (in_array($folderId, $allowedRootFolders)) {
        return true;
    }

    // Usamos la caché para no volver a verificar una carpeta ya procesada.
    if (isset($cache[$folderId])) {
        return $cache[$folderId];
    }

    try {
        $folder = $service->files->get($folderId, ['fields' => 'parents']);
        if (!empty($folder->getParents())) {
            $parentId = $folder->getParents()[0];
            // Llamada recursiva para verificar el padre. El resultado se guarda en caché.
            return $cache[$folderId] = isFolderAllowed($service, $parentId, $allowedRootFolders, $cache);
        }
    } catch (Exception $e) {
        // Si hay un error (ej. carpeta no encontrada), se deniega el acceso.
    }
    return $cache[$folderId] = false;
}

// --- Database Connection ---
$conn = new mysqli('localhost', 'root', '', 'proyecto_gestion', '3306');

// Establecemos que la respuesta será en formato JSON
header('Content-Type: application/json');

$searchQuery = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// ID de la carpeta raíz para el Administrador.
$defaultFolderId = "1w1X74_EI9LDVhkTrrgA89etnvofGhYSN";

// --- LÓGICA DE PERMISOS ---
// Solo el rol de Administrador (ID 1) tiene acceso total. Los demás se basan en su cargo.
$roles_con_acceso_total = [1];
$is_admin_role = isset($_SESSION['usuario_rol_id']) && in_array($_SESSION['usuario_rol_id'], $roles_con_acceso_total);

$allowed_folders = $_SESSION['allowed_folders'] ?? [];

// La carpeta raíz para este usuario. Si es admin, la general. Si no, la primera de su lista.
$userRootFolderId = $is_admin_role ? $defaultFolderId : ($allowed_folders[0] ?? null);

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
    $client = new Google_Client();
    // setAuthConfig es el método recomendado y suficiente para cargar las credenciales.
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

        // ¡CORRECCIÓN! Si el usuario no es admin y no tiene carpetas, no puede buscar.
        if (!$is_admin_role && empty($allowed_folders)) {
            throw new Exception("No tienes permiso para buscar archivos porque no tienes carpetas asignadas.");
        }


        // --- RESTRICCIÓN DE BÚSQUEDA POR PERMISOS ---
        $searchScopeQuery = "";
        if (!$is_admin_role) {
            if (empty($allowed_folders)) {
                throw new Exception("No tienes permiso para buscar archivos.");
            }
            $parentQueries = array_map(function($id) {
                return sprintf("'%s' in parents", addslashes($id));
            }, $allowed_folders);
            $searchScopeQuery = ' and (' . implode(' or ', $parentQueries) . ')';
        }

        // Parámetros para buscar archivos por nombre
        $optParams = [
            'q' => sprintf("name contains '%s' and trashed = false %s", addslashes($searchQuery), $searchScopeQuery),
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
                    // Usamos la carpeta raíz del usuario como tope para la recursión.
                    // ¡CORRECCIÓN CRÍTICA! Si el usuario no es admin, $userRootFolderId puede ser null.
                    // Para la búsqueda, siempre necesitamos un ID de tope válido. Usaremos el defaultFolderId
                    // si el específico del usuario no está disponible. Esto no da más permisos, solo evita un error fatal.
                    $pathParts = getFolderPathRecursive($service, $parentId, $userRootFolderId ?: $defaultFolderId, $folderPathCache);
                    
                    if (empty($pathParts)) {
                        // Si la ruta está vacía, el archivo está en la carpeta raíz.
                        // Creamos un enlace a la carpeta raíz.
                        $fullPath = $is_admin_role ? sprintf(
                            '<a href="?folderId=%s" data-folderid="%s">Carpeta Principal</a>',
                            htmlspecialchars($defaultFolderId),
                            htmlspecialchars($defaultFolderId)
                        ) : '';
                    } else {
                        $fullPath = implode(' / ', array_map(
                            function($part) {
                                return sprintf(
                                    '<a href="?folderId=%s" data-folderid="%s">%s</a>',
                                    htmlspecialchars($part['id']),
                                    htmlspecialchars($part['id']),
                                    htmlspecialchars($part['name'])
                                );
                            },
                            $pathParts
                        ));
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

    // --- LÓGICA DE LISTADO DE CARPETAS ---
    $folderId = filter_input(INPUT_GET, 'folderId', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Si no se especifica un folderId, mostramos las carpetas raíz del usuario.
    if (!$folderId) {
        if ($is_admin_role) {
            $folderId = $defaultFolderId; // El admin ve la carpeta raíz por defecto.
        } else {
            if (empty($allowed_folders)) {
                 $response['status'] = 'success';
                 $response['message'] = 'No tienes carpetas asignadas.';
                 $response['folderName'] = 'Mis Carpetas';
                 $response['fileListHtml'] = "<p class='no-files'>No tienes acceso a ninguna carpeta. Contacta al administrador.</p>";
                 $response['breadcrumbHtml'] = '<span>Mis Carpetas</span>';
                 echo json_encode($response);
                 $conn->close();
                 exit;
            }
            // Para usuarios no-admin, la "raíz" es una lista de sus carpetas permitidas.
            $response['status'] = 'success';
            $response['message'] = 'Mostrando tus carpetas asignadas.';
            $response['folderName'] = 'Mis Carpetas';
            $response['breadcrumbHtml'] = '<span class="current-folder">Mis Carpetas</span>';
            
            $fileListHtml = '<ul class="file-list">';
            foreach ($allowed_folders as $allowed_folder_id) {
                try {
                    $folder_info = $service->files->get($allowed_folder_id, ['fields' => 'id, name, iconLink']);
                    $fileListHtml .= sprintf(
                        '<li class="file-item">' .
                            '<a href="?folderId=%s" data-folderid="%s"><img src="%s" alt="icon" class="file-icon"> <span>%s</span></a>' .
                        '</li>',
                        htmlspecialchars($folder_info->getId()),
                        htmlspecialchars($folder_info->getId()),
                        htmlspecialchars($folder_info->getIconLink()),
                        htmlspecialchars($folder_info->getName())
                    );
                } catch (Exception $e) {
                    // Si una carpeta asignada no existe o no hay permisos, se omite.
                }
            }
            $fileListHtml .= '</ul>';
            $response['fileListHtml'] = $fileListHtml;
            echo json_encode($response);
            $conn->close();
            exit;
        }
    }

    // --- VALIDACIÓN DE PERMISOS PARA LA CARPETA SOLICITITADA ---
    if (!$is_admin_role) {
        // ¡NUEVA VALIDACIÓN ROBUSTA!
        // Usamos nuestra nueva función para verificar si la carpeta solicitada ($folderId)
        // es una de las carpetas raíz del usuario O una subcarpeta de alguna de ellas.
        $permissionCache = [];
        if (!isFolderAllowed($service, $folderId, $allowed_folders, $permissionCache)) {
            throw new Exception("Acceso denegado a esta carpeta.");
        }
    }

    $folder = $service->files->get($folderId, ['fields' => 'name, parents']);
    $response['folderName'] = $folder->getName();

    // Parámetros para listar archivos
    $optParams = [
        'q' => sprintf("'%s' in parents and trashed = false", $folderId),
        'pageSize' => 20,
        'fields' => 'files(id, name, iconLink, webViewLink, mimeType, createdTime, modifiedTime)'
    ];

    $results = $service->files->listFiles($optParams);
    $archivos = $results->getFiles();

    // Registramos la acción de visualización de la carpeta en el historial solo si hay un usuario logueado.
    if (isset($_SESSION['usuario_id']) && $conn->ping()) {
        log_action($conn, 'vista de carpeta', $folderId, $folder->getName());
    }

    // --- Generación de la ruta de navegación (Breadcrumbs) ---
    $breadcrumbHtml = '<a href="?folderId=" data-folderid="">Mis Carpetas</a>'; // Enlace a la raíz del usuario
    $breadcrumbCache = [];

    // La ruta solo se muestra si estamos dentro de una de las carpetas permitidas.
    // Para usuarios no-admin, la recursión se detiene en CUALQUIERA de sus carpetas raíz asignadas.
    // Si la carpeta actual no es descendiente de ninguna, la ruta estará vacía.
    $rootForPath = $is_admin_role ? $defaultFolderId : null;

    $pathParts = [];
    if ($is_admin_role) {
        $pathParts = getFolderPathRecursive($service, $folderId, $rootForPath, $breadcrumbCache);
    } else {
        // Para usuarios no-admin, buscamos la ruta desde la carpeta actual hasta una de sus carpetas raíz.
        foreach ($allowed_folders as $user_root) {
            $tempPath = getFolderPathRecursive($service, $folderId, $user_root, $breadcrumbCache);
            // Si encontramos una ruta válida (no está vacía y no contiene error), la usamos.
            if (!empty($tempPath) && $tempPath[0]['id'] !== null) {
                // Añadimos la carpeta raíz del usuario al inicio de la ruta para que sea visible
                $root_folder_info = $service->files->get($user_root, ['fields' => 'id, name']);
                array_unshift($tempPath, ['id' => $root_folder_info->getId(), 'name' => $root_folder_info->getName()]);
                $pathParts = $tempPath;
                break;
            }
        }
    }
    
    // Si la carpeta actual es una de las raíces del usuario, no mostramos la ruta completa, solo su nombre.
    if (in_array($folderId, $allowed_folders) && !$is_admin_role) {
        $breadcrumbHtml .= sprintf('<span class="separator">/</span><span class="current-folder">%s</span>', htmlspecialchars($folder->getName()));
    } else { // Para subcarpetas o para el admin
        foreach (($pathParts ?? []) as $index => $part) {
            $breadcrumbHtml .= '<span class="separator">/</span>';
            $isLast = $index === count($pathParts) - 1;
            if ($isLast) {
                $breadcrumbHtml .= sprintf('<span class="current-folder">%s</span>', htmlspecialchars($part['name']));
            } else {
                $breadcrumbHtml .= sprintf('<a href="?folderId=%s" data-folderid="%s">%s</a>', htmlspecialchars($part['id']), htmlspecialchars($part['id']), htmlspecialchars($part['name']));
            }
        }
    }
    $response['breadcrumbHtml'] = $breadcrumbHtml;

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

// Al final de get_files.php, antes de cualquier echo o salida
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Error fatal: ' . $error['message'],
            'fileListHtml' => '<p class="no-files">Ocurrió un error fatal en el servidor. Contacta al administrador.</p>'
        ]);
        exit;
    }
});
