/**
 * Sistema de Notificações Push
 * Integra com Web Push API e alertas visuais
 */

class NotificationSystem {
    constructor() {
        this.initialized = false;
        this.permission = 'default';
        this.checkInterval = 30000; // 30 segundos
        this.soundEnabled = true;
        this.init();
    }

    async init() {
        // Verificar suporte a notificações
        if (!('Notification' in window)) {
            console.warn('Este navegador não suporta notificações desktop');
            return;
        }

        this.permission = Notification.permission;
        
        // Iniciar polling de novas notificações
        this.startPolling();
        
        // Carregar notificações não lidas
        this.loadUnreadNotifications();
        
        this.initialized = true;
    }

    async requestPermission() {
        if (this.permission === 'granted') {
            return true;
        }

        try {
            const permission = await Notification.requestPermission();
            this.permission = permission;
            
            if (permission === 'granted') {
                this.showToast('Notificações ativadas com sucesso!', 'success');
                return true;
            } else {
                this.showToast('Você negou as notificações', 'warning');
                return false;
            }
        } catch (error) {
            console.error('Erro ao solicitar permissão:', error);
            return false;
        }
    }

    async showNotification(title, options = {}) {
        // Mostrar toast sempre
        this.showToast(title, options.type || 'info');

        // Tentar mostrar notificação push
        if (this.permission !== 'granted') {
            return;
        }

        const notification = new Notification(title, {
            body: options.body || '',
            icon: options.icon || '/MSLDA/assets/images/logo.png',
            badge: options.badge || '/MSLDA/assets/images/badge.png',
            tag: options.tag || 'mslda-notification',
            requireInteraction: options.requireInteraction || false,
            silent: !this.soundEnabled,
            data: options.data || {}
        });

        notification.onclick = () => {
            if (options.url) {
                window.focus();
                window.location.href = options.url;
            }
            notification.close();
        };

        // Auto-fechar após 5 segundos se não for crítica
        if (!options.requireInteraction) {
            setTimeout(() => notification.close(), 5000);
        }
    }

    showToast(message, type = 'info', duration = 5000) {
        // Criar container se não existir
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'fixed top-4 right-4 z-50 space-y-2';
            document.body.appendChild(container);
        }

        // Definir cores e ícones por tipo
        const types = {
            success: { bg: 'bg-green-500', icon: 'bi-check-circle-fill' },
            error: { bg: 'bg-red-500', icon: 'bi-x-circle-fill' },
            warning: { bg: 'bg-yellow-500', icon: 'bi-exclamation-triangle-fill' },
            info: { bg: 'bg-blue-500', icon: 'bi-info-circle-fill' }
        };

        const config = types[type] || types.info;

        // Criar toast
        const toast = document.createElement('div');
        toast.className = `${config.bg} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 transform transition-all duration-300 translate-x-full max-w-md`;
        toast.innerHTML = `
            <i class="bi ${config.icon} text-2xl"></i>
            <span class="flex-1">${message}</span>
            <button class="text-white hover:text-gray-200" onclick="this.parentElement.remove()">
                <i class="bi bi-x-lg"></i>
            </button>
        `;

        container.appendChild(toast);

        // Animar entrada
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
        }, 10);

        // Reproduzir som se habilitado
        if (this.soundEnabled) {
            this.playNotificationSound(type);
        }

        // Auto-remover
        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    playNotificationSound(type) {
        // Sons diferentes para cada tipo
        const frequencies = {
            success: 800,
            error: 400,
            warning: 600,
            info: 700
        };

        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.value = frequencies[type] || frequencies.info;
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
        } catch (error) {
            console.warn('Não foi possível reproduzir som:', error);
        }
    }

    async startPolling() {
        // Polling inicial
        await this.checkNewNotifications();

        // Continuar polling
        setInterval(() => {
            this.checkNewNotifications();
        }, this.checkInterval);
    }

    async checkNewNotifications() {
        try {
            const response = await fetch('/MSLDA/api/notifications.php?action=check_new');
            const data = await response.json();

            if (data.success && data.notifications && data.notifications.length > 0) {
                data.notifications.forEach(notif => {
                    this.showNotification(notif.title, {
                        body: notif.message,
                        type: notif.type,
                        url: notif.url,
                        requireInteraction: notif.priority === 'high',
                        data: { id: notif.id }
                    });
                });

                // Atualizar badge de notificações não lidas
                this.updateBadge(data.unread_count);
            }
        } catch (error) {
            console.error('Erro ao verificar notificações:', error);
        }
    }

    async loadUnreadNotifications() {
        try {
            const response = await fetch('/MSLDA/api/notifications.php?action=unread_count');
            const data = await response.json();

            if (data.success) {
                this.updateBadge(data.count);
            }
        } catch (error) {
            console.error('Erro ao carregar notificações:', error);
        }
    }

    updateBadge(count) {
        const badge = document.getElementById('notification-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    }

    async markAsRead(notificationId) {
        try {
            const response = await fetch('/MSLDA/api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_id: notificationId
                })
            });

            const data = await response.json();
            if (data.success) {
                this.loadUnreadNotifications();
            }
        } catch (error) {
            console.error('Erro ao marcar notificação como lida:', error);
        }
    }

    async markAllAsRead() {
        try {
            const response = await fetch('/MSLDA/api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_all_read'
                })
            });

            const data = await response.json();
            if (data.success) {
                this.showToast('Todas as notificações foram marcadas como lidas', 'success');
                this.loadUnreadNotifications();
            }
        } catch (error) {
            console.error('Erro ao marcar todas como lidas:', error);
        }
    }

    toggleSound() {
        this.soundEnabled = !this.soundEnabled;
        localStorage.setItem('notificationSound', this.soundEnabled);
        this.showToast(
            this.soundEnabled ? 'Sons ativados' : 'Sons desativados',
            'info',
            2000
        );
    }
}

// Inicializar sistema global
const notificationSystem = new NotificationSystem();

// Expor funções globais
window.showNotification = (title, options) => notificationSystem.showNotification(title, options);
window.showToast = (message, type, duration) => notificationSystem.showToast(message, type, duration);
window.requestNotificationPermission = () => notificationSystem.requestPermission();
