<?php
// edit_document.php - Página para editar un documento específico (solo accesible por administradores)

// Inicio de sesión y verificación de autenticación
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['idRol'] != 1) {
    header("Location: login.php");
    exit;
}

// Inclusión de conexión a la base de datos
include 'Conn.php';

// Obtener datos del documento a editar
$idDocumento = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$documento = [];
$lineas = [];
$estaciones = [];
$tiposDocumento = [];
$categorias = [];

if ($idDocumento > 0) {
    $stmt = $conn->prepare("SELECT d.IDDocumento, d.Codigo, d.Nombre_Documento, d.IDTipoDocumento, d.IDLinea, d.IDEstacion, d.IDCategoria 
                           FROM Documentos d 
                           WHERE d.IDDocumento = ? AND d.Activo = TRUE");
    $stmt->bind_param("i", $idDocumento);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $documento = $result->fetch_assoc();
    }
    $stmt->close();

    // Obtener datos para los selects
    $result = $conn->query("SELECT IDLinea, NombreLinea FROM Linea");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $lineas[$row['IDLinea']] = $row['NombreLinea'];
        }
    }

    $result = $conn->query("SELECT IDEstacion, NombreEstacion, IDLinea FROM Estacion");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $estaciones[$row['IDLinea']][$row['IDEstacion']] = $row['NombreEstacion'];
        }
    }

    $result = $conn->query("SELECT IDTipoDocumento, NombreTipo FROM TipoDocumento");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $tiposDocumento[$row['IDTipoDocumento']] = $row['NombreTipo'];
        }
    }

    $result = $conn->query("SELECT IDCategoria, NombreCategoria FROM Categoria");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categorias[$row['IDCategoria']] = $row['NombreCategoria'];
        }
    }
}

