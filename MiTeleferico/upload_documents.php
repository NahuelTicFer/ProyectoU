<?php
// upload_documents.php - Página para subir documentos (solo accesible por administradores)

// Inicio de sesión y verificación de autenticación
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['idRol'] != 1) {
    header("Location: login.php");
    exit;
}

// Inclusión de conexión a la base de datos
include 'Conn.php';

// Obtener datos iniciales para los selects
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

$tiposDocumento = [];
$result = $conn->query("SELECT IDTipoDocumento, NombreTipo FROM TipoDocumento");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tiposDocumento[$row['IDTipoDocumento']] = $row['NombreTipo'];
    }
}

$categorias = [];
$result = $conn->query("SELECT IDCategoria, NombreCategoria FROM Categoria");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categorias[$row['IDCategoria']] = $row['NombreCategoria'];
    }
}

// Manejo de la subida del documento
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['documento'])) {
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
        // Subir el archivo
        $file = $_FILES['documento'];
        $fileName = basename($file['name']);
        $fileTmp = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'docx', 'xlsx', 'jpg', 'png'];
        // Usar el nombre ingresado con su extensión original
        $sanitizedName = preg_replace('/[^a-zA-Z0-9-_]/', '_', $nombre); // Sanitizar nombre para evitar problemas
        $newFileName = $sanitizedName . '.' . $fileExt;
        $lineaNombre = $lineas[$idLinea];
        $estacionNombre = $estaciones[$idLinea][$idEstacion];
        $categoriaNombre = $categorias[$idCategoria];
        $uploadDir = 'BASE/' . $lineaNombre . '/' . $estacionNombre . '/' . $categoriaNombre . '/';
        $filePath = $uploadDir . $newFileName;

        if (!in_array($fileExt, $allowedExts)) {
            $error = "Formato de archivo no permitido. Usa: " . implode(', ', $allowedExts);
        } elseif ($file['size'] > 157286400) { // Límite de 150MB
            $error = "El archivo excede el tamaño máximo de 150MB.";
        } else {
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            if (move_uploaded_file($fileTmp, $filePath)) {
                // Insertar en Documentos
                $stmt = $conn->prepare("INSERT INTO Documentos (Codigo, Nombre_Documento, IDTipoDocumento, IDLinea, IDEstacion, IDCategoria, IDUsuario) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssiiiii", $codigo, $nombre, $idTipoDocumento, $idLinea, $idEstacion, $idCategoria, $idUsuario);
                if ($stmt->execute()) {
                    $idDocumento = $conn->insert_id;
                    // Insertar en VersionesDocumento
                    $stmtVersion = $conn->prepare("INSERT INTO VersionesDocumento (IDDocumento, NumeroVersion, DireccionDelDoc, IDUsuario) VALUES (?, '1.0', ?, ?)");
                    $stmtVersion->bind_param("isi", $idDocumento, $filePath, $idUsuario);
                    $stmtVersion->execute();
                    $success = "Documento subido exitosamente.";
                } else {
                    $error = "Error al guardar en la base de datos.";
                }
                $stmt->close();
                $stmtVersion->close();
            } else {
                $error = "Error al subir el archivo.";
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Documentos - Sistema de Gestión de Archivos MI TELEFÉRICO</title>
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

        .menu a,
        .menu .dropdown-btn {
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

        .menu a:hover,
        .menu .dropdown-btn:hover {
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

        main p {
            font-size: 18px;
            color: #555;
            margin-bottom: 30px;
        }

        .upload-form {
            display: flex;
            flex-direction: column;
            max-width: 600px;
            margin: 0 auto;
        }

        .upload-form label {
            text-align: left;
            margin-bottom: 8px;
            font-weight: bold;
            color: #34495e;
        }

        .upload-form input,
        .upload-form select {
            padding: 14px;
            margin-bottom: 25px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .upload-form input:focus,
        .upload-form select:focus {
            border-color: #00bfff;
            box-shadow: 0 0 10px rgba(0, 191, 255, 0.5);
            outline: none;
        }

        .upload-form button {
            padding: 14px;
            background: linear-gradient(135deg, #00bfff, #1e90ff);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s, transform 0.3s;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .upload-form button:hover {
            background: linear-gradient(135deg, #1e90ff, #00bfff);
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

            .menu a,
            .menu .dropdown-btn {
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

            .upload-form input,
            .upload-form select,
            .upload-form button {
                font-size: 14px;
                padding: 12px;
            }

            .upload-form label {
                font-size: 14px;
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
            <h1>Subir Documentos</h1>
            <p>MI TELEFÉRICO - CRM</p>
            <?php if (isset($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <form class="upload-form" method="POST" enctype="multipart/form-data">
                <label for="codigo">Código</label>
                <input type="text" id="codigo" name="codigo" required>

                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" required>

                <label for="id_tipo_documento">Tipo de Documento</label>
                <select id="id_tipo_documento" name="id_tipo_documento" required>
                    <?php foreach ($tiposDocumento as $id => $tipo): ?>
                        <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($tipo); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="id_linea">Línea</label>
                <select id="id_linea" name="id_linea" onchange="updateEstaciones()" required>
                    <?php foreach ($lineas as $id => $linea): ?>
                        <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($linea); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="id_estacion">Estación</label>
                <select id="id_estacion" name="id_estacion" required>
                    <?php foreach ($estaciones[1] ?? [] as $id => $estacion): ?>
                        <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($estacion); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="id_categoria">Categoría</label>
                <select id="id_categoria" name="id_categoria" required>
                    <?php foreach ($categorias as $id => $categoria): ?>
                        <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($categoria); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="documento">Documento</label>
                <input type="file" id="documento" name="documento" required>

                <button type="submit">Subir Documento</button>
            </form>
        </main>
    </div>

    <script>
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
        }

        // Llamar al cargar la página para inicializar las estaciones
        window.onload = updateEstaciones;
    </script>
</body>

</html>