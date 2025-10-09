#!/bin/bash

PROJECT_DIR="/www/wwwroot/whatsapp.cellcomweb.com.ar"
cd $PROJECT_DIR

case "$1" in
    start)
        echo "Iniciando servicios WhatsApp..."
        pm2 start server.js --name whatsapp-api
        pm2 save
        echo "✓ Servicios iniciados"
        ;;
    stop)
        echo "Deteniendo servicios WhatsApp..."
        pm2 stop whatsapp-api
        echo "✓ Servicios detenidos"
        ;;
    restart)
        echo "Reiniciando servicios WhatsApp..."
        pm2 restart whatsapp-api
        echo "✓ Servicios reiniciados"
        ;;
    status)
        pm2 status
        ;;
    logs)
        pm2 logs whatsapp-api
        ;;
    qr)
        echo "Código QR disponible en:"
        echo "https://whatsapp.cellcomweb.com.ar/qr.php"
        ;;
    health)
        echo "Estado de servicios:"
        echo "Node.js API:"
        curl -s http://localhost:3000/api/health | jq .
        echo ""
        echo "Redis:"
        redis-cli ping
        echo "MySQL:"
        systemctl status mysql --no-pager | grep Active
        ;;
    backup)
        BACKUP_DIR="$PROJECT_DIR/backups"
        mkdir -p $BACKUP_DIR
        TIMESTAMP=$(date +%Y%m%d_%H%M%S)
        
        echo "Creando backup..."
        
        # Backup de base de datos
        mysqldump -u whatsapp_user -p whatsapp_db > "$BACKUP_DIR/db_backup_$TIMESTAMP.sql"
        
        # Backup de sesión WhatsApp
        tar -czf "$BACKUP_DIR/session_backup_$TIMESTAMP.tar.gz" whatsapp-session/
        
        echo "✓ Backup completado en $BACKUP_DIR"
        ;;
    *)
        echo "Uso: $0 {start|stop|restart|status|logs|qr|health|backup}"
        exit 1
        ;;
esac
