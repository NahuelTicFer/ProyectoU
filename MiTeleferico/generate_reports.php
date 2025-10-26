<?php
// generate_reports.php - Página para generar reportes del sistema (solo accesible por administradores)

// Inicio de sesión y verificación de autenticación
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['idRol'] != 1) {
    header("Location: login.php");
    exit;
}

// Inclusión de conexión a la base de datos
include 'Conn.php';

// Obtener tipos de reporte predefinidos
$tiposReporte = [];
$result = $conn->query("SELECT IDTipoReporte, NombreTipo FROM TiposReporte");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tiposReporte[$row['IDTipoReporte']] = $row['NombreTipo'];
    }
}

// Generar reporte y guardar en la base de datos
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_report']) && isset($_POST['idTipoReporte'])) {
    $idTipoReporte = (int)$_POST['idTipoReporte'];
    $idUsuario = $_SESSION['idUsuario'];
    $fechaGeneracion = date('Y-m-d H:i:s');

    // Consultar datos según el tipo de reporte
    if ($idTipoReporte == 1) { // Reporte de Documentos Activos
        $documentos = [];
        $sql = "SELECT d.Codigo, d.Nombre_Documento, td.NombreTipo, l.NombreLinea, e.NombreEstacion, c.NombreCategoria, v.NumeroVersion 
                FROM Documentos d 
                INNER JOIN TipoDocumento td ON d.IDTipoDocumento = td.IDTipoDocumento 
                INNER JOIN Linea l ON d.IDLinea = l.IDLinea 
                INNER JOIN Estacion e ON d.IDEstacion = e.IDEstacion 
                INNER JOIN Categoria c ON d.IDCategoria = c.IDCategoria 
                INNER JOIN VersionesDocumento v ON d.IDDocumento = v.IDDocumento 
                WHERE d.Activo = TRUE";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $documentos[] = $row;
            }
        }

        // Generar contenido LaTeX
        $latexContent = <<<LATEX
\documentclass[a4paper,12pt]{article}
\usepackage[utf8]{inputenc}
\usepackage{geometry}
\geometry{a4paper, margin=1in}
\usepackage{graphicx}
\usepackage{array}
\usepackage{booktabs}

% Configuración de fuentes
\usepackage{fontspec} % Para XeLaTeX, si es necesario (no obligatorio aquí)

% Preambulo para tablas y formato
\setlength{\tabcolsep}{10pt}
\renewcommand{\arraystretch}{1.5}

\begin{document}

% Encabezado con logo
\begin{center}
    \includegraphics[width=0.3\textwidth]{IMG/logo.png}
    \vspace{10pt}
    {\Huge \textbf{Reporte de Documentos Activos}} \\
    {\large Generado el: $fechaGeneracion} \\
    \vspace{10pt}
\end{center}

% Tabla de documentos
\begin{center}
    \begin{tabular}{|m{2cm}|m{3cm}|m{2cm}|m{2cm}|m{2cm}|m{2cm}|m{2cm}|}
        \hline
        \textbf{Código} & \textbf{Nombre} & \textbf{Tipo} & \textbf{Línea} & \textbf{Estación} & \textbf{Categoría} & \textbf{Versión} \\ \hline
LATEX;

        foreach ($documentos as $doc) {
            $latexContent .= sprintf(
                "%s & %s & %s & %s & %s & %s & %s \\\\ \hline",
                htmlspecialchars($doc['Codigo']),
                htmlspecialchars($doc['Nombre_Documento']),
                htmlspecialchars($doc['NombreTipo']),
                htmlspecialchars($doc['NombreLinea']),
                htmlspecialchars($doc['NombreEstacion']),
                htmlspecialchars($doc['NombreCategoria']),
                htmlspecialchars($doc['NumeroVersion'])
            );
        }

        $latexContent .= <<<LATEX
    \end{tabular}
\end{center}

\end{document}
LATEX;

        // Guardar archivo LaTeX temporal
        $tempFile = tempnam(sys_get_temp_dir(), 'report_') . '.tex';
        file_put_contents($tempFile, $latexContent);

        // Generar PDF con latexmk
        $tipoReporte = $tiposReporte[$idTipoReporte];
        $subfolder = strtolower(str_replace(' ', '_', $tipoReporte));
        $reportFolder = "REPORTES/$subfolder";
        if (!file_exists($reportFolder)) {
            mkdir($reportFolder, 0777, true);
        }
        $filename = $tipoReporte . '_' . date('Ymd_His') . '.pdf';
        $outputPath = "$reportFolder/$filename";
        exec("latexmk -pdf -output-directory=" . dirname($tempFile) . " $tempFile");
        $pdfPath = dirname($tempFile) . '/report.pdf';
        if (file_exists($pdfPath)) {
            rename($pdfPath, $outputPath);
        }

        // Limpiar archivos temporales
        exec("latexmk -c -output-directory=" . dirname($tempFile));
        unlink($tempFile);

        // Guardar en la base de datos
        $stmt = $conn->prepare("INSERT INTO Reportes (IDTipoReporte, IDUsuario) VALUES (?, ?)");
        $stmt->bind_param("ii", $idTipoReporte, $idUsuario);
        $stmt->execute();
        $stmt->close();

        $success = "Reporte generado y guardado como $outputPath.";
    } else {
        $success = "Generación de reporte para '$tipoReporte' no implementada aún.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Reportes - Sistema de Gestión de Archivos MI TELEFÉRICO</title>
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

        .report-buttons {
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            justify-items: center;
        }

        .report-buttons form {
            width: 100%;
        }

        .report-buttons button {
            padding: 15px 25px; /* Botones más grandes */
            border: none;
            border-radius: 5px;
            font-size: 18px; /* Fuente más grande */
            cursor: pointer;
            background: #00bfff;
            color: #000000; /* Texto negro */
            transition: background 0.3s, transform 0.3s;
            width: 100%;
            box-sizing: border-box;
        }

        .report-buttons button:hover {
            background: #1e90ff;
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

            .report-buttons {
                grid-template-columns: 1fr; /* Una columna en pantallas pequeñas */
            }

            .report-buttons button {
                padding: 12px 20px; /* Ajuste en pantallas pequeñas */
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

            .report-buttons button {
                font-size: 14px;
                padding: 10px;
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
            <h1>Generar Reportes</h1>
            <p>MI TELEFÉRICO - CRM</p>
            <?php if (isset($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <div class="report-buttons">
                <?php foreach ($tiposReporte as $id => $nombreTipo): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="idTipoReporte" value="<?php echo $id; ?>">
                        <button type="submit" name="generate_report"><?php echo htmlspecialchars($nombreTipo); ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</body>
</html>