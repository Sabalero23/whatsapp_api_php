// session-manager-api.js
// Ubicación: /www/wwwroot/whatsapp.cellcomweb.com.ar/session-manager-api.js
// API para gestión de sesión de WhatsApp

const express = require('express');
const { exec } = require('child_process');
const { promisify } = require('util');
const fs = require('fs').promises;
const path = require('path');
const Redis = require('ioredis');

const execAsync = promisify(exec);
const router = express.Router();

// =====================================================
// CONFIGURACIÓN
// =====================================================

const API_TOKEN = process.env.API_KEY;
const PROJECT_ROOT = process.env.PROJECT_ROOT || '/www/wwwroot/whatsapp.cellcomweb.com.ar';

console.log('\n' + '='.repeat(60));
console.log('🔐 SESSION MANAGER API - INICIADO');
console.log('='.repeat(60));
console.log('📁 PROJECT_ROOT:', PROJECT_ROOT);
console.log('🔑 API_KEY:', API_TOKEN ? `✅ Configurado (${API_TOKEN.substring(0, 10)}...)` : '❌ NO CONFIGURADO');
console.log('='.repeat(60) + '\n');

if (!API_TOKEN) {
    console.error('❌ ERROR CRÍTICO: API_KEY no está configurada en el archivo .env');
    console.error('   Por favor, agrega la línea: API_KEY=tu_clave_secreta');
}

// Conectar a Redis
const redis = new Redis({
    host: process.env.REDIS_HOST || 'localhost',
    port: parseInt(process.env.REDIS_PORT) || 6379,
    password: process.env.REDIS_PASSWORD || null,
    retryStrategy: (times) => {
        const delay = Math.min(times * 50, 2000);
        console.log(`🔄 Redis: Reintento ${times}, esperando ${delay}ms`);
        return delay;
    },
    maxRetriesPerRequest: 3
});

redis.on('connect', () => console.log('✅ Redis: Conexión establecida'));
redis.on('ready', () => console.log('✅ Redis: Listo para usar'));
redis.on('error', (err) => console.error('❌ Redis error:', err.message));
redis.on('close', () => console.log('⚠️ Redis: Conexión cerrada'));

// =====================================================
// MIDDLEWARE DE AUTENTICACIÓN
// =====================================================

function authenticateToken(req, res, next) {
    // Obtener el token de diferentes fuentes (normalizar headers a minúsculas)
    const headers = req.headers;
    
    // Buscar en diferentes variantes del header (Express normaliza a minúsculas)
    const headerApiKey = headers['x-api-key'] || headers['X-API-Key'] || headers['X-Api-Key'];
    const authHeader = headers['authorization'] || headers['Authorization'];
    const bearerToken = authHeader?.replace('Bearer ', '');
    const queryToken = req.query.token;
    
    const token = headerApiKey || bearerToken || queryToken;
    
    console.log('\n' + '-'.repeat(60));
    console.log('🔐 VERIFICACIÓN DE AUTENTICACIÓN');
    console.log('-'.repeat(60));
    console.log('📍 Endpoint:', req.method, req.path);
    console.log('🔍 Headers recibidos:');
    console.log('   - x-api-key:', headerApiKey ? `${headerApiKey.substring(0, 10)}...` : 'No presente');
    console.log('   - authorization:', authHeader ? 'Presente' : 'No presente');
    console.log('   - Bearer Token:', bearerToken ? `${bearerToken.substring(0, 10)}...` : 'No presente');
    console.log('   - Query Token:', queryToken ? `${queryToken.substring(0, 10)}...` : 'No presente');
    console.log('🎯 Token seleccionado:', token ? `${token.substring(0, 10)}...` : 'NINGUNO');
    console.log('✓ Token esperado:', API_TOKEN ? `${API_TOKEN.substring(0, 10)}...` : 'NO CONFIGURADO');
    console.log('🔒 Coincidencia:', token && API_TOKEN && token === API_TOKEN ? '✅ SÍ' : '❌ NO');
    
    // DEBUG: Mostrar TODOS los headers recibidos
    console.log('\n📋 TODOS LOS HEADERS RECIBIDOS:');
    Object.keys(headers).forEach(key => {
        console.log(`   ${key}: ${headers[key]}`);
    });
    console.log('-'.repeat(60) + '\n');
    
    // Verificar si el servidor tiene configurado el token
    if (!API_TOKEN) {
        console.error('❌ RECHAZO: Servidor sin API_KEY configurada');
        return res.status(500).json({ 
            success: false, 
            error: 'Servidor mal configurado: API_KEY no establecida en .env' 
        });
    }
    
    // Verificar si el cliente envió un token
    if (!token) {
        console.error('❌ RECHAZO: Cliente no envió ningún token');
        return res.status(401).json({ 
            success: false, 
            error: 'No se proporcionó token de autenticación. Verifica que estés enviando X-API-Key en los headers.' 
        });
    }
    
    // Verificar si el token coincide
    if (token !== API_TOKEN) {
        console.error('❌ RECHAZO: Token inválido');
        return res.status(401).json({ 
            success: false, 
            error: 'API key inválida. Verifica que el token en .env sea correcto.' 
        });
    }
    
    console.log('✅ AUTORIZADO: Token válido');
    next();
}

