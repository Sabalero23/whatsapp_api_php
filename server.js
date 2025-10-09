// server.js - Servidor Node.js completo para WhatsApp Web
const express = require('express');
const { Client, LocalAuth, MessageMedia, Location, Buttons, List } = require('whatsapp-web.js');
const qrcode = require('qrcode');
const Redis = require('ioredis');
const winston = require('winston');
const rateLimit = require('express-rate-limit');
const helmet = require('helmet');
const fs = require('fs').promises;
const path = require('path');
const crypto = require('crypto');
require('dotenv').config();
const axios = require('axios');
const sessionManagerRouter = require('./session-manager-api');

// =====================================================
// FUNCIÓN HELPER PARA VERIFICAR DISPONIBILIDAD DEL CLIENTE
// =====================================================

/**
 * Verifica si el cliente de WhatsApp está disponible y listo para usar
 */
function isClientAvailable() {
    return (
        client && 
        isReady && 
        !global.isLoggingOut && 
        client.pupPage && 
        !client.pupPage.isClosed()
    );
}

/**
 * Middleware para verificar disponibilidad del cliente
 */
const requireClient = (req, res, next) => {
    if (!isClientAvailable()) {
        return res.status(503).json({ 
            error: 'Cliente no está listo o está cerrándose',
            isReady: isReady,
            isLoggingOut: global.isLoggingOut || false
        });
    }
    next();
};

/**
 * Maneja errores relacionados con sesiones cerradas
 */
const handleSessionError = (error) => {
    const sessionErrors = [
        'Session closed',
        'Protocol error',
        'Target closed',
        'Connection closed'
    ];
    
    return sessionErrors.some(msg => error.message && error.message.includes(msg));
};

const mysql = require('mysql2/promise');

// Configuración de MySQL
const dbConfig = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'whatsapp_db',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
};

const pool = mysql.createPool(dbConfig);

// NUEVO: Cache en memoria para evitar descargas repetidas
const mediaCache = new Map(); // messageId -> mediaUrl

const API_BASE_URL = process.env.API_BASE_URL || 'http://localhost';

// ==================== VERIFICACIÓN DE HORARIOS ====================

// Sincronizar estado del bot cada 30 segundos
setInterval(async () => {
    try {
        const response = await axios.get(`${API_BASE_URL}/api/get-bot-status.php`, {
            headers: { 'X-API-Key': process.env.API_KEY }
        });
        
        const estadoMySQL = response.data.activo ? '1' : '0';
        await redis.set('whatsapp:bot_activo', estadoMySQL);
        
        logger.debug(`Bot sincronizado: ${estadoMySQL}`);
    } catch (error) {
        logger.error('Error sincronizando estado del bot:', error.message);
    }
}, 30000);

async function verificarHorarioAtencion() {
    try {
        const response = await axios.post(`${API_BASE_URL}/api/verificar-horario.php`, {}, {
            headers: { 'X-API-Key': process.env.API_KEY }
        });
        
        return response.data.enHorario || false;
    } catch (error) {
        logger.error('Error verificando horario:', error.message);
        return true;
    }
}

// ==================== FUNCIÓN: procesarMensajeConHorario ====================
async function procesarMensajeConHorario(message) {
    try {
        // ✅ FILTRO: Ignorar mensajes de estados y propios
        if (message.fromMe || message.from === 'status@broadcast') {
            logger.debug(`Mensaje ignorado - fromMe: ${message.fromMe}, from: ${message.from}`);
            return;
        }
        
        // Verificar si el bot está activo
        const response = await axios.get('https://whatsapp.cellcomweb.com.ar/api/get-bot-status.php', {
            headers: { 'X-API-Key': process.env.API_KEY }
        });
        
        const botActivo = response.data.activo;
        
        if (!botActivo) {
            logger.info('Bot inactivo, mensaje ignorado');
            return;
        }
        
        const from = message.from;
        const body = message.body || '';
        
        logger.info(`Procesando mensaje de ${from}: "${body}"`);
        
        // PRIMERO: Intentar procesar respuestas automáticas (incluye consulta de horarios)
        const respuestaEnviada = await procesarRespuestaAutomatica(from, body);
        
        // Si se envió una respuesta automática, terminar aquí
        if (respuestaEnviada) {
            logger.info('✅ Respuesta automática enviada, no se procesa mensaje fuera de horario');
            return;
        }
        
        // SEGUNDO: Si NO hubo respuesta automática, verificar horario
        const enHorario = await verificarHorarioAtencion();
        
        if (!enHorario) {
            // FUERA DE HORARIO - Enviar mensaje automático
            await enviarMensajeFueraHorario(from);
        }
        
    } catch (error) {
        logger.error('Error procesando mensaje:', error);
    }
}


