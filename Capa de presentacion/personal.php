<?php
session_start();
include '../Capa de persistencia (BD)/db.php';

// Protección de Ruta (Solo el Administrador de nivel 1 puede gestionar personal)
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header("Location: index.php");
    exit();
}

$mensaje = "";

// PROCESAR REGISTRO DE NUEVO PERSONAL
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['crear_usuario'])) {
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Encriptación profesional
    $rol = $_POST['id_rol'];

    // Verificar si el correo ya existe para evitar duplicados
    $check_email = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    $check_email->bind_param("s", $email);
    $check_email->execute();
    
    if ($check_email->get_result()->num_rows > 0) {
        $mensaje = "<div style='color: #ef4444; margin-bottom: 15px;'>El correo ya está registrado.</div>";
    } else {
        $sql = "INSERT INTO usuarios (nombre, email, password_hash, id_rol) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $nombre, $email, $password, $rol);
        
        if ($stmt->execute()) {
            $mensaje = "<div style='color: #22c55e; margin-bottom: 15px;'>Usuario creado exitosamente.</div>";
        }
    }
}

// OBTENER LISTA DE PERSONAL (Con JOIN para ver el nombre del Rol)
$sql_personal = "SELECT u.id_usuario, u.nombre, u.email, r.nombre_rol 
                 FROM usuarios u 
                 JOIN roles r ON u.id_rol = r.id_rol 
                 ORDER BY r.id_rol ASC, u.nombre ASC";
$res_personal = $conn->query($sql_personal);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Personal - StockFlow</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f0f2f5; display: flex; color: #1e293b; }
        
        /* Sidebar Profesional */
        .sidebar { width: 260px; background: white; height: 100vh; padding: 24px; box-shadow: 2px 0 10px rgba(0,0,0,0.03); display: flex; flex-direction: column; position: fixed;}
        .sidebar h2 { color: #1a73e8; display: flex; align-items: center; gap: 12px; font-size: 22px; font-weight: 800; margin-bottom: 40px; }
        .menu-item { display: flex; align-items: center; gap: 14px; padding: 14px; color: #64748b; text-decoration: none; border-radius: 10px; margin-bottom: 8px; transition: 0.2s; }
        .menu-item:hover, .menu-item.active { background: #eff6ff; color: #1a73e8; font-weight: 600; }
        .logout { color: #ef4444; margin-top: auto; }

        /* Contenido */
        .main-content { flex: 1; padding: 40px; margin-left: 260px; }
        .header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
        
        /* Contenedores */
        .grid-personal { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
        .panel { background: white; padding: 24px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .panel h3 { margin-bottom: 20px; font-size: 18px; color: #0f172a; }

        /* Formulario */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 14px; font-weight: 600; color: #475569; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; }
        .btn-crear { width: 100%; padding: 12px; background: #1a73e8; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-crear:hover { background: #1557b0; }

        /* Tabla */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; color: #64748b; font-size: 13px; border-bottom: 2px solid #f1f5f9; text-transform: uppercase; }
        td { padding: 14px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .badge { padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 12px; }
        .badge-admin { background: #fef2f2; color: #ef4444; }
        .badge-cajero { background: #eff6ff; color: #3b82f6; }
    </style>
</head>
<body>

    <div class="sidebar">
    <h2><i data-lucide="package" style="width: 28px; height: 28px;"></i> StockFlow</h2>
    
    <?php if ($_SESSION['rol_id'] == 1): ?>
    <a href="dashboard_admin.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard_admin.php' ? 'active' : ''; ?>">
        <i data-lucide="layout-dashboard"></i> Dashboard
    </a>
    <?php endif; ?>

    <?php if (in_array($_SESSION['rol_id'], [1, 3])): ?>
    <a href="inventario.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'inventario.php' ? 'active' : ''; ?>">
        <i data-lucide="box"></i> Inventario
    </a>
    <?php endif; ?>

    <?php if ($_SESSION['rol_id'] == 1): ?>
    <a href="personal.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'personal.php' ? 'active' : ''; ?>">
        <i data-lucide="users"></i> Personal
    </a>
    <?php endif; ?>

    <a href="index.php" class="menu-item logout" style="margin-top: auto;">
        <i data-lucide="log-out"></i> Cerrar Sesión
    </a>
</div>

    <div class="main-content">
        <div class="header">
            <div>
                <h1>Gestión de Personal</h1>
                <p>Administra las cuentas de acceso y permisos de los empleados.</p>
            </div>
        </div>

        <div class="grid-personal">
            <div class="panel">
                <h3>Registrar Nuevo Usuario</h3>
                <?php echo $mensaje; ?>
                <form method="POST">
                    <input type="hidden" name="crear_usuario" value="1">
                    <div class="form-group">
                        <label>Nombre Completo</label>
                        <input type="text" name="nombre" placeholder="Ej. Juan Pérez" required>
                    </div>
                    <div class="form-group">
                        <label>Correo Electrónico</label>
                        <input type="email" name="email" placeholder="juan@empresa.com" required>
                    </div>
                    <div class="form-group">
                        <label>Contraseña Temporal</label>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>
                    <div class="form-group">
                        <label>Rol del Usuario</label>
                        <select name="id_rol" required>
                         <option value="1">Administrador (Acceso Total)</option>
                         <option value="2">Cajero (Solo Ventas)</option>
                         <option value="3">Encargado de Almacén (Solo Inventario)</option>
                         </select>
                    </div>
                    <button type="submit" class="btn-crear">Registrar Empleado</button>
                </form>
            </div>

            <div class="panel">
                <h3>Equipo Registrado</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($user = $res_personal->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo $user['nombre']; ?></strong></td>
                            <td><?php echo $user['email']; ?></td>
                            <td>
                                <span class="badge <?php echo ($user['nombre_rol'] == 'Administrador') ? 'badge-admin' : 'badge-cajero'; ?>">
                                    <?php echo $user['nombre_rol']; ?>
                                </span>
                            </td>
                            <td>
                                <button style="background:none; border:none; cursor:pointer; color:#94a3b8;"><i data-lucide="trash-2" style="width:18px;"></i></button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>