// Aplicar autenticación a todas las rutas EXCEPTO /health
router.get('/health', (req, res) => {
    res.json({
        success: true,
        status: 'online',
        timestamp: Date.now()
    });
});

// Aplicar autenticación al resto
router.use(authenticateToken);

// =====================================================
// RUTAS DE API
// =====================================================

/**
 * GET /api/session/qr
 * Obtiene el código QR desde Redis
 */
router.get('/qr', async (req, res) => {
    try {
        console.log('\n📱 GET /api/session/qr - Solicitando código QR');
        
        // Verificar estado actual
        const status = await redis.get('whatsapp:status');
        console.log('📊 Estado actual en Redis:', status);
        
        // Verificar si el QR existe
        const qrExists = await redis.exists('whatsapp:qr');
        console.log('🔍 QR existe en Redis:', qrExists === 1 ? 'SÍ' : 'NO');
        
        if (qrExists === 1) {
            const ttl = await redis.ttl('whatsapp:qr');
            console.log(`⏱️  TTL del QR: ${ttl} segundos`);
        }
        
        const qrData = await redis.get('whatsapp:qr');
        
        if (!qrData) {
            console.log('⚠️ QR no disponible en Redis');
            
            // Verificar si ya está conectado
            if (status === 'ready') {
                return res.json({
                    success: false,
                    error: 'Ya estás conectado',
                    connected: true
                });
            }
            
            return res.json({
                success: false,
                error: 'Código QR no disponible. El sistema está inicializándose, espera unos segundos y recarga.',
                initializing: true
            });
        }
        
        console.log('✅ QR encontrado y enviado');
        console.log(`📏 Longitud del QR: ${qrData.length} caracteres`);
        console.log(`🔤 Inicio del QR: ${qrData.substring(0, 50)}...`);
        
        res.json({
            success: true,
            qr: qrData,
            timestamp: Date.now(),
            expiresIn: await redis.ttl('whatsapp:qr')
        });
        
    } catch (error) {
        console.error('❌ Error obteniendo QR:', error);
        res.status(500).json({
            success: false,
            error: 'Error interno al obtener QR: ' + error.message
        });
    }
});

/**
 * POST /api/session/logout
 * Cierra la sesión de WhatsApp sin eliminar archivos
 */
router.post('/logout', async (req, res) => {
    try {
        console.log('\n🚪 POST /api/session/logout - Cerrando sesión de WhatsApp');
        
        const client = global.whatsappClient;
        
        if (!client) {
            console.log('⚠️ Cliente WhatsApp no está disponible globalmente');
            return res.status(503).json({
                success: false,
                error: 'Cliente de WhatsApp no está disponible. Verifica que el servidor esté corriendo correctamente.'
            });
        }
        
        // Marcar cliente como cerrándose para evitar operaciones concurrentes
        if (global.isLoggingOut) {
            console.log('⚠️ Ya hay un proceso de logout en curso');
            return res.status(409).json({
                success: false,
                error: 'Ya hay un proceso de cierre de sesión en curso'
            });
        }
        
        global.isLoggingOut = true;
        
        try {
            // Limpiar datos de Redis PRIMERO
            console.log('🧹 Limpiando datos de Redis...');
            await redis.del('whatsapp:status');
            await redis.del('whatsapp:qr');
            await redis.set('whatsapp:status', 'logging_out');
            console.log('✅ Redis limpiado');
            
            // Cerrar sesión en WhatsApp
            console.log('📤 Ejecutando logout en cliente WhatsApp...');
            await client.logout();
            console.log('✅ Logout ejecutado correctamente en cliente');
            
            // Limpiar referencia global
            global.whatsappClient = null;
            console.log('✅ Referencia global limpiada');
            
            // Enviar respuesta ANTES de reiniciar
            res.json({
                success: true,
                message: 'Sesión cerrada correctamente. El servicio se reiniciará automáticamente. Espera 10 segundos y recarga la página.'
            });
            
            // Reiniciar el servicio en segundo plano para reinicializar todo limpiamente
            setTimeout(async () => {
                try {
                    console.log('🔄 Reiniciando servicio PM2 después del logout...');
                    await execAsync('pm2 restart whatsapp');
                    console.log('✅ Servicio reiniciado exitosamente');
                } catch (error) {
                    console.error('❌ Error reiniciando PM2:', error.message);
                }
            }, 1000);
            
        } finally {
            // Asegurar que el flag se limpie
            setTimeout(() => {
                global.isLoggingOut = false;
            }, 3000);
        }
        
    } catch (error) {
        console.error('❌ Error en logout:', error);
        global.isLoggingOut = false;
        
        res.status(500).json({
            success: false,
            error: 'Error al cerrar sesión: ' + error.message
        });
    }
});