async function enviarMensajeFueraHorario(numero) {
    try {
        const cacheKey = `fuera_horario_enviado:${numero}:${new Date().toISOString().split('T')[0]}`;
        const yaEnviado = await redis.get(cacheKey);
        
        if (yaEnviado) {
            logger.info(`Ya se envió mensaje fuera de horario hoy a ${numero}`);
            return;
        }
        
        const response = await axios.get(`${API_BASE_URL}/api/get-mensaje-fuera-horario.php`, {
            headers: { 'X-API-Key': process.env.API_KEY }
        });
        
        const mensaje = response.data.mensaje || 'Gracias por tu mensaje. Te responderemos en nuestro horario de atención.';
        
        const chatId = numero.includes('@') ? numero : `${numero}@c.us`;
        await client.sendMessage(chatId, mensaje);
        
        await redis.set(cacheKey, '1', 'EX', 86400);
        
        logger.info(`✓ Mensaje fuera de horario enviado a ${numero}`);
        
        await axios.post(`${API_BASE_URL}/api/guardar-mensaje-fuera-horario.php`, {
            numero: numero,
            mensaje: mensaje
        }, {
            headers: { 'X-API-Key': process.env.API_KEY }
        });
        
    } catch (error) {
        logger.error('Error enviando mensaje fuera de horario:', error.message);
    }
}

async function generarTextoHorarios() {
    try {
        const connection = await pool.getConnection();
        
        const [horarios] = await connection.query(
            'SELECT * FROM horarios_atencion ORDER BY dia_semana ASC'
        );
        
        connection.release();
        
        const dias = {
            0: 'Domingo',
            1: 'Lunes',
            2: 'Martes',
            3: 'Miércoles',
            4: 'Jueves',
            5: 'Viernes',
            6: 'Sábado'
        };
        
        let resultado = '📅 *Nuestros horarios de atención:*\n\n';
        
        for (const h of horarios) {
            const dia = dias[h.dia_semana];
            
            if (!h.activo) {
                resultado += `❌ *${dia}:* Cerrado\n`;
                continue;
            }
            
            const turnos = [];
            
            if (h.manana_inicio && h.manana_fin) {
                turnos.push(`${h.manana_inicio.substring(0, 5)} a ${h.manana_fin.substring(0, 5)}`);
            }
            
            if (h.tarde_inicio && h.tarde_fin) {
                turnos.push(`${h.tarde_inicio.substring(0, 5)} a ${h.tarde_fin.substring(0, 5)}`);
            }
            
            if (turnos.length === 0) {
                resultado += `❌ *${dia}:* Cerrado\n`;
            } else {
                resultado += `✅ *${dia}:* ${turnos.join(' y ')}\n`;
            }
        }
        
        return resultado.trim();
        
    } catch (error) {
        logger.error('Error generando horarios:', error);
        return 'No se pudieron cargar los horarios en este momento.';
    }
}

// ==================== FUNCIÓN: procesarRespuestaAutomatica ====================
async function procesarRespuestaAutomatica(numero, mensaje) {
    try {
        // ✅ FILTRO: No procesar estados
        if (numero === 'status@broadcast') {
            return false;
        }
        
        const uniqueId = Math.random().toString(36).substr(2, 9);
        logger.info(`[${uniqueId}] 🔍 INICIO - Buscando respuesta para: "${mensaje}" de ${numero}`);
        
        const response = await axios.post(`${API_BASE_URL}/api/procesar-respuesta-automatica.php`, {
            numero: numero,
            mensaje: mensaje
        }, {
            headers: { 'X-API-Key': process.env.API_KEY }
        });
        
        logger.info(`[${uniqueId}] 📥 Respuesta de API: ${JSON.stringify(response.data)}`);
        
        if (response.data.respuesta) {
            const respuestaTexto = response.data.respuesta;
            
            logger.info(`[${uniqueId}] 📤 ENVIANDO mensaje a ${numero}`);
            
            const chatId = numero.includes('@') ? numero : `${numero}@c.us`;
            await client.sendMessage(chatId, respuestaTexto);
            
            logger.info(`[${uniqueId}] ✅ FIN - Mensaje enviado`);
            return true;
        } else {
            logger.info(`[${uniqueId}] ℹ️ FIN - No hay respuesta`);
            return false;
        }
        
    } catch (error) {
        logger.error('❌ Error:', error.message);
        return false;
    }
}



// NUEVO: Función para limpiar cache viejo (ejecutar cada hora)
setInterval(() => {
    const oneHourAgo = Date.now() - (60 * 60 * 1000);
    for (const [key, value] of mediaCache.entries()) {
        if (value.timestamp < oneHourAgo) {
            mediaCache.delete(key);
        }
    }
    logger.info(`Cache limpiado. Entradas actuales: ${mediaCache.size}`);
}, 60 * 60 * 1000);

const logger = winston.createLogger({
    level: 'info',
    format: winston.format.json(),
    transports: [
        new winston.transports.File({ filename: 'logs/error.log', level: 'error' }),
        new winston.transports.File({ filename: 'logs/combined.log' }),
        new winston.transports.Console({ format: winston.format.simple() })
    ]
});

const redis = new Redis({
    host: process.env.REDIS_HOST || 'localhost',
    port: process.env.REDIS_PORT || 6379,
    password: process.env.REDIS_PASSWORD || null,
    retryStrategy: (times) => Math.min(times * 50, 2000)
});

const app = express();
app.set('trust proxy', true); // Configurar proxy para rate limiting
app.use(express.json({ limit: '50mb' }));
app.use(helmet());

