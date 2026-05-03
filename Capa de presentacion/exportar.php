<?php
session_start();
include '../Capa de persistencia (BD)/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    die("Acceso denegado. No tienes permisos para realizar esta acción.");
}

// Limpiamos la variable para que solo acepte letras y números (Evita inyección en el nombre del archivo)
$tipo_raw = isset($_GET['tipo']) ? $_GET['tipo'] : 'inventario';
$tipo = preg_replace('/[^a-zA-Z0-9_]/', '', $tipo_raw);

if ($tipo == 'financiero') {
    $nombre_archivo = "Reporte_Mensual_Financiero_StockFlow_" . $fecha_actual . ".xls";
} else {
    $nombre_archivo = "Reporte_" . ucfirst($tipo) . "_StockFlow_" . $fecha_actual . ".xls";
}

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=" . $nombre_archivo);
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF"; 
echo "<table border='1' style='font-family: Arial, sans-serif; border-collapse: collapse;'>";

// ---------------------------------------------------------
// 1. REPORTE DE INVENTARIO BÁSICO
// ---------------------------------------------------------
if ($tipo == 'inventario') {
    echo "<tr style='background-color: #1a73e8; color: white; font-weight: bold;'>
            <th style='padding: 10px;'>ID</th>
            <th style='padding: 10px;'>Código de Barras</th>
            <th style='padding: 10px;'>Nombre del Producto</th>
            <th style='padding: 10px;'>Categoría</th>
            <th style='padding: 10px;'>Costo (Bs)</th>
            <th style='padding: 10px;'>Precio (Bs)</th>
            <th style='padding: 10px;'>Stock Actual</th>
          </tr>";

    $sql = "SELECT p.*, c.nombre_categoria FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id_categoria ORDER BY p.nombre ASC";
    $result = $conn->query($sql);

    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='text-align: center;'>" . $row['id_producto'] . "</td>";
        echo "<td style='mso-number-format:\"\\\@\"; text-align: center;'>" . $row['codigo_barras'] . "</td>"; 
        echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nombre_categoria'] ?? 'Sin categoría') . "</td>";
        echo "<td style='text-align: right;'>" . number_format($row['costo_compra'], 2) . "</td>";
        echo "<td style='text-align: right;'>" . number_format($row['precio_venta'], 2) . "</td>";
        echo "<td style='text-align: center; font-weight: bold;'>" . $row['stock_actual'] . "</td>";
        echo "</tr>";
    }

