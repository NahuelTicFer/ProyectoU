<?php
// view_document.php - Página para visualizar detalles de un documento (solo accesible por administradores)

// Inicio de sesión y verificación de autenticación
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['idRol'] != 1) {
    header("Location: login.php");
    exit;
}

// Inclusión de conexión a la base de datos
include 'Conn.php';

// Obtener el ID del documento desde la URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de documento no válido.");
}
$idDocumento = $_GET['id'];

// Consultar los detalles del documento
$documento = [];
$sql = "SELECT d.IDDocumento, d.Codigo, d.Nombre_Documento, td.NombreTipo, l.NombreLinea, e.NombreEstacion, c.NombreCategoria, u.Nombre, u.ApellidoPaterno, u.ApellidoMaterno, u.Correo, v.NumeroVersion, v.DireccionDelDoc 
        FROM Documentos d 
        INNER JOIN TipoDocumento td ON d.IDTipoDocumento = td.IDTipoDocumento 
        INNER JOIN Linea l ON d.IDLinea = l.IDLinea 
        INNER JOIN Estacion e ON d.IDEstacion = e.IDEstacion 
        INNER JOIN Categoria c ON d.IDCategoria = c.IDCategoria 
        INNER JOIN Usuarios u ON d.IDUsuario = u.IDUsuario 
        INNER JOIN VersionesDocumento v ON d.IDDocumento = v.IDDocumento 
        WHERE d.IDDocumento = ? AND d.Activo = TRUE";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idDocumento);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $documento = $result->fetch_assoc();
} else {
    die("Documento no encontrado.");
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Documento - Sistema de Gestión de Archivos MI TELEFÉRICO</title>
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

        .document-details {
            max-width: 800px;
            margin: 0 auto;
            text-align: left;
        }

        .document-details p {
            margin: 10px 0;
            font-size: 16px;
            color: #34495e;
        }

        .document-details strong {
            color: #001f3f;
        }

        .preview-container {
            margin: 20px 0;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: auto;
            max-height: 800px;
            width: 100%;
            max-width: 1000px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f9f9f9;
        }

        .preview-container iframe,
        .preview-container img {
            width: 100%;
            height: auto;
            min-height: 600px;
            object-fit: contain;
        }

        .preview-message {
            text-align: center;
            color: #ff6b6b;
            padding: 20px;
        }

        .full-view-btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, #00bfff, #1e90ff);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s, transform 0.3s;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .full-view-btn:hover {
            background: linear-gradient(135deg, #1e90ff, #00bfff);
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

            .document-details {
                max-width: 100%;
            }

            .preview-container {
                max-height: 600px;
                max-width: 100%;
            }

            .preview-container iframe,
            .preview-container img {
                min-height: 400px;
            }
        }

        @media (max-width: 480px) {
            .logo {
                width: 100px;
            }

            main h1 {
                font-size: 24px;
            }

            .document-details p {
                font-size: 14px;
            }

            .preview-container {
                max-height: 400px;
                max-width: 100%;
            }

            .preview-container iframe,
            .preview-container img {
                min-height: 300px;
            }

            .full-view-btn {
                font-size: 14px;
                padding: 10px 15px;
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
            <h1>Detalles del Documento</h1>
            <p>MI TELEFÉRICO - CRM</p>
            <div class="document-details">
                <p><strong>Código:</strong> <?php echo htmlspecialchars($documento['Codigo']); ?></p>
                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($documento['Nombre_Documento']); ?></p>
                <p><strong>Tipo:</strong> <?php echo htmlspecialchars($documento['NombreTipo']); ?></p>
                <p><strong>Línea:</strong> <?php echo htmlspecialchars($documento['NombreLinea']); ?></p>
                <p><strong>Estación:</strong> <?php echo htmlspecialchars($documento['NombreEstacion']); ?></p>
                <p><strong>Categoría:</strong> <?php echo htmlspecialchars($documento['NombreCategoria']); ?></p>
                <p><strong>Versión:</strong> <?php echo htmlspecialchars($documento['NumeroVersion']); ?></p>
                <p><strong>Subido por:</strong> <?php echo htmlspecialchars($documento['Nombre'] . ' ' . $documento['ApellidoPaterno'] . ' ' . $documento['ApellidoMaterno'] . ' (' . $documento['Correo'] . ')'); ?></p>
                <p><strong>Fecha de subida:</strong> <?php echo date('d/m/Y H:i', filemtime($documento['DireccionDelDoc'])); ?></p>
            </div>
            <div class="preview-container">
                <?php
                $fileExt = strtolower(pathinfo($documento['DireccionDelDoc'], PATHINFO_EXTENSION));
                $previewUrl = htmlspecialchars($documento['DireccionDelDoc']);
                if (in_array($fileExt, ['pdf'])) {
                    echo "<iframe src='$previewUrl' frameborder='0'></iframe>";
                } elseif (in_array($fileExt, ['jpg', 'png'])) {
                    echo "<img src='$previewUrl' alt='Vista previa'>";
                } elseif (in_array($fileExt, ['xls', 'xlsx', 'doc', 'docx'])) {
                    echo "<p class='preview-message'>No se puede previsualizar este formato. Use el botón para ver completo.</p>";
                } elseif ($fileExt === 'dwg') {
                    echo "<p class='preview-message'>No se puede previsualizar este formato (DWG).</p>";
                } else {
                    echo "<p class='preview-message'>No se puede previsualizar este formato.</p>";
                }
                ?>
            </div>
            <a href="<?php echo htmlspecialchars($documento['DireccionDelDoc']); ?>" target="_blank" class="full-view-btn">Ver documento completo</a>
        </main>
    </div>
</body>
</html>