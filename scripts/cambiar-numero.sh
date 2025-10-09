#!/bin/bash
# /root/scripts/cambiar-numero.sh
# Script para limpiar completamente la sesión de WhatsApp y permitir conectar otro número

set -e  # Detener si hay errores

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # Sin color

# Auto-detectar ruta del proyecto
# Prioridad: 1) Variable de entorno, 2) Archivo .env, 3) Buscar package.json
if [ -n "$PROJECT_ROOT" ]; then
    # Ya está definida como variable de entorno
    echo "  ℹ️  Usando PROJECT_ROOT de variable de entorno"
elif [ -f "/root/.env" ] && grep -q "PROJECT_ROOT=" "/root/.env"; then
    # Leer desde .env global
    PROJECT_ROOT=$(grep "PROJECT_ROOT=" /root/.env | cut -d '=' -f2 | tr -d '"' | tr -d "'")
    echo "  ℹ️  Usando PROJECT_ROOT de /root/.env"
else
    # Buscar automáticamente el proyecto
    echo "  🔍 Buscando proyecto WhatsApp..."
    PROJECT_ROOT=$(find /www/wwwroot /var/www /home -name "package.json" -path "*/whatsapp*/package.json" 2>/dev/null | head -1 | xargs dirname 2>/dev/null)
    
    if [ -z "$PROJECT_ROOT" ]; then
        # Fallback: ruta hardcodeada
        PROJECT_ROOT="/www/wwwroot/whatsapp.cellcomweb.com.ar"
        echo -e "${YELLOW}  ⚠️  No se encontró automáticamente, usando ruta por defecto${NC}"
    else
        echo -e "${GREEN}  ✓ Proyecto encontrado automáticamente${NC}"
    fi
fi

echo "  📂 Ruta del proyecto: $PROJECT_ROOT"

# Validar que la ruta existe
if [ ! -d "$PROJECT_ROOT" ]; then
    echo -e "${RED}❌ ERROR: No se encontró el proyecto en $PROJECT_ROOT${NC}"
    exit 1
fi

# Rutas derivadas
SESSION_PATH="$PROJECT_ROOT/whatsapp-session"
CACHE_PATH="$PROJECT_ROOT/.wwebjs_cache"
AUTH_PATH="$PROJECT_ROOT/.wwebjs_auth"

echo -e "${YELLOW}======================================${NC}"
echo -e "${YELLOW}  CAMBIO DE NÚMERO - LIMPIEZA TOTAL  ${NC}"
echo -e "${YELLOW}======================================${NC}"
echo ""

# Confirmación (si no se pasa 's' como input)
if [ ! -t 0 ]; then
    # Input viene de pipe/redirect
    read -r CONFIRM
else
    echo -e "${RED}⚠️  ADVERTENCIA: Esto eliminará toda la sesión actual${NC}"
    echo ""
    read -p "¿Estás seguro? (s/N): " CONFIRM
fi

if [[ ! "$CONFIRM" =~ ^[sS]$ ]]; then
    echo -e "${YELLOW}Operación cancelada${NC}"
    exit 0
fi

echo ""
echo -e "${GREEN}Iniciando limpieza...${NC}"
echo ""

# 1. Detener servicio PM2
echo "📦 Deteniendo servicio Node.js..."
pm2 restart whatsapp 2>/dev/null || echo "  ℹ️  Servicio ya estaba detenido"
sleep 2

# 2. Limpiar Redis
echo "🗄️  Limpiando Redis..."
if command -v redis-cli &> /dev/null; then
    # Intentar con y sin password
    redis-cli -a "${REDIS_PASSWORD:-}" DEL whatsapp:status whatsapp:qr whatsapp:session 2>/dev/null || \
    redis-cli DEL whatsapp:status whatsapp:qr whatsapp:session 2>/dev/null || \
    echo "  ⚠️  No se pudo conectar a Redis (continuando...)"
    
    # Limpiar todas las claves whatsapp:*
    redis-cli -a "${REDIS_PASSWORD:-}" --scan --pattern "whatsapp:*" | xargs -r redis-cli -a "${REDIS_PASSWORD:-}" DEL 2>/dev/null || true
    
    echo "  ✓ Redis limpiado"
else
    echo "  ⚠️  redis-cli no encontrado (omitiendo)"
fi

# 3. Eliminar carpeta de sesión
if [ -d "$SESSION_PATH" ]; then
    echo "📁 Eliminando carpeta de sesión..."
    rm -rf "$SESSION_PATH"
    echo "  ✓ whatsapp-session eliminada"
else
    echo "  ℹ️  Carpeta de sesión no existía"
fi

# 4. Eliminar caché de WhatsApp Web
if [ -d "$CACHE_PATH" ]; then
    echo "🗂️  Eliminando caché..."
    rm -rf "$CACHE_PATH"
    echo "  ✓ .wwebjs_cache eliminada"
else
    echo "  ℹ️  Caché no existía"
fi

# 5. Eliminar carpeta de autenticación (si existe)
if [ -d "$AUTH_PATH" ]; then
    echo "🔐 Eliminando datos de autenticación..."
    rm -rf "$AUTH_PATH"
    echo "  ✓ .wwebjs_auth eliminada"
fi

# 6. Limpiar logs antiguos (opcional)
echo "📄 Limpiando logs antiguos..."
find "$PROJECT_ROOT/logs" -type f -name "*.log" -mtime +7 -delete 2>/dev/null || true
echo "  ✓ Logs antiguos eliminados"

# 7. Reiniciar servicio
echo "🚀 Reiniciando servicio Node.js..."
pm2 restart whatsapp
sleep 3

# 8. Verificar estado
echo ""
echo -e "${GREEN}✅ Limpieza completada exitosamente${NC}"
echo ""
echo "📱 Ahora puedes:"
echo "   1. Acceder a la interfaz web"
echo "   2. Escanear el nuevo código QR"
echo "   3. Conectar un nuevo número de WhatsApp"
echo ""

# Mostrar estado del servicio
pm2 status whatsapp

echo ""
echo -e "${YELLOW}======================================${NC}"
echo -e "${GREEN}    ✓ Proceso completado con éxito    ${NC}"
echo -e "${YELLOW}======================================${NC}"

exit 0