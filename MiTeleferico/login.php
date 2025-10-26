<?php
// login.php - Página de inicio de sesión

// Inicio de sesión y inclusión de conexión
session_start();
include 'Conn.php';

// Manejo del formulario de login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = $_POST['correo'];
    $contrasena = $_POST['contrasena'];

    $stmt = $conn->prepare("SELECT IDUsuario, Contrasena, IDRol FROM Usuarios WHERE Correo = ? AND Activo = TRUE");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($idUsuario, $hashedPassword, $idRol);
        $stmt->fetch();

        if (password_verify($contrasena, $hashedPassword)) {
            $_SESSION['loggedin'] = true;
            $_SESSION['idUsuario'] = $idUsuario;
            $_SESSION['idRol'] = $idRol;

            // Registrar sesión activa en la tabla SesionesActivas
            $token = session_id(); // Usamos el ID de sesión de PHP como token
            $fecha = date('Y-m-d H:i:s');
            $stmtSession = $conn->prepare("INSERT INTO SesionesActivas (IDUsuario, FechaInicio, FechaUltimaActividad, Token) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE FechaUltimaActividad = ?, Token = ?");
            $stmtSession->bind_param("isssss", $idUsuario, $fecha, $fecha, $token, $fecha, $token);
            $stmtSession->execute();
            $stmtSession->close();

            // Redirección según el rol
            if ($idRol == 1) { // Asumiendo que 1 es Administrador
                header("Location: admin_dashboard.php");
            } else {
                header("Location: user_dashboard.php");
            }
            exit;
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "Correo no encontrado o usuario inactivo.";
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
    <title>Login - Sistema de Gestión de Archivos MI TELEFÉRICO</title>
    <!-- Estilos CSS -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: url('IMG/fondo1.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #fff;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 31, 63, 0.7); /* Overlay oscuro para mejorar legibilidad */
            z-index: 1;
        }

        .login-container {
            position: relative;
            z-index: 2;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeIn 0.8s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo {
            width: 150px;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
        }

        .login-header h1 {
            font-size: 28px;
            color: #fff;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .login-header p {
            font-size: 16px;
            color: #ddd;
            margin-bottom: 30px;
        }

        .login-form {
            display: flex;
            flex-direction: column;
        }

        .login-form label {
            text-align: left;
            margin-bottom: 8px;
            font-weight: bold;
            color: #fff;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .login-form input {
            padding: 14px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .login-form input::placeholder {
            color: #ccc;
        }

        .login-form input:focus {
            border-color: #00bfff;
            box-shadow: 0 0 10px rgba(0, 191, 255, 0.5);
            outline: none;
        }

        .login-form button {
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

        .login-form button:hover {
            background: linear-gradient(135deg, #1e90ff, #00bfff);
            transform: translateY(-2px);
        }

        .error-message {
            color: #ff6b6b;
            margin-bottom: 20px;
            font-size: 14px;
            background: rgba(255, 107, 107, 0.1);
            padding: 10px;
            border-radius: 8px;
        }

        /* Mejoras en responsividad para dispositivos móviles */
        @media (max-width: 768px) {
            .login-container {
                max-width: 90%;
                padding: 40px 30px;
            }

            .logo {
                width: 130px;
            }

            .login-header h1 {
                font-size: 26px;
            }

            .login-header p {
                font-size: 15px;
            }
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                margin: 10px;
            }

            .logo {
                width: 100px;
            }

            .login-header h1 {
                font-size: 22px;
            }

            .login-header p {
                font-size: 14px;
            }

            .login-form input,
            .login-form button {
                font-size: 14px;
                padding: 12px;
            }

            .login-form label {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Contenedor principal de login -->
    <div class="login-container">
        <img src="IMG/logo.png" alt="Mi Teleférico Logo" class="logo"> <!-- Ruta relativa corregida -->
        <div class="login-header">
            <h1>Bienvenido al Sistema de Gestión de Archivos</h1>
            <p>MI TELEFÉRICO - CRM</p>
        </div>
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        <form class="login-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <label for="correo">Correo Electrónico</label>
            <input type="email" id="correo" name="correo" placeholder="Ingrese su correo" required>
            
            <label for="contrasena">Contraseña</label>
            <input type="password" id="contrasena" name="contrasena" placeholder="Ingrese su contraseña" required>
            
            <button type="submit">Iniciar Sesión</button>
        </form>
    </div>

    <!-- Script JS para mejoras opcionales -->
    <script>
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            // Podría agregar validación del lado del cliente aquí si es necesario
        });
    </script>
</body>
</html>