// Manejo de la actualización del documento
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_edit'])) {
    $codigo = $_POST['codigo'];
    $nombre = $_POST['nombre'];
    $idTipoDocumento = $_POST['id_tipo_documento'];
    $idLinea = $_POST['id_linea'];
    $idEstacion = $_POST['id_estacion'];
    $idCategoria = $_POST['id_categoria'];
    $idUsuario = $_SESSION['idUsuario'];

    // Validar datos
    if (empty($codigo) || empty($nombre) || empty($idTipoDocumento) || empty($idLinea) || empty($idEstacion) || empty($idCategoria)) {
        $error = "Todos los campos son obligatorios.";
    } else {
        $stmt = $conn->prepare("UPDATE Documentos SET Codigo = ?, Nombre_Documento = ?, IDTipoDocumento = ?, IDLinea = ?, IDEstacion = ?, IDCategoria = ? WHERE IDDocumento = ?");
        $stmt->bind_param("ssiiiii", $codigo, $nombre, $idTipoDocumento, $idLinea, $idEstacion, $idCategoria, $idDocumento);
        if ($stmt->execute()) {
            // Registrar la edición en EdicionesDocumento
            $fechaEdicion = date('Y-m-d H:i:s');
            $stmtEdicion = $conn->prepare("INSERT INTO EdicionesDocumento (IDDocumento, IDUsuario, FechaEdicion) VALUES (?, ?, ?)");
            $stmtEdicion->bind_param("iis", $idDocumento, $idUsuario, $fechaEdicion);
            $stmtEdicion->execute();
            $stmtEdicion->close();
            $success = "Documento actualizado exitosamente.";
        } else {
            $error = "Error al actualizar el documento.";
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Documento - Sistema de Gestión de Archivos MI TELEFÉRICO</title>
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
            background: rgba(255, 255, 255, 0.8);
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
            background: linear-gradient(135deg, #001f3f, #00bfff);
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
            background: #001f3f;
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
            background: #00bfff;
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

        .edit-form {
            display: flex;
            flex-direction: column;
            max-width: 600px;
            margin: 0 auto;
        }

        .edit-form label {
            text-align: left;
            margin-bottom: 8px;
            font-weight: bold;
            color: #34495e;
        }

        .edit-form input,
        .edit-form select {
            padding: 14px;
            margin-bottom: 25px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .edit-form input:focus,
        .edit-form select:focus {
            border-color: #00bfff;
            box-shadow: 0 0 10px rgba(0, 191, 255, 0.5);
            outline: none;
        }

        .edit-form button {
            padding: 14px;
            background: linear-gradient(135deg, #00bfff, #1e90ff);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s, transform 0.3s;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin-right: 10px;
        }

        .edit-form button:hover {
            background: linear-gradient(135deg, #1e90ff, #00bfff);
            transform: translateY(-2px);
        }

        .cancel-btn {
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

        .cancel-btn:hover {
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

        /* Estilos para la ventana emergente */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .modal-content h2 {
            color: #001f3f;
            margin-bottom: 15px;
        }

        .modal-content p {
            margin-bottom: 20px;
            color: #333;
        }

        .modal-content .btn-group {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .modal-content button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s, transform 0.3s;
        }

        .modal-content .confirm-btn {
            background: #00bfff;
            color: #fff;
        }

        .modal-content .confirm-btn:hover {
            background: #1e90ff;
            transform: translateY(-2px);
        }

        .modal-content .cancel-modal-btn {
            background: #ff6b6b;
            color: #fff;
        }

        .modal-content .cancel-modal-btn:hover {
            background: #e74c3c;
            transform: translateY(-2px);
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

            .edit-form {
                max-width: 100%;
            }

            .modal-content {
                margin: 20% auto;
                width: 90%;
            }
        }

        @media (max-width: 480px) {
            .logo {
                width: 100px;
            }

            main h1 {
                font-size: 24px;
            }

            .edit-form input,
            .edit-form select,
            .edit-form button,
            .cancel-btn {
                font-size: 14px;
                padding: 12px;
            }

            .edit-form label {
                font-size: 14px;
            }

            .modal-content {
                padding: 15px;
            }

            .modal-content button {
                font-size: 14px;
                padding: 8px 16px;
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
            <h1>Editar Documento</h1>
            <p>MI TELEFÉRICO - CRM</p>
            <?php if (isset($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (!empty($documento)): ?>
                <form class="edit-form" method="POST" id="editForm" onsubmit="return showConfirmation(event)">
                    <label for="codigo">Código</label>
                    <input type="text" id="codigo" name="codigo" value="<?php echo htmlspecialchars($documento['Codigo']); ?>" required>

                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($documento['Nombre_Documento']); ?>" required>

                    <label for="id_tipo_documento">Tipo de Documento</label>
                    <select id="id_tipo_documento" name="id_tipo_documento" required>
                        <?php foreach ($tiposDocumento as $id => $tipo): ?>
                            <option value="<?php echo $id; ?>" <?php echo ($documento['IDTipoDocumento'] == $id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tipo); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="id_linea">Línea</label>
                    <select id="id_linea" name="id_linea" onchange="updateEstaciones()" required>
                        <?php foreach ($lineas as $id => $linea): ?>
                            <option value="<?php echo $id; ?>" <?php echo ($documento['IDLinea'] == $id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($linea); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="id_estacion">Estación</label>
                    <select id="id_estacion" name="id_estacion" required>
                        <?php
                        $selectedLinea = $documento['IDLinea'];
                        if (isset($estaciones[$selectedLinea])) {
                            foreach ($estaciones[$selectedLinea] as $id => $estacion) {
                                echo "<option value='$id' " . ($documento['IDEstacion'] == $id ? 'selected' : '') . ">" . htmlspecialchars($estacion) . "</option>";
                            }
                        }
                        ?>
                    </select>

                    <label for="id_categoria">Categoría</label>
                    <select id="id_categoria" name="id_categoria" required>
                        <?php foreach ($categorias as $id => $categoria): ?>
                            <option value="<?php echo $id; ?>" <?php echo ($documento['IDCategoria'] == $id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($categoria); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div>
                        <button type="submit" name="confirm_edit">Guardar Cambios</button>
                        <button type="button" class="cancel-btn" onclick="window.location.href='edit_documents.php'">Cancelar</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="error-message">Documento no encontrado o no existe.</p>
            <?php endif; ?>

            <!-- Ventana emergente de confirmación -->
            <div id="confirmationModal" class="modal">
                <div class="modal-content">
                    <h2>Confirmar Cambios</h2>
                    <p id="changesSummary"></p>
                    <div class="btn-group">
                        <button class="confirm-btn" onclick="confirmChanges()">Confirmar</button>
                        <button class="cancel-modal-btn" onclick="closeModal()">Cancelar</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Función para actualizar las estaciones según la línea seleccionada
        function updateEstaciones() {
            const lineaSelect = document.getElementById('id_linea');
            const estacionSelect = document.getElementById('id_estacion');
            const lineaId = lineaSelect.value;

            // Limpiar opciones actuales
            estacionSelect.innerHTML = '';

            // Obtener estaciones para la línea seleccionada (simulado desde PHP)
            <?php
            foreach ($estaciones as $lineaId => $ests) {
                echo "if (lineaId == '$lineaId') {";
                foreach ($ests as $id => $estacion) {
                    echo "var opt = document.createElement('option');";
                    echo "opt.value = '$id';";
                    echo "opt.text = '" . addslashes($estacion) . "';";
                    echo "estacionSelect.add(opt);";
                }
                echo "} ";
            }
            ?>
            // Restaurar la selección actual si existe
            const currentEstacion = '<?php echo $documento['IDEstacion']; ?>';
            if (currentEstacion && estacionSelect.options.length > 0) {
                estacionSelect.value = currentEstacion;
            }
        }

        // Mostrar ventana emergente con resumen de cambios
        function showConfirmation(event) {
            event.preventDefault();
            const form = document.getElementById('editForm');
            const original = <?php echo json_encode($documento); ?>;
            const changes = [];

            const codigo = form.codigo.value;
            const nombre = form.nombre.value;
            const idTipoDocumento = form.id_tipo_documento.value;
            const idLinea = form.id_linea.value;
            const idEstacion = form.id_estacion.value;
            const idCategoria = form.id_categoria.value;

            if (codigo !== original.Codigo) changes.push(`Código: "${original.Codigo}" → "${codigo}"`);
            if (nombre !== original.Nombre_Documento) changes.push(`Nombre: "${original.Nombre_Documento}" → "${nombre}"`);
            if (idTipoDocumento !== original.IDTipoDocumento.toString()) changes.push(`Tipo: "${<?php echo json_encode($tiposDocumento[$documento['IDTipoDocumento']]); ?>}" → "${tiposDocumento[idTipoDocumento]}"`);
            if (idLinea !== original.IDLinea.toString()) changes.push(`Línea: "${<?php echo json_encode($lineas[$documento['IDLinea']]); ?>}" → "${lineas[idLinea]}"`);
            if (idEstacion !== original.IDEstacion.toString()) changes.push(`Estación: "${<?php echo json_encode($estaciones[$documento['IDLinea']][$documento['IDEstacion']]); ?>}" → "${estaciones[idLinea][idEstacion]}"`);
            if (idCategoria !== original.IDCategoria.toString()) changes.push(`Categoría: "${<?php echo json_encode($categorias[$documento['IDCategoria']]); ?>}" → "${categorias[idCategoria]}"`);

            if (changes.length === 0) {
                alert("No se detectaron cambios para guardar.");
                return false;
            }

            const changesSummary = document.getElementById('changesSummary');
            changesSummary.innerHTML = changes.join('<br>');
            document.getElementById('confirmationModal').style.display = 'block';
            return false;
        }

        // Confirmar cambios y enviar el formulario
        function confirmChanges() {
            document.getElementById('editForm').submit();
        }

        // Cerrar la ventana emergente
        function closeModal() {
            document.getElementById('confirmationModal').style.display = 'none';
        }

        // Inicializar estaciones al cargar
        window.onload = updateEstaciones;
    </script>
</body>
</html>