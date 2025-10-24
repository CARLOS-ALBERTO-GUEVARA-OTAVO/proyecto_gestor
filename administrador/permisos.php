<?php
session_start();

// 1. Verificar si el usuario es Administrador.
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol_id']) || $_SESSION['usuario_rol_id'] != 1) {
    header('Location: ../index.php');
    exit;
}

require '../vendor/autoload.php';

// Conexión a la base de datos
$conn = new mysqli('localhost', 'root', '', 'proyecto_gestion', '3306');
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

$google_error = null;
$service = null;

try {
    // --- AUTENTICACIÓN CON GOOGLE DRIVE (necesaria para obtener nombres de carpetas) ---
    $credentialsFile = __DIR__ . '/../flotax-map-3949a96314d9.json';
    $client = new \Google\Client();
    $client->setAuthConfig($credentialsFile);
    $client->addScope(\Google\Service\Drive::DRIVE_READONLY);
    $token = $client->fetchAccessTokenWithAssertion();
    if (isset($token['error'])) {
        throw new Exception('Error de autenticación con Google: ' . $token['error_description']);
    }
    $service = new \Google\Service\Drive($client);
} catch (Exception $e) {
    $google_error = "No se pudo conectar con Google Drive para obtener los nombres de las carpetas. Se mostrarán solo los IDs. Error: " . $e->getMessage();
}

// --- MANEJO DE ACCIONES POST ---
$feedback = ['message' => '', 'type' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Acción para agregar un nuevo permiso
    if (isset($_POST['action']) && $_POST['action'] == 'add_permission') {
        $cargo_id = (int)$_POST['cargo_id'];
        $folder_id = trim($_POST['folder_id']);

        if (!empty($cargo_id) && !empty($folder_id)) {
            // Verificamos si el permiso ya existe para no duplicarlo
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM rol_permisos_carpetas WHERE cargo_id = ? AND folder_id = ?");
            $checkStmt->bind_param("is", $cargo_id, $folder_id);
            $checkStmt->execute();
            $checkStmt->bind_result($count);
            $checkStmt->fetch();
            $checkStmt->close();

            if ($count == 0) {
                $stmt = $conn->prepare("INSERT INTO rol_permisos_carpetas (cargo_id, folder_id) VALUES (?, ?)");
                $stmt->bind_param("is", $cargo_id, $folder_id);
                if ($stmt->execute()) {
                    $feedback = ['message' => 'Permiso agregado correctamente.', 'type' => 'success'];
                } else {
                    $feedback = ['message' => 'Error al agregar el permiso: ' . $stmt->error, 'type' => 'error'];
                }
                $stmt->close();
            } else {
                $feedback = ['message' => 'Este permiso ya existe para el cargo seleccionado.', 'type' => 'error'];
            }
        } else {
            $feedback = ['message' => 'Por favor, selecciona un cargo y proporciona un ID de carpeta.', 'type' => 'error'];
        }
    }

    // Acción para eliminar un permiso
    if (isset($_POST['action']) && $_POST['action'] == 'delete_permission') {
        $cargo_id = (int)$_POST['cargo_id'];
        $folder_id = trim($_POST['folder_id']);

        if (!empty($cargo_id) && !empty($folder_id)) {
            $stmt = $conn->prepare("DELETE FROM rol_permisos_carpetas WHERE cargo_id = ? AND folder_id = ?");
            $stmt->bind_param("is", $cargo_id, $folder_id);
            if ($stmt->execute()) {
                $feedback = ['message' => 'Permiso eliminado correctamente.', 'type' => 'success'];
            } else {
                $feedback = ['message' => 'Error al eliminar el permiso: ' . $stmt->error, 'type' => 'error'];
            }
            $stmt->close();
        }
    }
}

/**
 * Obtiene el nombre de una carpeta de Google Drive usando su ID.
 * Utiliza una caché simple para evitar llamadas repetidas a la API.
 */
function getFolderName($service, $folderId, &$cache) {
    if (isset($cache[$folderId])) {
        return $cache[$folderId];
    }
    if (!$service) {
        return '[Error de conexión con Drive]';
    }
    try {
        $folder = $service->files->get($folderId, ['fields' => 'name']);
        $cache[$folderId] = $folder->getName();
        return $cache[$folderId];
    } catch (Exception $e) {
        return '[Carpeta no encontrada o sin acceso]';
    }
}
$folderNameCache = [];

