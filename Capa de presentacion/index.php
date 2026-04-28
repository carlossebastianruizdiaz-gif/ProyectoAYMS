<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - StockFlow</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; color: #1e293b; }
        
        .login-container { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 400px; }
        
        .logo { display: flex; align-items: center; justify-content: center; gap: 10px; color: #1a73e8; font-size: 26px; font-weight: 800; margin-bottom: 30px; }
        .logo i { width: 32px; height: 32px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: #475569; }
        
        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-wrapper i { position: absolute; left: 14px; color: #94a3b8; width: 18px; }
        .input-wrapper input { width: 100%; padding: 12px 12px 12px 42px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; transition: 0.2s; font-size: 15px; }
        .input-wrapper input:focus { border-color: #1a73e8; box-shadow: 0 0 0 3px rgba(26,115,232,0.1); }
        
        /* Controles extra: Recordarme y Olvidé contraseña */
        .extra-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; font-size: 13.5px; }
        .remember-me { display: flex; align-items: center; gap: 6px; cursor: pointer; color: #64748b; }
        .forgot-pass { color: #1a73e8; text-decoration: none; font-weight: 600; transition: 0.2s; }
        .forgot-pass:hover { color: #1557b0; text-decoration: underline; }

        .btn-login { width: 100%; padding: 14px; background: #1a73e8; color: white; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; transition: 0.2s; }
        .btn-login:hover { background: #1557b0; }
        
        .footer-text { text-align: center; margin-top: 20px; font-size: 13px; color: #94a3b8; }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="logo">
            <i data-lucide="package"></i> StockFlow
        </div>
        
        <form action="../Capa de logica del negocio/auth.php" method="POST">
            
            <div class="form-group">
                <label>Correo Electrónico</label>
                <div class="input-wrapper">
                    <i data-lucide="mail"></i>
                    <input type="email" name="email" placeholder="usuario@empresa.com" required autocomplete="email">
                </div>
            </div>
            
            <div class="form-group">
                <label>Contraseña</label>
                <div class="input-wrapper">
                    <i data-lucide="lock"></i>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
            </div>

            <div class="extra-controls">
                <label class="remember-me">
                    <input type="checkbox" name="remember"> Recordarme
                </label>
                <a href="recuperar.php" class="forgot-pass">¿Olvidaste tu contraseña?</a>
            </div>
            
            <button type="submit" class="btn-login">Ingresar al Sistema</button>
            
        </form>
        
        <div class="footer-text">
            Sistema de Gestión de Inventario v1.0
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>