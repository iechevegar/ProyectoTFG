<?php
$servername = "sql100.infinityfree.com"; // Tu MySQL Host Name
$username = "if0_41551522";      // Tu MySQL User Name
$password = "QYCyVphFpO5F";      // Tu MySQL Password
$dbname = "if0_41551522_lectorapp"; // Tu Database Name

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Comprobar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
// Forzar utf8
$conn->set_charset("utf8");
?>