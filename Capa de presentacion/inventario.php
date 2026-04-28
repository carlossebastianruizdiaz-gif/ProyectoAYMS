<?php
session_start();
include '../Capa de persistencia (BD)/db.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol_id'], [1, 3])) {
    header("Location: index.php");
    exit();
}

// ==========================================
// PROCESAMIENTO DE FORMULARIOS
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    // 1. NUEVO PRODUCTO
    if ($accion == 'nuevo') {
        $codigo_barras = trim($_POST['codigo_barras']);
        $nombre = trim($_POST['nombre']);
        $id_categoria = $_POST['id_categoria'];
        $costo = $_POST['costo_compra'];
        $precio = $_POST['precio_venta']; 
        $stock = $_POST['stock_actual'];
        $minimo = $_POST['stock_minimo'];
        
        // Procesar la Imagen
        $imagen_url = 'default_product.png'; // Valor por defecto
        if (isset($_FILES['imagen_producto']) && $_FILES['imagen_producto']['error'] == 0) {
            $ext = pathinfo($_FILES['imagen_producto']['name'], PATHINFO_EXTENSION);
            $nombre_img = uniqid('prod_') . '.' . $ext; // Genera un nombre único
            $ruta_destino = 'img_productos/' . $nombre_img;
            
            // Movemos la foto de la memoria temporal a la carpeta
            if (move_uploaded_file($_FILES['imagen_producto']['tmp_name'], $ruta_destino)) {
                $imagen_url = $nombre_img;
            }
        }

        if (empty($codigo_barras) || empty($nombre) || empty($id_categoria)) {
            echo "<script>alert('Error: Datos incompletos.'); window.location.href='inventario.php';</script>";
            exit();
        }

        $sql_insert = "INSERT INTO productos (codigo_barras, nombre, id_categoria, costo_compra, precio_venta, stock_actual, stock_minimo, imagen_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql_insert);
        // Tipos: s (string), s (string), i (int), d (double), d (double), i (int), i (int), s (string)
        $stmt->bind_param("ssiddiis", $codigo_barras, $nombre, $id_categoria, $costo, $precio, $stock, $minimo, $imagen_url);
        
        try {
            $stmt->execute();
            header("Location: inventario.php?success=creado");
            exit();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                echo "<script>alert('Error: Código de Barras duplicado.'); window.location.href='inventario.php';</script>";
            }
        }
    }
    
    // 2. EDITAR PRODUCTO
    else if ($accion == 'editar') {
        $id_producto = $_POST['id_producto'];
        $codigo_barras = trim($_POST['codigo_barras']);
        $nombre = trim($_POST['nombre']);
        $id_categoria = $_POST['id_categoria'];
        $costo = $_POST['costo_compra'];
        $precio = $_POST['precio_venta']; 
        $stock = $_POST['stock_actual'];
        $minimo = $_POST['stock_minimo'];

        // Revisamos si subió una foto nueva al editar
        if (isset($_FILES['imagen_producto']) && $_FILES['imagen_producto']['error'] == 0) {
            $ext = pathinfo($_FILES['imagen_producto']['name'], PATHINFO_EXTENSION);
            $nombre_img = uniqid('prod_') . '.' . $ext;
            $ruta_destino = 'img_productos/' . $nombre_img;
            
            if (move_uploaded_file($_FILES['imagen_producto']['tmp_name'], $ruta_destino)) {
                $sql_update = "UPDATE productos SET codigo_barras=?, nombre=?, id_categoria=?, costo_compra=?, precio_venta=?, stock_actual=?, stock_minimo=?, imagen_url=? WHERE id_producto=?";
                $stmt = $conn->prepare($sql_update);
                $stmt->bind_param("ssiddiisi", $codigo_barras, $nombre, $id_categoria, $costo, $precio, $stock, $minimo, $nombre_img, $id_producto);
            }
        } else {
            // Si no subió foto nueva, actualizamos todo menos la imagen
            $sql_update = "UPDATE productos SET codigo_barras=?, nombre=?, id_categoria=?, costo_compra=?, precio_venta=?, stock_actual=?, stock_minimo=? WHERE id_producto=?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("ssiddiii", $codigo_barras, $nombre, $id_categoria, $costo, $precio, $stock, $minimo, $id_producto);
        }
        
        try {
            $stmt->execute();
            header("Location: inventario.php?success=editado");
            exit();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                echo "<script>alert('Error: El Código de Barras duplicado.'); window.location.href='inventario.php';</script>";
            }
        }
    }

    // 3. ELIMINAR PRODUCTO
    else if ($accion == 'eliminar') {
        $id_producto = $_POST['id_producto'];
        $sql_delete = "DELETE FROM productos WHERE id_producto=?";
        $stmt = $conn->prepare($sql_delete);
        $stmt->bind_param("i", $id_producto);
        $stmt->execute();
        header("Location: inventario.php?success=eliminado");
        exit();
    }
}

