<?php
// edit_user.php - Página para editar un usuario específico (solo accesible por administradores)

// Inicio de sesión y verificación de autenticación
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['idRol'] != 1) {
    header("Location: login.php");
    exit;
}

// Inclusión de conexión a la base de datos
include 'Conn.php';

// Verificar si se proporcionó un ID de usuario
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: edit_users.php");
    exit;
}

$idUsuarioEdit = $_GET['id'];

// Obtener datos del usuario a editar
$usuario = null;
$sql = "SELECT Nombre, ApellidoPaterno, ApellidoMaterno, Correo, IDRol, Activo FROM Usuarios WHERE IDUsuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idUsuarioEdit);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $usuario = $result->fetch_assoc();
}
$stmt->close();

if (!$usuario) {
    header("Location: edit_users.php");
    exit;
}

// Obtener roles disponibles para el select
$roles = [];
$sql = "SELECT IDRol, NombreRol FROM Roles";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
}

// Manejo del formulario de edición
$changes = [];
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_changes'])) {
    $nombre = $_POST['nombre'];
    $apellidoPaterno = $_POST['apellido_paterno'];
    $apellidoMaterno = $_POST['apellido_materno'];
    $correo = $_POST['correo'];
    $idRol = $_POST['id_rol'];
    $activo = isset($_POST['activo']) ? 1 : 0;
    $contrasena = !empty($_POST['contrasena']) ? password_hash($_POST['contrasena'], PASSWORD_DEFAULT) : null;

    // Detectar cambios
    if ($nombre !== $usuario['Nombre']) $changes[] = "Nombre cambiado de '$usuario[Nombre]' a '$nombre'";
    if ($apellidoPaterno !== $usuario['ApellidoPaterno']) $changes[] = "Apellido Paterno cambiado de '$usuario[ApellidoPaterno]' a '$apellidoPaterno'";
    if ($apellidoMaterno !== $usuario['ApellidoMaterno']) $changes[] = "Apellido Materno cambiado de '$usuario[ApellidoMaterno]' a '$apellidoMaterno'";
    if ($correo !== $usuario['Correo']) $changes[] = "Correo cambiado de '$usuario[Correo]' a '$correo'";
    if ($idRol != $usuario['IDRol']) {
        $nuevoRol = array_search($idRol, array_column($roles, 'IDRol'));
        $nuevoRolNombre = $roles[$nuevoRol]['NombreRol'];
        $rolActual = array_search($usuario['IDRol'], array_column($roles, 'IDRol'));
        $rolActualNombre = $roles[$rolActual]['NombreRol'];
        $changes[] = "Rol cambiado de '$rolActualNombre' a '$nuevoRolNombre'";
    }
    if ($activo != $usuario['Activo']) $changes[] = "Estado cambiado de '" . ($usuario['Activo'] ? 'Activo' : 'Inactivo') . "' a '" . ($activo ? 'Activo' : 'Inactivo') . "'";
    if ($contrasena) $changes[] = "Contraseña actualizada";

    if (!empty($changes) && isset($_POST['confirm_changes']) && $_POST['confirm_changes'] === 'yes') {
        // Aplicar cambios
        if ($contrasena) {
            $sql = "UPDATE Usuarios SET Nombre = ?, ApellidoPaterno = ?, ApellidoMaterno = ?, Correo = ?, Contrasena = ?, IDRol = ?, Activo = ?, FechaModificacion = CURRENT_TIMESTAMP WHERE IDUsuario = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssiii", $nombre, $apellidoPaterno, $apellidoMaterno, $correo, $contrasena, $idRol, $activo, $idUsuarioEdit);
        } else {
            $sql = "UPDATE Usuarios SET Nombre = ?, ApellidoPaterno = ?, ApellidoMaterno = ?, Correo = ?, IDRol = ?, Activo = ?, FechaModificacion = CURRENT_TIMESTAMP WHERE IDUsuario = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssiii", $nombre, $apellidoPaterno, $apellidoMaterno, $correo, $idRol, $activo, $idUsuarioEdit);
        }

        if ($stmt->execute()) {
            $success = "Usuario actualizado exitosamente.";
            
            // Log de actividad
            $idUsuarioLog = $_SESSION['idUsuario'];
            $accion = "Editó el usuario con ID: " . $idUsuarioEdit;
            $logStmt = $conn->prepare("INSERT INTO LogActividades (IDUsuario, Accion) VALUES (?, ?)");
            $logStmt->bind_param("is", $idUsuarioLog, $accion);
            $logStmt->execute();
            $logStmt->close();
        } else {
            $error = "Error al actualizar el usuario: " . $stmt->error;
        }

        $stmt->close();
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_changes']) && $_POST['confirm_changes'] === 'no') {
    header("Location: edit_users.php");
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - Sistema de Gestión de Archivos MI TELEFÉRICO</title>
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

        .edit-user-form {
            display: flex;
            flex-direction: column;
            max-width: 600px;
            margin: 0 auto;
        }

        .edit-user-form label {
            text-align: left;
            margin-bottom: 8px;
            font-weight: bold;
            color: #34495e;
        }

        .edit-user-form input,
        .edit-user-form select {
            padding: 14px;
            margin-bottom: 25px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .edit-user-form input:focus,
        .edit-user-form select:focus {
            border-color: #00bfff;
            box-shadow: 0 0 10px rgba(0, 191, 255, 0.5);
            outline: none;
        }

        .edit-user-form .checkbox-label {
            display: flex;
            align-items: center;
            font-weight: normal;
            margin-bottom: 25px;
        }

        .edit-user-form input[type="checkbox"] {
            margin-right: 10px;
        }

        .edit-user-form button {
            padding: 14px;
            background: linear-gradient(135deg, #00bfff, #1e90ff);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s, transform 0.3s;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
        }

        .edit-user-form button:hover {
            background: linear-gradient(135deg, #1e90ff, #00bfff);
            transform: translateY(-2px);
        }

        .back-btn {
            padding: 14px;
            background: #ff6b6b;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s, transform 0.3s;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .back-btn:hover {
            background: #e74c3c;
            transform: translateY(-2px);
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

        .modal-content ul {
            list-style: none;
            text-align: left;
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

            .edit-user-form input,
            .edit-user-form select,
            .edit-user-form button,
            .back-btn {
                font-size: 14px;
                padding: 12px;
            }

            .edit-user-form label {
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
            <h1>Editar Usuario</h1>
            <p>MI TELEFÉRICO - CRM</p>
            <?php if (isset($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <form class="edit-user-form" method="POST" id="editForm">
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($usuario['Nombre']); ?>" required>
                
                <label for="apellido_paterno">Apellido Paterno</label>
                <input type="text" id="apellido_paterno" name="apellido_paterno" value="<?php echo htmlspecialchars($usuario['ApellidoPaterno']); ?>" required>
                
                <label for="apellido_materno">Apellido Materno</label>
                <input type="text" id="apellido_materno" name="apellido_materno" value="<?php echo htmlspecialchars($usuario['ApellidoMaterno']); ?>" required>
                
                <label for="correo">Correo Electrónico</label>
                <input type="email" id="correo" name="correo" value="<?php echo htmlspecialchars($usuario['Correo']); ?>" required>
                
                <label for="contrasena">Contraseña (dejar en blanco para no cambiar)</label>
                <input type="password" id="contrasena" name="contrasena" placeholder="Nueva contraseña (opcional)">
                
                <label for="id_rol">Rol</label>
                <select id="id_rol" name="id_rol" required>
                    <?php foreach ($roles as $rol): ?>
                        <option value="<?php echo $rol['IDRol']; ?>" <?php echo ($rol['IDRol'] == $usuario['IDRol']) ? 'selected' : ''; ?>><?php echo $rol['NombreRol']; ?></option>
                    <?php endforeach; ?>
                </select>
                
                <label class="checkbox-label">
                    <input type="checkbox" name="activo" <?php echo $usuario['Activo'] ? 'checked' : ''; ?>>
                    Activo
                </label>
                
                <button type="button" onclick="showChangesModal()">Guardar Cambios</button>
            </form>
            <button class="back-btn" onclick="confirmBack()">Cancelar y Volver</button>

            <!-- Modal para confirmar cambios -->
            <div id="changesModal" class="modal">
                <div class="modal-content">
                    <h2>Confirmar Cambios</h2>
                    <p>Se realizarán los siguientes cambios:</p>
                    <ul id="changesList">
                        <!-- Se llenará dinámicamente con JS -->
                    </ul>
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $idUsuarioEdit; ?>" id="confirmForm">
                        <input type="hidden" name="nombre" id="modalNombre">
                        <input type="hidden" name="apellido_paterno" id="modalApellidoPaterno">
                        <input type="hidden" name="apellido_materno" id="modalApellidoMaterno">
                        <input type="hidden" name="correo" id="modalCorreo">
                        <input type="hidden" name="contrasena" id="modalContrasena">
                        <input type="hidden" name="id_rol" id="modalIdRol">
                        <input type="hidden" name="activo" id="modalActivo">
                        <input type="hidden" name="confirm_changes" id="modalConfirmChanges">
                        <button type="submit" class="confirm-btn">Confirmar Edición de Datos</button>
                        <button type="button" class="cancel-btn" onclick="closeModal()">Cancelar</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Script JS para manejo del modal y confirmación -->
    <script>
        function confirmBack() {
            if (confirm("¿Cancelar edición?")) {
                window.location.href = "edit_users.php";
            }
        }

        function showChangesModal() {
            const form = document.getElementById('editForm');
            const nombre = form.querySelector('#nombre').value;
            const apellidoPaterno = form.querySelector('#apellido_paterno').value;
            const apellidoMaterno = form.querySelector('#apellido_materno').value;
            const correo = form.querySelector('#correo').value;
            const contrasena = form.querySelector('#contrasena').value;
            const idRol = form.querySelector('#id_rol').value;
            const activo = form.querySelector('input[name="activo"]').checked;

            const originalUsuario = <?php echo json_encode($usuario); ?>;
            const roles = <?php echo json_encode($roles); ?>;
            let changes = [];

            if (nombre !== originalUsuario.Nombre) changes.push(`Nombre cambiado de '${originalUsuario.Nombre}' a '${nombre}'`);
            if (apellidoPaterno !== originalUsuario.ApellidoPaterno) changes.push(`Apellido Paterno cambiado de '${originalUsuario.ApellidoPaterno}' a '${apellidoPaterno}'`);
            if (apellidoMaterno !== originalUsuario.ApellidoMaterno) changes.push(`Apellido Materno cambiado de '${originalUsuario.ApellidoMaterno}' a '${apellidoMaterno}'`);
            if (correo !== originalUsuario.Correo) changes.push(`Correo cambiado de '${originalUsuario.Correo}' a '${correo}'`);
            if (idRol != originalUsuario.IDRol) {
                const nuevoRol = roles.find(r => r.IDRol == idRol).NombreRol;
                const rolActual = roles.find(r => r.IDRol == originalUsuario.IDRol).NombreRol;
                changes.push(`Rol cambiado de '${rolActual}' a '${nuevoRol}'`);
            }
            if (activo !== originalUsuario.Activo) changes.push(`Estado cambiado de '${originalUsuario.Activo ? 'Activo' : 'Inactivo'}' a '${activo ? 'Activo' : 'Inactivo'}'`);
            if (contrasena) changes.push("Contraseña actualizada");

            const changesList = document.getElementById('changesList');
            changesList.innerHTML = changes.length ? changes.map(c => `<li>${c}</li>`).join('') : '<li>No hay cambios para mostrar.</li>';

            if (changes.length) {
                document.getElementById('modalNombre').value = nombre;
                document.getElementById('modalApellidoPaterno').value = apellidoPaterno;
                document.getElementById('modalApellidoMaterno').value = apellidoMaterno;
                document.getElementById('modalCorreo').value = correo;
                document.getElementById('modalContrasena').value = contrasena;
                document.getElementById('modalIdRol').value = idRol;
                document.getElementById('modalActivo').value = activo ? 1 : 0;
                document.getElementById('modalConfirmChanges').value = 'yes';
                document.getElementById('changesModal').style.display = 'flex';
            } else {
                alert('No se detectaron cambios para guardar.');
            }
        }

        function closeModal() {
            document.getElementById('changesModal').style.display = 'none';
            document.getElementById('modalConfirmChanges').value = 'no';
            document.getElementById('confirmForm').submit();
        }
    </script>
</body>
</html>