<?php
// Conn.php - Archivo de conexión a la base de datos

// Parámetros de conexión a la base de datos
$servername = "localhost";
$username = "root"; // Cambia esto por tu usuario de DB
$password = ""; // Cambia esto por tu contraseña de DB
$dbname = "CRM_Teleferico";

// Creación de la conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificación de conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Configuración de charset
$conn->set_charset("utf8mb4");
?>