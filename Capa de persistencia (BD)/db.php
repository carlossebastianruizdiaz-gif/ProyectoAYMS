<?php


// Ajusta la ruta dependiendo de dónde llames a este archivo
$ini_path = __DIR__ . '/../config.ini'; 

if (file_exists($ini_path)) {
    $config = parse_ini_file($ini_path);

    $host = $config['host'];
    $user = $config['user'];
    $pass = $config['pass'];
    $db   = $config['name'];

    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
} else {
    die("Error: Archivo de configuración no encontrado.");
}
?>