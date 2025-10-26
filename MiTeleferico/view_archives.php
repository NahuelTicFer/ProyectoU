<?php
// view_documents.php - Página para ver y previsualizar documentos con filtros y búsqueda

// Inicio de sesión y verificación de autenticación
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Inclusión de conexión a la base de datos
include 'Conn.php';

// Verificar y establecer conexión si no está definida
if (!isset($pdo) || $pdo === null) {
    try {
        $host = 'localhost';
        $dbname = 'CRM_Teleferico';
        $username = 'root'; // Cambia esto según tu configuración
        $password = '';     // Cambia esto según tu configuración
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Error de conexión a la base de datos: " . $e->getMessage() . ". Verifica Conn.php o las credenciales.");
    }
}

// Obtener datos para los filtros
try {
    $tiposDocumento = $pdo->query("SELECT IDTipoDocumento, NombreTipo FROM TipoDocumento ORDER BY NombreTipo")->fetchAll(PDO::FETCH_ASSOC);
    $lineas = $pdo->query("SELECT IDLinea, NombreLinea FROM Linea ORDER BY NombreLinea")->fetchAll(PDO::FETCH_ASSOC);
    $categorias = $pdo->query("SELECT IDCategoria, NombreCategoria FROM Categoria ORDER BY NombreCategoria")->fetchAll(PDO::FETCH_ASSOC);
    $estaciones = $pdo->query("SELECT IDEstacion, NombreEstacion, IDLinea FROM Estacion ORDER BY NombreEstacion")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener datos para filtros: " . $e->getMessage());
}

// Procesar filtros y búsqueda
$whereClauses = ["d.Activo = TRUE"];
$params = [];

$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$tipoDocumento = isset($_GET['tipoDocumento']) ? (int)$_GET['tipoDocumento'] : 0;
$linea = isset($_GET['linea']) ? (int)$_GET['linea'] : 0;
$estacion = isset($_GET['estacion']) ? (int)$_GET['estacion'] : 0;
$categoria = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;

if (!empty($busqueda)) {
    $whereClauses[] = "(d.Codigo LIKE :busqueda OR d.Nombre LIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}

if ($tipoDocumento > 0) {
    $whereClauses[] = "d.IDTipoDocumento = :tipoDocumento";
    $params[':tipoDocumento'] = $tipoDocumento;
}

if ($linea > 0) {
    $whereClauses[] = "d.IDLinea = :linea";
    $params[':linea'] = $linea;
}

if ($estacion > 0) {
    $whereClauses[] = "d.IDEstacion = :estacion";
    $params[':estacion'] = $estacion;
}

if ($categoria > 0) {
    $whereClauses[] = "d.IDCategoria = :categoria";
    $params[':categoria'] = $categoria;
}

$sql = "
    SELECT 
        d.IDDocumento, 
        d.Codigo, 
        d.Nombre_Documento, 
        t.NombreTipo AS TipoDocumento, 
        l.NombreLinea AS Linea, 
        e.NombreEstacion AS Estacion, 
        c.NombreCategoria AS Categoria, 
        MIN(v.NumeroVersion) AS Version, 
        MIN(v.FechaVersion) AS FechaCarga,
        v.DireccionDelDoc AS RutaArchivo
    FROM 
        Documentos d
    JOIN 
        TipoDocumento t ON d.IDTipoDocumento = t.IDTipoDocumento
    JOIN 
        Linea l ON d.IDLinea = l.IDLinea
    JOIN 
        Estacion e ON d.IDEstacion = e.IDEstacion
    JOIN 
        Categoria c ON d.IDCategoria = c.IDCategoria
    JOIN 
        VersionesDocumento v ON d.IDDocumento = v.IDDocumento
    JOIN 
        Usuarios u ON d.IDUsuario = u.IDUsuario
    " . (count($whereClauses) > 0 ? "WHERE " . implode(" AND ", $whereClauses) : "") . "
    GROUP BY 
        d.IDDocumento, u.IDUsuario, v.DireccionDelDoc
    ORDER BY 
        FechaCarga DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}