// ==========================================
// CONSULTAS DE LECTURA
// ==========================================
$categorias = [];
$sql_cat = "SELECT id_categoria, nombre_categoria FROM categorias";
$res_cat = $conn->query($sql_cat);
if ($res_cat && $res_cat->num_rows > 0) {
    while($row = $res_cat->fetch_assoc()) { $categorias[] = $row; }
}

$sql_productos = "SELECT p.id_producto, p.codigo_barras, p.nombre, p.id_categoria, p.costo_compra, p.precio_venta, p.stock_actual, p.stock_minimo, p.imagen_url, c.nombre_categoria 
                  FROM productos p 
                  LEFT JOIN categorias c ON p.id_categoria = c.id_categoria 
                  ORDER BY p.id_producto DESC";
$res_productos = $conn->query($sql_productos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario - StockFlow</title>
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
        .header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
        .header h1 { font-size: 28px; color: #0f172a; margin-bottom: 5px; }
        
        .filtros-container { display: flex; gap: 15px; margin-bottom: 20px; background: white; padding: 15px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); align-items: center; }
        .filtro-select { padding: 10px 15px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; font-size: 14px; background-color: white; color: #475569; font-weight: 500; cursor: pointer; min-width: 180px; }
        .filtro-select:focus { border-color: #1a73e8; }
        
        .btn-primary { background: #1a73e8; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-primary:hover { background: #1557b0; }
        .btn-icon { background: none; border: none; cursor: pointer; color: #64748b; padding: 4px; transition: 0.2s; }
        .btn-icon:hover { color: #1a73e8; }
        .panel { background: white; padding: 24px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 14px; color: #64748b; font-weight: 600; border-bottom: 2px solid #e2e8f0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;}
        td { padding: 16px 14px; border-bottom: 1px solid #f1f5f9; font-size: 15px; color: #334155; vertical-align: middle; }
        tr:hover { background-color: #f8fafc; }
        .badge { padding: 6px 10px; border-radius: 6px; font-weight: 600; font-size: 12px; display: inline-block; text-align: center; min-width: 80px;}
        .badge-ok { background: #f0fdf4; color: #22c55e; }
        .badge-critico { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }
        
        /* Estilo para la mini foto en la tabla */
        .thumb-img { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; background: #f8fafc; }
        
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal { background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .modal h2 { margin-bottom: 20px; color: #0f172a; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 14px; font-weight: 600; color: #475569; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 14px; background-color: white;}
        .form-group input:focus, .form-group select:focus { border-color: #1a73e8; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px; }
        .btn-secondary { background: #f1f5f9; color: #475569; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .btn-secondary:hover { background: #e2e8f0; }
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
                <h1>Catálogo de Productos</h1>
                <p>Gestiona las existencias y precios de la tienda.</p>
            </div>
            <button class="btn-primary" onclick="abrirModalNuevo()">
                <i data-lucide="plus" style="width: 18px;"></i> Nuevo Producto
            </button>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i data-lucide="check-circle" style="width: 20px;"></i> 
                <?php 
                    if($_GET['success'] == 'creado') echo "Producto registrado correctamente.";
                    if($_GET['success'] == 'editado') echo "Producto actualizado correctamente.";
                    if($_GET['success'] == 'eliminado') echo "Producto eliminado del sistema.";
                ?>
            </div>
        <?php endif; ?>

        <div class="filtros-container">
            <div style="flex: 1; position: relative;">
                <i data-lucide="search" style="position: absolute; left: 12px; top: 10px; color: #94a3b8; width: 18px;"></i>
                <input type="text" id="buscadorInventario" placeholder="Buscar por código o nombre..." style="width: 100%; padding: 10px 10px 10px 38px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; font-size: 14px;" onkeyup="ejecutarFiltros()">
            </div>
            
            <select id="filtroCat" class="filtro-select" onchange="ejecutarFiltros()">
                <option value="todas">Todas las categorías</option>
                <?php foreach($categorias as $cat): ?>
                    <option value="<?php echo $cat['id_categoria']; ?>">
                        <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="filtroStock" class="filtro-select" onchange="ejecutarFiltros()">
                <option value="todos">Todos los estados</option>
                <option value="optimo">Solo Óptimo</option>
                <option value="critico">Solo Crítico (Bajo Stock)</option>
            </select>
            
            <button class="btn-secondary" style="padding: 10px;" onclick="limpiarFiltros()" title="Limpiar Filtros">
                <i data-lucide="filter-x" style="width: 18px;"></i>
            </button>
        </div>

        <div class="panel">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Costo</th>
                        <th>Precio (Bs)</th>
                        <th>Stock Actual</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaProductos">
                    <?php while($prod = $res_productos->fetch_assoc()): 
                        $es_critico = $prod['stock_actual'] <= $prod['stock_minimo'];
                        $estado_clase = $es_critico ? 'critico' : 'optimo';
                        $cat_id = $prod['id_categoria'] ? $prod['id_categoria'] : 'sin_categoria';
                        
                        // Preparar la imagen para la tabla
                        $img_src = (isset($prod['imagen_url']) && $prod['imagen_url'] != 'default_product.png' && !empty($prod['imagen_url'])) 
                                    ? "img_productos/" . htmlspecialchars($prod['imagen_url']) 
                                    : ""; 
                    ?>
                    <tr class="fila-producto" 
                        data-categoria="<?php echo $cat_id; ?>" 
                        data-estado="<?php echo $estado_clase; ?>"
                        data-nombre="<?php echo strtolower(htmlspecialchars($prod['nombre'])); ?>"
                        data-codigo="<?php echo strtolower(htmlspecialchars($prod['codigo_barras'])); ?>">
                        
                        <td>
                            <strong><?php echo htmlspecialchars($prod['codigo_barras'] ?? ''); ?></strong><br>
                            <span style="color: #94a3b8; font-size: 12px;">ID: #<?php echo str_pad($prod['id_producto'], 4, "0", STR_PAD_LEFT); ?></span>
                        </td>
                        
                        <td style="font-weight: 500; color: #0f172a;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <?php if($img_src): ?>
                                    <img src="<?php echo $img_src; ?>" class="thumb-img">
                                <?php else: ?>
                                    <div class="thumb-img" style="display: flex; align-items: center; justify-content: center;">
                                        <i data-lucide="image" style="width: 20px; color: #cbd5e1;"></i>
                                    </div>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($prod['nombre']); ?>
                            </div>
                        </td>

                        <td>
                            <span style="background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-size: 13px; color: #475569; font-weight: 500;">
                                <?php echo htmlspecialchars($prod['nombre_categoria'] ?? 'Sin Categoría'); ?>
                            </span>
                        </td>
                        <td>Bs <?php echo number_format($prod['costo_compra'], 2); ?></td>
                        <td style="font-weight: bold; color: #1a73e8;">Bs <?php echo number_format($prod['precio_venta'], 2); ?></td>
                        <td style="<?php echo $es_critico ? 'color: #ef4444; font-weight: bold;' : ''; ?>">
                            <?php echo $prod['stock_actual']; ?> und.
                        </td>
                        <td>
                            <?php if($es_critico): ?>
                                <span class="badge badge-critico">Crítico</span>
                            <?php else: ?>
                                <span class="badge badge-ok">Óptimo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn-icon" title="Editar" onclick="editarProducto(
                                <?php echo $prod['id_producto']; ?>, 
                                '<?php echo addslashes($prod['codigo_barras']); ?>', 
                                '<?php echo addslashes($prod['nombre']); ?>', 
                                '<?php echo $prod['id_categoria'] ?? ''; ?>', 
                                <?php echo $prod['costo_compra']; ?>, 
                                <?php echo $prod['precio_venta']; ?>, 
                                <?php echo $prod['stock_actual']; ?>, 
                                <?php echo $prod['stock_minimo']; ?>
                            )"><i data-lucide="edit-3" style="width: 18px;"></i></button>
                            
                            <button class="btn-icon" title="Eliminar" style="color: #ef4444;" onclick="eliminarProducto(<?php echo $prod['id_producto']; ?>)">
                                <i data-lucide="trash-2" style="width: 18px;"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <tr id="msjVacio" style="display: none;">
                        <td colspan="8" style="text-align:center; padding: 30px; color: #94a3b8;">No se encontraron productos con esos filtros.</td>
                    </tr>
                    
                    <?php if($res_productos->num_rows == 0): ?>
                    <tr><td colspan="8" style="text-align:center; padding: 30px; color: #94a3b8;">No hay productos en el inventario. Añade el primero.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal-overlay" id="modalProducto">
        <div class="modal">
            <h2 id="modalTitulo">Registrar Nuevo Producto</h2>
            <form method="POST" action="inventario.php" id="formProducto" enctype="multipart/form-data">
                <input type="hidden" name="accion" id="modalAccion" value="nuevo">
                <input type="hidden" name="id_producto" id="modalIdProducto" value="">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Código de Barras</label>
                        <input type="text" name="codigo_barras" id="modalCodigo" placeholder="Ej. 777123456" required>
                    </div>
                    <div class="form-group">
                        <label>Categoría</label>
                        <select name="id_categoria" id="modalCategoria" required>
                            <option value="" disabled selected>Seleccione una...</option>
                            <?php foreach($categorias as $cat): ?>
                                <option value="<?php echo $cat['id_categoria']; ?>">
                                    <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Nombre del Producto</label>
                    <input type="text" name="nombre" id="modalNombre" placeholder="Ej. Tupper Plástico 1L" required>
                </div>
                
                <div class="form-group">
                    <label>Imagen del Producto (Opcional)</label>
                    <input type="file" name="imagen_producto" id="modalImagen" accept="image/*" style="padding: 6px;">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Costo (Bs)</label>
                        <input type="number" step="0.01" name="costo_compra" id="modalCosto" placeholder="2.50" required>
                    </div>
                    <div class="form-group">
                        <label>Precio de Venta (Bs)</label>
                        <input type="number" step="0.01" name="precio_venta" id="modalPrecio" value="4.00" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Stock Inicial</label>
                        <input type="number" name="stock_actual" id="modalStock" placeholder="100" required>
                    </div>
                    <div class="form-group">
                        <label>Alerta Mínima</label>
                        <input type="number" name="stock_minimo" id="modalMinimo" placeholder="15" required>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="btn-primary" id="btnGuardarModal">Guardar Producto</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        const modal = document.getElementById('modalProducto');
        
        function abrirModal() { modal.style.display = 'flex'; }
        function cerrarModal() { modal.style.display = 'none'; }
        window.onclick = function(event) { if (event.target == modal) { cerrarModal(); } }

        function ejecutarFiltros(guardarEnMemoria = true) {
            const valTexto = document.getElementById('buscadorInventario').value.toLowerCase();
            const valCategoria = document.getElementById('filtroCat').value;
            const valStock = document.getElementById('filtroStock').value;
            const filas = document.querySelectorAll('.fila-producto');
            let filasVisibles = 0;

            if (guardarEnMemoria) {
                sessionStorage.setItem('stockflow_busqueda', valTexto);
                sessionStorage.setItem('stockflow_categoria', valCategoria);
                sessionStorage.setItem('stockflow_stock', valStock);
            }

            filas.forEach(fila => {
                const catFila = fila.getAttribute('data-categoria');
                const estFila = fila.getAttribute('data-estado');
                const nomFila = fila.getAttribute('data-nombre');
                const codFila = fila.getAttribute('data-codigo');

                const pasaCategoria = (valCategoria === 'todas') || (catFila === valCategoria);
                const pasaStock = (valStock === 'todos') || (estFila === valStock);
                const pasaTexto = nomFila.includes(valTexto) || codFila.includes(valTexto);

                if (pasaCategoria && pasaStock && pasaTexto) {
                    fila.style.display = ''; 
                    filasVisibles++;
                } else {
                    fila.style.display = 'none'; 
                }
            });

            const msjVacio = document.getElementById('msjVacio');
            if (filasVisibles === 0 && filas.length > 0) {
                msjVacio.style.display = '';
            } else {
                msjVacio.style.display = 'none';
            }
        }

        function restaurarFiltros() {
            if(sessionStorage.getItem('stockflow_categoria')) {
                document.getElementById('buscadorInventario').value = sessionStorage.getItem('stockflow_busqueda') || '';
                document.getElementById('filtroCat').value = sessionStorage.getItem('stockflow_categoria');
                document.getElementById('filtroStock').value = sessionStorage.getItem('stockflow_stock');
                ejecutarFiltros(false);
            }
        }

        function limpiarFiltros() {
            document.getElementById('buscadorInventario').value = '';
            document.getElementById('filtroCat').value = 'todas';
            document.getElementById('filtroStock').value = 'todos';
            ejecutarFiltros();
        }

        window.addEventListener('DOMContentLoaded', restaurarFiltros);

        function abrirModalNuevo() {
            document.getElementById('modalTitulo').innerText = 'Registrar Nuevo Producto';
            document.getElementById('modalAccion').value = 'nuevo';
            document.getElementById('btnGuardarModal').innerText = 'Guardar Producto';
            document.getElementById('formProducto').reset();
            abrirModal();
        }

        function editarProducto(id, codigo, nombre, categoria, costo, precio, stock, minimo) {
            document.getElementById('modalTitulo').innerText = 'Editar Producto';
            document.getElementById('modalAccion').value = 'editar';
            document.getElementById('modalIdProducto').value = id;
            document.getElementById('btnGuardarModal').innerText = 'Guardar Cambios';

            document.getElementById('modalCodigo').value = codigo;
            document.getElementById('modalNombre').value = nombre;
            document.getElementById('modalCategoria').value = categoria;
            document.getElementById('modalCosto').value = costo;
            document.getElementById('modalPrecio').value = precio;
            document.getElementById('modalStock').value = stock;
            document.getElementById('modalMinimo').value = minimo;
            
            // Limpiamos el input file para que no se envíe la foto vieja por accidente
            document.getElementById('modalImagen').value = ''; 
            abrirModal();
        }

        function eliminarProducto(id) {
            if (confirm('¿Estás seguro de que deseas eliminar este producto? Esta acción no se puede deshacer.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'inventario.php';

                const inputAccion = document.createElement('input');
                inputAccion.type = 'hidden';
                inputAccion.name = 'accion';
                inputAccion.value = 'eliminar';

                const inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'id_producto';
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