/**
 * POST /api/session/clean
 * Limpieza completa: elimina sesión, caché y datos de Redis
 */
router.post('/clean', async (req, res) => {
    try {
        console.log('\n🧹 POST /api/session/clean - Iniciando limpieza completa del sistema');
        
        const scriptPath = path.join(PROJECT_ROOT, 'scripts', 'cambiar-numero.sh');
        
        console.log('📂 Buscando script en:', scriptPath);
        
        // Verificar que el script existe
        try {
            await fs.access(scriptPath);
            console.log('✅ Script encontrado');
        } catch (error) {
            console.error('❌ Script NO encontrado:', scriptPath);
            return res.status(404).json({
                success: false,
                error: `Script de limpieza no encontrado en: ${scriptPath}`
            });
        }
        
        // Enviar respuesta INMEDIATAMENTE antes de ejecutar el script
        res.json({
            success: true,
            message: 'Limpieza completa iniciada en segundo plano. El servicio se reiniciará automáticamente. Por favor, espera 15 segundos y recarga la página.',
            logs: 'Ejecutando limpieza completa del sistema...'
        });
        
        // Ejecutar limpieza en segundo plano (después de enviar la respuesta)
        setTimeout(async () => {
            try {
                console.log('🧹 Ejecutando script de limpieza completa...');
                console.log('📝 Comando: bash ' + scriptPath);
                
                // Ejecutar el script con 's' como entrada automática
                const { stdout, stderr } = await execAsync(`bash ${scriptPath} << EOF
s
EOF`, {
                    cwd: PROJECT_ROOT,
                    timeout: 60000 // 60 segundos de timeout
                });
                
                console.log('✅ Script de limpieza ejecutado exitosamente');
                console.log('📄 Stdout:', stdout);
                if (stderr) console.log('⚠️ Stderr:', stderr);
                
            } catch (error) {
                console.error('❌ Error ejecutando script de limpieza:', error.message);
                if (error.stdout) console.error('   Stdout:', error.stdout);
                if (error.stderr) console.error('   Stderr:', error.stderr);
            }
        }, 500);
        
    } catch (error) {
        console.error('❌ Error en clean:', error);
        res.status(500).json({
            success: false,
            error: 'Error al iniciar limpieza: ' + error.message
        });
    }
});

/**
 * POST /api/session/restart
 * Reinicia el servicio PM2 sin eliminar datos
 */
router.post('/restart', async (req, res) => {
    try {
        console.log('\n🔄 POST /api/session/restart - Reiniciando servicio PM2');
        
        // Enviar respuesta INMEDIATAMENTE antes de reiniciar
        res.json({
            success: true,
            message: 'Servicio reiniciándose. Por favor, espera 10 segundos y recarga la página.',
            logs: 'Ejecutando reinicio de PM2 en segundo plano...'
        });
        
        // Reiniciar en segundo plano (después de enviar la respuesta)
        setTimeout(async () => {
            try {
                console.log('🔄 Ejecutando reinicio de PM2...');
                const { stdout, stderr } = await execAsync('pm2 restart whatsapp', {
                    timeout: 30000
                });
                
                console.log('✅ PM2 restart ejecutado exitosamente');
                console.log('📄 Stdout:', stdout);
                if (stderr) console.log('⚠️ Stderr:', stderr);
                
            } catch (error) {
                console.error('❌ Error reiniciando PM2:', error.message);
                if (error.stdout) console.error('   Stdout:', error.stdout);
                if (error.stderr) console.error('   Stderr:', error.stderr);
            }
        }, 500);
        
    } catch (error) {
        console.error('❌ Error en restart:', error);
        res.status(500).json({
            success: false,
            error: 'Error al iniciar reinicio: ' + error.message
        });
    }
});

/**
 * GET /api/session/status
 * Obtiene información detallada del sistema
 */
