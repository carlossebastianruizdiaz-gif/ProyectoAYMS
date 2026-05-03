<?php
session_start();
include '../Capa de persistencia (BD)/db.php'; 

// Importar las clases de PHPMailer en la parte superior
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Requerir los archivos de PHPMailer que descargaste
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$error = "";
$mensaje_recuperacion = "";
$mostrar_recuperacion = false; 
$clave_secreta = "StockFlow_2026_Secret!"; 

// =======================================================
// 0. CERRAR SESIÓN
// =======================================================
if (isset($_GET['logout'])) {
    session_destroy(); 
    setcookie('stockflow_remember', '', time() - 3600, "/"); 
    header("Location: index.php");
    exit();
}

// =======================================================
// 1. AUTO-LOGIN CON COOKIE (MANTENIDO)
// =======================================================
// ... (Toda tu lógica de Auto-Login sigue aquí, no la toqué para ahorrar espacio visual, 
// pero asume que el bloque if(!isset($_SESSION...)) está aquí tal cual lo teníamos).
if (!isset($_SESSION['usuario_id']) && isset($_COOKIE['stockflow_remember'])) {
    $cookie_data = explode('|', $_COOKIE['stockflow_remember']);
    if (count($cookie_data) == 2) {
        $id_cookie = $cookie_data[0];
        $token_cookie = $cookie_data[1];
        $sql_c = "SELECT id_usuario, nombre, password_hash, id_rol FROM usuarios WHERE id_usuario = ?";
        $stmt_c = $conn->prepare($sql_c);
        $stmt_c->bind_param("i", $id_cookie);
        $stmt_c->execute();
        $res_c = $stmt_c->get_result();
        if ($res_c->num_rows > 0) {
            $user_c = $res_c->fetch_assoc();
            $token_real = hash('sha256', $user_c['password_hash'] . $clave_secreta);
            if (hash_equals($token_real, $token_cookie)) {
                $_SESSION['usuario_id'] = $user_c['id_usuario'];
                $_SESSION['nombre'] = $user_c['nombre'];
                $_SESSION['rol_id'] = $user_c['id_rol'];
                if ($user_c['id_rol'] == 1) header("Location: dashboard_admin.php");
                elseif ($user_c['id_rol'] == 2) header("Location: pos_vendedor.php");
                else header("Location: inventario.php");
                exit();
            }
        }
    }
}
if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['rol_id'] == 1) header("Location: dashboard_admin.php");
    elseif ($_SESSION['rol_id'] == 2) header("Location: pos_vendedor.php");
    else header("Location: inventario.php");
    exit();
}

// =======================================================
// 2. PROCESAR LOGIN NORMAL
// =======================================================
// ... (Aquí va todo tu bloque normal de if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_normal'])) tal como estaba en el anterior mensaje) ...
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_normal'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $recordarme = isset($_POST['recordarme']) ? true : false; 
    $sql = "SELECT id_usuario, nombre, password_hash, id_rol FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
        if (password_verify($password, $usuario['password_hash'])) {
            $_SESSION['usuario_id'] = $usuario['id_usuario'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol_id'] = $usuario['id_rol'];
            if ($recordarme) {
                $token = hash('sha256', $usuario['password_hash'] . $clave_secreta);
                $valor_cookie = $usuario['id_usuario'] . '|' . $token;
                setcookie('stockflow_remember', $valor_cookie, time() + (86400 * 30), "/"); 
            }
            if ($usuario['id_rol'] == 1) header("Location: dashboard_admin.php");
            elseif ($usuario['id_rol'] == 2) header("Location: pos_vendedor.php");
            else header("Location: inventario.php");
            exit();
        } else { $error = "Contraseña incorrecta."; }
    } else { $error = "El correo no está registrado."; }
}

