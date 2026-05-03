<?php
// Author: Dennis Delgado
// Version: 4.2 (Buscador visual con Código de Barras expuesto)
session_start();
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol_id'], [1, 2])) {
    header("Location: index.php");
    exit();
}
include '../Capa de persistencia (BD)/db.php';

// ==========================================
// OBTENER LAS ÚLTIMAS NOVEDADES
// ==========================================
$sqlNovedades = "SELECT nombre, precio_venta, stock_actual, imagen_url FROM productos ORDER BY id_producto DESC LIMIT 5";
$resNovedades = $conn->query($sqlNovedades);
$novedades = [];
if ($resNovedades && $resNovedades->num_rows > 0) {
    while($row = $resNovedades->fetch_assoc()) {
        $novedades[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - StockFlow</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { display: flex; height: 100vh; background-color: #f0f2f5; color: #1e293b; overflow: hidden; }

        .sidebar-pos { width: 80px; background-color: #1e293b; display: flex; flex-direction: column; align-items: center; padding: 20px 0; flex-shrink: 0; }
        .logo-mini { margin-bottom: auto; background: white; padding: 10px; border-radius: 12px; }
        .logout-mini { color: #ef4444; padding: 15px; border-radius: 8px; transition: 0.2s; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .logout-mini:hover { background-color: rgba(239, 68, 68, 0.1); }

        .main-content { flex: 1; display: flex; flex-direction: column; padding: 25px; overflow-y: auto; }
        .header-pos { margin-bottom: 25px; display: flex; flex-direction: column; gap: 15px; }
        .header-pos h1 { font-size: 24px; color: #0f172a; }

        .productos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; align-content: start; }
        .tarjeta-producto { background: white; padding: 15px; border-radius: 12px; text-align: center; border: 1px solid #e2e8f0; cursor: pointer; transition: 0.2s; user-select: none; display: flex; flex-direction: column; }
        .tarjeta-producto:hover { border-color: #3b82f6; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15); transform: translateY(-2px); }
        .tarjeta-producto:active { transform: translateY(0); }
        
        .prod-img-container { width: 100%; height: 120px; margin-bottom: 15px; border-radius: 8px; overflow: hidden; background: #f8fafc; display: flex; align-items: center; justify-content: center; border: 1px dashed #cbd5e1; }
        .prod-img-container img { width: 100%; height: 100%; object-fit: cover; }
        
        .prod-nombre { font-weight: 600; font-size: 14px; margin-bottom: 4px; height: 38px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .prod-precio { color: #1a73e8; font-weight: 800; font-size: 18px; margin-top: 10px; margin-bottom: 5px; }
        .prod-stock { font-size: 12px; color: #64748b; }

        .cart-panel { width: 380px; background: white; border-left: 1px solid #e2e8f0; display: flex; flex-direction: column; flex-shrink: 0; }
        .cart-header { padding: 25px 20px; text-align: center; border-bottom: 1px solid #e2e8f0; }
        .cart-header h2 { font-size: 20px; color: #0f172a; margin-bottom: 5px; }
        .cart-header p { font-size: 13px; color: #64748b; }
        
        .cart-items { flex: 1; padding: 20px; display: flex; flex-direction: column; overflow-y: auto; align-items: center; justify-content: flex-start; gap: 10px; }
        .cart-empty { margin-top: auto; margin-bottom: auto; color: #94a3b8; }
        
        .cart-footer { padding: 20px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
        .total-row { display: flex; justify-content: space-between; align-items: center; font-size: 24px; font-weight: 800; margin-bottom: 15px; color: #0f172a; }
        
        /* Botones */
        .btn-cobrar { width: 100%; padding: 15px; background: #94a3b8; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 10px; cursor: not-allowed; transition: 0.2s; }
        .btn-cobrar.activo { background: #1a73e8; cursor: pointer; }
        .btn-cobrar.activo:hover { background: #1557b0; }
        .btn-primary { background: #1a73e8; color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-primary:hover { background: #1557b0; }
        .btn-icon { background: none; border: none; cursor: pointer; color: #64748b; padding: 4px; transition: 0.2s; }
        .btn-icon:hover { color: #1a73e8; }
        .btn-qty { padding: 4px 8px; border: 1px solid #cbd5e1; background: white; border-radius: 4px; cursor: pointer; }
        .btn-qty:hover { background: #f1f5f9; }
        .btn-del { padding: 4px 8px; border: none; background: #fee2e2; color: #ef4444; border-radius: 4px; cursor: pointer; display: flex; align-items: center; }
        .btn-del:hover { background: #fecaca; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal { background: white; padding: 30px; border-radius: 16px; width: 100%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

    <div class="sidebar-pos">
        <div class="logo-mini">
            <i data-lucide="package" style="color: #1a73e8; width: 28px; height: 28px;"></i>
        </div>
        <a href="index.php" class="logout-mini" title="Cerrar Sesión">
            <i data-lucide="log-out"></i>
        </a>
    </div>

    <div class="main-content">
        <div class="header-pos">
            <h1>Catálogo de Ventas</h1>
            <div style="display: flex; gap: 15px;">
                <div style="flex: 1; position: relative;">
                    <i data-lucide="search" style="position: absolute; left: 14px; top: 14px; color: #94a3b8; width: 20px;"></i>
                    <input type="text" id="buscadorProductos" autofocus placeholder="Buscar por código (Ej. T4-018), nombre o escáner..." style="width: 100%; padding: 14px 14px 14px 45px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; font-size: 15px;">
                </div>
               <select id="filtroCategoria" style="width: 200px; padding: 14px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; font-size: 15px; background: white; cursor: pointer;">
                    <option value="todas">Todas las categorías</option>
                    <?php
                    $sqlCat = "SELECT id_categoria, nombre_categoria FROM categorias";
                    $resCat = $conn->query($sqlCat);
                    if ($resCat && $resCat->num_rows > 0) {
                        while($cat = $resCat->fetch_assoc()) {
                            echo "<option value='".$cat['id_categoria']."'>".htmlspecialchars($cat['nombre_categoria'])."</option>";
                        }
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="productos-grid">
            <?php
            $sql = "SELECT p.* FROM productos p WHERE p.stock_actual > 0 ORDER BY p.nombre ASC";
            $result = $conn->query($sql);

            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $id = $row['id_producto'];
                    $nombre = htmlspecialchars($row['nombre']);
                    $precioBD = $row['precio_venta']; 
                    $precioFormat = number_format($precioBD, 2);
                    $stock = $row['stock_actual'];
                    $id_categoria = isset($row['id_categoria']) ? $row['id_categoria'] : 'sin_categoria';
                    $codigo_barras = htmlspecialchars(trim($row['codigo_barras'] ?? 'N/A'));
                    
                    $imagen_html = "";
                    if (isset($row['imagen_url']) && $row['imagen_url'] != 'default_product.png' && !empty($row['imagen_url'])) {
                        $ruta_img = "img_productos/" . htmlspecialchars($row['imagen_url']);
                        $imagen_html = "<img src='{$ruta_img}' alt='{$nombre}' style='width: 100%; height: 100%; object-fit: cover;'>";
                    } else {
                        $imagen_html = "<i data-lucide='image' style='width: 40px; height: 40px; color: #cbd5e1;'></i>";
                    }
                    
                    // --- AQUI ESTA LA MEJORA VISUAL DEL CODIGO ---
                    echo "
                    <div class='tarjeta-producto' 
                         data-nombre='".strtolower($nombre)."' 
                         data-categoria='".$id_categoria."'
                         data-codigo='".strtolower($codigo_barras)."'
                         onclick='agregarAlCarrito($id, \"".addslashes($nombre)."\", $precioBD, $stock)'>
                         
                        <div class='prod-img-container'>
                            {$imagen_html}
                        </div>
                        
                        <div class='prod-nombre'>{$nombre}</div>
                        
                        <!-- Etiqueta visual del código de barras -->
                        <div style='margin-bottom: auto;'>
                            <span style='font-size: 11px; font-weight: 600; color: #475569; background: #f1f5f9; padding: 3px 8px; border-radius: 6px; border: 1px solid #e2e8f0;'>
                                <i data-lucide='barcode' style='width: 12px; height: 12px; display: inline-block; vertical-align: -2px; margin-right: 3px;'></i>{$codigo_barras}
                            </span>
                        </div>
                        
                        <div class='prod-precio'>Bs {$precioFormat}</div>
                        <div class='prod-stock'>Stock: {$stock} u.</div>
                    </div>
                    ";
                }
            } else {
                echo "<p style='grid-column: 1 / -1; color: #94a3b8;'>No hay productos disponibles.</p>";
            }
            ?>
        </div>
    </div>

    <div class="cart-panel">
        <div class="cart-header">
            <h2>Ticket de Venta</h2>
            <p>Cajero: <?php echo isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre']) : 'Vendedor'; ?></p>
        </div>
        
        <div class="cart-items" id="contenedorCarrito">
            <span class="cart-empty">El carrito está vacío</span>
        </div>
        
        <div class="cart-footer">
            <div class="total-row">
                <span>Total:</span>
                <span id="totalCarrito">Bs 0.00</span>
            </div>
            <select id="metodoPago" style="width: 100%; padding: 12px; margin-bottom: 15px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
                <option value="Efectivo">Pago en Efectivo</option>
                <option value="QR">Pago con QR / Transferencia</option>
            </select>
            <button class="btn-cobrar" id="btnCobrar" onclick="procesarCobro()">
                <i data-lucide="shopping-cart"></i> Procesar Cobro
            </button>
        </div>
    </div>

    <div class="modal-overlay" id="modalNovedades">
        <div class="modal" style="max-width: 550px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0; color: #1a73e8; display: flex; align-items: center; gap: 10px; font-size: 22px;">
                    <i data-lucide="sparkles"></i> Novedades de Inventario
                </h2>
                <button class="btn-icon" onclick="cerrarNovedades()"><i data-lucide="x"></i></button>
            </div>
            <p style="color: #64748b; font-size: 14px; margin-bottom: 20px;">
                ¡Hola! Estos son los últimos productos que se han agregado al sistema. Échales un vistazo antes de empezar a vender:
            </p>
            
            <div style="display: flex; flex-direction: column; gap: 12px; max-height: 350px; overflow-y: auto; padding-right: 5px;">
                <?php foreach($novedades as $nov): 
                    $ruta_img_nov = (isset($nov['imagen_url']) && $nov['imagen_url'] != 'default_product.png' && !empty($nov['imagen_url'])) 
                                    ? "img_productos/" . htmlspecialchars($nov['imagen_url']) : "";
                ?>
                <div style="display: flex; align-items: center; gap: 15px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;">
                    <?php if($ruta_img_nov): ?>
                        <img src="<?php echo $ruta_img_nov; ?>" style="width: 50px; height: 50px; border-radius: 6px; object-fit: cover; border: 1px solid #cbd5e1;">
                    <?php else: ?>
                        <div style="width: 50px; height: 50px; border-radius: 6px; background: #e2e8f0; display: flex; align-items: center; justify-content: center;">
                            <i data-lucide="image" style="color: #94a3b8; width: 24px;"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div style="flex: 1;">
                        <h4 style="margin: 0; color: #0f172a; font-size: 15px;"><?php echo htmlspecialchars($nov['nombre']); ?></h4>
                        <span style="color: #64748b; font-size: 13px;">Stock Inicial: <?php echo $nov['stock_actual']; ?> und.</span>
                    </div>
                    
                    <div style="font-weight: 800; color: #1a73e8; font-size: 16px;">
                        Bs <?php echo number_format($nov['precio_venta'], 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if(empty($novedades)): ?>
                    <p style="text-align: center; color: #94a3b8; padding: 20px;">No hay productos recientes.</p>
                <?php endif; ?>
            </div>
            
            <div class="modal-actions" style="margin-top: 25px;">
                <button class="btn-primary" style="width: 100%; justify-content: center; font-size: 16px;" onclick="cerrarNovedades()">¡Entendido, a vender!</button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function cerrarNovedades() {
            document.getElementById('modalNovedades').style.display = 'none';
            document.getElementById('buscadorProductos').focus(); 
        }

        window.addEventListener('DOMContentLoaded', () => {
            if (!sessionStorage.getItem('novedadesStockFlow')) {
                document.getElementById('modalNovedades').style.display = 'flex';
                sessionStorage.setItem('novedadesStockFlow', 'visto');
            }
        });

        const buscador = document.getElementById('buscadorProductos');
        const filtroCat = document.getElementById('filtroCategoria');
        const tarjetas = document.querySelectorAll('.tarjeta-producto');

        function filtrarProductos() {
            const textoBusqueda = buscador.value.toLowerCase().trim();
            const categoriaSeleccionada = filtroCat.value;

            tarjetas.forEach(tarjeta => {
                const nombre = tarjeta.getAttribute('data-nombre') || "";
                const codigo = tarjeta.getAttribute('data-codigo') || ""; 
                const categoria = tarjeta.getAttribute('data-categoria') || "";
                
                const coincideTexto = nombre.includes(textoBusqueda) || codigo.includes(textoBusqueda);
                const coincideCategoria = (categoriaSeleccionada === 'todas') || (categoria === categoriaSeleccionada);

                tarjeta.style.display = (coincideTexto && coincideCategoria) ? 'flex' : 'none';
            });
        }
        
        buscador.addEventListener('input', filtrarProductos);
        filtroCat.addEventListener('change', filtrarProductos);

        // ==========================================
        // LÓGICA DE TECLADO (BUSCADOR MANUAL + ESCÁNER)
        // ==========================================
        let bufferEscaneo = "";
        let tiempoEscaneo;

        document.addEventListener('keydown', function(event) {
            if (document.getElementById('modalNovedades').style.display === 'flex') return;

            // Escáner en el aire (fuera del buscador)
            if (document.activeElement.id !== 'buscadorProductos' && event.key.length === 1) {
                bufferEscaneo += event.key;
                clearTimeout(tiempoEscaneo);
                tiempoEscaneo = setTimeout(() => { bufferEscaneo = ""; }, 100); 
            }

            if (event.key === 'Enter') {
                let productoEncontrado = null;

                // 1. Si presionaste Enter ESTANDO dentro del buscador
                if (document.activeElement.id === 'buscadorProductos') {
                    event.preventDefault(); 
                    const textoBusqueda = buscador.value.toLowerCase().trim();

                    if (textoBusqueda !== "") {
                        tarjetas.forEach(t => {
                            if (t.getAttribute('data-codigo') === textoBusqueda) {
                                productoEncontrado = t;
                            }
                        });

                        if (!productoEncontrado) {
                            let visibles = Array.from(tarjetas).filter(t => t.style.display !== 'none');
                            if (visibles.length === 1) {
                                productoEncontrado = visibles[0];
                            }
                        }

                        if (productoEncontrado) {
                            productoEncontrado.click();
                            buscador.value = "";
                            filtrarProductos(); 
                        }
                    }
                } 
                // 2. Si presionaste Enter AFUERA del buscador (Escáner Global)
                else if (bufferEscaneo !== "") {
                    event.preventDefault();
                    let codigoABuscar = bufferEscaneo.toLowerCase().trim();
                    tarjetas.forEach(t => {
                        if (t.getAttribute('data-codigo') === codigoABuscar) productoEncontrado = t;
                    });
                    if (productoEncontrado) productoEncontrado.click();
                    bufferEscaneo = "";
                }
            }
        });

        // ==========================================
        // LÓGICA DEL CARRITO DE COMPRAS
        // ==========================================
        let carrito = [];
        const contenedorCarrito = document.getElementById('contenedorCarrito');
        const txtTotal = document.getElementById('totalCarrito');
        const btnCobrar = document.getElementById('btnCobrar');

        function agregarAlCarrito(id, nombre, precio, stockMaximo) {
            let productoExistente = carrito.find(item => item.id === id);

            if (productoExistente) {
                if (productoExistente.cantidad < stockMaximo) {
                    productoExistente.cantidad++;
                } else {
                    alert('No puedes agregar más. Stock máximo alcanzado (' + stockMaximo + ' u).');
                }
            } else {
                carrito.push({
                    id: id,
                    nombre: nombre,
                    precio: parseFloat(precio),
                    cantidad: 1,
                    stockMaximo: stockMaximo
                });
            }
            renderizarCarrito();
        }

        function cambiarCantidad(index, cambio) {
            let item = carrito[index];
            let nuevaCantidad = item.cantidad + cambio;

            if (nuevaCantidad > 0 && nuevaCantidad <= item.stockMaximo) {
                item.cantidad = nuevaCantidad;
            } else if (nuevaCantidad === 0) {
                eliminarDelCarrito(index);
                return;
            } else {
                alert('Stock insuficiente.');
            }
            renderizarCarrito();
        }

        function eliminarDelCarrito(index) {
            carrito.splice(index, 1);
            renderizarCarrito();
        }

        function renderizarCarrito() {
            if (carrito.length === 0) {
                contenedorCarrito.innerHTML = '<span class="cart-empty">El carrito está vacío</span>';
                txtTotal.innerText = 'Bs 0.00';
                btnCobrar.classList.remove('activo');
                return;
            }

            let html = '';
            let total = 0;

            carrito.forEach((item, index) => {
                let subtotal = item.precio * item.cantidad;
                total += subtotal;

                html += `
                <div style="width: 100%; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="font-size: 14px; font-weight: 600; color: #0f172a;">${item.nombre}</span>
                        <span style="font-weight: bold; color: #1a73e8;">Bs ${subtotal.toFixed(2)}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 13px; color: #64748b;">Bs ${item.precio.toFixed(2)} c/u</span>
                        <div style="display: flex; gap: 5px; align-items: center;">
                            <button class="btn-qty" onclick="cambiarCantidad(${index}, -1)">-</button>
                            <span style="font-size: 14px; font-weight: 600; min-width: 20px; text-align: center;">${item.cantidad}</span>
                            <button class="btn-qty" onclick="cambiarCantidad(${index}, 1)">+</button>
                            <button class="btn-del" onclick="eliminarDelCarrito(${index})" style="margin-left: 5px;">
                                <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                            </button>
                        </div>
                    </div>
                </div>`;
            });

            contenedorCarrito.innerHTML = html;
            txtTotal.innerText = 'Bs ' + total.toFixed(2);
            btnCobrar.classList.add('activo');
            lucide.createIcons();
        }

        function procesarCobro() {
            if (carrito.length === 0) return;
            
            const btn = document.getElementById('btnCobrar');
            btn.innerHTML = 'Procesando...';
            btn.disabled = true;

            const metodoPago = document.getElementById('metodoPago').value;
            const total = carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);

            fetch('../Capa de logica del negocio/procesar_venta.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    carrito: carrito,
                    total: total,
                    metodo_pago: metodoPago
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('¡Cobro exitoso! Venta guardada en el sistema.');
                    carrito = []; 
                    location.reload(); 
                } else {
                    alert('Error: ' + data.error);
                    btn.innerHTML = '<i data-lucide="shopping-cart"></i> Procesar Cobro';
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión con el servidor.');
                btn.innerHTML = '<i data-lucide="shopping-cart"></i> Procesar Cobro';
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>