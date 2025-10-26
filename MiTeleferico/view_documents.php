<?php
// view_documents.php - Página para visualizar documentos (solo accesible por administradores)

// Inicio de sesión y verificación de autenticación
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['idRol'] != 1) {
    header("Location: login.php");
    exit;
}

// Inclusión de conexión a la base de datos
include 'Conn.php';

// Obtener datos iniciales para los filtros
$lineas = [];
$result = $conn->query("SELECT IDLinea, NombreLinea FROM Linea");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $lineas[$row['IDLinea']] = $row['NombreLinea'];
    }
}

$estaciones = [];
$result = $conn->query("SELECT IDEstacion, NombreEstacion, IDLinea FROM Estacion");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $estaciones[$row['IDLinea']][$row['IDEstacion']] = $row['NombreEstacion'];
    }
}

$categorias = [];
$result = $conn->query("SELECT IDCategoria, NombreCategoria FROM Categoria");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categorias[$row['IDCategoria']] = $row['NombreCategoria'];
    }
}

$usuarios = [];
$result = $conn->query("SELECT IDUsuario, Nombre, ApellidoPaterno, ApellidoMaterno FROM Usuarios");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $usuarios[$row['IDUsuario']] = $row['Nombre'] . ' ' . $row['ApellidoPaterno'] . ' ' . $row['ApellidoMaterno'];
    }
}

// Consulta base para documentos con filtros del servidor (incluye Activo = 0 y 1)
$documentos = [];
$sql = "SELECT d.IDDocumento, d.Codigo, d.Nombre_Documento, td.NombreTipo, l.NombreLinea, e.NombreEstacion, c.NombreCategoria, u.Nombre, u.ApellidoPaterno, u.ApellidoMaterno, v.NumeroVersion, v.DireccionDelDoc, d.Activo 
        FROM Documentos d 
        INNER JOIN TipoDocumento td ON d.IDTipoDocumento = td.IDTipoDocumento 
        INNER JOIN Linea l ON d.IDLinea = l.IDLinea 
        INNER JOIN Estacion e ON d.IDEstacion = e.IDEstacion 
        INNER JOIN Categoria c ON d.IDCategoria = c.IDCategoria 
        INNER JOIN Usuarios u ON d.IDUsuario = u.IDUsuario 
        INNER JOIN VersionesDocumento v ON d.IDDocumento = v.IDDocumento";
$filters = [];
$params = [];

// Aplicar filtros desde la URL (GET)
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $conn->real_escape_string($_GET['search']) . "%";
    $filters[] = "d.Nombre_Documento LIKE ?";
    $params[] = $search;
}
if (isset($_GET['id_linea']) && $_GET['id_linea'] != '') {
    $filters[] = "d.IDLinea = ?";
    $params[] = $_GET['id_linea'];
}
if (isset($_GET['id_estacion']) && $_GET['id_estacion'] != '') {
    $filters[] = "d.IDEstacion = ?";
    $params[] = $_GET['id_estacion'];
}
if (isset($_GET['id_categoria']) && $_GET['id_categoria'] != '') {
    $filters[] = "d.IDCategoria = ?";
    $params[] = $_GET['id_categoria'];
}
if (isset($_GET['id_usuario']) && $_GET['id_usuario'] != '') {
    $filters[] = "d.IDUsuario = ?";
    $params[] = $_GET['id_usuario'];
}

if (!empty($filters)) {
    $sql .= " WHERE " . implode(" AND ", $filters);
}
$sql .= " ORDER BY d.Nombre_Documento ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $types = str_repeat("s", count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $documentos[] = $row;
    }
}
$stmt->close();

