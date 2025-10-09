# 📱 WhatsApp Web API - Sistema de Gestión

Sistema profesional de administración y automatización de WhatsApp Web con interfaz web completa, desarrollado con Node.js, PHP, Redis y MySQL.

## Captura

![Captura](Captura.png)

## 📋 Características

### Funcionalidades Core
- ✅ Conexión persistente con WhatsApp Web mediante whatsapp-web.js
- ✅ Autenticación por QR Code con auto-refresh
- ✅ Cola de mensajes con Redis para envío asíncrono
- ✅ Webhook para mensajes entrantes
- ✅ API REST completa con autenticación por API Key
- ✅ Panel web de administración con auto-actualización
- ✅ Sistema de notificaciones en tiempo real
  - Notificaciones visuales (toast)
  - Notificaciones de escritorio
  - Sonido personalizado de WhatsApp
  - Badge en título del navegador
  - Contador en sidebar con animación
  - Vibración en dispositivos móviles
- ✅ Sistema de roles y permisos (RBAC)

### Mensajería
- Envío de mensajes de texto con formato (negrita, cursiva, código)
- Envío de imágenes, videos, audios y documentos
- Envío de ubicación (coordenadas GPS)
- Envío de contactos (vCard)
- Responder y reenviar mensajes
- Validación de números con WhatsApp

### Gestión de Chats
- Listar todos los chats (individuales y grupos)
- Obtener mensajes de un chat específico
- Buscar mensajes por texto
- Marcar como leído/no leído
- Archivar/desarchivar chats
- Fijar/desfijar chats
- Silenciar/desilenciar chats
- Eliminar mensajes y chats
- Auto-actualización en tiempo real
- Sistema inteligente de actualización (solo cuando hay cambios)
- Precarga de nombres de contactos con caché
- Detección automática de mensajes nuevos
- Notificaciones push para mensajes entrantes

### Gestión de Contactos
- Listar y gestionar contactos
- Obtener información y foto de perfil
- Bloquear/desbloquear contactos

### Gestión de Grupos
- Crear y administrar grupos
- Agregar/eliminar participantes
- Promover/degradar administradores
- Cambiar nombre, descripción y foto del grupo
- Obtener y revocar código de invitación

### Estados (Stories)
- Crear estados de texto con personalización
- Ver estados activos (últimas 24 horas)
- Personalizar color de fondo y tipografía
- Vista previa en tiempo real

### Multimedia
- Almacenamiento organizado en `/media/`
  - `/media/incoming/` - Archivos recibidos
  - `/media/uploads/` - Archivos enviados
- Proxy de medios con autenticación
- Visualización de multimedia en modal
- Soporte para múltiples formatos (JPG, PNG, MP4, MP3, PDF, DOC, XLS, etc.)

## 🔧 Requisitos del Sistema

- **SO**: Ubuntu 20.04+ o similar
- **Node.js**: 18+
- **PHP**: 8.0+
- **Redis**: 6+
- **MySQL**: 8+
- **Nginx**
- **Composer**
- **PM2** (para gestión de procesos Node.js)

### Recursos Recomendados
- **CPU**: 2 cores mínimo
- **RAM**: 2GB mínimo (4GB recomendado)
- **Disco**: 10GB mínimo
- **Red**: Conexión estable a Internet

## 🏗️ Arquitectura

```
┌──────────────────────────────────────────────────────────┐
│                    FRONTEND WEB                          │
│              (Dashboard PHP - index.php)                 │
└──────────────────┬───────────────────────────────────────┘
                   │ HTTP/HTTPS
┌──────────────────▼───────────────────────────────────────┐
│                API PHP (public/api/)                      │
└──────────┬───────────────────────────────────────────────┘
           │
    ┌──────┴──────┐
    │             │
┌───▼──────┐  ┌──▼────────────────────────────────────────┐
│WhatsApp  │  │      Node.js Server                        │
│Client.php│  │      (server.js)                           │
└───┬──────┘  └──┬────────────────────────────────────────┘
    │            │ whatsapp-web.js
    │            │
    │      ┌─────▼──────────┐
    │      │  WhatsApp Web  │
    │      │  (Puppeteer)   │
    │      └────────────────┘
    │
┌───┴─────┬────────────┬────────┐
│         │            │        │
▼         ▼            ▼        ▼
MySQL   Redis       Logs    Media
(BD)    (Cola)     (JSON)   Files
```