router.get('/status', async (req, res) => {
    try {
        console.log('\n📊 GET /api/session/status - Consultando estado completo del sistema');
        
        const sessionPath = path.join(PROJECT_ROOT, 'whatsapp-session');
        let sessionExists = false;
        
        try {
            await fs.access(sessionPath);
            sessionExists = true;
            console.log('✅ Carpeta de sesión existe:', sessionPath);
        } catch {
            sessionExists = false;
            console.log('⚠️ Carpeta de sesión NO existe:', sessionPath);
        }
        
        // Verificar estado de PM2
        let pm2Status = 'unknown';
        let pm2Uptime = null;
        let pm2Memory = null;
        let pm2Restarts = null;
        
        try {
            const { stdout } = await execAsync('pm2 jlist');
            const processes = JSON.parse(stdout);
            const whatsappProcess = processes.find(p => p.name === 'whatsapp');
            
            if (whatsappProcess) {
                pm2Status = whatsappProcess.pm2_env.status;
                pm2Uptime = whatsappProcess.pm2_env.pm_uptime;
                pm2Memory = Math.round(whatsappProcess.monit.memory / 1024 / 1024);
                pm2Restarts = whatsappProcess.pm2_env.restart_time;
                
                console.log(`✅ PM2 Info: Status=${pm2Status}, Memory=${pm2Memory}MB, Restarts=${pm2Restarts}`);
            } else {
                console.log('⚠️ Proceso "whatsapp" no encontrado en PM2');
            }
        } catch (error) {
            console.error('❌ Error obteniendo estado PM2:', error.message);
        }
        
        // Obtener estado de WhatsApp desde Redis
        const redisStatus = await redis.get('whatsapp:status');
        const hasQR = await redis.exists('whatsapp:qr');
        
        console.log(`📊 Redis: Status=${redisStatus || 'none'}, HasQR=${hasQR ? 'yes' : 'no'}`);
        
        const systemInfo = {
            success: true,
            data: {
                session: {
                    exists: sessionExists,
                    path: sessionPath
                },
                pm2: {
                    status: pm2Status,
                    uptime: pm2Uptime,
                    memory: pm2Memory,
                    restarts: pm2Restarts
                },
                redis: {
                    status: redisStatus || 'unknown',
                    hasQR: hasQR === 1
                },
                system: {
                    nodeVersion: process.version,
                    uptime: Math.floor(process.uptime()),
                    platform: process.platform,
                    timestamp: Date.now()
                }
            }
        };
        
        console.log('✅ Estado del sistema recopilado exitosamente');
        res.json(systemInfo);
        
    } catch (error) {
        console.error('❌ Error en status:', error);
        res.status(500).json({
            success: false,
            error: 'Error al obtener estado: ' + error.message
        });
    }
});

/**
 * POST /api/session/clear-redis
 * Limpia solo los datos de Redis (útil para debug)
 */
router.post('/clear-redis', async (req, res) => {
    try {
        console.log('\n🗑️ POST /api/session/clear-redis - Limpiando claves de Redis');
        
        const keys = await redis.keys('whatsapp:*');
        
        if (keys.length > 0) {
            await redis.del(...keys);
            console.log(`✅ ${keys.length} claves eliminadas:`, keys);
        } else {
            console.log('ℹ️ No hay claves de WhatsApp en Redis para eliminar');
        }
        
        res.json({
            success: true,
            message: `Redis limpiado correctamente (${keys.length} claves eliminadas)`,
            deletedKeys: keys
        });
        
    } catch (error) {
        console.error('❌ Error limpiando Redis:', error);
        res.status(500).json({
            success: false,
            error: 'Error al limpiar Redis: ' + error.message
        });
    }
});

// =====================================================
// MANEJO DE ERRORES GLOBAL
// =====================================================

router.use((err, req, res, next) => {
    console.error('\n❌ ERROR NO MANEJADO EN SESSION MANAGER API:');
    console.error(err);
    
    res.status(500).json({
        success: false,
        error: 'Error interno del servidor',
        details: process.env.NODE_ENV === 'development' ? err.message : undefined
    });
});

// =====================================================
// LIMPIEZA AL CERRAR
// =====================================================

process.on('SIGINT', async () => {
    console.log('\n\n🛑 Señal SIGINT recibida, cerrando conexiones...');
    try {
        await redis.quit();
        console.log('✅ Redis desconectado correctamente');
    } catch (error) {
        console.error('❌ Error al cerrar Redis:', error.message);
    }
    process.exit(0);
});

process.on('SIGTERM', async () => {
    console.log('\n\n🛑 Señal SIGTERM recibida, cerrando conexiones...');
    try {
        await redis.quit();
        console.log('✅ Redis desconectado correctamente');
    } catch (error) {
        console.error('❌ Error al cerrar Redis:', error.message);
    }
    process.exit(0);
});

console.log('✅ Session Manager API: Todas las rutas configuradas correctamente\n');

module.exports = router;