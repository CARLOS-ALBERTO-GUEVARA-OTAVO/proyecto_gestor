<?php
// Conexión a la base de datos
$conn = new mysqli('localhost', 'root', '123456', 'proyecto_gestion', '3309');
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Manejo de acciones CRUD
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
    $rol_id = (int)$_POST['rol_id'];
    $estado_id = (int)$_POST['estado'];
    $cargo_id = (int)$_POST['cargo_id'];

    if (isset($_GET['action']) && $_GET['action'] == 'save') {
        $sql = "INSERT INTO usuarios (nombre, email, password_hash, rol_id, estado_id, cargo_id) 
                VALUES ('$nombre', '$email', '$password', $rol_id, $estado_id, $cargo_id)";
        $conn->query($sql);
        header("Location: dashboard.php");
        exit();
    } elseif (isset($_GET['action']) && $_GET['action'] == 'update') {
        $sql = "UPDATE usuarios SET nombre='$nombre', email='$email', rol_id=$rol_id, estado_id=$estado_id, cargo_id=$cargo_id";
        if ($password) {
            $sql .= ", password_hash='$password'";
        }
        $sql .= " WHERE id=$id";
        $conn->query($sql);
        header("Location: dashboard.php");
        exit();
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $conn->query("DELETE FROM usuarios WHERE id=$id");
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
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans text-gray-800">
    <nav class="bg-green-600 shadow-lg">
        <div class="container mx-auto px-4 py-3">
            <a class="text-white text-xl font-bold flex items-center" href="#">
                <i class="fas fa-leaf mr-2"></i> Dashboard Administrador Natural
            </a>
        </div>
    </nav>

    <div class="container mx-auto mt-6 p-6 bg-white rounded-lg shadow-xl">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-2xl font-semibold text-green-600"><i class="fas fa-users mr-2"></i> Gestión de Usuarios</h4>
            <a href="?action=add" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition"><i class="fas fa-plus mr-2"></i> Agregar Usuario</a>
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
                                <td class='py-3 px-4'>{$row['rol']}</td>
                                <td class='py-3 px-4'>" . ucfirst($row['estado_id']) . "</td>
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
    </div>

    <!-- Bootstrap JS for Modal -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($action == 'add' || $action == 'edit') { ?>
            var modal = new bootstrap.Modal(document.getElementById('userModal'));
            modal.show();
        <?php } ?>
    </script>

    <?php $conn->close(); ?>
</body>
</html>