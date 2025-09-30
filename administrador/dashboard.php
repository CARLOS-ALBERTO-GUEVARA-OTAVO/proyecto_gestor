<?php
session_start();

// Aumentamos los límites para la generación de reportes pesados.
ini_set('memory_limit', '1024M');
set_time_limit(600);

require '../vendor/autoload.php'; // Requerido para las librerías de Google y PhpSpreadsheet

// 1. Verificar si el usuario ha iniciado sesión.
// 2. Verificar si el usuario tiene el rol de Administrador (asumimos que el ID del rol es 1).
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol_id']) || $_SESSION['usuario_rol_id'] != 1) {
    // Si no es un administrador, redirigir a la página principal o de login.
    header('Location: ../index.php');
    exit;
}

// Conexión a la base de datos
// Usar la conexión PDO para consistencia es una buena práctica, pero mantenemos mysqli por ahora.
$conn = new mysqli('localhost', 'root', '', 'proyecto_gestion', '3306');
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// --- MANEJO DE ACCIÓN: GENERAR REPORTE EXCEL DE GOOGLE DRIVE ---
if (isset($_GET['action']) && $_GET['action'] == 'generate_report') {
    try {
        // ID de la carpeta raíz específica para el reporte.
        // Cambia este ID por el de tu carpeta "proyecto_gestor_simu".
        $reportRootFolderId = '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN';

        // --- 1. AUTENTICACIÓN CON GOOGLE DRIVE ---
        $credentialsFile = __DIR__ . '/../flotax-map-3949a96314d9.json';
        $client = new \Google\Client();
        $client->setAuthConfig($credentialsFile);
        $client->addScope(\Google\Service\Drive::DRIVE_READONLY);
        $token = $client->fetchAccessTokenWithAssertion();
        if (isset($token['error'])) {
            throw new Exception('Error de autenticación con Google: ' . $token['error_description']);
        }
        $service = new \Google\Service\Drive($client);

        // --- 2. OBTENER TODOS LOS ARCHIVOS Y CARPETAS (CON PAGINACIÓN) ---
        $allFiles = [];
        $pageToken = null;
        do {
            // 1. Obtenemos TODOS los archivos y carpetas. Es más eficiente que hacer llamadas recursivas.
            //    Luego filtraremos para quedarnos solo con los que están dentro de la carpeta de reporte.
            $results = $service->files->listFiles([
                'q' => "trashed = false",
                'pageSize' => 1000,
                'fields' => 'nextPageToken, files(id, name, mimeType, parents, createdTime, modifiedTime)',
                'pageToken' => $pageToken
            ]);
            $allFiles = array_merge($allFiles, $results->getFiles());
            $pageToken = $results->getNextPageToken();
        } while ($pageToken !== null);

        // --- 3. CONSTRUIR MAPAS PARA RUTAS Y FILTRADO (MÉTODO EFICIENTE) ---
        // Obtenemos el nombre de la carpeta raíz para usarlo como base de la ruta.
        $rootFolder = $service->files->get($reportRootFolderId, ['fields' => 'name']);
        $rootFolderName = $rootFolder->getName();

        $folderMap = [];
        $parentMap = [];
        foreach ($allFiles as $file) {
            if ($file->getMimeType() === 'application/vnd.google-apps.folder') {
                $folderMap[$file->getId()] = $file->getName();
            }
            if (!empty($file->getParents())) {
                $parentMap[$file->getId()] = $file->getParents();
            }
        }

        // Función recursiva para construir la ruta. Se define aquí para no colisionar.
        $getPathFunc = function(string $fileId, array $parentMap, array $folderMap, string $stopFolderId) use (&$getPathFunc): ?string {
            // Si llegamos a la carpeta raíz del reporte o no tiene padre, detenemos la recursión.
            if (!isset($parentMap[$fileId])) {
                return null; // No tiene padre, no está en nuestra carpeta de reporte.
            }
            $parentId = $parentMap[$fileId][0];
            if ($parentId === $stopFolderId) {
                return ''; // Llegamos a la raíz del reporte, la ruta relativa es vacía.
            }

            $path = $getPathFunc($parentId, $parentMap, $folderMap, $stopFolderId);

            // Si la recursión devolvió null, significa que este archivo no desciende de nuestra carpeta raíz.
            if ($path === null) {
                return null;
            }

            // Construimos la ruta relativa
            return ($path ? $path . '/' : '') . $folderMap[$parentId];
        };

        // --- 4. PREPARAR Y GENERAR EL ARCHIVO EXCEL ---
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rutas Drive');

        // Mapa para descripciones amigables de tipos MIME comunes.
        $mimeDescriptions = [
            'pdf' => 'Archivo PDF',
            'jpeg' => 'Imagen JPEG',
            'png' => 'Imagen PNG',
            'plain' => 'Archivo de texto (.txt)',
            'vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Archivo Excel (.xlsx)',
            'zip' => 'Archivo comprimido (.zip)',
            'vnd.google-apps.document' => 'Documento de Google',
            'vnd.google-apps.spreadsheet' => 'Hoja de Cálculo de Google',
            'vnd.google-apps.presentation' => 'Presentación de Google',
        ];

        // Encabezados
        $sheet->fromArray(['Nombre del Archivo', 'Ruta Completa', 'ID del Archivo', 'Tipo Principal', 'Formato'], NULL, 'A1');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        $rowIndex = 2;
        foreach ($allFiles as $file) {
            if ($file->getMimeType() === 'application/vnd.google-apps.folder') continue;

            // Procesar el tipo MIME para dividirlo
            $mimeType = $file->getMimeType();
            list($mainType, $subType) = explode('/', $mimeType, 2);

            // Obtener la descripción amigable o usar el subtipo por defecto
            $formatDescription = $mimeDescriptions[$subType] ?? ucfirst($subType);

            if (isset($parentMap[$file->getId()])) {
                $parentId = $parentMap[$file->getId()][0];
                $relativePath = $getPathFunc($file->getId(), $parentMap, $folderMap, $reportRootFolderId);

                // Si getPathFunc devuelve null, el archivo no está en el árbol de nuestra carpeta raíz. Lo ignoramos.
                if ($relativePath === null) {
                    continue;
                }

                // Construimos la ruta final a mostrar en el Excel
                $fullPath = $rootFolderName . ($relativePath ? '/' . $relativePath : '');
            } else {
                continue; // El archivo no tiene padre, no puede estar en nuestra carpeta.
            }
            $sheet->setCellValue('A' . $rowIndex, $file->getName());
            $sheet->setCellValue('B' . $rowIndex, $fullPath);
            $sheet->setCellValue('C' . $rowIndex, $file->getId());
            $sheet->setCellValue('D' . $rowIndex, ucfirst($mainType));
            $sheet->setCellValue('E' . $rowIndex, $formatDescription);
            $rowIndex++;
        }

        foreach (range('A', 'E') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // --- 5. ENVIAR EL ARCHIVO AL NAVEGADOR PARA DESCARGA ---
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'reporte_rutas_drive_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');
        $writer->save('php://output');

        // Cerramos la conexión y terminamos el script para asegurar que solo se envíe el archivo.
        $conn->close();
        exit;

    } catch (Exception $e) {
        // Si algo sale mal, mostramos un error claro.
        die("Error al generar el reporte: " . $e->getMessage());
    }
}