const limiter = rateLimit({
    windowMs: 1 * 60 * 1000,
    max: 100,
    message: 'Demasiadas solicitudes, intente más tarde',
    standardHeaders: true,
    legacyHeaders: false,
    skip: (req) => req.path === '/api/health',
    // AGREGAR ESTA LÍNEA para confiar en el primer proxy
    trustProxy: 1  // <-- Cambiar de true a 1
});

app.use('/api/', limiter);
// Router de gestión de sesión (NO aplicar rate limiting aquí)
app.use('/api/session', sessionManagerRouter);

const authMiddleware = (req, res, next) => {
    const apiKey = req.headers['x-api-key'];
    if (!apiKey || apiKey !== process.env.API_KEY) {
        return res.status(401).json({ error: 'API key inválida' });
    }
    next();
};

let client;
let isReady = false;
let qrCode = null;

const initializeWhatsApp = () => {
    client = new Client({
        authStrategy: new LocalAuth({
            clientId: 'whatsapp-client',
            dataPath: './whatsapp-session'
        }),
        puppeteer: {
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--disable-gpu'
            ],
            timeout: 60000
        },
        authTimeoutMs: 60000
    });

    // ⭐ AEExportr el cliente globalmente
    global.whatsappClient = client;


    client.on('qr', async (qr) => {
    try {
        logger.info('📱 QR Code recibido - Generando imagen...');
        
        qrCode = await qrcode.toDataURL(qr);
        
        logger.info(`✅ QR generado: ${qrCode.substring(0, 50)}...`);
        logger.info(`📏 Longitud del QR: ${qrCode.length} caracteres`);
        
        // Guardar en Redis con 5 minutos de expiración
        await redis.set('whatsapp:qr', qrCode, 'EX', 300);
        
        // Verificar que se guardó
        const verificacion = await redis.get('whatsapp:qr');
        if (verificacion) {
            logger.info('✅ QR guardado correctamente en Redis');
            logger.info(`📊 TTL del QR: ${await redis.ttl('whatsapp:qr')} segundos`);
        } else {
            logger.error('❌ ERROR: QR NO se guardó en Redis');
        }
        
        await redis.set('whatsapp:status', 'qr_ready');
        logger.info('✅ Estado actualizado a: qr_ready');
        
    } catch (error) {
        logger.error('❌ Error procesando QR:', error);
    }
});

    client.on('ready', async () => {
        logger.info('Cliente de WhatsApp listo');
        isReady = true;
        qrCode = null;
        await redis.set('whatsapp:status', 'ready');
        await redis.del('whatsapp:qr');
    });

    client.on('authenticated', () => {
        logger.info('Autenticación exitosa');
    });
    
    client.on('loading_screen', (percent, message) => {
    logger.info(`Cargando WhatsApp: ${percent}% - ${message}`);
});

client.on('change_state', state => {
    logger.info(`Estado cambió a: ${state}`);
});


client.on('auth_failure', msg => {
    logger.error('Fallo de autenticación:', msg);
});

    client.on('disconnected', async (reason) => {
        logger.warn('Cliente desconectado:', reason);
        isReady = false;
        await redis.set('whatsapp:status', 'disconnected');
        setTimeout(() => {
            logger.info('Intentando reconectar...');
            client.initialize();
        }, 5000);
    });

    client.on('auth_failure', async (msg) => {
        logger.error('Fallo de autenticación:', msg);
        await redis.set('whatsapp:status', 'auth_failed');
    });

    // ==================== EVENT LISTENER: client.on('message') ====================
client.on('message', async (message) => {
    try {
        // ✅ FILTRO PRINCIPAL: Salir inmediatamente si es estado
        if (message.from === 'status@broadcast') {
            return; // No procesar, no registrar
        }
        
        // Procesar mensaje con horario
        await procesarMensajeConHorario(message);
        
        // Webhook (si existe)
        if (process.env.WEBHOOK_URL) {
            // Crear el objeto messageData ANTES de usarlo
            const messageData = {
                id: message.id._serialized,
                from: message.from,
                to: message.to,
                body: message.body || '',
                timestamp: message.timestamp,
                hasMedia: message.hasMedia || false,
                type: message.type || 'chat',
                isForwarded: message.isForwarded || false,
                isStarred: message.isStarred || false,
                fromMe: message.fromMe || false
            };
            
            fetch(process.env.WEBHOOK_URL, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Webhook-Secret': process.env.WEBHOOK_SECRET
                },
                body: JSON.stringify(messageData)
            }).catch(err => logger.error('Error en webhook:', err));
        }
    } catch (error) {
        logger.error('Error procesando mensaje entrante:', error);
    }
});


    client.on('message_create', async (message) => {
        if (message.fromMe) {
            logger.info('Mensaje enviado desde este cliente');
        }
    });

    client.on('message_ack', async (message, ack) => {
    logger.info(`Mensaje ${message.id._serialized} - Estado: ${ack}`);
    
    // ack = 1: Enviado al servidor
    // ack = 2: Entregado al destinatario
    // ack = 3: Leído por el destinatario
    // ack = 4: Reproducido (mensajes de voz)
    
    try {
        // Guardar actualización de estado en Redis para tracking
        await redis.lpush('whatsapp:message_status_updates', JSON.stringify({
            messageId: message.id._serialized,
            status: ack,
            timestamp: Date.now(),
            from: message.from,
            to: message.to
        }));
        
        // Mantener solo últimas 1000 actualizaciones
        await redis.ltrim('whatsapp:message_status_updates', 0, 999);
    } catch (error) {
        logger.error('Error guardando actualización de estado:', error);
    }
});

