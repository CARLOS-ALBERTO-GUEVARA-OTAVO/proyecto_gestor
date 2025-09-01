<?php
// Conexión a la base de datos (ajusta según tu configuración)
$conn = new mysqli('localhost', 'root', '123456', 'proyecto_gestion', '3309');
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Manejo de acciones CRUD antes de cualquier salida
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
    $rol_id = (int)$_POST['rol_id'];
    $estado_id = (int)$_POST['estado'];

    if (isset($_GET['action']) && $_GET['action'] == 'save') {
        $sql = "INSERT INTO usuarios (nombre, email, password_hash, rol_id, estado_id) 
                VALUES ('$nombre', '$email', '$password', $rol_id, $estado_id)";
        $conn->query($sql);
        header("Location: dashboard.php");
        exit();
    } elseif (isset($_GET['action']) && $_GET['action'] == 'update') {
        $sql = "UPDATE usuarios SET nombre='$nombre', email='$email', rol_id=$rol_id, estado_id=$estado_id";
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
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f7f4;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        .navbar {
            background-color: #4CAF50;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .navbar-brand {
            color: #fff !important;
            font-weight: bold;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }
        .table {
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
        }
        .table thead {
            background-color: #8BC34A;
            color: #fff;
        }
        .btn-primary {
            background-color: #4CAF50;
            border: none;
        }
        .btn-primary:hover {
            background-color: #388E3C;
        }
        .btn-warning {
            background-color: #FFC107;
        }
        .btn-danger {
            background-color: #f44336;
        }
        .icon-leaf {
            color: #4CAF50;
            margin-right: 5px;
        }
        .container {
            background: url('https://via.placeholder.com/1920x1080?text=Nature+Background') no-repeat center center;
            background-size: cover;
            padding: 20px;
            border-radius: 10px;
        }
        .modal-content {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="fas fa-leaf icon-leaf"></i> Dashboard Administrador Natural</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4><i class="fas fa-users icon-leaf"></i> Gestión de Usuarios</h4>
                <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> Agregar Usuario</a>
            </div>
            <div class="card-body">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Creado En</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Conexión a la base de datos (ajusta según tu configuración)
                        $conn = new mysqli('localhost', 'root', '123456', 'proyecto_gestion', '3309');
                        if ($conn->connect_error) {
                            die("Conexión fallida: " . $conn->connect_error);
                        }

                        // Obtener usuarios
                        $result = $conn->query("SELECT u.id, u.nombre, u.email, r.nombre AS rol, u.estado_id, u.creado_en 
                                                FROM usuarios u 
                                                LEFT JOIN roles r ON u.rol_id = r.id");
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>
                                    <td>{$row['id']}</td>
                                    <td>{$row['nombre']}</td>
                                    <td>{$row['email']}</td>
                                    <td>{$row['rol']}</td>
                                    <td>" . ucfirst($row['estado_id']) . "</td>
                                    <td>{$row['creado_en']}</td>
                                    <td>
                                        <a href='?action=edit&id={$row['id']}' class='btn btn-warning btn-sm'><i class='fas fa-edit'></i></a>
                                        <a href='?action=delete&id={$row['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"¿Estás seguro de eliminar este usuario?\")'><i class='fas fa-trash'></i></a>
                                    </td>
                                  </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para Agregar/Editar -->
    <?php
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $user = ['id' => '', 'nombre' => '', 'email' => '', 'rol_id' => '', 'estado_id' => 1];
    if ($action == 'edit' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $result = $conn->query("SELECT * FROM usuarios WHERE id = $id");
        $user = $result->fetch_assoc();
    }
    ?>
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel"><i class="fas fa-leaf icon-leaf"></i> <?php echo $action == 'edit' ? 'Editar' : 'Agregar'; ?> Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="?action=<?php echo $action == 'edit' ? 'update&id=' . $user['id'] : 'save'; ?>">
                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($user['nombre']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña (hash)</label>
                            <input type="password" class="form-control" id="password" name="password" <?php echo $action == 'edit' ? '' : 'required'; ?> placeholder="Ingresa nueva contraseña si deseas cambiarla">
                        </div>
                        <div class="mb-3">
                            <label for="rol_id" class="form-label">Rol</label>
                            <select class="form-select" id="rol_id" name="rol_id" required>
                                <?php
                                $roles = $conn->query("SELECT id, nombre FROM roles");
                                while ($rol = $roles->fetch_assoc()) {
                                    $selected = $rol['id'] == $user['rol_id'] ? 'selected' : '';
                                    echo "<option value='{$rol['id']}' $selected>{$rol['nombre']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <?php
                                $estados = $conn->query("SELECT id, estado FROM estados_usuario");
                                while ($estado = $estados->fetch_assoc()) {
                                    $selected = $estado['id'] == (isset($user['estado_id']) ? $user['estado_id'] : 1) ? 'selected' : '';
                                    echo "<option value='{$estado['id']}' $selected>" . ucfirst($estado['estado']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($action == 'add' || $action == 'edit') { ?>
            var modal = new bootstrap.Modal(document.getElementById('userModal'));
            modal.show();
        <?php } ?>
    </script>

    <?php
    // Manejo de acciones CRUD
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nombre = $conn->real_escape_string($_POST['nombre']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
        $rol_id = (int)$_POST['rol_id'];
    $estado_id = (int)$_POST['estado'];

        if (isset($_GET['action']) && $_GET['action'] == 'save') {
            $sql = "INSERT INTO usuarios (nombre, email, password_hash, rol_id, estado_id) 
                    VALUES ('$nombre', '$email', '$password', $rol_id, $estado_id)";
            $conn->query($sql);
            header("Location: dashboard.php");
        } elseif (isset($_GET['action']) && $_GET['action'] == 'update') {
            $sql = "UPDATE usuarios SET nombre='$nombre', email='$email', rol_id=$rol_id, estado_id=$estado_id";
            if ($password) {
                $sql .= ", password_hash='$password'";
            }
            $sql .= " WHERE id=$id";
            $conn->query($sql);
            header("Location: dashboard.php");
        }
    }

    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $conn->query("DELETE FROM usuarios WHERE id=$id");
        header("Location: dashboard.php");
    }

    $conn->close();
    ?>
</body>
</html>