// Obtenemos todos los cargos y sus permisos
$cargos_con_permisos = [];
$result_cargos = $conn->query("SELECT id, nombre FROM cargos ORDER BY nombre ASC");
while ($cargo = $result_cargos->fetch_assoc()) {
    $cargos_con_permisos[$cargo['id']] = [
        'nombre' => $cargo['nombre'],
        'permisos' => []
    ];
}

$result_permisos = $conn->query("SELECT cargo_id, folder_id FROM rol_permisos_carpetas");
while ($permiso = $result_permisos->fetch_assoc()) {
    if (isset($cargos_con_permisos[$permiso['cargo_id']])) {
        $cargos_con_permisos[$permiso['cargo_id']]['permisos'][] = $permiso['folder_id'];
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gestión de Permisos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans text-gray-800">
    <nav class="bg-green-600 shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div>
                <a class="text-white text-xl font-bold flex items-center" href="dashboard.php">
                    <i class="fas fa-leaf mr-2"></i> Dashboard Administrador
                </a>
            </div>
            <div class="text-white text-sm">
                <span>Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></strong></span>
            </div>
        </div>
    </nav>

    <div class="container mx-auto mt-6 p-6 bg-white rounded-lg shadow-xl">
        <div class="flex justify-between items-center mb-6">
            <h4 class="text-2xl font-semibold text-green-600"><i class="fas fa-key mr-2"></i> Gestión de Permisos por Cargo</h4>
            <a href="dashboard.php" class="text-blue-500 hover:text-blue-700 transition"><i class="fas fa-arrow-left mr-2"></i> Volver a Usuarios</a>
        </div>

        <?php if ($google_error): ?>
            <div class="mb-4 p-4 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700">
                <p><strong>Advertencia:</strong> <?php echo htmlspecialchars($google_error); ?></p>
            </div>
        <?php endif; ?>

        <!-- Formulario para agregar nuevos permisos -->
        <div class="mb-8 p-6 bg-gray-50 border border-gray-200 rounded-lg">
            <h5 class="text-xl font-semibold mb-4 text-gray-700">Asignar Nueva Carpeta</h5>
            <?php if (!empty($feedback['message'])): ?>
                <div class="mb-4 p-3 rounded-lg <?php echo $feedback['type'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo htmlspecialchars($feedback['message']); ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="permisos.php" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <input type="hidden" name="action" value="add_permission">
                <div>
                    <label for="cargo_id" class="block text-sm font-medium text-gray-700">Cargo</label>
                    <select name="cargo_id" id="cargo_id" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500" required>
                        <option value="">Selecciona un cargo...</option>
                        <?php foreach ($cargos_con_permisos as $id => $cargo): ?>
                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($cargo['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="folder_id" class="block text-sm font-medium text-gray-700">ID de la Carpeta de Google Drive</label>
                    <input type="text" name="folder_id" id="folder_id" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500" placeholder="Ej: 1w1X74_EI9LDVhkTrrgA89etnvofGhYSN" required>
                </div>
                <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition w-full md:w-auto"><i class="fas fa-plus mr-2"></i> Asignar Permiso</button>
            </form>
        </div>

        <!-- Lista de cargos y sus permisos -->
        <div class="space-y-6">
            <?php foreach ($cargos_con_permisos as $id_cargo => $cargo): ?>
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="p-4 bg-gray-50 border-b border-gray-200">
                        <h6 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($cargo['nombre']); ?></h6>
                    </div>
                    <div class="p-4">
                        <?php if (empty($cargo['permisos'])): ?>
                            <p class="text-gray-500 italic">Este cargo no tiene carpetas asignadas.</p>
                        <?php else: ?>
                            <ul class="space-y-2">
                                <?php foreach ($cargo['permisos'] as $folder_id): ?>
                                    <li class="flex justify-between items-center p-2 rounded-md hover:bg-gray-50">
                                        <div>
                                            <span class="font-mono text-sm text-blue-600"><?php echo htmlspecialchars($folder_id); ?></span>
                                            <span class="text-gray-600 ml-2">(<?php echo htmlspecialchars(getFolderName($service, $folder_id, $folderNameCache)); ?>)</span>
                                        </div>
                                        <form method="POST" action="permisos.php" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este permiso?');">
                                            <input type="hidden" name="action" value="delete_permission">
                                            <input type="hidden" name="cargo_id" value="<?php echo $id_cargo; ?>">
                                            <input type="hidden" name="folder_id" value="<?php echo htmlspecialchars($folder_id); ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700 text-sm">
                                                <i class="fas fa-trash-alt mr-1"></i> Quitar
                                            </button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php $conn->close(); ?>
</body>
</html>