// Manejo de acciones CRUD
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $rol_id = (int)$_POST['rol_id'];
    $estado_id = (int)$_POST['estado'];
    $cargo_id = (int)$_POST['cargo_id'];

    if (isset($_GET['action']) && $_GET['action'] == 'save') {
        // La contraseña es obligatoria al crear
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol_id, estado_id, cargo_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiis", $nombre, $email, $password, $rol_id, $estado_id, $cargo_id);
        $stmt->execute();
        $stmt->close();
        header("Location: dashboard.php");
        exit();
    } elseif (isset($_GET['action']) && $_GET['action'] == 'update') {
        if (!empty($_POST['password'])) {
            // Si se proporciona una nueva contraseña, la actualizamos
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, email=?, rol_id=?, estado_id=?, cargo_id=?, password_hash=? WHERE id=?");
            $stmt->bind_param("ssiiisi", $nombre, $email, $rol_id, $estado_id, $cargo_id, $password, $id);
        } else {
            // Si no, actualizamos todo excepto la contraseña
            $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, email=?, rol_id=?, estado_id=?, cargo_id=? WHERE id=?");
            $stmt->bind_param("ssiiii", $nombre, $email, $rol_id, $estado_id, $cargo_id, $id);
        }
        $stmt->execute();
        $stmt->close();
        header("Location: dashboard.php");
        exit();
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    // También usar consultas preparadas para eliminar
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrador - Usuarios</title>
    <!-- Tailwind CSS -->
    <style>
        /* --- Modal de Advertencia de Inactividad --- */
        .inactivity-modal {
            display: none; /* Oculto por defecto */
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
        }
        .inactivity-modal-content {
            background-color: #fff;
            padding: 25px 30px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,.5);
            max-width: 400px;
        }
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans text-gray-800">
    <nav class="bg-green-600 shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div>
                <a class="text-white text-xl font-bold flex items-center" href="#">
                    <i class="fas fa-leaf mr-2"></i> Dashboard Administrador
                </a>
            </div>
            <div class="text-white text-sm">
                <span>Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></strong> (<?php echo htmlspecialchars($_SESSION['usuario_rol_nombre'] ?? 'Administrador'); ?>)</span>
            </div>
        </div>
    </nav>

    <!-- Modal de Advertencia de Inactividad -->
    <div id="inactivity-warning-modal" class="inactivity-modal">
        <div class="inactivity-modal-content text-gray-800">
            <h2 class="text-2xl font-bold mb-2">¡Tu sesión está a punto de expirar!</h2>
            <p>Por seguridad, tu sesión se cerrará automáticamente por inactividad.</p>
            <p>La sesión se cerrará en <strong id="countdown-timer" class="text-red-600">10</strong> segundos.</p>
            <p class="mt-4 text-sm text-gray-600">Mueve el mouse o presiona cualquier tecla para continuar.</p>
        </div>
    </div>


    <div class="container mx-auto mt-6 p-6 bg-white rounded-lg shadow-xl">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-2xl font-semibold text-green-600"><i class="fas fa-users mr-2"></i> Gestión de Usuarios</h4>
            <a href="?action=add" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition"><i class="fas fa-plus mr-2"></i> Agregar Usuario</a>
        </div>
        <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
            <a href="?action=generate_report" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition inline-flex items-center"><i class="fas fa-file-excel mr-2"></i> Generar Reporte Drive</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full bg-white rounded-lg shadow">
                <thead class="bg-green-500 text-white">
                    <tr>
                        <th class="py-3 px-4 text-left">ID</th>
                        <th class="py-3 px-4 text-left">Nombre</th>
                        <th class="py-3 px-4 text-left">Email</th>
                        <th class="py-3 px-4 text-left">Cargo</th>
                        <th class="py-3 px-4 text-left">Rol</th>
                        <th class="py-3 px-4 text-left">Estado</th>
                        <th class="py-3 px-4 text-left">Creado En</th>
                        <th class="py-3 px-4 text-left">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT u.id, u.nombre, u.email, c.nombre AS cargo, r.nombre AS rol, u.estado_id, u.creado_en 
                                            FROM usuarios u 
                                            LEFT JOIN cargos c ON u.cargo_id = c.id 
                                            LEFT JOIN roles r ON u.rol_id = r.id");
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr class='hover:bg-gray-50'>
                                <td class='py-3 px-4'>{$row['id']}</td>
                                <td class='py-3 px-4'>{$row['nombre']}</td>
                                <td class='py-3 px-4'>{$row['email']}</td>
                                <td class='py-3 px-4'>" . ($row['cargo'] ?? 'Sin cargo') . "</td>
                                <td class='py-3 px-4'>{$row['rol']}</td>";
                        
                        // Pequeña corrección para mostrar el nombre del estado en lugar del ID
                        $estado_texto = $row['estado_id'] == 1 ? 'Activo' : 'Inactivo'; // Simplificado, idealmente se consultaría la tabla estados_usuario

                        echo "  <td class='py-3 px-4'>" . $estado_texto . "</td>
                                <td class='py-3 px-4'>{$row['creado_en']}</td>
                                <td class='py-3 px-4'>
                                    <a href='?action=edit&id={$row['id']}' class='text-yellow-500 hover:text-yellow-600 mr-2'><i class='fas fa-edit'></i></a>
                                    <a href='?action=delete&id={$row['id']}' class='text-red-500 hover:text-red-600' onclick='return confirm(\"¿Estás seguro de eliminar este usuario?\")'><i class='fas fa-trash'></i></a>
                                </td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal para Agregar/Editar -->
    <?php
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $user = ['id' => '', 'nombre' => '', 'email' => '', 'rol_id' => '', 'estado_id' => 1, 'cargo_id' => ''];
    if ($action == 'edit' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $result = $conn->query("SELECT * FROM usuarios WHERE id = $id");
        $user = $result->fetch_assoc();
    }
    ?>
    <div class="modal fade fixed top-0 left-0 hidden w-full h-full bg-black bg-opacity-50" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog max-w-lg mx-auto mt-20">
            <div class="bg-white rounded-lg shadow-xl">
                <div class="p-4 border-b">
                    <h5 class="text-xl font-semibold text-green-600" id="userModalLabel"><i class="fas fa-leaf mr-2"></i> <?php echo $action == 'edit' ? 'Editar' : 'Agregar'; ?> Usuario</h5>
                    <button type="button" class="text-gray-500 hover:text-gray-700 float-right" data-bs-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="p-4">
                    <form method="POST" action="?action=<?php echo $action == 'edit' ? 'update&id=' . $user['id'] : 'save'; ?>">
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                        <div class="mb-4">
                            <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
                            <input type="text" class="w-full p-2 border rounded-lg" id="nombre" name="nombre" value="<?php echo htmlspecialchars($user['nombre']); ?>" required>
                        </div>
                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" class="w-full p-2 border rounded-lg" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                            <input type="password" class="w-full p-2 border rounded-lg" id="password" name="password" <?php echo $action == 'edit' ? '' : 'required'; ?> placeholder="Ingresa nueva contraseña si deseas cambiarla">
                        </div>
                        <div class="mb-4">
                            <label for="cargo_id" class="block text-sm font-medium text-gray-700">Cargo</label>
                            <select class="w-full p-2 border rounded-lg" id="cargo_id" name="cargo_id" required>
                                <?php
                                $cargos = $conn->query("SELECT id, nombre FROM cargos");
                                while ($cargo = $cargos->fetch_assoc()) {
                                    $selected = $cargo['id'] == $user['cargo_id'] ? 'selected' : '';
                                    echo "<option value='{$cargo['id']}' $selected>{$cargo['nombre']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="rol_id" class="block text-sm font-medium text-gray-700">Rol</label>
                            <select class="w-full p-2 border rounded-lg" id="rol_id" name="rol_id" required>
                                <?php
                                $roles = $conn->query("SELECT id, nombre FROM roles");
                                while ($rol = $roles->fetch_assoc()) {
                                    $selected = $rol['id'] == $user['rol_id'] ? 'selected' : '';
                                    echo "<option value='{$rol['id']}' $selected>{$rol['nombre']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="estado" class="block text-sm font-medium text-gray-700">Estado</label>
                            <select class="w-full p-2 border rounded-lg" id="estado" name="estado" required>
                                <?php
                                $estados = $conn->query("SELECT id, estado FROM estados_usuario");
                                while ($estado = $estados->fetch_assoc()) {
                                    $selected = $estado['id'] == (isset($user['estado_id']) ? $user['estado_id'] : 1) ? 'selected' : '';
                                    echo "<option value='{$estado['id']}' $selected>" . ucfirst($estado['estado']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition">Guardar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="container mx-auto mt-4">
        <a href="../login/logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition">
            <i class="fas fa-sign-out-alt mr-2"></i> Cerrar sesión
        </a>
        <a href="../index.php" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition ml-4">
            <i class="fas fa-folder-open mr-2"></i> Ir al Visor de Archivos
        </a>
    </div>

    <!-- Bootstrap JS for Modal -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../inactivity-timer.js"></script>
    <script>
        <?php if ($action == 'add' || $action == 'edit') { ?>
            var modal = new bootstrap.Modal(document.getElementById('userModal'));
            modal.show();
        <?php } ?>

        // Inicializa el temporizador de inactividad
        document.addEventListener('DOMContentLoaded', function() {
            const inactivityTimer = new InactivityTimer({
                logoutUrl: '../login/logout.php',
                warningModalId: 'inactivity-warning-modal',
                countdownSpanId: 'countdown-timer'
            });
        });
    </script>

    <?php $conn->close(); ?>
</body>
</html>