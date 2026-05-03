<?php
session_start();
include '../Capa de persistencia (BD)/db.php';

// Protección de Ruta (Solo el Administrador de nivel 1 puede gestionar personal)
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header("Location: index.php");
    exit();
}

$mensaje = "";

// PROCESAR CRUD DE PERSONAL (CREAR, EDITAR, ELIMINAR)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion_personal'])) {
    $accion = $_POST['accion_personal'];
    
    // 1. CREAR USUARIO
    if ($accion == 'crear') {
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT); 
        $rol = $_POST['id_rol'];

        $check_email = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        
        if ($check_email->get_result()->num_rows > 0) {
            $mensaje = "<div style='color: #ef4444; background: #fee2e2; padding: 10px; border-radius: 8px; margin-bottom: 15px;'>El correo ya está registrado.</div>";
        } else {
            $sql = "INSERT INTO usuarios (nombre, email, password_hash, id_rol) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $nombre, $email, $password, $rol);
            if ($stmt->execute()) {
                $mensaje = "<div style='color: #166534; background: #dcfce7; padding: 10px; border-radius: 8px; margin-bottom: 15px;'>Usuario creado exitosamente.</div>";
            }
        }
    } 
    // 2. EDITAR USUARIO
    elseif ($accion == 'editar') {
        $id_usuario = $_POST['id_usuario'];
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $rol = $_POST['id_rol'];
        $password_raw = $_POST['password'];

        // Verificar que el correo no pertenezca a OTRO usuario
        $check_email = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
        $check_email->bind_param("si", $email, $id_usuario);
        $check_email->execute();

        if ($check_email->get_result()->num_rows > 0) {
            $mensaje = "<div style='color: #ef4444; background: #fee2e2; padding: 10px; border-radius: 8px; margin-bottom: 15px;'>El correo ya está en uso por otro empleado.</div>";
        } else {
            // Si dejó la contraseña en blanco, no la actualizamos
            if (empty($password_raw)) {
                $sql = "UPDATE usuarios SET nombre=?, email=?, id_rol=? WHERE id_usuario=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssii", $nombre, $email, $rol, $id_usuario);
            } else {
                // Si escribió algo, generamos el nuevo hash y lo guardamos
                $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET nombre=?, email=?, password_hash=?, id_rol=? WHERE id_usuario=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssii", $nombre, $email, $password_hash, $rol, $id_usuario);
            }
            if ($stmt->execute()) {
                $mensaje = "<div style='color: #166534; background: #dcfce7; padding: 10px; border-radius: 8px; margin-bottom: 15px;'>Usuario actualizado exitosamente.</div>";
            }
        }
    }
    // 3. ELIMINAR USUARIO
    elseif ($accion == 'eliminar') {
        $id_usuario = $_POST['id_usuario'];
        
        // Medida de seguridad: Evitar que el administrador borre su propia cuenta en uso
        if ($id_usuario == $_SESSION['usuario_id']) {
            $mensaje = "<div style='color: #ef4444; background: #fee2e2; padding: 10px; border-radius: 8px; margin-bottom: 15px;'>Error crítico: No puedes eliminar tu propia cuenta de Administrador.</div>";
        } else {
            $sql_del = "DELETE FROM usuarios WHERE id_usuario = ?";
            $stmt_del = $conn->prepare($sql_del);
            $stmt_del->bind_param("i", $id_usuario);
            if ($stmt_del->execute()) {
                $mensaje = "<div style='color: #166534; background: #dcfce7; padding: 10px; border-radius: 8px; margin-bottom: 15px;'>Usuario retirado del sistema.</div>";
            }
        }
    }
}

