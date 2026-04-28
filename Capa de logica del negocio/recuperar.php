<?php
include '../Capa de logica del negocio/db.php';
$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    
    // Verificar si el correo existe en la base de datos
    $sql = "SELECT id_usuario FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // En un entorno real, aquí se usaría la función mail() de PHP.
        // Para la defensa, mostramos un mensaje de éxito simulado.
        $mensaje = "<div style='color: green; font-weight: bold; margin-bottom: 15px;'>
                    Se ha enviado un enlace de recuperación a tu correo electrónico. 
                    (Simulación XAMPP)</div>";
    } else {
        $mensaje = "<div style='color: red; font-weight: bold; margin-bottom: 15px;'>
                    No existe ninguna cuenta asociada a este correo.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar Contraseña - StockFlow</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Reutilizamos el estilo limpio de tu Login */
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .recovery-card { background: white; padding: 40px; border-radius: 12px; width: 100%; max-width: 400px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); text-align: center; }
        input { width: 100%; padding: 12px; margin: 15px 0; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #1a73e8; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        a { display: block; margin-top: 15px; color: #1a73e8; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>

<div class="recovery-card">
    <i data-lucide="key-round" style="width: 48px; height: 48px; color: #1a73e8; margin-bottom: 10px;"></i>
    <h2>Recuperar Contraseña</h2>
    <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Ingresa tu correo y te enviaremos las instrucciones para resetear tu clave.</p>
    
    <?php echo $mensaje; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Tu correo electrónico registrado" required>
        <button type="submit">Enviar Enlace</button>
    </form>
    
    <a href="index.php">Volver al Inicio de Sesión</a>
</div>

<script>lucide.createIcons();</script>
</body>
</html>