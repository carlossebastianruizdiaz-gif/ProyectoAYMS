<?php
session_start();
// Conexión a la base de datos
include '../Capa de persistencia (BD)/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Buscamos al usuario
    $sql = "SELECT id_usuario, nombre, password_hash, id_rol FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password_hash'])) {
            
            // Guardamos la sesión
            $_SESSION['usuario_id'] = $row['id_usuario'];
            $_SESSION['nombre'] = $row['nombre'];
            $_SESSION['rol_id'] = $row['id_rol'];

            // --- LÓGICA DE REDIRECCIÓN POR ROL ---
            
            if ($row['id_rol'] == 1) {
                // Administrador -> Dashboard
                header("Location: ../Capa de presentacion/dashboard_admin.php");
                exit();
            } 
            else if ($row['id_rol'] == 2) {
                // Cajero -> Punto de Venta
                header("Location: ../Capa de presentacion/pos_vendedor.php");
                exit();
            } 
            else if ($row['id_rol'] == 3) {
                // ENCARGADO DE ALMACÉN -> Inventario 
                header("Location: ../Capa de presentacion/inventario.php");
                exit();
            } 
            else {
                // LA RED DE SEGURIDAD: Atrapa cualquier rol no reconocido
                $rol_detectado = $row['id_rol'];
                echo "<script>alert('Error crítico: Tienes el rol número [$rol_detectado] y no existe una pantalla para ti.'); window.location.href='../Capa de presentacion/index.php';</script>";
                exit();
            }

        } else {
            echo "<script>alert('Contraseña incorrecta'); window.location.href='../Capa de presentacion/index.php';</script>";
        }
    } else {
        echo "<script>alert('Usuario no encontrado'); window.location.href='../Capa de presentacion/index.php';</script>";
    }
}
?>