## 📦 Instalación

### Requisitos Previos

Antes de comenzar, asegúrate de tener instalado:

```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar dependencias del sistema
sudo apt install -y curl git nginx redis-server mysql-server \
    php8.3 php8.3-fpm php8.3-mysql php8.3-redis php8.3-curl \
    php8.3-mbstring php8.3-xml php8.3-zip

# Instalar Node.js 18+
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Instalar PM2 globalmente
sudo npm install -g pm2
```

### 1. Preparar Directorio y Clonar Repositorio

```bash
# Crear directorio base
sudo mkdir -p /www/wwwroot
cd /www/wwwroot

# Clonar repositorio (o descargar desde tu fuente)
sudo git clone https://tu-repositorio.git whatsapp

# O si tienes un archivo ZIP:
# sudo unzip whatsapp-system.zip -d whatsapp

# Establecer permisos
cd whatsapp
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
```

### 2. Instalar Dependencias Node.js

```bash
npm install
```

**Dependencias principales**: whatsapp-web.js, express, ioredis, qrcode, winston, helmet, express-rate-limit

### 3. Instalar Dependencias PHP

```bash
composer install
```

### 4. Configurar Base de Datos

```bash
mysql -u root -p
```

```sql
CREATE DATABASE whatsapp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'whatsapp_user'@'localhost' IDENTIFIED BY 'TU_PASSWORD_SEGURA';
GRANT ALL PRIVILEGES ON whatsapp_db.* TO 'whatsapp_user'@'localhost';
FLUSH PRIVILEGES;

USE whatsapp_db;
SOURCE /ruta/a/database_schema.sql;
```

### 5. Configurar Variables de Entorno

```bash
nano .env
```

```env
# API Configuration
PORT=3000
API_KEY=tu_api_key_segura_aqui_32_caracteres

# Redis Configuration
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=tu_password_redis

# Webhook Configuration
WEBHOOK_URL=https://tu-dominio.com/webhook.php
WEBHOOK_SECRET=tu_webhook_secret_64_caracteres

# WhatsApp Configuration
MAX_RETRIES=3
MESSAGE_DELAY=2000

# Logging
LOG_LEVEL=info
```

**Generar claves seguras:**
```bash
openssl rand -hex 32  # API Key
openssl rand -hex 32  # Webhook Secret
```

### 6. Configurar Redis

```bash
nano /etc/redis/redis.conf
# Descomentar: requirepass tu_password_redis

systemctl restart redis
redis-cli -a tu_password_redis ping
```

### 7. Iniciar Servidor Node.js

```bash
# Producción con PM2
pm2 start ecosystem.config.js
pm2 save
pm2 startup

# Ver logs
pm2 logs whatsapp-api
```

### 8. Configurar Nginx

```nginx
server {
    listen 80;
    server_name whatsapp.tudominio.com;
    root /www/wwwroot/whatsapp/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.env {
        deny all;
    }
}
```

### 9. Vincular WhatsApp

1. Acceder al dashboard: `https://whatsapp.tudominio.com/`
2. Hacer clic en "Conectar WhatsApp" en el sidebar
3. Escanear código QR con WhatsApp móvil
   - WhatsApp > Configuración > Dispositivos Vinculados > Vincular Dispositivo


## 🔔 Sistema de Notificaciones

### Características del Sistema
El sistema incluye notificaciones en tiempo real que se activan automáticamente cuando llegan mensajes nuevos:

**Notificaciones Visuales:**
- Toast verde en la esquina superior derecha
- Duración: 6 segundos
- Click para abrir el chat
- Muestra nombre del remitente y cantidad de mensajes

**Notificaciones de Escritorio:**
- Requiere permiso del usuario (se solicita automáticamente)
- Solo se muestran cuando la pestaña no está activa
- Incluye icono de WhatsApp
- Click para enfocar la ventana

