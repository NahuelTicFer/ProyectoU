<?php
session_start();
include 'Conn.php';

if (isset($_SESSION['idUsuario'])) {
    $idUsuario = $_SESSION['idUsuario'];
    $token = session_id();
    $stmt = $conn->prepare("DELETE FROM SesionesActivas WHERE IDUsuario = ? AND Token = ?");
    $stmt->bind_param("is", $idUsuario, $token);
    $stmt->execute();
    $stmt->close();
}

session_destroy();
header("Location: login.php");
exit;
$conn->close();
?>