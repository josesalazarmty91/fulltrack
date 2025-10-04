<?php
// api/config.php

// Configuración de CORS: Permite que tu frontend acceda a esta API.
// En desarrollo, '*' es aceptable. PARA PRODUCCIÓN, REEMPLAZA '*' CON EL DOMINIO DE TU APLICACIÓN (ej. 'https://tudominio.com').
header("Access-Control-Allow-Origin: https://grupoamalgama.com.mx");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejo de solicitudes OPTIONS (pre-flight requests de CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Credenciales de la base de datos MySQL
$servername = "localhost"; // Generalmente 'localhost' en hosting compartido
$username = "grupoam6_diesel"; // Reemplaza con tu usuario de MySQL
$password = "Cortometraje@3"; // Reemplaza con tu contraseña de MySQL
$dbname = "grupoam6_diesel"; // Reemplaza con el nombre de tu base de datos

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    http_response_code(500); // Internal Server Error
    echo json_encode(["success" => false, "message" => "Error de conexión a la base de datos: " . $conn->connect_error]);
    exit();
}

// Establecer el charset para la conexión
$conn->set_charset("utf8mb4");
?>