**Notificaciones de Sonido:**
- Archivo: `/assets/sounds/new-message.mp3`
- Se reproduce automáticamente al recibir mensajes
- Volumen: 40%
- Requiere interacción previa del usuario (limitación del navegador)

**Badge en Título:**
- Formato: `(3) WhatsApp Dashboard`
- Se actualiza en tiempo real
- Se remueve cuando no hay mensajes pendientes

**Contador en Sidebar:**
- Badge rojo con animación
- Se actualiza cada 5 segundos
- Animación al aparecer/desaparecer

**Vibración:**
- Solo en dispositivos móviles compatibles
- Patrón: 200ms, pausa 100ms, 200ms

### Configuración

El sistema verifica mensajes nuevos cada 5 segundos mediante:
**Personalizar intervalo:**
```javascript
// En index.php, buscar:
const GLOBAL_CONFIG = {
    CHECK_INTERVAL: 5000, // Cambiar a los milisegundos deseados
    SOUND_ENABLED: true,
    DESKTOP_NOTIFICATIONS: true
};

Deshabilitar sonido:
SOUND_ENABLED: false

Deshabilitar notificaciones de escritorio:
DESKTOP_NOTIFICATIONS: false



## ⚙️ Estructura de Archivos

```
whatsapp/
├── public/
│   ├── index.php                    # Dashboard principal
│   ├── login.php                    # Autenticación
│   ├── logout.php
│   ├── webhook.php                  # Webhook para mensajes entrantes
│   ├── logs.php                     # Visor de logs
│   ├── flyers.php
│   ├── guia.php
│   ├── favicon.ico
│   │
│   ├── api/                         # APIs REST
│   │   ├── send.php                 # Envío de mensajes
│   │   ├── send-media.php           # Envío de multimedia
│   │   ├── send-gif.php             # Envío de GIFs
│   │   ├── gif-search.php           # Búsqueda de GIFs
│   │   ├── chats.php                # Gestión de chats
│   │   ├── get-chats.php            # Listar chats
│   │   ├── get-chat-messages.php    # Obtener mensajes de un chat
│   │   ├── check-unread.php         # ✨ Verificar mensajes no leídos (notificaciones)
│   │   ├── check-messages.php       # Verificación de mensajes
│   │   ├── mark-read.php            # Marcar como leído
│   │   ├── mark-all-read.php        # Marcar todos como leídos
│   │   ├── contacts.php             # CRUD de contactos
│   │   ├── get-contact-name.php     # ✨ Obtener nombre de contacto individual
│   │   ├── get-all-contact-names.php # ✨ Precarga de todos los nombres
│   │   ├── groups.php               # Gestión de grupos
│   │   ├── get-groups-status.php    # Estado de grupos
│   │   ├── group-management-modal.php
│   │   ├── broadcast.php            # Difusión masiva
│   │   ├── templates.php            # Plantillas de mensajes
│   │   ├── auto-reply.php           # Respuestas automáticas
│   │   ├── procesar-respuesta-automatica.php
│   │   ├── bot-processor.php        # Procesador del bot
│   │   ├── get-bot-status.php       # Estado del bot
│   │   ├── sync-bot-status.php      # Sincronización estado bot
│   │   ├── horarios.php             # Gestión de horarios
│   │   ├── verificar-horario.php    # Verificación de horario actual
│   │   ├── get-mensaje-fuera-horario.php
│   │   ├── guardar-mensaje-fuera-horario.php
│   │   ├── media-proxy.php          # Proxy de archivos multimedia
│   │   ├── stats.php                # Estadísticas
│   │   ├── dashboard-stats.php      # Datos para gráficos
│   │   ├── get-logs.php             # Obtener logs
│   │   ├── settings.php             # Configuración del sistema
│   │   ├── users.php                # Gestión de usuarios
│   │   ├── roles.php                # Gestión de roles y permisos
│   │   ├── profile.php              # Perfil de usuario
│   │   └── maintenance.php          # Mantenimiento del sistema
│   │
│   ├── pages/                       # Páginas del dashboard
│   │   ├── chats.php                # ✨ Interfaz de chats con notificaciones
│   │   ├── contacts.php             # Gestión de contactos
│   │   ├── groups.php               # Gestión de grupos
│   │   ├── broadcast.php            # Difusión masiva
│   │   ├── templates.php            # Plantillas
│   │   ├── auto-reply.php           # Respuestas automáticas
│   │   ├── status.php               # Estados/Stories
│   │   ├── stats.php                # Estadísticas y reportes
│   │   ├── settings.php             # Configuración
│   │   ├── users.php                # Administración de usuarios
│   │   ├── qr-connect.php           # Conexión por QR
│   │   └── session-manager.php      # Gestión de sesiones
│   │
│   └── assets/
│       ├── css/
│       │   ├── dashboard.css
│       │   └── dashboard-compact.css
│       ├── js/
│       │   ├── dashboard.js
│       │   └── groups-realtime.js
│       ├── img/
│       │   ├── favicon.png
│       │   ├── whatsapp-icon.png
│       │   └── badge-icon.png
│       └── sounds/                  # ✨ Sonidos de notificación
│           ├── new-message.mp3      # Sonido principal (WhatsApp)
│           └── chat-message.mp3     # Sonido alternativo
│
├── media/                           # Archivos multimedia
│   └── incoming/                    # Archivos recibidos de WhatsApp
│
├── uploads/                         # Archivos subidos
│   ├── media/
│   └── temp/
│
├── src/                             # Clases PHP
│   ├── WhatsAppClient.php           # Cliente principal de WhatsApp
│   ├── Database.php                 # Conexión y queries a MySQL
│   └── Auth.php                     # Sistema de autenticación y permisos
│
├── database/
│   └── schema.sql                   # Esquema de base de datos
│
├── logs/                            # Logs del sistema
│   ├── combined.log
│   ├── error.log
│   └── access.log
│
├── scripts/                         # Scripts de utilidad
│   ├── manage.sh                    # Script principal de gestión
│   ├── monitor-bot.sh               # Monitoreo del bot
│   ├── limpieza-completa.sh         # Limpieza del sistema
│   └── cambiar-numero.sh            # Cambio de número de WhatsApp
│
├── backups/                         # Backups automáticos
│
├── docs/                            # Documentación adicional
│
├── config/                          # Archivos de configuración
│
├── whatsapp-session/                # Sesión de WhatsApp (generada automáticamente)
│
├── server.js                        # ✨ Servidor Node.js principal
├── session-manager-api.js           # API de gestión de sesiones
├── ecosystem.config.js              # Configuración PM2
├── package.json                     # Dependencias Node.js
├── package-lock.json
├── composer.json                    # Dependencias PHP
├── composer.lock
├── .env                             # Variables de entorno (NO VERSIONAR)
├── .gitignore
├── README.md                        # Este archivo
├── guia-mantenimiento-whatsapp.txt  # Guía de mantenimiento
└── estructura de archivos.txt       # Listado de archivos
```
### Archivos Clave del Sistema de Notificaciones

| Archivo | Descripción |
|---------|-------------|
| `public/api/check-unread.php` | Endpoint que verifica mensajes no leídos cada 5s |
| `public/api/get-contact-name.php` | Obtiene nombre de un contacto individual |
| `public/api/get-all-contact-names.php` | Precarga todos los nombres al inicio |
| `public/assets/sounds/new-message.mp3` | Sonido de notificación de WhatsApp |
| `server.js` (líneas 100-150) | Filtro de mensajes `status@broadcast` |
| `public/index.php` (líneas 3400-3650) | Sistema global de notificaciones JavaScript |
| `public/pages/chats.php` (líneas 2750-2900) | Sistema de actualización inteligente de chats |


## 🚀 Uso del Sistema

### Dashboard Web

**Acceso:**
```
https://whatsapp.tudominio.com/
Usuario: admin
Password: admin123 (cambiar después del primer login)
```

**Módulos:**
1. Dashboard - Vista general con estadísticas
2. Chats - Interfaz tipo WhatsApp Web
3. Contactos - CRUD completo
4. Grupos - Creación y gestión
5. Difusión - Envío masivo programado
6. Plantillas - Mensajes predefinidos
7. Respuestas Auto - Bot con palabras clave
8. Estados - Stories/Estados de WhatsApp
9. Estadísticas - Gráficos y reportes
10. Configuración - Ajustes del sistema
11. Usuarios - Gestión de usuarios y roles

### Uso desde PHP

```php
<?php
require_once 'src/WhatsAppClient.php';