// Obtener detalles del documento seleccionado para previsualización
$documentoSeleccionado = null;
if (isset($_GET['idDocumento']) && is_numeric($_GET['idDocumento'])) {
    $idDocumento = (int)$_GET['idDocumento'];
    $sqlDetalles = "
        SELECT 
            d.IDDocumento, 
            d.Codigo, 
            d.Nombre_Documento, 
            t.NombreTipo AS NombreTipo, 
            l.NombreLinea AS NombreLinea, 
            e.NombreEstacion AS NombreEstacion, 
            c.NombreCategoria AS NombreCategoria, 
            u.Nombre, 
            u.ApellidoPaterno, 
            u.ApellidoMaterno, 
            u.Correo, 
            v.NumeroVersion, 
            v.DireccionDelDoc AS DireccionDelDoc,
            v.FechaVersion
        FROM 
            Documentos d 
        JOIN 
            TipoDocumento t ON d.IDTipoDocumento = t.IDTipoDocumento 
        JOIN 
            Linea l ON d.IDLinea = l.IDLinea 
        JOIN 
            Estacion e ON d.IDEstacion = e.IDEstacion 
        JOIN 
            Categoria c ON d.IDCategoria = c.IDCategoria 
        JOIN 
            Usuarios u ON d.IDUsuario = u.IDUsuario 
        JOIN 
            VersionesDocumento v ON d.IDDocumento = v.IDDocumento 
        WHERE 
            d.IDDocumento = :idDocumento AND d.Activo = TRUE
        ORDER BY 
            v.NumeroVersion DESC
        LIMIT 1
    ";
    try {
        $stmtDetalles = $pdo->prepare($sqlDetalles);
        $stmtDetalles->execute([':idDocumento' => $idDocumento]);
        $documentoSeleccionado = $stmtDetalles->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error al obtener detalles del documento: " . $e->getMessage());
    }
}

