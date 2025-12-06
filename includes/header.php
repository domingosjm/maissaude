<?php
/**
 * Header comum para todos os módulos
 */

if (!isset($_SESSION['user_id'])) {
    header('Location: /MSLDA/index.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Usuário';
$user_id = $_SESSION['user_id'];

// Detectar módulo atual
$current_module = '';
$current_path = $_SERVER['REQUEST_URI'];
if (strpos($current_path, 'recepcao') !== false) $current_module = 'recepcao';
elseif (strpos($current_path, 'laboratorio') !== false) $current_module = 'laboratorio';
elseif (strpos($current_path, 'farmacia') !== false) $current_module = 'farmacia';
elseif (strpos($current_path, 'consultorio') !== false) $current_module = 'consultorio';
elseif (strpos($current_path, 'financeiro') !== false) $current_module = 'financeiro';
elseif (strpos($current_path, 'admin') !== false) $current_module = 'admin';

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'MSLDA' ?> - Integrada Mais Saúde</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
        }
        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        @media (prefers-color-scheme: dark) {
            .glass-effect {
                background: rgba(31, 41, 55, 0.9);
            }
        }
        /* Animações de notificações */
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .notification-badge {
            animation: pulse 2s infinite;
        }
    </style>
    <?= $custom_styles ?? '' ?>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="glass-effect shadow-lg border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-8">
                    <a href="/MSLDA/dashboard" class="flex items-center space-x-3 hover:opacity-80 transition">
                        <div class="bg-gradient-to-br from-emerald-500 to-green-600 p-2 rounded-xl shadow-lg">
                            <i class="bi bi-hospital text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Mais Saúde</h1>
                            <p class="text-xs text-emerald-600 font-medium"><?= $module_name ?? 'MSLDA' ?></p>
                        </div>
                    </a>
                    
          
                <div class="flex items-center space-x-4">
                    <!-- Notificações -->
                    <div class="relative">
                        <button id="notificationBtn" class="relative p-2 rounded-lg hover:bg-gray-100 transition">
                            <i class="bi bi-bell text-gray-700 text-xl"></i>
                            <span class="notification-badge absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full w-5 h-5 items-center justify-center hidden" id="notification-badge">0</span>
                        </button>
                        
                        <!-- Dropdown de Notificações -->
                        <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-xl border border-gray-200 z-50">
                            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                                <h3 class="font-semibold text-gray-800">Notificações</h3>
                                <div class="flex items-center space-x-2">
                                    <button id="toggleSound" class="p-1 hover:bg-gray-100 rounded" title="Ativar/Desativar sons">
                                        <i class="bi bi-volume-up text-gray-600"></i>
                                    </button>
                                    <button id="requestPermission" class="p-1 hover:bg-gray-100 rounded" title="Ativar notificações push">
                                        <i class="bi bi-bell-fill text-gray-600"></i>
                                    </button>
                                    <button id="markAllRead" class="text-sm text-blue-600 hover:text-blue-700">Marcar todas como lida</button>
                                </div>
                            </div>
                            <div id="notificationList" class="max-h-96 overflow-y-auto">
                                <div class="p-8 text-center text-gray-500">
                                    <i class="bi bi-inbox text-4xl mb-2"></i>
                                    <p>Nenhuma notificação</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Chat -->
                    <a href="/MSLDA/chat" class="relative p-2 rounded-lg hover:bg-gray-100 transition">
                        <i class="bi bi-chat-dots text-gray-700 text-xl"></i>
                        <span class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center" id="chatBadge" style="display: none;">0</span>
                    </a>
                    
                    <!-- User Menu -->
                    <div class="flex items-center space-x-3 px-4 py-2 rounded-xl bg-emerald-50">
                        <i class="bi bi-person-circle text-emerald-600 text-2xl"></i>
                        <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($username) ?></span>
                    </div>
                    
                    <a href="/MSLDA/logout" class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-lg shadow-md hover:shadow-lg transition">
                        <i class="bi bi-box-arrow-right mr-2"></i>Sair
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sistema de Notificações -->
    <script src="/MSLDA/assets/js/notifications.js"></script>
    
    <script>
        // Verificar mensagens não lidas do chat
        function checkUnreadMessages() {
            fetch('/MSLDA/api/chat/unread')
                .then(r => r.json())
                .then(data => {
                    const badge = document.getElementById('chatBadge');
                    if (data.unread > 0) {
                        badge.textContent = data.unread;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                });
        }
        
        // Verificar a cada 30 segundos
        setInterval(checkUnreadMessages, 30000);
        checkUnreadMessages();
        
        // Toggle dropdown de notificações
        document.getElementById('notificationBtn').addEventListener('click', function() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('hidden');
            loadNotifications();
        });
        
        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notificationDropdown');
            const btn = document.getElementById('notificationBtn');
            if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
        
        // Carregar notificações no dropdown
        async function loadNotifications() {
            try {
                const response = await fetch('/MSLDA/api/notifications.php?action=get_all&limit=20');
                const data = await response.json();
                
                const list = document.getElementById('notificationList');
                
                if (data.success && data.notifications.length > 0) {
                    list.innerHTML = data.notifications.map(notif => `
                        <div class="p-4 border-b border-gray-100 hover:bg-gray-50 cursor-pointer ${notif.is_read ? 'opacity-60' : ''}" 
                             onclick="handleNotificationClick(${notif.id}, '${notif.url || ''}')">
                            <div class="flex items-start space-x-3">
                                <i class="bi bi-${getNotificationIcon(notif.type)} text-${getNotificationColor(notif.type)}-500 text-xl"></i>
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-800">${notif.title}</h4>
                                    <p class="text-sm text-gray-600 mt-1">${notif.message}</p>
                                    <span class="text-xs text-gray-400 mt-2 block">${formatNotificationDate(notif.created_at)}</span>
                                </div>
                                ${!notif.is_read ? '<span class="w-2 h-2 bg-blue-500 rounded-full"></span>' : ''}
                            </div>
                        </div>
                    `).join('');
                } else {
                    list.innerHTML = `
                        <div class="p-8 text-center text-gray-500">
                            <i class="bi bi-inbox text-4xl mb-2"></i>
                            <p>Nenhuma notificação</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Erro ao carregar notificações:', error);
            }
        }
        
        function handleNotificationClick(id, url) {
            notificationSystem.markAsRead(id);
            if (url) {
                window.location.href = url;
            }
        }
        
        function getNotificationIcon(type) {
            const icons = {
                success: 'check-circle-fill',
                error: 'x-circle-fill',
                warning: 'exclamation-triangle-fill',
                info: 'info-circle-fill'
            };
            return icons[type] || icons.info;
        }
        
        function getNotificationColor(type) {
            const colors = {
                success: 'green',
                error: 'red',
                warning: 'yellow',
                info: 'blue'
            };
            return colors[type] || colors.info;
        }
        
        function formatNotificationDate(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) return 'Agora';
            if (diff < 3600000) return Math.floor(diff / 60000) + ' min atrás';
            if (diff < 86400000) return Math.floor(diff / 3600000) + 'h atrás';
            return date.toLocaleDateString('pt-BR');
        }
        
        // Marcar todas como lidas
        document.getElementById('markAllRead').addEventListener('click', function(e) {
            e.stopPropagation();
            notificationSystem.markAllAsRead();
            setTimeout(loadNotifications, 500);
        });
        
        // Toggle som
        document.getElementById('toggleSound').addEventListener('click', function(e) {
            e.stopPropagation();
            notificationSystem.toggleSound();
        });
        
        // Solicitar permissão
        document.getElementById('requestPermission').addEventListener('click', function(e) {
            e.stopPropagation();
            notificationSystem.requestPermission();
        });
    </script>
