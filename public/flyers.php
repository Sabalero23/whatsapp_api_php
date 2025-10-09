<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flyer - WhatsApp Web API Sistema</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1a2e;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .flyer-container {
            width: 800px;
            height: 1200px;
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 30px 100px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        /* Decorative Elements */
        .deco-circle {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
        }

        .deco-circle-1 {
            width: 400px;
            height: 400px;
            background: #25D366;
            top: -200px;
            right: -200px;
        }

        .deco-circle-2 {
            width: 300px;
            height: 300px;
            background: #128C7E;
            bottom: -150px;
            left: -150px;
        }

        .deco-circle-3 {
            width: 200px;
            height: 200px;
            background: #25D366;
            top: 50%;
            left: -100px;
        }

        /* Header */
        .flyer-header {
            padding: 50px 60px 40px;
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .logo-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #25D366, #128C7E);
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: white;
            box-shadow: 0 10px 40px rgba(37, 211, 102, 0.4);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .logo-text h1 {
            font-size: 3rem;
            font-weight: 900;
            background: linear-gradient(135deg, #fff, #25D366);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .logo-text p {
            font-size: 1.3rem;
            color: #25D366;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 3px;
        }

        .tagline {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.4rem;
            margin-top: 20px;
            font-weight: 500;
            line-height: 1.6;
        }

        .version-badge {
            display: inline-block;
            background: linear-gradient(135deg, #25D366, #128C7E);
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 1rem;
            margin-top: 15px;
            box-shadow: 0 5px 20px rgba(37, 211, 102, 0.4);
        }

        /* Features Section */
        .features-section {
            padding: 30px 60px;
            position: relative;
            z-index: 10;
        }

        .section-title {
            text-align: center;
            font-size: 2rem;
            color: #fff;
            margin-bottom: 30px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .section-title i {
            color: #25D366;
            margin-right: 10px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(37, 211, 102, 0.3);
            border-radius: 20px;
            padding: 25px;
            transition: all 0.3s;
        }

        .feature-card:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-5px);
            border-color: #25D366;
        }

        .feature-icon {
            font-size: 2.5rem;
            color: #25D366;
            margin-bottom: 15px;
        }

        .feature-title {
            font-size: 1.2rem;
            color: #fff;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .feature-desc {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.5;
        }

        /* Stats Section */
        .stats-section {
            padding: 30px 60px;
            position: relative;
            z-index: 10;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 40px;
        }

        .stat-box {
            background: rgba(37, 211, 102, 0.2);
            border: 2px solid rgba(37, 211, 102, 0.4);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 900;
            color: #25D366;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
            text-transform: uppercase;
        }

        /* CTA Section */
        .cta-section {
            padding: 40px 60px;
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .cta-box {
            background: linear-gradient(135deg, #25D366, #128C7E);
            border-radius: 25px;
            padding: 35px;
            box-shadow: 0 15px 50px rgba(37, 211, 102, 0.4);
        }

        .cta-title {
            font-size: 1.8rem;
            color: white;
            font-weight: 800;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 25px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            background: rgba(255, 255, 255, 0.2);
            padding: 15px 25px;
            border-radius: 15px;
            font-size: 1.1rem;
            color: white;
            font-weight: 600;
        }

        .contact-item i {
            font-size: 1.5rem;
        }

        /* Footer */
        .flyer-footer {
            padding: 30px 60px;
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .footer-logo {
            font-size: 1.5rem;
            color: #25D366;
            font-weight: 700;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .footer-text {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .footer-copyright {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.85rem;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Download Button */
        .download-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #25D366, #128C7E);
            color: white;
            padding: 15px 30px;
            border-radius: 50px;
            border: none;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(37, 211, 102, 0.4);
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            z-index: 1000;
        }

        .download-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(37, 211, 102, 0.6);
        }

        .download-btn i {
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="flyer-container" id="flyer">
        <!-- Decorative Elements -->
        <div class="deco-circle deco-circle-1"></div>
        <div class="deco-circle deco-circle-2"></div>
        <div class="deco-circle deco-circle-3"></div>

        <!-- Header -->
        <div class="flyer-header">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fab fa-whatsapp"></i>
                </div>
            </div>
            <div class="logo-text">
                <h1>WhatsApp Web API</h1>
                <p>Sistema de Gestión</p>
            </div>
            <p class="tagline">
                Automatiza tu comunicación empresarial<br>
                con la plataforma más completa del mercado
            </p>
            <div class="version-badge">
                <i class="fas fa-code-branch"></i> Versión 1.2.0
            </div>
        </div>

        <!-- Features -->
        <div class="features-section">
            <h2 class="section-title">
                <i class="fas fa-star"></i> Características Principales
            </h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="feature-title">API REST Completa</div>
                    <div class="feature-desc">Integración total con endpoints documentados</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="feature-title">Seguridad Avanzada</div>
                    <div class="feature-desc">Sistema de roles y autenticación robusta</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="feature-title">Envío Masivo</div>
                    <div class="feature-desc">Difusión programada con cola inteligente</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="feature-title">Respuestas Auto</div>
                    <div class="feature-desc">Bot con palabras clave personalizables</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="feature-title">Gestión de Grupos</div>
                    <div class="feature-desc">Creación y administración completa</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="feature-title">Analytics Real-Time</div>
                    <div class="feature-desc">Estadísticas y reportes detallados</div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-section">
            <h2 class="section-title">
                <i class="fas fa-trophy"></i> Capacidades del Sistema
            </h2>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-number">100+</div>
                    <div class="stat-label">Chats</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">∞</div>
                    <div class="stat-label">Contactos</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">99.9%</div>
                    <div class="stat-label">Uptime</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Soporte</div>
                </div>
            </div>
        </div>

        <!-- CTA -->
        <div class="cta-section">
            <div class="cta-box">
                <div class="cta-title">
                    <i class="fas fa-phone-alt"></i> Contáctanos Ahora
                </div>
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fab fa-whatsapp"></i>
                        <span>+54 9 3482 309495</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span>soporte@cellcomweb.com.ar</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-globe"></i>
                        <span>whatsapp.cellcomweb.com.ar</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="flyer-footer">
            <div class="footer-logo">
                CELLCOM TECHNOLOGY
            </div>
            <div class="footer-text">
                Soluciones empresariales de comunicación digital
            </div>
            <div class="footer-copyright">
                <i class="fas fa-copyright"></i> 2025 Cellcom Technology - Todos los derechos reservados
            </div>
        </div>
    </div>

    <!-- Download Button -->
    <button class="download-btn" onclick="downloadFlyer()">
        <i class="fas fa-download"></i>
        Descargar Flyer
    </button>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        async function downloadFlyer() {
            const flyer = document.getElementById('flyer');
            const button = document.querySelector('.download-btn');
            
            // Hide button temporarily
            button.style.display = 'none';
            
            try {
                const canvas = await html2canvas(flyer, {
                    scale: 2,
                    backgroundColor: null,
                    logging: false,
                    width: 800,
                    height: 1200
                });
                
                // Convert to blob and download
                canvas.toBlob(function(blob) {
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.download = 'whatsapp-web-api-flyer.png';
                    link.href = url;
                    link.click();
                    URL.revokeObjectURL(url);
                });
                
            } catch (error) {
                console.error('Error generating image:', error);
                alert('Error al generar la imagen. Por favor, intenta nuevamente.');
            } finally {
                // Show button again
                button.style.display = 'flex';
            }
        }
    </script>
</body>
</html>