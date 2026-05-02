<?php
session_start();
include '../Capa de persistencia (BD)/db.php';

// Protección de Ruta (Solo Admin)
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header("Location: index.php");
    exit();
}

// GDIYV-29: Lógica del Filtro de Rango de Fechas
$rango = $_GET['rango'] ?? 'todo';
$filtro_fecha = "";

switch ($rango) {
    case 'hoy':
        $filtro_fecha = " AND DATE(v.fecha_hora) = CURDATE() ";
        break;
    case 'semana':
        $filtro_fecha = " AND v.fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ";
        break;
    case 'mes':
        $filtro_fecha = " AND MONTH(v.fecha_hora) = MONTH(CURDATE()) AND YEAR(v.fecha_hora) = YEAR(CURDATE()) ";
        break;
    default:
        // Si no es ninguno de los anteriores, no aplicamos filtro
        $filtro_fecha = ""; 
        break;
}
// 1. Ingresos Totales (Afectado por filtro)
$sql_ventas = "SELECT SUM(total_pagar) as ingresos FROM ventas v WHERE v.estado = 'Completada' $filtro_fecha";
$res_ventas = $conn->query($sql_ventas);
$ingresos = $res_ventas->fetch_assoc()['ingresos'] ?? 0;

// 2. Ganancia Neta (Afectado por filtro)
$sql_ganancia = "
    SELECT SUM((dv.precio_unitario - p.costo_compra) * dv.cantidad) as ganancia_neta 
    FROM detalle_venta dv
    JOIN productos p ON dv.id_producto = p.id_producto
    JOIN ventas v ON dv.id_venta = v.id_venta
    WHERE v.estado = 'Completada' $filtro_fecha";
$res_ganancia = $conn->query($sql_ganancia);
$ganancia = $res_ganancia->fetch_assoc()['ganancia_neta'] ?? 0;

// 3. Alertas de Stock Crítico (No se afecta por fecha, es el stock actual)
$sql_alertas = "SELECT COUNT(*) as alertas FROM productos WHERE stock_actual <= stock_minimo";
$res_alertas = $conn->query($sql_alertas);
$alertas_stock = $res_alertas->fetch_assoc()['alertas'] ?? 0;

// 4. Datos para el Gráfico Top 5 (Afectado por filtro)
$sql_top = "
    SELECT p.nombre, SUM(dv.cantidad) as total_vendido 
    FROM detalle_venta dv
    JOIN productos p ON dv.id_producto = p.id_producto
    JOIN ventas v ON dv.id_venta = v.id_venta
    WHERE v.estado = 'Completada' $filtro_fecha
    GROUP BY p.id_producto ORDER BY total_vendido DESC LIMIT 5";
$res_top = $conn->query($sql_top);
$nombres_prod = []; $cantidades_prod = [];
while($row = $res_top->fetch_assoc()) {
    $nombres_prod[] = $row['nombre'];
    $cantidades_prod[] = $row['total_vendido'];
}

// 5. Últimas 5 Ventas para la tabla (Afectado por filtro)
$sql_ultimas_ventas = "
    SELECT v.id_venta, v.fecha_hora, v.total_pagar, v.metodo_pago, u.nombre as cajero
    FROM ventas v
    JOIN usuarios u ON v.id_usuario = u.id_usuario
    WHERE v.estado = 'Completada' $filtro_fecha
    ORDER BY v.fecha_hora DESC LIMIT 5";