// =======================================================
// 3. PROCESAR RECUPERACIÓN (LÓGICA BLINDADA)
// =======================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    $mostrar_recuperacion = true; 
    $email_recup = trim($_POST['email_recuperacion']);

    $sql_chk = "SELECT id_usuario, nombre FROM usuarios WHERE email = ?";
    $stmt_chk = $conn->prepare($sql_chk);
    $stmt_chk->bind_param("s", $email_recup);
    $stmt_chk->execute();
    $resultado = $stmt_chk->get_result();
    
    if ($resultado->num_rows > 0) {
        $usuario_recup = $resultado->fetch_assoc();
        
        $clave_temporal = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
        $hash_temporal = password_hash($clave_temporal, PASSWORD_DEFAULT);

        $mail = new PHPMailer(true);

        try {
            // 1. PRIMERO INTENTAMOS ENVIAR EL CORREO
            $mail->isSMTP();                                            
            $mail->Host       = 'smtp.gmail.com';                     
            $mail->SMTPAuth   = true;                                   
            
            // TUS DATOS
            $mail->Username   = 'carlossebastianruizdiaz@gmail.com'; 
            $mail->Password   = 'zwsk wytb cfzu kkje';  
            
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;            
            $mail->Port       = 587;                                    

            $mail->setFrom('TU_CORREO@gmail.com', 'Sistema StockFlow');
            $mail->addAddress($email_recup, $usuario_recup['nombre']);     

            $mail->isHTML(true);                                  
            $mail->Subject = 'Recuperacion de Acceso - StockFlow';
            $mail->Body = "Hola <b>{$usuario_recup['nombre']}</b>, tu nueva clave es: <h2>{$clave_temporal}</h2>";

            // Si esto falla, saltará directo al 'catch' y no ejecutará la línea de abajo
            $mail->send(); 

            // 2. SI EL CORREO LLEGÓ, RECIÉN ACTUALIZAMOS LA BASE DE DATOS
            $sql_upd = "UPDATE usuarios SET password_hash = ? WHERE email = ?";
            $stmt_upd = $conn->prepare($sql_upd);
            $stmt_upd->bind_param("ss", $hash_temporal, $email_recup);
            $stmt_upd->execute();

            $mensaje_recuperacion = "<div class='success-msj'>¡Éxito! Hemos enviado una contraseña temporal a tu correo.</div>";
        
        } catch (Exception $e) {
            // Si el correo rebota, avisamos y la contraseña antigua se mantiene intacta
            $mensaje_recuperacion = "<div class='error-msj'>Error al enviar el correo. La contraseña NO fue cambiada. Detalle: {$mail->ErrorInfo}</div>";
        }
    } else {
        $mensaje_recuperacion = "<div class='error-msj'>Este correo no existe en nuestro sistema.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- ... (Todo el HTML y CSS se mantiene EXACTAMENTE igual al del diseño anterior) ... -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - StockFlow</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f0f2f5; display: flex; align-items: center; justify-content: center; height: 100vh; color: #1e293b; }
        .login-card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 400px; transition: 0.3s; }
        .logo-container { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 30px; }
        .logo-container h1 { color: #1a73e8; font-size: 26px; font-weight: 800; letter-spacing: -0.5px; transition: 0.3s; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: #475569; }
        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-wrapper svg { position: absolute; left: 14px; color: #94a3b8; width: 20px; height: 20px; pointer-events: none; transition: color 0.2s; }
        .input-wrapper input { width: 100%; padding: 12px 12px 12px 42px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 15px; background-color: #f8fafc; color: #0f172a; transition: all 0.2s; }
        .input-wrapper input:focus { border-color: #1a73e8; background-color: #ffffff; box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1); }
        .input-wrapper:focus-within svg { color: #1a73e8; }
        .options-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; margin-bottom: 25px; }
        .options-row a { color: #1a73e8; text-decoration: none; font-weight: 600; cursor: pointer; }
        .options-row a:hover { text-decoration: underline; }
        .btn-login { width: 100%; padding: 14px; background: #1a73e8; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-login:hover { background: #1557b0; }
        .btn-secondary { background: #f1f5f9; color: #475569; }
        .btn-secondary:hover { background: #e2e8f0; }
        .footer-text { text-align: center; margin-top: 25px; font-size: 12px; color: #94a3b8; }
        .error-msj { background: #fef2f2; color: #ef4444; padding: 10px; border-radius: 8px; font-size: 14px; text-align: center; margin-bottom: 20px; border: 1px solid #fecaca; }
        .success-msj { background: #f0fdf4; color: #166534; padding: 12px; border-radius: 8px; font-size: 14px; text-align: center; margin-bottom: 20px; border: 1px solid #bbf7d0; line-height: 1.5; }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="logo-container">
            <i data-lucide="package" style="color: #1a73e8; width: 32px; height: 32px;"></i>
            <h1 id="titulo-principal">StockFlow</h1>
        </div>

        <!-- FORMULARIO 1: LOGIN -->
        <div id="panel-login" style="display: <?php echo $mostrar_recuperacion ? 'none' : 'block'; ?>;">
            <?php if (!empty($error)): ?>
                <div class="error-msj"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="index.php" autocomplete="off">
                <input type="hidden" name="login_normal" value="1">
                <input style="display:none" type="text" name="fakeusernameremembered"/>
                <input style="display:none" type="password" name="fakepasswordremembered"/>

                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <div class="input-wrapper">
                        <i data-lucide="mail"></i>
                        <input type="email" name="email" placeholder="ejemplo@empresa.com" autocomplete="new-password" required <?php echo !$mostrar_recuperacion ? 'autofocus' : ''; ?>>
                    </div>
                </div>

                <div class="form-group">
                    <label>Contraseña</label>
                    <div class="input-wrapper">
                        <i data-lucide="lock"></i>
                        <input type="password" name="password" placeholder="••••••••" autocomplete="new-password" required>
                    </div>
                </div>

                <div class="options-row">
                    <label style="display: flex; align-items: center; gap: 5px; color: #64748b; cursor: pointer;">
                        <input type="checkbox" name="recordarme" id="recordarme"> Recordarme
                    </label>
                    <a onclick="cambiarPanel('recuperacion')">¿Olvidaste tu contraseña?</a>
                </div>

                <button type="submit" class="btn-login">Ingresar al Sistema</button>
            </form>
        </div>

        <!-- FORMULARIO 2: RECUPERAR CONTRASEÑA -->
        <div id="panel-recuperacion" style="display: <?php echo $mostrar_recuperacion ? 'block' : 'none'; ?>;">
            <p style="font-size: 14px; color: #475569; text-align: center; margin-bottom: 20px;">
                Ingresa tu correo. Si existe en la base de datos, te enviaremos una clave temporal de acceso.
            </p>

            <?php echo $mensaje_recuperacion; ?>

            <form method="POST" action="index.php" autocomplete="off">
                <input type="hidden" name="reset_password" value="1">
                
                <div class="form-group">
                    <label>Correo Electrónico a recuperar</label>
                    <div class="input-wrapper">
                        <i data-lucide="at-sign"></i>
                        <!-- Quité el campo del PIN de la versión anterior, ahora solo pide correo -->
                        <input type="email" name="email_recuperacion" placeholder="Tu correo registrado..." required <?php echo $mostrar_recuperacion ? 'autofocus' : ''; ?>>
                    </div>
                </div>

                <button type="submit" class="btn-login" style="margin-bottom: 10px; background: #0f172a;">Enviar Correo</button>
                <button type="button" class="btn-login btn-secondary" onclick="cambiarPanel('login')">Cancelar</button>
            </form>
        </div>

        <div class="footer-text">
            Sistema de Gestión de Inventario v1.0
        </div>
    </div>

    <script>
        lucide.createIcons();
        function cambiarPanel(panel) {
            const panelLogin = document.getElementById('panel-login');
            const panelRecuperacion = document.getElementById('panel-recuperacion');
            const titulo = document.getElementById('titulo-principal');

            if (panel === 'recuperacion') {
                panelLogin.style.display = 'none';
                panelRecuperacion.style.display = 'block';
                titulo.innerText = 'Recuperar Acceso';
            } else {
                panelRecuperacion.style.display = 'none';
                panelLogin.style.display = 'block';
                titulo.innerText = 'StockFlow';
            }
        }
    </script>
</body>
</html>