// Cerrar conexión
$pdo = null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Archivos MI TELEFÉRICO</title>
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

        .menu a {
            color: #fff;
            text-decoration: none;
            font-size: 16px;
            padding: 10px 15px;
            border-radius: 5px;
            transition: background 0.3s, transform 0.3s;
            display: block;
        }

        .menu a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        main {
            background: #fff;
            padding: 40px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        main h1 {
            font-size: 32px;
            color: #001f3f;
            margin-bottom: 20px;
            text-align: center;
        }

        .filtros {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            justify-content: center;
        }

        .filtros input[type="text"], .filtros select {
            padding: 8px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 200px;
        }

        .filtros button {
            padding: 8px 16px;
            background: #00bfff;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .filtros button:hover {
            background: #0099cc;
        }

        .filtros .btn-limpiar {
            background: #ff6b6b;
        }

        .filtros .btn-limpiar:hover {
            background: #e74c3c;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #f2f2f2;
            color: #001f3f;
            font-weight: bold;
        }

        tr:hover {
            background: #f5f5f5;
        }

        .no-documentos {
            text-align: center;
            color: #888;
            font-size: 18px;
            margin-top: 20px;
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

        .document-details {
            max-width: 800px;
            margin: 20px auto;
            text-align: left;
        }

        .document-details h2 {
            font-size: 24px;
            color: #001f3f;
            margin-bottom: 15px;
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
            background: #00bfff;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .full-view-btn:hover {
            background: #0099cc;
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

            .menu a {
                text-align: left;
                width: 100%;
            }

            .filtros {
                flex-direction: column;
                align-items: stretch;
            }

            .filtros input[type="text"], .filtros select {
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
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header>
            <img src="IMG/logo.png" alt="Mi Teleférico Logo" class="logo">
            <nav>
                <ul class="menu">
                </ul>
                <form action="logout.php" method="POST">
                    <button type="submit" class="logout-btn">Cerrar Sesión</button>
                </form>
            </nav>
        </header>
        <main>
            <div class="filtros">
                <input type="text" name="busqueda" id="busqueda" placeholder="Buscar por nombre..." 
                       value="<?php echo htmlspecialchars($busqueda); ?>">
                <select name="tipoDocumento" id="tipoDocumento">
                    <option value="0">Todos los tipos</option>
                    <?php foreach ($tiposDocumento as $tipo): ?>
                        <option value="<?php echo $tipo['IDTipoDocumento']; ?>" 
                                <?php echo $tipoDocumento == $tipo['IDTipoDocumento'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tipo['NombreTipo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="linea" id="linea" onchange="actualizarEstaciones()">
                    <option value="0">Todas las líneas</option>
                    <?php foreach ($lineas as $l): ?>
                        <option value="<?php echo $l['IDLinea']; ?>" 
                                <?php echo $linea == $l['IDLinea'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($l['NombreLinea']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="estacion" id="estacion">
                    <option value="0">Todas las estaciones</option>
                    <?php if ($linea > 0): ?>
                        <?php foreach ($estaciones as $est): ?>
                            <?php if ($est['IDLinea'] == $linea): ?>
                                <option value="<?php echo $est['IDEstacion']; ?>" 
                                        <?php echo $estacion == $est['IDEstacion'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($est['NombreEstacion']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <select name="categoria" id="categoria">
                    <option value="0">Todas las categorías</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['IDCategoria']; ?>" 
                                <?php echo $categoria == $cat['IDCategoria'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['NombreCategoria']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" onclick="submitFilters()">Filtrar</button>
                <button type="button" class="btn-limpiar" 
                        onclick="window.location.href='view_documents.php?busqueda=&tipoDocumento=0&linea=0&estacion=0&categoria=0'">Limpiar</button>
            </div>

            <form id="filtrosForm" method="GET" style="display: none;">
                <input type="hidden" name="busqueda" id="formBusqueda">
                <input type="hidden" name="tipoDocumento" id="formTipoDocumento">
                <input type="hidden" name="linea" id="formLinea">
                <input type="hidden" name="estacion" id="formEstacion">
                <input type="hidden" name="categoria" id="formCategoria">
                <input type="hidden" name="idDocumento" id="formIdDocumento">
            </form>

            <?php if (empty($documentos)): ?>
                <p class="no-documentos">No hay documentos disponibles con los filtros seleccionados.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Línea</th>
                            <th>Estación</th>
                            <th>Categoría</th>
                            <th>Versión</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documentos as $doc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doc['Codigo']); ?></td>
                                <td><?php echo htmlspecialchars($doc['Nombre_Documento']); ?></td>
                                <td><?php echo htmlspecialchars($doc['TipoDocumento']); ?></td>
                                <td><?php echo htmlspecialchars($doc['Linea']); ?></td>
                                <td><?php echo htmlspecialchars($doc['Estacion']); ?></td>
                                <td><?php echo htmlspecialchars($doc['Categoria']); ?></td>
                                <td><?php echo htmlspecialchars($doc['Version']); ?></td>
                                <td>
                                    <a href="javascript:void(0);" 
                                       onclick="selectDocument(<?php echo htmlspecialchars($doc['IDDocumento']); ?>)"
                                       style="background: #00bfff; color: #fff; border: none; padding: 5px 10px; border-radius: 5px; text-decoration: none; display: inline-block;">Previsualizar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($documentoSeleccionado): ?>
                <div class="document-details">
                    <h2>Detalles del Documento</h2>
                    <p><strong>Código:</strong> <?php echo htmlspecialchars($documentoSeleccionado['Codigo']); ?></p>
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($documentoSeleccionado['Nombre_Documento']); ?></p>
                    <p><strong>Tipo:</strong> <?php echo htmlspecialchars($documentoSeleccionado['NombreTipo']); ?></p>
                    <p><strong>Línea:</strong> <?php echo htmlspecialchars($documentoSeleccionado['NombreLinea']); ?></p>
                    <p><strong>Estación:</strong> <?php echo htmlspecialchars($documentoSeleccionado['NombreEstacion']); ?></p>
                    <p><strong>Categoría:</strong> <?php echo htmlspecialchars($documentoSeleccionado['NombreCategoria']); ?></p>
                    <p><strong>Versión:</strong> <?php echo htmlspecialchars($documentoSeleccionado['NumeroVersion']); ?></p>
                    <p><strong>Fecha de subida:</strong> <?php echo date('d/m/Y H:i', strtotime($documentoSeleccionado['FechaVersion'])); ?></p>
                </div>
                <div class="preview-container">
                    <?php
                    $fileExt = strtolower(pathinfo($documentoSeleccionado['DireccionDelDoc'], PATHINFO_EXTENSION));
                    $previewUrl = htmlspecialchars($documentoSeleccionado['DireccionDelDoc']);
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
                <a href="<?php echo htmlspecialchars($documentoSeleccionado['DireccionDelDoc']); ?>" target="_blank" class="full-view-btn">Ver documento completo</a>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Datos de estaciones para el filtro dinámico
        const estaciones = <?php echo json_encode($estaciones); ?>;
        
        // Función para actualizar las opciones del select de estaciones
        function actualizarEstaciones() {
            const lineaSelect = document.getElementById('linea');
            const estacionSelect = document.getElementById('estacion');
            const lineaId = lineaSelect.value;

            // Limpiar opciones actuales
            estacionSelect.innerHTML = '<option value="0">Todas las estaciones</option>';

            // Filtrar y añadir estaciones correspondientes a la línea seleccionada
            if (lineaId !== '0') {
                estaciones.forEach(estacion => {
                    if (estacion.IDLinea == lineaId) {
                        const option = document.createElement('option');
                        option.value = estacion.IDEstacion;
                        option.text = estacion.NombreEstacion;
                        estacionSelect.appendChild(option);
                    }
                });
            }
        }

        // Función para enviar filtros y seleccionar documento
        function selectDocument(idDocumento) {
            document.getElementById('formIdDocumento').value = idDocumento;
            submitFilters();
        }

        // Función para enviar el formulario de filtros
        function submitFilters() {
            const form = document.getElementById('filtrosForm');
            document.getElementById('formBusqueda').value = document.getElementById('busqueda').value;
            document.getElementById('formTipoDocumento').value = document.getElementById('tipoDocumento').value;
            document.getElementById('formLinea').value = document.getElementById('linea').value;
            document.getElementById('formEstacion').value = document.getElementById('estacion').value;
            document.getElementById('formCategoria').value = document.getElementById('categoria').value;
            form.submit();
        }

        // Actualizar estaciones al cargar la página
        document.addEventListener('DOMContentLoaded', actualizarEstaciones);
    </script>
</body>
</html>
