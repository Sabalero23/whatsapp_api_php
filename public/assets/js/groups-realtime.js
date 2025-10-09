// Sistema de actualización en tiempo real para grupos
// Agregar este código al final de groups.php o en un archivo separado

const GroupsRealtimeSystem = {
    config: {
        UPDATE_INTERVAL: 5000, // 5 segundos
        MESSAGE_UPDATE_INTERVAL: 3000, // 3 segundos
        MAX_RETRIES: 3
    },
    
    state: {
        isUpdating: false,
        updateInterval: null,
        messageUpdateInterval: null,
        lastMessageTimestamp: 0,
        consecutiveErrors: 0,
        messageIds: new Set()
    },
    
    init(groupId) {
        console.log('🔄 Inicializando sistema de grupos en tiempo real');
        
        // Cargar mensajes iniciales
        this.loadInitialMessages();
        
        // Iniciar actualizaciones
        if (groupId) {
            this.startMessageUpdates(groupId);
        }
        
        this.startGroupsListUpdates();
        
        // Limpiar al salir
        window.addEventListener('beforeunload', () => this.cleanup());
    },
    
    loadInitialMessages() {
        const messages = document.querySelectorAll('.message-bubble[data-message-id]');
        messages.forEach(msg => {
            this.state.messageIds.add(msg.dataset.messageId);
        });
        
        // Calcular timestamp del último mensaje
        if (messages.length > 0) {
            const today = new Date();
            this.state.lastMessageTimestamp = Math.floor(today.getTime() / 1000);
        }
        
        console.log(`📨 ${messages.length} mensajes iniciales cargados`);
    },
    
    startMessageUpdates(groupId) {
        console.log('🔄 Iniciando actualización de mensajes del grupo');
        
        this.state.messageUpdateInterval = setInterval(() => {
            this.updateGroupMessages(groupId);
        }, this.config.MESSAGE_UPDATE_INTERVAL);
        
        // Primera actualización inmediata
        setTimeout(() => this.updateGroupMessages(groupId), 1000);
    },
    
    startGroupsListUpdates() {
        console.log('🔄 Iniciando actualización de lista de grupos');
        
        this.state.updateInterval = setInterval(() => {
            this.updateGroupsList();
        }, this.config.UPDATE_INTERVAL);
    },
    
    async updateGroupMessages(groupId) {
        if (this.state.isUpdating) return;
        
        this.state.isUpdating = true;
        
        try {
            const url = `api/get-chat-messages.php?chatId=${encodeURIComponent(groupId)}&after=${this.state.lastMessageTimestamp}&t=${Date.now()}`;
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.messages && data.messages.length > 0) {
                console.log(`📨 ${data.messages.length} mensajes nuevos en el grupo`);
                
                this.appendNewGroupMessages(data.messages, groupId);
                
                if (data.lastTimestamp) {
                    this.state.lastMessageTimestamp = data.lastTimestamp;
                }
                
                this.state.consecutiveErrors = 0;
            }
        } catch (error) {
            console.error('❌ Error actualizando mensajes:', error);
            this.state.consecutiveErrors++;
            
            if (this.state.consecutiveErrors >= this.config.MAX_RETRIES) {
                console.warn('⚠️ Demasiados errores, pausando actualizaciones');
                this.cleanup();
            }
        } finally {
            this.state.isUpdating = false;
        }
    },
    
    appendNewGroupMessages(messages, groupId) {
        const container = document.getElementById('messagesContainer') || 
                         document.getElementById('groupMessagesContainer');
        
        if (!container) return;
        
        const wasAtBottom = this.shouldAutoScroll(container);
        let newMessagesCount = 0;
        
        messages.forEach(msg => {
            if (this.state.messageIds.has(msg.id)) return;
            
            this.state.messageIds.add(msg.id);
            const bubble = this.createGroupMessageBubble(msg);
            
            bubble.style.opacity = '0';
            bubble.style.transform = 'translateY(20px)';
            container.appendChild(bubble);
            
            requestAnimationFrame(() => {
                bubble.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                bubble.style.opacity = '1';
                bubble.style.transform = 'translateY(0)';
            });
            
            newMessagesCount++;
        });
        
        if (newMessagesCount > 0) {
            console.log(`✅ ${newMessagesCount} mensajes agregados al DOM`);
            
            // Sonido de notificación
            this.playNotificationSound();
            
            // Auto-scroll si estaba al final
            if (wasAtBottom) {
                setTimeout(() => this.scrollToBottom(container), 100);
            }
            
            // Notificación visual
            if (!document.hasFocus()) {
                this.showDesktopNotification(newMessagesCount);
            }
        }
    },
    
    createGroupMessageBubble(msg) {
        const bubble = document.createElement('div');
        bubble.className = `message-bubble ${msg.fromMe ? 'sent' : 'received'}`;
        bubble.dataset.messageId = msg.id;
        
        const time = new Date(msg.timestamp * 1000).toLocaleTimeString('es-AR', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        // Extraer nombre del remitente (para grupos)
        const senderName = msg.fromMe ? 'Tú' : (msg.from ? msg.from.split('@')[0] : 'Participante');
        
        bubble.innerHTML = `
            <div class="bubble-content">
                ${!msg.fromMe ? `<div class="message-sender" style="font-weight: 600; font-size: 0.85em; color: #25D366; margin-bottom: 3px;">${this.escapeHtml(senderName)}</div>` : ''}
                ${msg.body ? `<div class="message-text">${this.escapeHtml(msg.body).replace(/\n/g, '<br>')}</div>` : ''}
                <div class="message-time">
                    ${time}
                    ${msg.fromMe ? '<span><i class="fas fa-check-double"></i></span>' : ''}
                </div>
            </div>
        `;
        
        return bubble;
    },
    
    async updateGroupsList() {
        try {
            const response = await fetch('api/get-groups-status.php?t=' + Date.now());
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.groups) {
                this.updateGroupsListUI(data.groups);
            }
        } catch (error) {
            console.error('Error actualizando lista de grupos:', error);
        }
    },
    
    updateGroupsListUI(groups) {
        groups.forEach(group => {
            const groupItem = document.querySelector(`[data-group-id="${group.id}"]`);
            
            if (!groupItem) return;
            
            // Actualizar contador de no leídos
            const currentUnread = parseInt(groupItem.dataset.unread || '0');
            const newUnread = group.unreadCount || 0;
            
            if (newUnread !== currentUnread) {
                groupItem.dataset.unread = newUnread;
                
                const badge = groupItem.querySelector('.conversation-unread');
                
                if (newUnread > 0) {
                    if (badge) {
                        badge.textContent = newUnread;
                    } else {
                        const header = groupItem.querySelector('.conversation-header');
                        const newBadge = document.createElement('span');
                        newBadge.className = 'conversation-unread';
                        newBadge.textContent = newUnread;
                        header.appendChild(newBadge);
                    }
                    
                    // Animación de nuevo mensaje
                    if (newUnread > currentUnread) {
                        groupItem.classList.add('new-message');
                        setTimeout(() => groupItem.classList.remove('new-message'), 800);
                    }
                } else if (badge) {
                    badge.remove();
                }
            }
        });
    },
    
    shouldAutoScroll(container) {
        const distanceFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
        return distanceFromBottom < 100;
    },
    
    scrollToBottom(container, smooth = true) {
        container.scrollTo({
            top: container.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto'
        });
    },
    
    playNotificationSound() {
        try {
            const audio = new Audio('assets/sounds/notification.mp3');
            audio.volume = 0.3;
            audio.play().catch(() => {});
        } catch (e) {}
    },
    
    showDesktopNotification(count) {
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification('Nuevo mensaje en grupo', {
                body: `${count} mensaje${count > 1 ? 's' : ''} nuevo${count > 1 ? 's' : ''}`,
                icon: 'assets/img/whatsapp-icon.png',
                tag: 'group-message'
            });
            
            notification.onclick = () => {
                window.focus();
                notification.close();
            };
            
            setTimeout(() => notification.close(), 5000);
        }
    },
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    cleanup() {
        console.log('🛑 Limpiando sistema de grupos');
        
        if (this.state.updateInterval) {
            clearInterval(this.state.updateInterval);
        }
        
        if (this.state.messageUpdateInterval) {
            clearInterval(this.state.messageUpdateInterval);
        }
    }
};

// Auto-inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    const selectedGroupId = document.querySelector('[data-selected-group]')?.dataset.selectedGroup;
    
    if (selectedGroupId || document.querySelector('.conversations-list')) {
        GroupsRealtimeSystem.init(selectedGroupId);
    }
});

// Solicitar permisos de notificación
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}