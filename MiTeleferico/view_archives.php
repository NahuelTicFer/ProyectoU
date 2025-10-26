<?php
// view_documents.php - Página para ver documentos con filtros y búsqueda

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
        d.Nombre, 
        t.NombreTipo AS TipoDocumento, 
        l.NombreLinea AS Linea, 
        e.NombreEstacion AS Estacion, 
        c.NombreCategoria AS Categoria, 
        MIN(v.NumeroVersion) AS Version, 
        u.Nombre AS SubidoPor, 
        MIN(v.FechaVersion) AS FechaCarga,
        v.RutaArchivo
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
        d.IDDocumento, u.IDUsuario, v.RutaArchivo
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

// Cerrar conexión
$pdo = null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Documentos - Sistema de Gestión de Archivos MI TELEFÉRICO</title>
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
            background: rgba(255, 255, 255, 0.8); /* Overlay claro para legibilidad */
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
        }

        @media (max-width: 480px) {
            .logo {
                width: 100px;
            }

            main h1 {
                font-size: 24px;
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
                    <li><a href="view_documents.php">Ver Documentos</a></li>
                </ul>
                <form action="logout.php" method="POST">
                    <button type="submit" class="logout-btn">Cerrar Sesión</button>
                </form>
            </nav>
        </header>
        <main>
            <h1>Ver Documentos</h1>
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
                <button type="submit" onclick="document.getElementById('filtrosForm').submit();">Filtrar</button>
                <button type="button" class="btn-limpiar" 
                        onclick="window.location.href='view_documents.php?busqueda=&tipoDocumento=0&linea=0&estacion=0&categoria=0'">Limpiar</button>
            </div>

            <form id="filtrosForm" method="GET" style="display: none;">
                <input type="hidden" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>">
                <input type="hidden" name="tipoDocumento" value="<?php echo $tipoDocumento; ?>">
                <input type="hidden" name="linea" value="<?php echo $linea; ?>">
                <input type="hidden" name="estacion" value="<?php echo $estacion; ?>">
                <input type="hidden" name="categoria" value="<?php echo $categoria; ?>">
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
                            <th>Subido por</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documentos as $doc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doc['Codigo']); ?></td>
                                <td><?php echo htmlspecialchars($doc['Nombre']); ?></td>
                                <td><?php echo htmlspecialchars($doc['TipoDocumento']); ?></td>
                                <td><?php echo htmlspecialchars($doc['Linea']); ?></td>
                                <td><?php echo htmlspecialchars($doc['Estacion']); ?></td>
                                <td><?php echo htmlspecialchars($doc['Categoria']); ?></td>
                                <td><?php echo htmlspecialchars($doc['Version']); ?></td>
                                <td><?php echo htmlspecialchars($doc['SubidoPor']); ?></td>
                                <td>
                                    <a href="view_archivos.php?id=<?php echo htmlspecialchars($doc['IDDocumento']); ?>" 
                                       style="background: #00bfff; color: #fff; border: none; padding: 5px 10px; border-radius: 5px; text-decoration: none; display: inline-block;">Ver</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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

        // Actualizar estaciones al cargar la página
        document.addEventListener('DOMContentLoaded', actualizarEstaciones);
    </script>
</body>
</html>