$res_ultimas_ventas = $conn->query($sql_ultimas_ventas);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin - StockFlow</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #f0f2f5; display: flex; color: #1e293b; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: white; height: 100vh; padding: 24px; box-shadow: 2px 0 10px rgba(0,0,0,0.03); display: flex; flex-direction: column; position: fixed;}
        .sidebar h2 { color: #1a73e8; display: flex; align-items: center; gap: 12px; font-size: 22px; font-weight: 800; margin-bottom: 40px; }
        .menu-item { display: flex; align-items: center; gap: 14px; padding: 14px; color: #64748b; text-decoration: none; border-radius: 10px; margin-bottom: 8px; transition: all 0.2s; font-weight: 500; }
        .menu-item:hover, .menu-item.active { background: #eff6ff; color: #1a73e8; font-weight: 600; }
        .logout { color: #ef4444; margin-top: auto; }
        .logout:hover { background: #fef2f2; color: #dc2626; }

        /* Main Content */
        .main-content { flex: 1; padding: 40px; margin-left: 260px; }
        .header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
        .header h1 { font-size: 28px; color: #0f172a; margin-bottom: 5px; }
        .header p { color: #64748b; }

        /* Cards Grid */
        .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 30px; }
        .card { background: white; padding: 24px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; }
        .card-info h3 { color: #64748b; font-size: 14px; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;}
        .card-info p { color: #0f172a; font-size: 28px; font-weight: bold; }
        
        .icon-box { padding: 16px; border-radius: 12px; }
        .icon-blue { background: #eff6ff; color: #3b82f6; }
        .icon-green { background: #f0fdf4; color: #22c55e; }
        .icon-red { background: #fef2f2; color: #ef4444; }

        /* Grid Inferior */
        .bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .panel { background: white; padding: 24px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .panel h3 { margin-bottom: 20px; font-size: 18px; color: #0f172a; }

        /* Estilos de Tabla */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; color: #64748b; font-weight: 600; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        td { padding: 14px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        tr:hover { background-color: #f8fafc; }
        .badge { background: #eff6ff; color: #3b82f6; padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 12px; }
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
                <h1>Visión General del Negocio</h1>
                <p>Bienvenido, Administrador. Aquí está el resumen de ventas.</p>
            </div>
            
            <div style="display: flex; gap: 15px; align-items: center;">
                <form method="GET" action="dashboard_admin.php" id="formFiltro">
                    <select name="rango" onchange="document.getElementById('formFiltro').submit()" 
                            style="padding: 10px; border-radius: 8px; border: 1px solid #ddd; outline: none; background: white; color: #334155; font-weight: 500;">
                        <option value="todo" <?php echo $rango == 'todo' ? 'selected' : ''; ?>>Histórico Completo</option>
                        <option value="hoy" <?php echo $rango == 'hoy' ? 'selected' : ''; ?>>Solo Hoy</option>
                        <option value="semana" <?php echo $rango == 'semana' ? 'selected' : ''; ?>>Últimos 7 Días</option>
                        <option value="mes" <?php echo $rango == 'mes' ? 'selected' : ''; ?>>Este Mes</option>
                    </select>
                </form>

                <button style="padding: 10px 20px; background: #1a73e8; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s;">
                    <i data-lucide="download" style="width: 18px;"></i> Exportar
                </button>
            </div>
        </div>
        <div class="cards">
            <div class="card">
                <div class="card-info">
                    <h3>Ingresos Brutos</h3>
                    <p>Bs <?php echo number_format($ingresos, 2); ?></p>
                </div>
                <div class="icon-box icon-blue"><i data-lucide="wallet" style="width: 32px; height: 32px;"></i></div>
            </div>
            
            <div class="card">
                <div class="card-info">
                    <h3>Ganancia Neta</h3>
                    <p>Bs <?php echo number_format($ganancia, 2); ?></p>
                </div>
                <div class="icon-box icon-green"><i data-lucide="trending-up" style="width: 32px; height: 32px;"></i></div>
            </div>

            <div class="card">
                <div class="card-info">
                    <h3>Alertas de Stock</h3>
                    <p style="color: #ef4444;"><?php echo $alertas_stock; ?> Productos</p>
                </div>
                <div class="icon-box icon-red"><i data-lucide="alert-triangle" style="width: 32px; height: 32px;"></i></div>
            </div>
        </div>

        <div class="bottom-grid">
            <div class="panel">
                <h3>Top 5: Mayor Rotación</h3>
                <div style="height: 300px;">
                    <canvas id="rotacionChart"></canvas>
                </div>
            </div>

            <div class="panel">
                <h3>Últimas Transacciones</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID Venta</th>
                            <th>Cajero</th>
                            <th>Método</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($fila = $res_ultimas_ventas->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad($fila['id_venta'], 4, "0", STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo $fila['cajero']; ?></td>
                            <td><span class="badge"><?php echo $fila['metodo_pago']; ?></span></td>
                            <td style="font-weight: bold; color: #0f172a;">Bs <?php echo number_format($fila['total_pagar'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if($res_ultimas_ventas->num_rows == 0): ?>
                        <tr><td colspan="4" style="text-align:center; padding: 30px; color: #94a3b8;">No hay ventas registradas en este periodo.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const ctx = document.getElementById('rotacionChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($nombres_prod); ?>,
                datasets: [{
                    label: 'Unidades Vendidas',
                    data: <?php echo json_encode($cantidades_prod); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: '#3b82f6',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { 
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
    
</body>
</html>