client.initialize();
};

const processOutgoingQueue = async () => {
    while (true) {
        try {
            if (isReady) {
                const result = await redis.brpop('whatsapp:outgoing_queue', 5);
                if (result) {
                    const [, messageJson] = result;
                    const message = JSON.parse(messageJson);
                    await sendMessage(message);
                }
            } else {
                await new Promise(resolve => setTimeout(resolve, 2000));
            }
        } catch (error) {
            logger.error('Error procesando cola saliente:', error);
            await new Promise(resolve => setTimeout(resolve, 5000));
        }
    }
};

const sendMessage = async (messageData) => {
    try {
        const { to, message, media, messageId } = messageData;
        const chatId = to.includes('@') ? to : `${to}@c.us`;
        let result;
        
        if (media) {
            const mediaFile = MessageMedia.fromFilePath(media.path);
            result = await client.sendMessage(chatId, mediaFile, { caption: message });
        } else {
            result = await client.sendMessage(chatId, message);
        }
        
        await redis.set(
            `whatsapp:result:${messageId}`,
            JSON.stringify({ success: true, messageId: result.id._serialized }),
            'EX', 300
        );
        logger.info(`Mensaje enviado a ${to}`);
        return result;
    } catch (error) {
        logger.error('Error enviando mensaje:', error);
        await redis.set(
            `whatsapp:result:${messageData.messageId}`,
            JSON.stringify({ success: false, error: error.message }),
            'EX', 300
        );
        throw error;
    }
};

// ==================== ENDPOINTS BÁSICOS ====================

app.get('/api/health', (req, res) => {
    res.json({ status: 'ok', whatsappReady: isReady, uptime: process.uptime() });
});

app.get('/api/status', authMiddleware, async (req, res) => {
    const status = await redis.get('whatsapp:status');
    res.json({ isReady, status: status || 'initializing', hasQR: !!qrCode });
});

app.get('/api/qr', authMiddleware, async (req, res) => {
    if (qrCode) {
        res.json({ qr: qrCode });
    } else if (isReady) {
        res.json({ message: 'Cliente ya está autenticado' });
    } else {
        res.status(404).json({ error: 'QR no disponible aún' });
    }
});