$whatsapp = new WhatsAppClient(
    'http://127.0.0.1:3000',
    'tu_api_key',
    [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => 'tu_redis_password'
    ]
);

// Verificar estado
if (!$whatsapp->isReady()) {
    die("WhatsApp no está conectado");
}

// Enviar mensaje simple
$messageId = $whatsapp->queueMessage(
    '5493482303030',
    '¡Hola! Este es un mensaje de prueba.'
);

// Enviar imagen
$whatsapp->sendImage(
    '5493482303030',
    '/ruta/a/imagen.jpg',
    'Mira esta imagen'
);

// Enviar ubicación
$whatsapp->sendLocation(
    '5493482303030',
    -34.6037,  // Latitud
    -58.3816,  // Longitud
    'Buenos Aires, Argentina'
);

// Obtener chats
$chats = $whatsapp->getChats(50);

// Crear grupo
$whatsapp->createGroup('Mi Grupo', [
    '5491112345678',
    '5491187654321'
]);
```

## 👥 Sistema de Roles y Permisos

### Roles Disponibles

| Rol | Descripción | Permisos por Defecto |
|-----|-------------|---------------------|
| **Administrador** | Control total del sistema | Acceso completo a todos los módulos |
| **Operador** | Usuario avanzado | Gestión de chats, contactos, grupos y difusión |
| **Supervisor** | Monitoreo y reportes | Visualización de estadísticas y reportes |
| **Visor** | Solo lectura | Consulta de información sin modificar |

### Estructura de Base de Datos

```sql
-- Tablas principales
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) UNIQUE NOT NULL,
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE
);

