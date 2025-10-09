// Dashboard JavaScript

// Función para refrescar datos
function refreshData() {
    location.reload();
}

// Función para abrir modal
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

// Función para cerrar modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

// Cerrar modal al hacer clic fuera
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});

// Función para enviar mensaje
async function sendMessage(to, message) {
    try {
        const response = await fetch('api/send.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ to, message })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Mensaje enviado correctamente', 'success');
            return true;
        } else {
            showNotification('Error al enviar mensaje: ' + data.error, 'error');
            return false;
        }
    } catch (error) {
        showNotification('Error de conexión', 'error');
        return false;
    }
}

// Función para mostrar notificaciones
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Función para formatear número de teléfono
function formatPhoneNumber(number) {
    return number.replace(/[^0-9]/g, '');
}

// Función para confirmar acción
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Auto-refresh de chats cada 30 segundos
if (window.location.search.includes('page=chats')) {
    setInterval(() => {
        fetch('api/chats.php?action=check_new')
            .then(res => res.json())
            .then(data => {
                if (data.hasNew) {
                    showNotification('Nuevos mensajes recibidos', 'info');
                }
            });
    }, 30000);
}

// Búsqueda en tiempo real
function setupSearch(inputId, listSelector) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        const items = document.querySelectorAll(listSelector);
        
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (text.includes(filter)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });
}

// Inicializar tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Setup de búsquedas
    setupSearch('searchContacts', '.contact-item');
    setupSearch('searchChats', '.message-item');
    
    // Agregar estilos de notificación
    if (!document.getElementById('notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: -300px;
                background: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 3000;
                transition: right 0.3s;
                max-width: 300px;
            }
            .notification.show {
                right: 20px;
            }
            .notification-success {
                border-left: 4px solid #28a745;
            }
            .notification-error {
                border-left: 4px solid #dc3545;
            }
            .notification-info {
                border-left: 4px solid #17a2b8;
            }
        `;
        document.head.appendChild(style);
    }
});

// Función para copiar al portapapeles
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Copiado al portapapeles', 'success');
    }).catch(() => {
        showNotification('Error al copiar', 'error');
    });
}

// Función para descargar como CSV
function downloadCSV(data, filename) {
    const csv = convertToCSV(data);
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function convertToCSV(data) {
    if (!data || data.length === 0) return '';
    
    const headers = Object.keys(data[0]);
    const rows = data.map(row => 
        headers.map(header => 
            JSON.stringify(row[header] ?? '')
        ).join(',')
    );
    
    return [headers.join(','), ...rows].join('\n');
}

// Agregar al final de dashboard.js o antes de cerrar </body>

// ============================================
// SISTEMA DE SOMBRAS DE SCROLL
// ============================================

function initScrollShadows() {
    const scrollContainers = document.querySelectorAll('.chats-list, .messages-container, .sidebar-nav');
    
    scrollContainers.forEach(container => {
        updateScrollShadows(container);
        
        container.addEventListener('scroll', () => {
            updateScrollShadows(container);
        });
        
        // Observar cambios en el contenido
        const observer = new ResizeObserver(() => {
            updateScrollShadows(container);
        });
        
        observer.observe(container);
    });
}

function updateScrollShadows(container) {
    const scrollTop = container.scrollTop;
    const scrollHeight = container.scrollHeight;
    const clientHeight = container.clientHeight;
    const scrollBottom = scrollHeight - scrollTop - clientHeight;
    
    // Agregar clase si tiene scroll
    if (scrollHeight > clientHeight) {
        container.classList.add('has-scroll');
    } else {
        container.classList.remove('has-scroll');
    }
    
    // Agregar clase si puede hacer scroll hacia arriba
    if (scrollTop > 10) {
        container.classList.add('has-scroll');
    } else {
        container.classList.remove('has-scroll');
    }
    
    // Agregar clase si puede hacer scroll hacia abajo
    if (scrollBottom > 10) {
        container.classList.add('can-scroll-down');
    } else {
        container.classList.remove('can-scroll-down');
    }
}

// ============================================
// SCROLLBAR VISIBLE AL TOCAR (MÓVIL)
// ============================================

if ('ontouchstart' in window) {
    let scrollTimeout;
    
    document.querySelectorAll('*').forEach(element => {
        element.addEventListener('scroll', function() {
            this.classList.add('scrolling');
            
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                this.classList.remove('scrolling');
            }, 1000);
        }, { passive: true });
    });
}

// ============================================
// SMOOTH SCROLL PARA ANCLAS
// ============================================

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href === '#' || href === '') return;
        
        e.preventDefault();
        const target = document.querySelector(href);
        
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// ============================================
// ANIMACIÓN AL HACER SCROLL
// ============================================

const observerOptions = {
    root: null,
    rootMargin: '0px',
    threshold: 0.1
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animate-on-scroll');
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

// Observar elementos que quieras animar al hacer scroll
document.querySelectorAll('.stat-card, .card, .message-item').forEach(element => {
    observer.observe(element);
});

// ============================================
// PREVENIR SCROLL HORIZONTAL ACCIDENTAL
// ============================================

document.addEventListener('wheel', function(e) {
    // Si es scroll horizontal pero el contenedor no tiene overflow-x
    if (e.deltaX !== 0) {
        const target = e.target.closest('.chats-list, .messages-container, .sidebar-nav');
        if (target) {
            const hasHorizontalScroll = target.scrollWidth > target.clientWidth;
            if (!hasHorizontalScroll) {
                e.preventDefault();
            }
        }
    }
}, { passive: false });

// ============================================
// INICIALIZAR AL CARGAR LA PÁGINA
// ============================================

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initScrollShadows);
} else {
    initScrollShadows();
}

// Reinicializar después de cambios dinámicos
window.addEventListener('load', initScrollShadows);

// ============================================
// FUNCIÓN AUXILIAR PARA AUTO-SCROLL
// ============================================

function smoothScrollToBottom(container, duration = 300) {
    if (!container) return;
    
    const start = container.scrollTop;
    const target = container.scrollHeight - container.clientHeight;
    const distance = target - start;
    const startTime = performance.now();
    
    function animation(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing function (ease-out)
        const easeOut = 1 - Math.pow(1 - progress, 3);
        
        container.scrollTop = start + (distance * easeOut);
        
        if (progress < 1) {
            requestAnimationFrame(animation);
        }
    }
    
    requestAnimationFrame(animation);
}

// Exportar para uso global
window.smoothScrollToBottom = smoothScrollToBottom;
window.updateScrollShadows = updateScrollShadows;

console.log('✨ Sistema de scroll profesional inicializado');