<?php
session_start();
header('Content-Type: application/json');

// Si no hay sesión de usuario, rechazar
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
    exit();
}

include '../Capa de persistencia (BD)/db.php';

// Recibir los datos enviados por JavaScript (JSON)
$datos = json_decode(file_get_contents('php://input'), true);

if (!$datos || empty($datos['carrito'])) {
    echo json_encode(['success' => false, 'error' => 'El carrito está vacío']);
    exit();
}

$id_usuario = $_SESSION['usuario_id'];
$total_venta = $datos['total'];
$metodo_pago = $datos['metodo_pago'];
$carrito = $datos['carrito'];

// Iniciar Transacción (Si algo falla, no se guarda nada a medias)
$conn->begin_transaction();

try {
    // 1. Insertar la Venta general
   $sql_venta = "INSERT INTO ventas (id_usuario, total_pagar, metodo_pago) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql_venta);
    $stmt->bind_param("ids", $id_usuario, $total_venta, $metodo_pago);
    $stmt->execute();
    $id_venta = $conn->insert_id; // Obtener el ID de la venta recién creada

    // 2. Insertar cada producto en detalle_venta y descontar stock
    $sql_detalle = "INSERT INTO detalle_venta (id_venta, id_producto, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)";
    $stmt_det = $conn->prepare($sql_detalle);

    $sql_stock = "UPDATE productos SET stock_actual = stock_actual - ? WHERE id_producto = ?";
    $stmt_stock = $conn->prepare($sql_stock);

    foreach ($carrito as $item) {
        $id_producto = $item['id'];
        $cantidad = $item['cantidad'];
        $precio = $item['precio'];
        $subtotal = $cantidad * $precio;

        // Guardar detalle
        $stmt_det->bind_param("iiidd", $id_venta, $id_producto, $cantidad, $precio, $subtotal);
        $stmt_det->execute();

        // Descontar stock
        $stmt_stock->bind_param("ii", $cantidad, $id_producto);
        $stmt_stock->execute();
    }

    // Confirmar todos los cambios
    $conn->commit();
    echo json_encode(['success' => true, 'mensaje' => 'Venta registrada correctamente']);

} catch (Exception $e) {
    // Si hay error, deshacer todo
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Error al guardar en la BD: ' . $e->getMessage()]);
}
?>