app.post('/api/logout', authMiddleware, async (req, res) => {
    try {
        await client.logout();
        await redis.del('whatsapp:status');
        isReady = false;
        res.json({ success: true, message: 'Sesión cerrada' });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/restart', authMiddleware, async (req, res) => {
    try {
        await client.destroy();
        isReady = false;
        setTimeout(() => initializeWhatsApp(), 2000);
        res.json({ success: true, message: 'Cliente reiniciado' });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});
// ==================== INFORMACIÓN DE CUENTA ====================

app.get('/api/me', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    try {
        const info = client.info;
        res.json({
            number: info.wid.user,
            pushname: info.pushname,
            platform: info.platform
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// ==================== ENVÍO DE MENSAJES ====================

app.post('/api/send', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { to, message, media } = req.body;
    if (!to || !message) return res.status(400).json({ error: 'Faltan parámetros' });
    
    try {
        const messageId = `msg_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        await redis.lpush('whatsapp:outgoing_queue', JSON.stringify({ to, message, media, messageId }));
        res.json({ success: true, messageId, message: 'Mensaje encolado' });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/send-media', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { to, message, mediaPath, filename } = req.body;
    if (!to || !mediaPath) return res.status(400).json({ error: 'Faltan parámetros' });
    
    try {
        const chatId = to.includes('@') ? to : `${to}@c.us`;
        const media = MessageMedia.fromFilePath(mediaPath);
        if (filename) media.filename = filename;
        
        const result = await client.sendMessage(chatId, media, { caption: message || '' });
        res.json({ success: true, messageId: result.id._serialized });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/send-location', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { to, latitude, longitude, description } = req.body;
    if (!to || !latitude || !longitude) return res.status(400).json({ error: 'Faltan parámetros' });
    
    try {
        const chatId = to.includes('@') ? to : `${to}@c.us`;
        const location = new Location(latitude, longitude, description);
        const result = await client.sendMessage(chatId, location);
        res.json({ success: true, messageId: result.id._serialized });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/send-contact', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { to, contact } = req.body;
    if (!to || !contact) return res.status(400).json({ error: 'Faltan parámetros' });
    
    try {
        const chatId = to.includes('@') ? to : `${to}@c.us`;
        const contactId = contact.number.includes('@') ? contact.number : `${contact.number}@c.us`;
        const vcard = `BEGIN:VCARD\nVERSION:3.0\nFN:${contact.name}\nTEL;type=CELL;type=VOICE;waid=${contact.number}:+${contact.number}\nEND:VCARD`;
        const result = await client.sendMessage(chatId, vcard, { contactCard: true });
        res.json({ success: true, messageId: result.id._serialized });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/reply', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { messageId, message } = req.body;
    if (!messageId || !message) return res.status(400).json({ error: 'Faltan parámetros' });
    
    try {
        const msg = await client.getMessageById(messageId);
        const result = await msg.reply(message);
        res.json({ success: true, messageId: result.id._serialized });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/forward', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { messageId, to } = req.body;
    if (!messageId || !to) return res.status(400).json({ error: 'Faltan parámetros' });
    
    try {
        const chatId = to.includes('@') ? to : `${to}@c.us`;
        const msg = await client.getMessageById(messageId);
        const result = await msg.forward(chatId);
        res.json({ success: true, messageId: result.id._serialized });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/result/:messageId', authMiddleware, async (req, res) => {
    const { messageId } = req.params;
    const result = await redis.get(`whatsapp:result:${messageId}`);
    if (result) {
        res.json(JSON.parse(result));
    } else {
        res.status(404).json({ error: 'Resultado no encontrado' });
    }
});

// ==================== GESTIÓN DE MENSAJES ====================

app.post('/api/delete-message', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { messageId, forEveryone } = req.body;
    if (!messageId) return res.status(400).json({ error: 'Falta messageId' });
    
    try {
        const msg = await client.getMessageById(messageId);
        await msg.delete(forEveryone || false);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/star-message', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { messageId } = req.body;
    if (!messageId) return res.status(400).json({ error: 'Falta messageId' });
    
    try {
        const msg = await client.getMessageById(messageId);
        await msg.star();
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/unstar-message', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { messageId } = req.body;
    if (!messageId) return res.status(400).json({ error: 'Falta messageId' });
    
    try {
        const msg = await client.getMessageById(messageId);
        await msg.unstar();
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/mark-read', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    let { chatId } = req.body;
    
    if (!chatId) {
        return res.status(400).json({ error: 'Falta chatId' });
    }
    
    try {
        // Asegurar formato correcto del chatId
        if (!chatId.includes('@')) {
            chatId = chatId + '@c.us';
        }
        
        logger.info(`Marcando chat como leído: ${chatId}`);
        
        const chat = await client.getChatById(chatId);
        
        if (!chat) {
            return res.status(404).json({ error: 'Chat no encontrado' });
        }
        
        await chat.sendSeen();
        
        res.json({ success: true, message: 'Chat marcado como leído' });
    } catch (error) {
        logger.error('Error marcando chat como leído:', error);
        res.status(500).json({ 
            error: error.message,
            details: 'No se pudo marcar el chat como leído',
            chatId: chatId
        });
    }
});

app.post('/api/mark-all-read', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    
    try {
        const chats = await client.getChats();
        let processed = 0;
        let errors = 0;
        
        for (const chat of chats) {
            if (chat.unreadCount > 0) {
                try {
                    await chat.sendSeen();
                    processed++;
                } catch (error) {
                    logger.error(`Error marcando chat ${chat.id._serialized}:`, error);
                    errors++;
                }
            }
        }
        
        res.json({ 
            success: true, 
            processed, 
            errors,
            message: `${processed} chats marcados como leídos`
        });
    } catch (error) {
        logger.error('Error marcando todos como leídos:', error);
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/mark-unread', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    let { chatId } = req.body;
    
    if (!chatId) {
        return res.status(400).json({ error: 'Falta chatId' });
    }
    
    try {
        if (!chatId.includes('@')) {
            chatId = chatId + '@c.us';
        }
        
        logger.info(`Marcando chat como no leído: ${chatId}`);
        
        const chat = await client.getChatById(chatId);
        
        if (!chat) {
            return res.status(404).json({ error: 'Chat no encontrado' });
        }
        
        await chat.markUnread();
        
        res.json({ success: true, message: 'Chat marcado como no leído' });
    } catch (error) {
        logger.error('Error marcando chat como no leído:', error);
        res.status(500).json({ 
            error: error.message,
            details: 'No se pudo marcar el chat como no leído',
            chatId: chatId
        });
    }
});

// ==================== CHATS ====================

app.get('/api/chats', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const limit = parseInt(req.query.limit) || 50;
    
    try {
        const chats = await client.getChats();
        const limitedChats = chats.slice(0, limit).map(chat => ({
            id: chat.id._serialized,
            name: chat.name,
            isGroup: chat.isGroup,
            unreadCount: chat.unreadCount,
            timestamp: chat.timestamp,
            archived: chat.archived,
            pinned: chat.pinned,
            isMuted: chat.isMuted
        }));
        res.json({ chats: limitedChats });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/chat/:chatId', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { chatId } = req.params;
    
    try {
        const chat = await client.getChatById(chatId);
        res.json({
            id: chat.id._serialized,
            name: chat.name,
            isGroup: chat.isGroup,
            unreadCount: chat.unreadCount,
            timestamp: chat.timestamp,
            archived: chat.archived,
            pinned: chat.pinned,
            isMuted: chat.isMuted
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// ==================== CORRECCIONES PARA server.js ====================
// Reemplaza estos dos endpoints en tu server.js

// CORRECCIÓN 1: Endpoint para obtener mensajes de un chat
app.get('/api/chat/:chatId/messages', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    let { chatId } = req.params;
    const limit = parseInt(req.query.limit) || 50;
    
    try {
        chatId = decodeURIComponent(chatId);
        if (!chatId.includes('@')) {
            chatId = chatId + '@c.us';
        }
        
        logger.info(`Obteniendo mensajes del chat: ${chatId}`);
        
        const chat = await client.getChatById(chatId);
        
        if (!chat) {
            return res.status(404).json({ error: 'Chat no encontrado' });
        }
        
        const messages = await chat.fetchMessages({ limit });
        
        // Crear directorio para media si no existe
        const mediaDir = path.join(__dirname, 'media', 'incoming');
        await fs.mkdir(mediaDir, { recursive: true });
        
        const mappedMessages = await Promise.all(messages.map(async (msg) => {
            const messageId = msg.id._serialized;
            let mediaUrl = null;
            let mediaPath = null;
            
            // Si el mensaje tiene media, procesarlo
            if (msg.hasMedia) {
                if (mediaCache.has(messageId)) {
                    const cached = mediaCache.get(messageId);
                    mediaUrl = cached.url;
                    mediaPath = cached.path;
                } else {
                    try {
                        const media = await msg.downloadMedia();
                        
                        if (media) {
                            const hash = crypto.createHash('md5')
                                .update(messageId)
                                .digest('hex');
                            
                            let ext = 'bin';
                            if (media.mimetype) {
                                const mimeMap = {
                                    'image/jpeg': 'jpg',
                                    'image/jpg': 'jpg',
                                    'image/png': 'png',
                                    'image/gif': 'gif',
                                    'image/webp': 'webp',
                                    'video/mp4': 'mp4',
                                    'video/3gpp': '3gp',
                                    'audio/ogg': 'ogg',
                                    'audio/mpeg': 'mp3',
                                    'audio/mp4': 'm4a',
                                    'audio/aac': 'aac',
                                    'application/pdf': 'pdf',
                                    'application/msword': 'doc',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx'
                                };
                                ext = mimeMap[media.mimetype] || 'bin';
                            }
                            
                            const filename = `${hash}.${ext}`;
                            const fullPath = path.join(mediaDir, filename);
                            
                            let fileExists = false;
                            try {
                                await fs.access(fullPath);
                                fileExists = true;
                            } catch (e) {
                                fileExists = false;
                            }
                            
                            if (!fileExists) {
                                await fs.writeFile(fullPath, media.data, 'base64');
                                logger.info(`Media guardado: ${filename}`);
                            }
                            
                            mediaUrl = `/media/incoming/${filename}`;
                            mediaPath = fullPath;
                            
                            mediaCache.set(messageId, {
                                url: mediaUrl,
                                path: mediaPath,
                                timestamp: Date.now()
                            });
                        }
                    } catch (mediaError) {
                        logger.error(`Error descargando media ${messageId}: ${mediaError.message}`);
                    }
                }
            }
            
            // ⚠️ CORRECCIÓN CRÍTICA: El timestamp de WhatsApp YA está en la zona horaria local del servidor
            // NO necesitamos convertirlo, solo devolverlo tal cual
            return {
                id: messageId,
                body: msg.body || '',
                from: msg.from || '',
                to: msg.to || '',
                timestamp: msg.timestamp, // ✅ Este timestamp ya está correcto
                hasMedia: msg.hasMedia || false,
                type: msg.type || 'chat',
                fromMe: msg.fromMe || false,
                mediaUrl: mediaUrl,
                mediaPath: mediaPath
            };
        }));
        
        res.json({ messages: mappedMessages });
    } catch (error) {
        logger.error('Error obteniendo mensajes del chat:', error);
        res.status(500).json({ 
            error: error.message,
            details: 'No se pudieron obtener los mensajes del chat',
            chatId: chatId
        });
    }
});


// AGREGAR: Endpoint para servir archivos media
app.use('/media', express.static(path.join(__dirname, 'media')));


// AGREGAR: Endpoint para obtener estadísticas de caché (útil para debugging)
app.get('/api/cache-stats', authMiddleware, (req, res) => {
    res.json({
        cacheSize: mediaCache.size,
        entries: Array.from(mediaCache.keys()).slice(0, 10)
    });
});

app.post('/api/search-messages', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { query, chatId, limit } = req.body;
    if (!query) return res.status(400).json({ error: 'Falta query' });
    
    try {
        const options = { limit: limit || 50 };
        if (chatId) options.chatId = chatId;
        const messages = await client.searchMessages(query, options);
        res.json({ messages: messages.map(msg => ({
            id: msg.id._serialized,
            body: msg.body,
            from: msg.from,
            timestamp: msg.timestamp
        }))});
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/archive-chat', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { chatId } = req.body;
    if (!chatId) return res.status(400).json({ error: 'Falta chatId' });
    
    try {
        const chat = await client.getChatById(chatId);
        await chat.archive();
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/unarchive-chat', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { chatId } = req.body;
    if (!chatId) return res.status(400).json({ error: 'Falta chatId' });
    
    try {
        const chat = await client.getChatById(chatId);
        await chat.unarchive();
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/pin-chat', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { chatId } = req.body;
    if (!chatId) return res.status(400).json({ error: 'Falta chatId' });
    
    try {
        const chat = await client.getChatById(chatId);
        await chat.pin();
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/unpin-chat', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { chatId } = req.body;
    if (!chatId) return res.status(400).json({ error: 'Falta chatId' });
    
    try {
        const chat = await client.getChatById(chatId);
        await chat.unpin();
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/mute-chat', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { chatId, duration } = req.body;
    if (!chatId) return res.status(400).json({ error: 'Falta chatId' });
    
    try {
        const chat = await client.getChatById(chatId);
        const muteExpiration = duration ? new Date(Date.now() + duration * 1000) : null;
        await chat.mute(muteExpiration);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/unmute-chat', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { chatId } = req.body;
    if (!chatId) return res.status(400).json({ error: 'Falta chatId' });
    
    try {
        const chat = await client.getChatById(chatId);
        await chat.unmute();
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/clear-chat', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { chatId } = req.body;
    if (!chatId) return res.status(400).json({ error: 'Falta chatId' });
    
    try {
        const chat = await client.getChatById(chatId);
        await chat.clearMessages();
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/delete-chat', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { chatId } = req.body;
    if (!chatId) return res.status(400).json({ error: 'Falta chatId' });
    
    try {
        const chat = await client.getChatById(chatId);
        await chat.delete();
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});


// ==================== ESTADOS / STATUS ====================

// Enviar estado de texto
app.post('/api/send-status', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { text, backgroundColor, font } = req.body;
    if (!text) return res.status(400).json({ error: 'Falta texto' });
    
    try {
        const result = await client.sendMessage('status@broadcast', text, {
            backgroundColor: backgroundColor || '#25D366',
            font: font || 'sans-serif'
        });
        res.json({ success: true, statusId: result.id._serialized });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// Obtener estados de contactos
app.get('/api/statuses', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    
    try {
        // WhatsApp Web.js no expone estados directamente
        // Se deben capturar mediante eventos
        res.json({ 
            success: true, 
            message: 'Los estados se capturan automáticamente via webhook'
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// ==================== CONTACTOS ====================

app.get('/api/contacts', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    
    try {
        const contacts = await client.getContacts();
        const mappedContacts = contacts.map(contact => ({
            id: contact.id._serialized,
            name: contact.name,
            pushname: contact.pushname,
            number: contact.number,
            isMyContact: contact.isMyContact,
            isBlocked: contact.isBlocked
        }));
        res.json({ contacts: mappedContacts });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/contact/:contactId', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { contactId } = req.params;
    
    try {
        const contact = await client.getContactById(contactId);
        res.json({
            id: contact.id._serialized,
            name: contact.name,
            pushname: contact.pushname,
            number: contact.number,
            isMyContact: contact.isMyContact,
            isBlocked: contact.isBlocked
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/contact/:contactId/profile-pic', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { contactId } = req.params;
    
    try {
        const contact = await client.getContactById(contactId);
        const profilePic = await contact.getProfilePicUrl();
        res.json({ profilePic });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/block-contact', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { contactId } = req.body;
    if (!contactId) return res.status(400).json({ error: 'Falta contactId' });
    
    try {
        const contact = await client.getContactById(contactId);
        await contact.block();
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/unblock-contact', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { contactId } = req.body;
    if (!contactId) return res.status(400).json({ error: 'Falta contactId' });
    
    try {
        const contact = await client.getContactById(contactId);
        await contact.unblock();
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/blocked-contacts', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    
    try {
        const contacts = await client.getContacts();
        const blocked = contacts.filter(c => c.isBlocked).map(c => ({
            id: c.id._serialized,
            name: c.name,
            number: c.number
        }));
        res.json({ contacts: blocked });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// ==================== GRUPOS ====================

app.post('/api/create-group', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { name, participants } = req.body;
    if (!name || !participants) return res.status(400).json({ error: 'Faltan parámetros' });
    
    try {
        const participantIds = participants.map(p => p.includes('@') ? p : `${p}@c.us`);
        const group = await client.createGroup(name, participantIds);
        res.json({ success: true, groupId: group.gid._serialized });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/groups', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    
    try {
        const chats = await client.getChats();
        const groups = chats.filter(c => c.isGroup).map(g => ({
            id: g.id._serialized,
            name: g.name,
            participants: g.participants?.length || 0
        }));
        res.json({ groups });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/group/:groupId', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { groupId } = req.params;
    
    try {
        const chat = await client.getChatById(groupId);
        res.json({
            id: chat.id._serialized,
            name: chat.name,
            description: chat.description,
            participants: chat.participants
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/group/add-participants', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { groupId, participants } = req.body;
    if (!groupId || !participants) return res.status(400).json({ error: 'Faltan parámetros' });
    
    try {
        const chat = await client.getChatById(groupId);
        const participantIds = participants.map(p => p.includes('@') ? p : `${p}@c.us`);
        await chat.addParticipants(participantIds);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/group/remove-participant', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { groupId, participantId } = req.body;
    if (!groupId || !participantId) return res.status(400).json({ error: 'Faltan parámetros' });
    
    try {
        const chat = await client.getChatById(groupId);
        const pId = participantId.includes('@') ? participantId : `${participantId}@c.us`;
        await chat.removeParticipants([pId]);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/group/promote', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { groupId, participantId } = req.body;
    if (!groupId || !participantId) return res.status(400).json({ error: 'Faltan parámetros' });
    
    try {
        const chat = await client.getChatById(groupId);
        const pId = participantId.includes('@') ? participantId : `${participantId}@c.us`;
        await chat.promoteParticipants([pId]);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/group/demote', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { groupId, participantId } = req.body;
    if (!groupId || !participantId) return res.status(400).json({ error: 'Faltan parámetros' });
    
    try {
        const chat = await client.getChatById(groupId);
        const pId = participantId.includes('@') ? participantId : `${participantId}@c.us`;
        await chat.demoteParticipants([pId]);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/group/set-subject', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { groupId, subject } = req.body;
    if (!groupId || !subject) return res.status(400).json({ error: 'Faltan parámetros' });
    
    try {
        const chat = await client.getChatById(groupId);
        await chat.setSubject(subject);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/group/set-description', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { groupId, description } = req.body;
    if (!groupId || !description) return res.status(400).json({ error: 'Faltan parámetros' });
    
    try {
        const chat = await client.getChatById(groupId);
        await chat.setDescription(description);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/group/set-picture', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { groupId, imagePath } = req.body;
    if (!groupId || !imagePath) return res.status(400).json({ error: 'Faltan parámetros' });
    
    try {
        const chat = await client.getChatById(groupId);
        const media = MessageMedia.fromFilePath(imagePath);
        await chat.setPicture(media);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/group/leave', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { groupId } = req.body;
    if (!groupId) return res.status(400).json({ error: 'Falta groupId' });
    
    try {
        const chat = await client.getChatById(groupId);
        await chat.leave();
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/group/:groupId/invite-code', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { groupId } = req.params;
    
    try {
        const chat = await client.getChatById(groupId);
        const inviteCode = await chat.getInviteCode();
        res.json({ inviteCode });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/group/revoke-invite', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { groupId } = req.body;
    if (!groupId) return res.status(400).json({ error: 'Falta groupId' });
    
    try {
        const chat = await client.getChatById(groupId);
        await chat.revokeInvite();
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// ==================== PERFIL ====================

app.post('/api/set-profile-name', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { name } = req.body;
    if (!name) return res.status(400).json({ error: 'Falta name' });
    
    try {
        await client.setDisplayName(name);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/set-profile-status', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { status } = req.body;
    if (!status) return res.status(400).json({ error: 'Falta status' });
    
    try {
        await client.setStatus(status);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/set-profile-picture', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { imagePath } = req.body;
    if (!imagePath) return res.status(400).json({ error: 'Falta imagePath' });
    
    try {
        const media = MessageMedia.fromFilePath(imagePath);
        await client.setProfilePicture(media);
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/remove-profile-picture', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    
    try {
        await client.removeProfilePicture();
        res.json({ success: true });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// ==================== VALIDACIÓN ====================

app.post('/api/validate', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { number } = req.body;
    if (!number) return res.status(400).json({ error: 'Número requerido' });
    
    try {
        const numberId = number.includes('@') ? number : `${number}@c.us`;
        const isRegistered = await client.isRegisteredUser(numberId);
        res.json({ valid: isRegistered, number: numberId });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/get-number-id', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { number } = req.body;
    if (!number) return res.status(400).json({ error: 'Número requerido' });
    
    try {
        const numberId = await client.getNumberId(number);
        res.json({ numberId: numberId ? numberId._serialized : null });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// ==================== ESTADOS / STATUS ====================

app.post('/api/send-status', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { text, backgroundColor, font } = req.body;
    if (!text) return res.status(400).json({ error: 'Falta texto' });
    
    try {
        const result = await client.sendMessage('status@broadcast', text, {
            backgroundColor: backgroundColor || '#25D366',
            font: font || 'sans-serif'
        });
        res.json({ success: true, statusId: result.id._serialized });
    } catch (error) {
        logger.error('Error enviando estado:', error);
        res.status(500).json({ error: error.message });
    }
});

app.post('/api/delete-status', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    const { statusId } = req.body;
    if (!statusId) return res.status(400).json({ error: 'Falta statusId' });
    
    try {
        const msg = await client.getMessageById(statusId);
        await msg.delete(true);
        res.json({ success: true });
    } catch (error) {
        logger.error('Error eliminando estado:', error);
        res.status(500).json({ error: error.message });
    }
});

app.get('/api/statuses', authMiddleware, async (req, res) => {
    if (!isReady) return res.status(503).json({ error: 'Cliente no está listo' });
    
    try {
        // WhatsApp Web.js no expone estados directamente
        res.json({ 
            success: true, 
            message: 'Estados capturados vía eventos'
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
});

// ==================== MANEJO DE ERRORES ====================

app.use((err, req, res, next) => {
    logger.error('Error no manejado:', err);
    res.status(500).json({ error: 'Error interno del servidor' });
});

// ==================== INICIO DEL SERVIDOR ====================

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    logger.info(`Servidor corriendo en puerto ${PORT}`);
});

initializeWhatsApp();
processOutgoingQueue();

process.on('SIGINT', async () => {
    logger.info('Cerrando servidor...');
    if (client) await client.destroy();
    await redis.quit();
    process.exit(0);
});
