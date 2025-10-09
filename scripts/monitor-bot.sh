#!/bin/bash

# Monitor del Bot de Respuestas Automáticas
PROJECT_DIR="/www/wwwroot/whatsapp.cellcomweb.com.ar"
LOG_FILE="$PROJECT_DIR/logs/bot-monitor.log"

timestamp() {
    date "+%Y-%m-%d %H:%M:%S"
}

log() {
    echo "[$(timestamp)] $1" >> "$LOG_FILE"
}

# Verificar si el bot está activo
BOT_STATUS=$(mysql -u whatsapp_user -p'PASSWORD' whatsapp_db -se \
    "SELECT valor FROM configuracion WHERE clave = 'bot_activo'")

log "Bot Status: $BOT_STATUS"

# Contar respuestas del día
RESPUESTAS_HOY=$(mysql -u whatsapp_user -p'PASSWORD' whatsapp_db -se \
    "SELECT COUNT(*) FROM mensajes_salientes 
     WHERE tipo = 'auto_reply' 
     AND DATE(fecha_envio) = CURDATE()")

log "Respuestas automáticas hoy: $RESPUESTAS_HOY"

# Verificar errores en webhook
ERRORES=$(tail -100 "$PROJECT_DIR/logs/webhook.log" | grep -c "ERROR")

if [ $ERRORES -gt 10 ]; then
    log "ALERTA: Demasiados errores en webhook ($ERRORES)"
    # Aquí puedes agregar notificación por email o SMS
fi

# Estadísticas de uso
RESPUESTAS_ACTIVAS=$(mysql -u whatsapp_user -p'PASSWORD' whatsapp_db -se \
    "SELECT COUNT(*) FROM respuestas_automaticas WHERE activa = 1")

log "Respuestas activas: $RESPUESTAS_ACTIVAS"

echo "Monitor ejecutado correctamente"