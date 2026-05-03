<?php
include '../Capa de persistencia (BD)/db.php'; 

// Vamos a forzar que tu clave vuelva a ser "12345"
$nueva_clave = "12345";
$hash = password_hash($nueva_clave, PASSWORD_DEFAULT);

$sql = "UPDATE usuarios SET password_hash = '$hash' WHERE email = 'admin@empresa.com'";

if ($conn->query($sql)) {
    echo "<h2 style='color: green;'>¡Éxito! Tu contraseña ha vuelto a ser: 12345</h2>";
    echo "<p>Ya puedes volver a <a href='index.php'>iniciar sesión</a> y luego BORRA este archivo.</p>";
} else {
    echo "Error: " . $conn->error;
}
?>