CREATE TABLE permisos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) UNIQUE NOT NULL,
    descripcion TEXT,
    modulo VARCHAR(50) NOT NULL,
    accion VARCHAR(50) NOT NULL
);

CREATE TABLE roles_permisos (
    rol_id INT,
    permiso_id INT,
    PRIMARY KEY (rol_id, permiso_id),
    FOREIGN KEY (rol_id) REFERENCES roles(id),
    FOREIGN KEY (permiso_id) REFERENCES permisos(id)
);

-- Agregar rol a usuarios
ALTER TABLE usuarios ADD COLUMN rol_id INT;
ALTER TABLE usuarios ADD FOREIGN KEY (rol_id) REFERENCES roles(id);
```

### Implementación

La clase `Auth.php` gestiona permisos:

```php
$auth = Auth::getInstance();

// Verificar permiso
if ($auth->hasPermission('enviar_mensajes')) {
    // Permitir acción
}

// Verificar acceso a página
if ($auth->canViewPage('chats')) {
    // Mostrar página
}
```

## 📡 API REST

### Autenticación

Todos los endpoints requieren header:
```
X-API-Key: tu_api_key
```

### Endpoints Principales

```bash
# Estado del servidor
GET /api/health

# Estado de WhatsApp
GET /api/status

# Código QR
GET /api/qr

# Enviar mensaje
POST /api/send
{
  "to": "5493482303030",
  "message": "Hola!"
}

# Validar número
POST /api/validate
{
  "number": "5493482303030"
}
```

## 🛠️ Mantenimiento

### Script de Gestión

```bash
./manage.sh [comando]
```

**Comandos:**
- `start` - Iniciar servicios
- `stop` - Detener servicios
- `restart` - Reiniciar servicios
- `status` - Ver estado
- `logs` - Ver logs en tiempo real
- `health` - Verificar salud del sistema
- `backup` - Crear backup completo

### Backups Automáticos

```bash
crontab -e

# Backup diario a las 2 AM
0 2 * * * /www/wwwroot/whatsapp/manage.sh backup
```

### Logs

```bash
# Logs Node.js
pm2 logs whatsapp-api
tail -f logs/combined.log

