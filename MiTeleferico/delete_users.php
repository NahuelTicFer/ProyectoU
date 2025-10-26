<?php
// delete_users.php - Página para eliminar lógicamente usuarios (solo accesible por administradores)

// Inicio de sesión y verificación de autenticación
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['idRol'] != 1) {
    header("Location: login.php");
    exit;
}

// Inclusión de conexión a la base de datos
include 'Conn.php';

// Obtener usuarios activos (Activo = 1)
$usuarios = [];
$sql = "SELECT u.IDUsuario, u.Nombre, u.ApellidoPaterno, u.ApellidoMaterno, u.Correo, r.NombreRol 
        FROM Usuarios u 
        INNER JOIN Roles r ON u.IDRol = r.IDRol 
        WHERE u.Activo = 1 
        ORDER BY u.Nombre ASC";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $usuarios[] = $row;
    }
}

// Manejo de eliminación lógica
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
    $idUsuarioDelete = $_POST['id'];
    $stmt = $conn->prepare("UPDATE Usuarios SET Activo = 0, FechaModificacion = CURRENT_TIMESTAMP WHERE IDUsuario = ?");
    $stmt->bind_param("i", $idUsuarioDelete);
    if ($stmt->execute()) {
        $success = "Usuario eliminado lógicamente exitosamente.";
        // Log de actividad
        $idUsuarioLog = $_SESSION['idUsuario'];
        $accion = "Eliminó lógicamente el usuario con ID: " . $idUsuarioDelete;
        $logStmt = $conn->prepare("INSERT INTO LogActividades (IDUsuario, Accion) VALUES (?, ?)");
        $logStmt->bind_param("is", $idUsuarioLog, $accion);
        $logStmt->execute();
        $logStmt->close();
    } else {
        $error = "Error al eliminar el usuario: " . $stmt->error;
    }
    $stmt->close();
    // Recargar usuarios después de la eliminación
    $result = $conn->query($sql);
    $usuarios = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'no') {
    // No hacer nada, simplemente recargar la página
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Usuarios - Sistema de Gestión de Archivos MI TELEFÉRICO</title>
    <!-- Estilos CSS -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: url('IMG/fondo2.png') no-repeat center center fixed;
            background-size: cover;
            color: #333;
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8); /* Overlay claro para legibilidad sobre fondo */
            z-index: 1;
        }

        .dashboard-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: linear-gradient(135deg, #001f3f, #00bfff); /* Gradiente navy a azul celeste */
            color: #fff;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .logo {
            width: 120px;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        nav {
            display: flex;
            align-items: center;
        }

        .menu {
            list-style: none;
            display: flex;
            margin: 0;
            padding: 0;
        }

        .menu li {
            position: relative;
            margin-left: 20px;
        }

        .menu a, .menu .dropdown-btn {
            color: #fff;
            text-decoration: none;
            font-size: 16px;
            padding: 10px 15px;
            border-radius: 5px;
            transition: background 0.3s, transform 0.3s;
            display: block;
        }

        .menu .dropdown-btn {
            cursor: pointer;
            background: none;
            border: none;
            font: inherit;
        }

        .menu a:hover, .menu .dropdown-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background: #001f3f; /* Navy oscuro */
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            z-index: 3;
            border-radius: 5px;
            overflow: hidden;
        }

        .dropdown-content a {
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            color: #fff;
            transition: background 0.3s;
        }

        .dropdown-content a:hover {
            background: #00bfff; /* Azul celeste */
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        main {
            background: #fff;
            padding: 40px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        main h1 {
            font-size: 32px;
            color: #001f3f;
            margin-bottom: 20px;
        }

        main p {
            font-size: 18px;
            color: #555;
            margin-bottom: 30px;
        }

        .search-input {
            padding: 12px;
            width: 100%;
            max-width: 400px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 auto;
        }

        .users-table th, .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .users-table th {
            background: #f4f4f4;
            font-weight: bold;
        }

        .delete-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #e74c3c;
            transition: color 0.3s;
        }

        .delete-btn:hover {
            color: #c0392b;
        }

        .success-message {
            color: #32ff6a;
            margin-bottom: 20px;
            font-size: 14px;
            background: rgba(50, 255, 106, 0.1);
            padding: 10px;
            border-radius: 8px;
        }

        .error-message {
            color: #ff6b6b;
            margin-bottom: 20px;
            font-size: 14px;
            background: rgba(255, 107, 107, 0.1);
            padding: 10px;
            border-radius: 8px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .modal-content h2 {
            color: #001f3f;
            margin-bottom: 20px;
        }

        .modal-content p {
            margin-bottom: 20px;
        }

        .modal-content button {
            padding: 10px 20px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .modal-content .confirm-btn {
            background: #2ecc71;
            color: #fff;
        }

        .modal-content .confirm-btn:hover {
            background: #27ae60;
        }

        .modal-content .cancel-btn {
            background: #ff6b6b;
            color: #fff;
        }

        .modal-content .cancel-btn:hover {
            background: #e74c3c;
        }

        .logout-btn {
            background: #ff6b6b;
            color: #fff;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            margin-left: 20px;
        }

        .logout-btn:hover {
            background: #e74c3c;
        }

        /* Responsividad para dispositivos móviles */
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                align-items: flex-start;
            }

            nav {
                width: 100%;
                margin-top: 10px;
            }

            .menu {
                flex-direction: column;
                width: 100%;
            }

            .menu li {
                margin-left: 0;
                margin-bottom: 10px;
                width: 100%;
            }

            .menu a, .menu .dropdown-btn {
                text-align: left;
                width: 100%;
            }

            .dropdown-content {
                position: static;
                width: 100%;
            }

            .logout-btn {
                width: 100%;
                margin-left: 0;
                margin-top: 10px;
            }

            main {
                padding: 20px;
            }

            main h1 {
                font-size: 28px;
            }

            main p {
                font-size: 16px;
            }

            .users-table {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .logo {
                width: 100px;
            }

            main h1 {
                font-size: 24px;
            }

            main p {
                font-size: 14px;
            }

            .search-input {
                font-size: 14px;
            }

            .modal-content {
                padding: 15px;
            }

            .modal-content button {
                width: 100%;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header>
            <img src="IMG/logo.png" alt="Mi Teleférico Logo" class="logo">
            <nav>
                <ul class="menu">
                    <li class="dropdown">
                        <button class="dropdown-btn">Usuarios ▼</button>
                        <div class="dropdown-content">
                            <a href="CreateUser.php">Crear Usuario</a>
                            <a href="edit_users.php">Editar Usuarios</a>
                            <a href="delete_users.php">Eliminar Usuarios</a>
                            <a href="view_users.php">Ver Usuarios</a>
                        </div>
                    </li>
                    <li class="dropdown">
                        <button class="dropdown-btn">Documentos ▼</button>
                        <div class="dropdown-content">
                            <a href="upload_documents.php">Subir Documentos</a>
                            <a href="edit_documents.php">Editar Documentos</a>
                            <a href="delete_documents.php">Eliminar Documentos</a>
                            <a href="view_documents.php">Ver Documentos</a>
                            <a href="manage_versions.php">Gestionar Versiones</a>
                        </div>
                    </li>
                    <li class="dropdown">
                        <button class="dropdown-btn">Reportes ▼</button>
                        <div class="dropdown-content">
                            <a href="generate_reports.php">Generar Reportes</a>
                            <a href="view_reports.php">Ver Reportes Generados</a>
                            <a href="manage_report_types.php">Gestionar Tipos de Reporte</a>
                        </div>
                    </li>
                    <li class="dropdown">
                        <button class="dropdown-btn">Logs y Notificaciones ▼</button>
                        <div class="dropdown-content">
                            <a href="view_logs.php">Ver Acciones de Usuarios</a>
                            <a href="manage_notifications.php">Gestionar Notificaciones</a>
                        </div>
                    </li>
                    <li class="dropdown">
                        <button class="dropdown-btn">Maestros ▼</button>
                        <div class="dropdown-content">
                            <a href="manage_roles.php">Gestionar Roles</a>
                            <a href="manage_doc_types.php">Gestionar Tipos de Documento</a>
                            <a href="manage_lines.php">Gestionar Líneas</a>
                            <a href="manage_stations.php">Gestionar Estaciones</a>
                            <a href="manage_categories.php">Gestionar Categorías</a>
                        </div>
                    </li>
                </ul>
                <form action="logout.php" method="POST">
                    <button type="submit" class="logout-btn">Cerrar Sesión</button>
                </form>
            </nav>
        </header>
        <main>
            <h1>Eliminar Usuarios</h1>
            <p>MI TELEFÉRICO - CRM</p>
            <?php if (isset($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <input type="text" id="searchInput" class="search-input" placeholder="Buscar por nombre o correo...">
            <table class="users-table" id="usersTable">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Apellido Paterno</th>
                        <th>Apellido Materno</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($usuario['Nombre']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['ApellidoPaterno']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['ApellidoMaterno']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['Correo']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['NombreRol']); ?></td>
                            <td>
                                <button class="delete-btn" onclick="showDeleteModal(<?php echo $usuario['IDUsuario']; ?>, '<?php echo addslashes($usuario['Nombre'] . ' ' . $usuario['ApellidoPaterno']); ?>')">🗑️</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Modal para confirmar eliminación -->
            <div id="deleteModal" class="modal">
                <div class="modal-content">
                    <h2>Confirmar Eliminación</h2>
                    <p>¿Está seguro que desea eliminar al usuario <strong id="userName"></strong>?</p>
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <input type="hidden" name="id" id="modalUserId">
                        <input type="hidden" name="confirm_delete" id="modalConfirmDelete">
                        <button type="submit" class="confirm-btn">Sí</button>
                        <button type="button" class="cancel-btn" onclick="closeDeleteModal()">No</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Script JS para filtrado de búsqueda y manejo del modal -->
    <script>
        const searchInput = document.getElementById('searchInput');
        const table = document.getElementById('usersTable');
        const rows = table.getElementsByTagName('tr');

        searchInput.addEventListener('keyup', function() {
            const filter = searchInput.value.toLowerCase();
            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;
                // Buscar en nombre completo o correo (columnas 0,1,2,3)
                for (let j = 0; j < 4; j++) {
                    if (cells[j] && cells[j].textContent.toLowerCase().includes(filter)) {
                        match = true;
                        break;
                    }
                }
                rows[i].style.display = match ? '' : 'none';
            }
        });

        function showDeleteModal(id, name) {
            document.getElementById('modalUserId').value = id;
            document.getElementById('userName').textContent = name;
            document.getElementById('modalConfirmDelete').value = 'yes';
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.getElementById('modalConfirmDelete').value = 'no';
        }
    </script>
</body>
</html>