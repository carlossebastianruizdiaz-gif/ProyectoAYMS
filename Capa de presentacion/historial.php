<?php
session_start();
include '../Capa de persistencia (BD)/db.php';

// Solo el Administrador (rol 1) puede ver esta pantalla
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header("Location: index.php");
    exit();
}

$sql_historial = "SELECT * FROM historial_inventario ORDER BY fecha DESC";
$res_historial = $conn->query($sql_historial);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bitácora - StockFlow</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #f0f2f5; display: flex; color: #1e293b; }
        .sidebar { width: 260px; background: white; height: 100vh; padding: 24px; box-shadow: 2px 0 10px rgba(0,0,0,0.03); display: flex; flex-direction: column; position: fixed;}
        .sidebar h2 { color: #1a73e8; display: flex; align-items: center; gap: 12px; font-size: 22px; font-weight: 800; margin-bottom: 40px; }
        .menu-item { display: flex; align-items: center; gap: 14px; padding: 14px; color: #64748b; text-decoration: none; border-radius: 10px; margin-bottom: 8px; transition: all 0.2s; font-weight: 500; }
        .menu-item:hover, .menu-item.active { background: #eff6ff; color: #1a73e8; font-weight: 600; }
        .logout { color: #ef4444; margin-top: auto; }
        .main-content { flex: 1; padding: 40px; margin-left: 260px; }
        .header { margin-bottom: 30px; }
        .header h1 { font-size: 28px; color: #0f172a; margin-bottom: 5px; }
        .panel { background: white; padding: 24px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px; color: #64748b; font-weight: 600; border-bottom: 2px solid #e2e8f0; font-size: 14px; text-transform: uppercase; }
        td { padding: 16px 14px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; vertical-align: middle; }
        tr:hover { background-color: #f8fafc; }
        
        .badge { padding: 6px 10px; border-radius: 6px; font-weight: 600; font-size: 12px; }
        .b-creacion { background: #dcfce7; color: #166534; }
        .b-edicion { background: #fef08a; color: #854d0e; }
        .b-eliminacion { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2><i data-lucide="package" style="width: 28px; height: 28px;"></i> StockFlow</h2>
        <a href="dashboard_admin.php" class="menu-item"><i data-lucide="layout-dashboard"></i> Dashboard</a>
        <a href="inventario.php" class="menu-item"><i data-lucide="box"></i> Inventario</a>
        <a href="historial.php" class="menu-item active"><i data-lucide="history"></i> Bitácora</a>
        <a href="personal.php" class="menu-item"><i data-lucide="users"></i> Personal</a>
        <a href="index.php" class="menu-item logout"><i data-lucide="log-out"></i> Cerrar Sesión</a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Bitácora de Auditoría</h1>
            <p style="color: #64748b;">Registro de todas las modificaciones realizadas en el inventario.</p>
        </div>

        <div class="panel">
            <table>
                <thead>
                    <tr>
                        <th>Fecha y Hora</th>
                        <th>Usuario (Responsable)</th>
                        <th>Acción</th>
                        <th>Producto Afectado</th>
                        <th>Detalles del Cambio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($res_historial && $res_historial->num_rows > 0): ?>
                        <?php while($log = $res_historial->fetch_assoc()): 
                            $fecha_formateada = date("d/m/Y - h:i A", strtotime($log['fecha']));
                            
                            $clase_badge = 'b-edicion';
                            if ($log['accion'] == 'NUEVO PRODUCTO') $clase_badge = 'b-creacion';
                            if ($log['accion'] == 'ELIMINACIÓN') $clase_badge = 'b-eliminacion';
                        ?>
                        <tr>
                            <td style="color: #64748b; font-weight: 500;"><?php echo $fecha_formateada; ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px; font-weight: bold;">
                                    <div style="background: #e2e8f0; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #475569;">
                                        <i data-lucide="user" style="width: 14px;"></i>
                                    </div>
                                    <?php echo htmlspecialchars($log['usuario_nombre']); ?>
                                </div>
                            </td>
                            <td><span class="badge <?php echo $clase_badge; ?>"><?php echo $log['accion']; ?></span></td>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($log['producto_nombre']); ?></td>
                            <td style="color: #475569; font-size: 13px;"><?php echo htmlspecialchars($log['detalle']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding: 30px; color: #94a3b8;">No hay registros en la bitácora aún.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>