// OBTENER LISTA DE PERSONAL (Traemos también u.id_rol para mandarlo al JavaScript)
$sql_personal = "SELECT u.id_usuario, u.nombre, u.email, u.id_rol, r.nombre_rol 
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
        .form-group input:focus, .form-group select:focus { border-color: #1a73e8; }
        
        .btn-crear { width: 100%; padding: 12px; background: #1a73e8; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.2s; flex: 2;}
        .btn-crear:hover { background: #1557b0; }
        
        .btn-cancelar { padding: 12px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.2s; flex: 1; display: none;}
        .btn-cancelar:hover { background: #e2e8f0; }

        /* Botones de acción tabla */
        .btn-icon { background: none; border: none; cursor: pointer; padding: 4px; transition: 0.2s; color: #94a3b8; }
        .btn-icon.edit:hover { color: #1a73e8; }
        .btn-icon.delete:hover { color: #ef4444; }

        /* Tabla */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; color: #64748b; font-size: 13px; border-bottom: 2px solid #f1f5f9; text-transform: uppercase; }
        td { padding: 14px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .badge { padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 12px; }
        .badge-admin { background: #fef2f2; color: #ef4444; }
        .badge-cajero { background: #eff6ff; color: #3b82f6; }
        .badge-almacen { background: #fef3c7; color: #d97706; }
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
        <a href="historial.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'historial.php' ? 'active' : ''; ?>">
            <i data-lucide="history"></i> Bitácora
        </a>
        <a href="personal.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'personal.php' ? 'active' : ''; ?>">
            <i data-lucide="users"></i> Personal
        </a>
        <?php endif; ?>
        
        <a href="index.php?logout=true" class="menu-item logout" style="margin-top: auto;">
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
                <h3 id="form_titulo">Registrar Nuevo Usuario</h3>
                <?php echo $mensaje; ?>
                
                <form method="POST">
                    <!-- Campos ocultos para controlar si es crear o editar -->
                    <input type="hidden" name="accion_personal" id="form_accion" value="crear">
                    <input type="hidden" name="id_usuario" id="form_id_usuario" value="">
                    
                    <div class="form-group">
                        <label>Nombre Completo</label>
                        <input type="text" name="nombre" id="form_nombre" placeholder="Ej. Juan Pérez" required>
                    </div>
                    <div class="form-group">
                        <label>Correo Electrónico</label>
                        <input type="email" name="email" id="form_email" placeholder="juan@empresa.com" required>
                    </div>
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" name="password" id="form_password" placeholder="••••••••" required>
                    </div>
                    <div class="form-group">
                        <label>Rol del Usuario</label>
                        <select name="id_rol" id="form_rol" required>
                         <option value="1">Administrador (Acceso Total)</option>
                         <option value="2">Cajero (Solo Ventas)</option>
                         <option value="3">Encargado de Almacén (Solo Inventario)</option>
                         </select>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn-cancelar" id="btn_cancelar" onclick="cancelarEdicion()">Cancelar</button>
                        <button type="submit" class="btn-crear" id="btn_submit">Registrar Empleado</button>
                    </div>
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
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($user = $res_personal->fetch_assoc()): 
                            $clase_badge = 'badge-cajero';
                            if ($user['nombre_rol'] == 'Administrador') $clase_badge = 'badge-admin';
                            if ($user['nombre_rol'] == 'Encargado de Almacen') $clase_badge = 'badge-almacen';
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge <?php echo $clase_badge; ?>">
                                    <?php echo htmlspecialchars($user['nombre_rol']); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <!-- Botón Editar -->
                                <button class="btn-icon edit" title="Editar Usuario" onclick="editarUsuario(
                                    <?php echo $user['id_usuario']; ?>, 
                                    '<?php echo addslashes($user['nombre']); ?>', 
                                    '<?php echo addslashes($user['email']); ?>', 
                                    <?php echo $user['id_rol']; ?>
                                )"><i data-lucide="edit-3" style="width:18px;"></i></button>

                                <!-- Botón Eliminar -->
                                <button class="btn-icon delete" title="Eliminar Usuario" onclick="eliminarUsuario(<?php echo $user['id_usuario']; ?>)">
                                    <i data-lucide="trash-2" style="width:18px;"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function editarUsuario(id, nombre, email, id_rol) {
            // Cambiar modo del formulario
            document.getElementById('form_accion').value = 'editar';
            document.getElementById('form_id_usuario').value = id;
            
            // Cargar datos
            document.getElementById('form_nombre').value = nombre;
            document.getElementById('form_email').value = email;
            document.getElementById('form_rol').value = id_rol;
            
            // La contraseña es opcional al editar
            const pwdInput = document.getElementById('form_password');
            pwdInput.required = false;
            pwdInput.value = '';
            pwdInput.placeholder = "(Opcional) Nueva contraseña...";
            
            // Cambiar interfaz
            document.getElementById('form_titulo').innerText = 'Editar Usuario';
            document.getElementById('btn_submit').innerText = 'Guardar Cambios';
            document.getElementById('btn_cancelar').style.display = 'block';
            
            // Scroll arriba suave
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function cancelarEdicion() {
            // Restaurar modo crear
            document.getElementById('form_accion').value = 'crear';
            document.getElementById('form_id_usuario').value = '';
            
            // Limpiar datos
            document.getElementById('form_nombre').value = '';
            document.getElementById('form_email').value = '';
            document.getElementById('form_rol').value = '1';
            
            // La contraseña vuelve a ser obligatoria
            const pwdInput = document.getElementById('form_password');
            pwdInput.required = true;
            pwdInput.value = '';
            pwdInput.placeholder = "••••••••";
            
            // Restaurar interfaz
            document.getElementById('form_titulo').innerText = 'Registrar Nuevo Usuario';
            document.getElementById('btn_submit').innerText = 'Registrar Empleado';
            document.getElementById('btn_cancelar').style.display = 'none';
        }

        function eliminarUsuario(id) {
            if (confirm("¿Estás seguro de que deseas eliminar este usuario del sistema?")) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'personal.php';

                const inputAccion = document.createElement('input');
                inputAccion.type = 'hidden';
                inputAccion.name = 'accion_personal';
                inputAccion.value = 'eliminar';

                const inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'id_usuario';
                inputId.value = id;

                form.appendChild(inputAccion);
                form.appendChild(inputId);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>