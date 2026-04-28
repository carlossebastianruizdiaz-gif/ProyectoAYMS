<?php
include 'db.php';

// Generamos el hash real usando el algoritmo de PHP
$pass_clara = "admin123";
$nuevo_hash = password_hash($pass_clara, PASSWORD_DEFAULT);

// Actualizamos la base de datos
$sql = "UPDATE usuarios SET password_hash = ? WHERE email = 'admin@empresa.com'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nuevo_hash);

if ($stmt->execute()) {
    echo "<h1>¡Contraseña actualizada con éxito!</h1>";
    echo "<p>El nuevo hash generado es: <b>$nuevo_hash</b></p>";
    echo "<p>Ahora intenta loguearte con <b>admin123</b> en <a href='index.php'>el login</a>.</p>";
} else {
    echo "Error al actualizar: " . $conn->error;
}
?>