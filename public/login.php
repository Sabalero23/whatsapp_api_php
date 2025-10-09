<?php
session_start();
require_once __DIR__ . '/../src/Database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $db = Database::getInstance();
        $user = $db->fetch(
            "SELECT * FROM usuarios WHERE username = ? AND activo = 1",
            [$username]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['rol'] = $user['rol'];
            
            // Actualizar último acceso
            $db->update('usuarios', 
                ['ultimo_acceso' => date('Y-m-d H:i:s')],
                'id = ?',
                [$user['id']]
            );
            
            // Registrar log
            $db->insert('logs', [
                'usuario_id' => $user['id'],
                'accion' => 'Login',
                'descripcion' => 'Inicio de sesión exitoso',
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    } else {
        $error = 'Por favor complete todos los campos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Web API - Sistema de Gestión | Cellcom Technology</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            min-height: 100vh;
            color: #fff;
            overflow-x: hidden;
        }

        /* Header */
        .header {
            background: rgba(15, 32, 39, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(37, 211, 102, 0.2);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo i {
            font-size: 2.5rem;
            color: #25D366;
            animation: pulse 2s infinite;
        }

        .logo-text h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.2rem;
        }

        .logo-text p {
            font-size: 0.85rem;
            color: #25D366;
            font-weight: 500;
        }

        .version-badge {
            background: linear-gradient(135deg, #25D366, #128C7E);
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
        }

        /* Hero Section */
        .hero {
            max-width: 1400px;
            margin: 3rem auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-content h2 {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #fff, #25D366);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }

        .hero-content p {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 1rem;
            background: rgba(37, 211, 102, 0.1);
            border-radius: 10px;
            border: 1px solid rgba(37, 211, 102, 0.2);
            transition: all 0.3s;
        }

        .feature-item:hover {
            background: rgba(37, 211, 102, 0.15);
            transform: translateX(5px);
        }

        .feature-item i {
            color: #25D366;
            font-size: 1.3rem;
        }

        .feature-item span {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.9);
        }

        /* Login Card */
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header i {
            font-size: 3.5rem;
            color: #25D366;
            margin-bottom: 1rem;
            display: inline-block;
            animation: bounce 2s infinite;
        }

        .login-header h3 {
            font-size: 1.8rem;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #6b7280;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #1f2937;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 1.1rem;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s;
            background: #fff;
            color: #1f2937;
        }

        .form-control:focus {
            outline: none;
            border-color: #25D366;
            box-shadow: 0 0 0 4px rgba(37, 211, 102, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            cursor: pointer;
            font-size: 1.1rem;
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: #25D366;
        }

        .error {
            background: linear-gradient(135deg, #fee, #fdd);
            color: #c33;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            border: 1px solid rgba(204, 51, 51, 0.3);
            font-weight: 500;
            animation: shake 0.5s;
        }

        .btn-login {
            width: 100%;
            padding: 1.2rem;
            background: linear-gradient(135deg, #25D366, #128C7E);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(37, 211, 102, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Stats Section */
        .stats {
            max-width: 1400px;
            margin: 4rem auto;
            padding: 0 2rem;
        }

        .stats h3 {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 3rem;
            color: #fff;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s;
        }

        .stat-card:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 3rem;
            color: #25D366;
            margin-bottom: 1rem;
        }

        .stat-card h4 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: #25D366;
        }

        .stat-card p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
        }

        /* Footer */
        .footer {
            background: rgba(15, 32, 39, 0.95);
            border-top: 1px solid rgba(37, 211, 102, 0.2);
            padding: 2rem 0;
            margin-top: 4rem;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-links {
            display: flex;
            gap: 2rem;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.3s;
            font-size: 0.9rem;
        }

        .footer-links a:hover {
            color: #25D366;
        }

        .footer-copyright {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }

        /* Animations */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        /* Responsive */
        @media (max-width: 968px) {
            .hero {
                grid-template-columns: 1fr;
                gap: 3rem;
            }

            .hero-content h2 {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .features-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .login-card {
                padding: 2rem;
            }

            .footer-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .footer-links {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fab fa-whatsapp"></i>
                <div class="logo-text">
                    <h1>WhatsApp Web API</h1>
                    <p>Sistema de Gestión Profesional</p>
                </div>
            </div>
            <div class="version-badge">
                <i class="fas fa-code-branch"></i> v1.2.0
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h2>Plataforma Integral de WhatsApp Business</h2>
            <p>Automatiza, gestiona y potencia tu comunicación empresarial con tecnología de vanguardia.</p>
            
            <div class="features-grid">
                <div class="feature-item">
                    <i class="fas fa-rocket"></i>
                    <span>API REST Completa</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Seguridad Avanzada</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-users"></i>
                    <span>Gestión de Contactos</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics en Tiempo Real</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-paper-plane"></i>
                    <span>Mensajería Masiva</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-robot"></i>
                    <span>Respuestas Automáticas</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-images"></i>
                    <span>Multimedia Completo</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-clock"></i>
                    <span>Envíos Programados</span>
                </div>
            </div>
        </div>

        <!-- Login Card -->
        <div class="login-card">
            <div class="login-header">
                <i class="fas fa-lock"></i>
                <h3>Acceso al Sistema</h3>
                <p>Ingrese sus credenciales para continuar</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> Usuario
                    </label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-user"></i>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-control" 
                               placeholder="Ingrese su usuario"
                               required 
                               autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Contraseña
                    </label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-lock"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="Ingrese su contraseña"
                               required>
                        <i class="password-toggle fas fa-eye" id="togglePassword"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Iniciar Sesión
                </button>
            </form>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <h3>Características del Sistema</h3>
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-comments"></i>
                <h4>100+</h4>
                <p>Chats Simultáneos</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h4>∞</h4>
                <p>Contactos Ilimitados</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-server"></i>
                <h4>99.9%</h4>
                <p>Uptime Garantizado</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-bolt"></i>
                <h4>24/7</h4>
                <p>Disponibilidad Total</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-copyright">
                <i class="fas fa-copyright"></i> 2025 Cellcom Technology - Todos los derechos reservados
            </div>
            <div class="footer-links">
                <a href="https://www.cellcomweb.com.ar" target="_blank">
                    <i class="fas fa-globe"></i> Sitio Web
                </a>
                <a href="mailto:soporte@cellcomweb.com.ar">
                    <i class="fas fa-envelope"></i> Soporte
                </a>
                <a href="https://wa.me/5493482549555" target="_blank">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>
            </div>
        </div>
    </footer>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        let showing = false;

        togglePassword.addEventListener('click', () => {
            showing = !showing;
            password.type = showing ? 'text' : 'password';
            togglePassword.classList.toggle('fa-eye');
            togglePassword.classList.toggle('fa-eye-slash');
        });

        // Auto-focus on error
        <?php if ($error): ?>
        document.getElementById('username').focus();
        <?php endif; ?>
    </script>
</body>
</html>