// Manejo de la reactivación del documento
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reactivate_id'])) {
    $idDocumento = (int)$_POST['reactivate_id'];
    $idUsuario = $_SESSION['idUsuario'];
    $fechaReactivacion = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("UPDATE Documentos SET Activo = 1 WHERE IDDocumento = ?");
    $stmt->bind_param("i", $idDocumento);
    if ($stmt->execute()) {
        // Opcional: Registrar la reactivación (puedes usar EliminacionesDocumento o una nueva tabla)
        $success = "Documento reactivado exitosamente.";
    } else {
        $error = "Error al reactivar el documento.";
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Documentos - Sistema de Gestión de Archivos MI TELEFÉRICO</title>
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

        .filter-section {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }

        .filter-section input,
        .filter-section select {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .filter-section input:focus,
        .filter-section select:focus {
            border-color: #00bfff;
            outline: none;
        }

        .documents-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 auto;
        }

        .documents-table th,
        .documents-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .documents-table th {
            background: #f4f4f4;
            font-weight: bold;
        }

        .documents-table tr.inactive {
            background-color: #ffcccc; /* Color rojo claro para documentos eliminados */
            color: #800000; /* Texto en rojo oscuro */
        }

        .view-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #00bfff;
            transition: color 0.3s;
        }

        .view-btn:hover {
            color: #1e90ff;
        }

        .recover-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #32cd32; /* Verde para recuperar */
            transition: color 0.3s;
        }

        .recover-btn:hover {
            color: #228b22;
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

            .filter-section {
                flex-direction: column;
            }

            .filter-section input,
            .filter-section select {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .logo {
                width: 100px;
            }

            main h1 {
                font-size: 24px;
            }

            .filter-section input,
            .filter-section select {
                font-size: 14px;
                padding: 10px;
            }

            .documents-table th,
            .documents-table td {
                font-size: 14px;
                padding: 8px;
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
            <h1>Ver Documentos</h1>
            <p>MI TELEFÉRICO - CRM</p>
            <?php if (isset($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <div class="filter-section">
                <form method="GET" id="filterForm" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="text" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="Buscar por nombre..." style="padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;">
                    <select name="id_linea" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Todas las líneas</option>
                        <?php foreach ($lineas as $id => $linea): ?>
                            <option value="<?php echo $id; ?>" <?php echo (isset($_GET['id_linea']) && $_GET['id_linea'] == $id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($linea); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="id_estacion" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Todas las estaciones</option>
                        <?php
                        $selectedLinea = isset($_GET['id_linea']) ? $_GET['id_linea'] : '';
                        if ($selectedLinea && isset($estaciones[$selectedLinea])) {
                            foreach ($estaciones[$selectedLinea] as $id => $estacion) {
                                echo "<option value='$id' " . (isset($_GET['id_estacion']) && $_GET['id_estacion'] == $id ? 'selected' : '') . ">" . htmlspecialchars($estacion) . "</option>";
                            }
                        }
                        ?>
                    </select>
                    <select name="id_categoria" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $id => $categoria): ?>
                            <option value="<?php echo $id; ?>" <?php echo (isset($_GET['id_categoria']) && $_GET['id_categoria'] == $id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($categoria); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="id_usuario" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Todos los usuarios</option>
                        <?php foreach ($usuarios as $id => $usuario): ?>
                            <option value="<?php echo $id; ?>" <?php echo (isset($_GET['id_usuario']) && $_GET['id_usuario'] == $id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($usuario); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <table class="documents-table" id="documentsTable">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Línea</th>
                        <th>Estación</th>
                        <th>Categoría</th>
                        <th>Versión</th>
                        <th>Subido por</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documentos as $doc): ?>
                        <tr class="<?php echo ($doc['Activo'] == 0) ? 'inactive' : ''; ?>">
                            <td><?php echo htmlspecialchars($doc['Codigo']); ?></td>
                            <td><?php echo htmlspecialchars($doc['Nombre_Documento']); ?></td>
                            <td><?php echo htmlspecialchars($doc['NombreTipo']); ?></td>
                            <td><?php echo htmlspecialchars($doc['NombreLinea']); ?></td>
                            <td><?php echo htmlspecialchars($doc['NombreEstacion']); ?></td>
                            <td><?php echo htmlspecialchars($doc['NombreCategoria']); ?></td>
                            <td><?php echo htmlspecialchars($doc['NumeroVersion']); ?></td>
                            <td><?php echo htmlspecialchars($doc['Nombre'] . ' ' . $doc['ApellidoPaterno'] . ' ' . $doc['ApellidoMaterno']); ?></td>
                            <td>
                                <?php if ($doc['Activo'] == 1): ?>
                                    <a href="view_document.php?id=<?php echo $doc['IDDocumento']; ?>" class="view-btn">👁️</a>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="reactivate_id" value="<?php echo $doc['IDDocumento']; ?>">
                                        <button type="submit" class="recover-btn">🔄</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </div>

    <script>
        // Función para actualizar las estaciones según la línea seleccionada
        function updateEstaciones() {
            const lineaSelect = document.querySelector('select[name="id_linea"]');
            const estacionSelect = document.querySelector('select[name="id_estacion"]');
            const lineaId = lineaSelect.value;

            // Limpiar opciones actuales
            estacionSelect.innerHTML = '<option value="">Todas las estaciones</option>';

            // Obtener estaciones para la línea seleccionada
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
            // Mantener la selección actual si existe
            if ('<?php echo isset($_GET['id_estacion']) ? $_GET['id_estacion'] : ''; ?>') {
                estacionSelect.value = '<?php echo $_GET['id_estacion']; ?>';
            }
        }

        // Llamar al cargar la página para inicializar las estaciones
        window.onload = updateEstaciones;
    </script>
</body>
</html>