// ---------------------------------------------------------
// 2. REPORTE DE BITÁCORA (HISTORIAL)
// ---------------------------------------------------------
} elseif ($tipo == 'bitacora') {
    echo "<tr style='background-color: #1a73e8; color: white; font-weight: bold;'>
            <th style='padding: 10px;'>Fecha y Hora</th>
            <th style='padding: 10px;'>Usuario</th>
            <th style='padding: 10px;'>Acción</th>
            <th style='padding: 10px;'>Producto Afectado</th>
            <th style='padding: 10px;'>Detalles del Cambio</th>
          </tr>";

    $sql = "SELECT * FROM historial_inventario ORDER BY fecha DESC";
    $result = $conn->query($sql);

    while($row = $result->fetch_assoc()) {
        $fecha_formateada = date("d/m/Y - H:i", strtotime($row['fecha']));
        echo "<tr>";
        echo "<td style='text-align: center;'>" . $fecha_formateada . "</td>";
        echo "<td>" . htmlspecialchars($row['usuario_nombre']) . "</td>";
        echo "<td style='text-align: center; font-weight: bold;'>" . htmlspecialchars($row['accion']) . "</td>";
        echo "<td>" . htmlspecialchars($row['producto_nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($row['detalle']) . "</td>";
        echo "</tr>";
    }

// ---------------------------------------------------------
// 3. REPORTE FINANCIERO DINÁMICO + AUDITORÍA DE INVENTARIO
// ---------------------------------------------------------
} elseif ($tipo == 'financiero') {
    
    $rango = isset($_GET['rango']) ? $_GET['rango'] : 'todo';
    $fecha_filtro = isset($_GET['fecha_filtro']) ? $_GET['fecha_filtro'] : '';
    $mes_filtro = isset($_GET['mes_filtro']) ? $_GET['mes_filtro'] : '';
    $anio_filtro = isset($_GET['anio_filtro']) ? $_GET['anio_filtro'] : '';
    
    $filtro_fecha = "";
    $titulo_reporte = "";

    if (!empty($fecha_filtro)) {
        $filtro_fecha = " AND DATE(v.fecha_hora) = '" . $conn->real_escape_string($fecha_filtro) . "' ";
        $titulo_reporte = "REPORTE FINANCIERO DEL DÍA - " . date('d/m/Y', strtotime($fecha_filtro));
    } elseif (!empty($mes_filtro)) {
        $partes = explode('-', $mes_filtro);
        $anio = $conn->real_escape_string($partes[0]);
        $mes = $conn->real_escape_string($partes[1]);
        $filtro_fecha = " AND MONTH(v.fecha_hora) = '$mes' AND YEAR(v.fecha_hora) = '$anio' ";
        $meses = ['01'=>'Enero', '02'=>'Febrero', '03'=>'Marzo', '04'=>'Abril', '05'=>'Mayo', '06'=>'Junio', '07'=>'Julio', '08'=>'Agosto', '09'=>'Septiembre', '10'=>'Octubre', '11'=>'Noviembre', '12'=>'Diciembre'];
        $nombre_mes = $meses[$mes] ?? $mes;
        $titulo_reporte = "REPORTE FINANCIERO MENSUAL - " . strtoupper($nombre_mes . " " . $anio);
    } elseif (!empty($anio_filtro)) {
        $anio = $conn->real_escape_string($anio_filtro);
        $filtro_fecha = " AND YEAR(v.fecha_hora) = '$anio' ";
        $titulo_reporte = "REPORTE FINANCIERO ANUAL - " . $anio;
    } else {
        switch ($rango) {
            case 'hoy':
                $filtro_fecha = " AND DATE(v.fecha_hora) = CURDATE() ";
                $titulo_reporte = "REPORTE FINANCIERO DIARIO - " . date('d/m/Y');
                break;
            case 'semana':
                $filtro_fecha = " AND v.fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ";
                $titulo_reporte = "REPORTE FINANCIERO - ÚLTIMOS 7 DÍAS";
                break;
            case 'mes':
                $filtro_fecha = " AND MONTH(v.fecha_hora) = MONTH(CURRENT_DATE()) AND YEAR(v.fecha_hora) = YEAR(CURRENT_DATE()) ";
                $titulo_reporte = "REPORTE FINANCIERO MENSUAL - " . strtoupper(date('F Y'));
                break;
            case 'todo':
            default:
                $filtro_fecha = "";
                $titulo_reporte = "REPORTE FINANCIERO - HISTÓRICO COMPLETO";
                break;
        }
    }

    // TÍTULO DEL REPORTE DESINFECTADO (Previene XSS)
    $titulo_seguro = htmlspecialchars($titulo_reporte, ENT_QUOTES, 'UTF-8');
    echo "<tr><th colspan='6' style='background-color: #0f172a; color: white; font-size: 18px; padding: 15px;'>{$titulo_seguro}</th></tr>";
    echo "<tr><td colspan='6'></td></tr>";

    // SECCIÓN A: INGRESOS Y GANANCIAS
    echo "<tr><th colspan='6' style='background-color: #1a73e8; color: white; text-align: left; padding: 10px;'>1. RESUMEN DE INGRESOS (VENTAS COMPLETADAS)</th></tr>";
    echo "<tr style='background-color: #f1f5f9; font-weight: bold;'>
            <th colspan='3' style='padding: 10px;'>Ingresos Brutos (Total Ventas)</th>
            <th colspan='3' style='padding: 10px;'>Ganancia Neta (Utilidad Real)</th>
          </tr>";

    $sql_ingresos = "SELECT COALESCE(SUM(v.total_pagar), 0) AS ingresos_brutos 
                     FROM ventas v 
                     WHERE v.estado = 'Completada' $filtro_fecha";
    $res_ingresos = $conn->query($sql_ingresos);
    $ingresos_brutos = $res_ingresos->fetch_assoc()['ingresos_brutos'];

    $sql_ganancia = "SELECT COALESCE(SUM(dv.cantidad * (dv.precio_unitario - p.costo_compra)), 0) AS ganancia_neta
                     FROM ventas v
                     JOIN detalle_venta dv ON v.id_venta = dv.id_venta
                     JOIN productos p ON dv.id_producto = p.id_producto
                     WHERE v.estado = 'Completada' $filtro_fecha";
    $res_ganancia = $conn->query($sql_ganancia);
    $ganancia_neta = $res_ganancia->fetch_assoc()['ganancia_neta'];

    echo "<tr>
            <td colspan='3' style='font-size: 16px; font-weight: bold; text-align: center; color: #1a73e8;'>Bs " . number_format($ingresos_brutos, 2) . "</td>
            <td colspan='3' style='font-size: 16px; font-weight: bold; text-align: center; color: #166534;'>Bs " . number_format($ganancia_neta, 2) . "</td>
          </tr>";
    echo "<tr><td colspan='6'></td></tr>";

    // SECCIÓN B: TOP 6 PRODUCTOS MÁS VENDIDOS
    echo "<tr><th colspan='6' style='background-color: #1a73e8; color: white; text-align: left; padding: 10px;'>2. TOP 6 PRODUCTOS CON MAYOR ROTACIÓN</th></tr>";
    echo "<tr style='background-color: #f1f5f9; font-weight: bold;'>
            <th colspan='4' style='padding: 10px;'>Producto</th>
            <th style='padding: 10px;'>Cantidad Vendida</th>
            <th style='padding: 10px;'>Ingresos Generados (Bs)</th>
          </tr>";

    $sql_top = "SELECT p.nombre, SUM(dv.cantidad) as total_vendido, SUM(dv.cantidad * dv.precio_unitario) as ingresos_generados
                FROM detalle_venta dv
                JOIN ventas v ON dv.id_venta = v.id_venta
                JOIN productos p ON dv.id_producto = p.id_producto
                WHERE v.estado = 'Completada' $filtro_fecha
                GROUP BY dv.id_producto
                ORDER BY total_vendido DESC LIMIT 6";
    $res_top = $conn->query($sql_top);

    if ($res_top && $res_top->num_rows > 0) {
        while($top = $res_top->fetch_assoc()) {
            echo "<tr>
                    <td colspan='4'>" . htmlspecialchars($top['nombre']) . "</td>
                    <td style='text-align: center; font-weight: bold;'>" . $top['total_vendido'] . " und.</td>
                    <td style='text-align: right;'>Bs " . number_format($top['ingresos_generados'], 2) . "</td>
                  </tr>";
        }
    } else {
         echo "<tr><td colspan='6' style='text-align: center;'>No hay ventas registradas en este periodo.</td></tr>";
    }
    echo "<tr><td colspan='6'></td></tr>";

    // SECCIÓN C: TRANSACCIONES
    echo "<tr><th colspan='6' style='background-color: #1a73e8; color: white; text-align: left; padding: 10px;'>3. DETALLE DE TRANSACCIONES</th></tr>";
    echo "<tr style='background-color: #f1f5f9; font-weight: bold;'>
            <th style='padding: 10px;'>Ticket (ID)</th>
            <th colspan='2' style='padding: 10px;'>Fecha y Hora</th>
            <th style='padding: 10px;'>Método de Pago</th>
            <th style='padding: 10px;'>Cajero Responsable</th>
            <th style='padding: 10px;'>Total Venta (Bs)</th>
          </tr>";

    $sql_ventas = "SELECT v.id_venta, v.fecha_hora, v.total_pagar, v.metodo_pago, u.nombre as cajero
                   FROM ventas v
                   LEFT JOIN usuarios u ON v.id_usuario = u.id_usuario
                   WHERE v.estado = 'Completada' $filtro_fecha
                   ORDER BY v.fecha_hora DESC";
    $res_ventas = $conn->query($sql_ventas);

    if ($res_ventas && $res_ventas->num_rows > 0) {
        while($venta = $res_ventas->fetch_assoc()) {
            $fecha_v = date("d/m/Y - H:i", strtotime($venta['fecha_hora']));
            echo "<tr>
                    <td style='text-align: center;'>#" . str_pad($venta['id_venta'], 5, "0", STR_PAD_LEFT) . "</td>
                    <td colspan='2' style='text-align: center;'>" . $fecha_v . "</td>
                    <td style='text-align: center;'>" . htmlspecialchars($venta['metodo_pago']) . "</td>
                    <td>" . htmlspecialchars($venta['cajero'] ?? 'Desconocido') . "</td>
                    <td style='text-align: right; font-weight: bold;'>Bs " . number_format($venta['total_pagar'], 2) . "</td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='6' style='text-align: center;'>No hay transacciones en este periodo.</td></tr>";
    }
    echo "<tr><td colspan='6'></td></tr>";
    echo "<tr><td colspan='6'></td></tr>"; // Doble espacio para separar

    // =========================================================
    // SECCIÓN D: AUDITORÍA FÍSICA Y COSTOS (CORREGIDO Y AMPLIADO)
    // =========================================================
    echo "<tr><th colspan='7' style='background-color: #0f172a; color: white; text-align: left; padding: 10px;'>4. AUDITORÍA DE INVENTARIO FÍSICO Y VALORIZACIÓN</th></tr>";
    echo "<tr style='background-color: #f1f5f9; font-weight: bold;'>
            <th style='padding: 10px;'>Código</th>
            <th style='padding: 10px;'>Producto</th>
            <th style='padding: 10px; color: #1e3a8a;'>Stock Inicial (Histórico)</th>
            <th style='padding: 10px; color: #047857;'>Stock Restante (Físico)</th>
            <th style='padding: 10px;'>Costo Compra Un.</th>
            <th style='padding: 10px; color: #b45309;'>Inversión Inicial (Bs)</th>
            <th style='padding: 10px; color: #1d4ed8;'>Valor Actual Almacén (Bs)</th>
          </tr>";

    // Consulta con "Ingeniería Inversa" para calcular el stock inicial histórico
    $sql_costos = "SELECT 
                    p.codigo_barras, 
                    p.nombre, 
                    p.costo_compra, 
                    p.stock_actual, 
                    (p.costo_compra * p.stock_actual) AS valor_actual,
                    (p.stock_actual + COALESCE((SELECT SUM(dv.cantidad) FROM detalle_venta dv JOIN ventas v ON dv.id_venta = v.id_venta WHERE dv.id_producto = p.id_producto AND v.estado = 'Completada'), 0)) AS stock_inicial
                   FROM productos p 
                   ORDER BY p.nombre ASC";
    $res_costos = $conn->query($sql_costos);
    
    $total_valor_actual = 0;
    $total_inversion_inicial = 0;
    $total_stock_inicial = 0;
    $total_stock_restante = 0;

    if ($res_costos && $res_costos->num_rows > 0) {
        while($costo = $res_costos->fetch_assoc()) {
            
            // Calculamos la inversión inicial en PHP
            $inversion_inicial = $costo['stock_inicial'] * $costo['costo_compra'];
            
            // Sumatorias totales
            $total_valor_actual += $costo['valor_actual'];
            $total_inversion_inicial += $inversion_inicial;
            $total_stock_inicial += $costo['stock_inicial'];
            $total_stock_restante += $costo['stock_actual'];

            echo "<tr>
                    <td style='mso-number-format:\"\\\@\"; text-align: center;'>" . htmlspecialchars($costo['codigo_barras'] ?? 'N/A') . "</td>
                    <td>" . htmlspecialchars($costo['nombre']) . "</td>
                    <td style='text-align: center; color: #64748b;'>" . $costo['stock_inicial'] . " und.</td>
                    <td style='text-align: center; font-weight: bold; color: #047857;'>" . $costo['stock_actual'] . " und.</td>
                    <td style='text-align: right;'>Bs " . number_format($costo['costo_compra'], 2) . "</td>
                    <td style='text-align: right; font-weight: bold; color: #b45309;'>Bs " . number_format($inversion_inicial, 2) . "</td>
                    <td style='text-align: right; font-weight: bold; color: #1a73e8;'>Bs " . number_format($costo['valor_actual'], 2) . "</td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='7' style='text-align: center;'>No hay productos registrados en el inventario.</td></tr>";
    }

    // Fila final con la suma total de todas las métricas
    echo "<tr>
            <th colspan='2' style='text-align: right; padding: 12px; background-color: #e2e8f0; font-size: 14px;'>TOTALES GENERALES EN ALMACÉN:</th>
            <th style='text-align: center; padding: 12px; background-color: #f1f5f9; font-size: 15px; color: #1e3a8a;'>" . $total_stock_inicial . " und.</th>
            <th style='text-align: center; padding: 12px; background-color: #d1fae5; font-size: 16px; color: #047857;'>" . $total_stock_restante . " und.</th>
            <th style='text-align: right; padding: 12px; background-color: #e2e8f0;'>GRAN TOTAL:</th>
            <th style='text-align: right; padding: 12px; background-color: #fef3c7; font-size: 16px; color: #b45309;'>Bs " . number_format($total_inversion_inicial, 2) . "</th>
            <th style='text-align: right; padding: 12px; background-color: #eff6ff; font-size: 16px; color: #1d4ed8;'>Bs " . number_format($total_valor_actual, 2) . "</th>
          </tr>";
}

echo "</table>";
?>