# Logs PHP
tail -f /var/log/nginx/error.log
```

## 🔍 Troubleshooting

### WhatsApp no conecta

```bash
curl http://localhost:3000/api/health
pm2 logs whatsapp-api
pm2 restart whatsapp-api
```

### Error de Redis

```bash
redis-cli -a tu_password ping
systemctl restart redis
```

### Mensajes no se envían

```bash
redis-cli -a tu_password
LLEN whatsapp:outgoing_queue
LRANGE whatsapp:outgoing_queue 0 -1
```

### Sesión expira

```bash
rm -rf whatsapp-session/
pm2 restart whatsapp-api
# Volver a escanear QR
```

## 🔒 Seguridad

### Checklist de Seguridad

- [ ] Cambiar password de admin
- [ ] Cambiar API_KEY del .env
- [ ] Configurar HTTPS (SSL/TLS)
- [ ] Configurar firewall
- [ ] Permisos correctos en archivos (644/755)
- [ ] .env con permisos 600
- [ ] Rate limiting configurado
- [ ] Backups automáticos

### Configurar Firewall

```bash
ufw allow 80/tcp
ufw allow 443/tcp
ufw deny 3000/tcp  # Node.js solo local
ufw deny 6379/tcp  # Redis solo local
ufw enable
```

### Configurar HTTPS

```bash
certbot --nginx -d whatsapp.tudominio.com
```

## 📊 Base de Datos

### Mantenimiento

```sql
-- Limpiar mensajes antiguos (>90 días)
DELETE FROM mensajes_entrantes 
WHERE fecha_recepcion < DATE_SUB(NOW(), INTERVAL 90 DAY);

DELETE FROM mensajes_salientes 
WHERE fecha_creacion < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Optimizar tablas
OPTIMIZE TABLE mensajes_entrantes, mensajes_salientes, logs;
```

## 📝 Mejores Prácticas

### Envío de Mensajes

1. **Respetar límites de WhatsApp**: Máximo 100 mensajes/hora
2. **Validar números antes de enviar**
3. **Usar cola para envíos masivos**
4. **Delay mínimo 2 segundos entre mensajes**
5. **Personalizar mensajes con variables**

### Gestión de Sesión

1. Mantener sesión activa
2. Backup regular de `whatsapp-session/`
3. Activar multi-device en WhatsApp móvil
4. Monitorear desconexiones

## 📚 Documentación Adicional

- WhatsApp Web.js: https://wwebjs.dev/
- Redis: https://redis.io/documentation
- Express: https://expressjs.com/
- PM2: https://pm2.keymetrics.io/

## 📄 Changelog

### v1.2.0 (2025-10-05) - ⭐ ACTUAL
- ✅ Sistema completo de Roles y Permisos (RBAC)
- ✅ Sistema de notificaciones en tiempo real
  - Notificaciones visuales (toast)
  - Notificaciones de escritorio
  - Sonido de WhatsApp
  - Badge en título del navegador
  - Contador animado en sidebar
- ✅ Sistema inteligente de actualización de chats
  - Solo actualiza cuando hay cambios reales
  - Precarga de nombres de contactos con caché
  - Detección automática de mensajes nuevos
- ✅ Clase Auth.php para gestión de autenticación
- ✅ Control granular por módulo y acción
- ✅ Logs de auditoría

### v1.1.0 (2025-10-03)
- ✅ Sistema de multimedia reorganizado
- ✅ Modal interactivo para visualización
- ✅ Proxy de medios con seguridad mejorada

### v1.0.0 (2025-09-30)
- ✅ Sistema completo funcional
- ✅ Dashboard web responsivo
- ✅ API REST completa

## 📞 Contacto y Soporte

- **Web**: https://whatsapp.cellcomweb.com.ar
- **Email**: soporte@cellcomweb.com.ar
- **WhatsApp**: +54 9 3482 309495

### Horarios de Soporte
- Lunes a Viernes: 9:00 - 18:00 (GMT-3)
- Sábados: 9:00 - 13:00 (GMT-3)

## ⚖️ Licencia

Uso privado - Todos los derechos reservados  
Copyright (c) 2025 Cellcom Technology

---

**Última actualización**: 5 de Octubre, 2025  
**Versión**: 1.2.0  
**Desarrollado por**: Cellcom Technology# whatsapp_api_php
