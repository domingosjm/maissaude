<?php
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /MSLDA/index.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['user_name'] ?? 'Usuário';

// Criar tabelas se não existirem
$mysqli->query("
    CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sender (sender_id),
        INDEX idx_receiver (receiver_id),
        INDEX idx_created (created_at),
        INDEX idx_conversation (sender_id, receiver_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$mysqli->query("
    CREATE TABLE IF NOT EXISTS user_online_status (
        user_id INT PRIMARY KEY,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_online BOOLEAN DEFAULT TRUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Atualizar status online do usuário atual
$mysqli->query("INSERT INTO user_online_status (user_id, last_activity, is_online) VALUES ($current_user_id, NOW(), TRUE) ON DUPLICATE KEY UPDATE last_activity = NOW(), is_online = TRUE");

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'enviar_mensagem') {
        $receiver_id = (int)$_POST['receiver_id'];
        $message = trim($_POST['message']);
        
        if ($receiver_id > 0 && !empty($message)) {
            $stmt = $mysqli->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            $stmt->bind_param('iis', $current_user_id, $receiver_id, $message);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    if ($acao === 'marcar_lidas') {
        $sender_id = (int)$_POST['sender_id'];
        $mysqli->query("UPDATE chat_messages SET is_read = TRUE WHERE sender_id = $sender_id AND receiver_id = $current_user_id AND is_read = FALSE");
        echo json_encode(['success' => true]);
        exit;
    }
}

// Buscar usuários para conversa
$usuarios_query = $mysqli->query("
    SELECT u.id, u.name, COALESCE(ur.name, 'Usuário') as role, uos.is_online, uos.last_activity,
    (SELECT COUNT(*) FROM chat_messages WHERE sender_id = u.id AND receiver_id = $current_user_id AND is_read = FALSE) as unread_count,
    (SELECT message FROM chat_messages WHERE (sender_id = u.id AND receiver_id = $current_user_id) OR (sender_id = $current_user_id AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_message,
    (SELECT created_at FROM chat_messages WHERE (sender_id = u.id AND receiver_id = $current_user_id) OR (sender_id = $current_user_id AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_message_time
    FROM users u
    LEFT JOIN user_online_status uos ON u.id = uos.user_id
    LEFT JOIN user_roles ur ON u.role_id = ur.id
    WHERE u.id != $current_user_id
    ORDER BY unread_count DESC, last_message_time DESC, u.name ASC
");

$usuarios = [];
while ($row = $usuarios_query->fetch_assoc()) {
    // Considerar online se atividade nos últimos 5 minutos
    $row['is_online'] = $row['last_activity'] && (strtotime($row['last_activity']) > strtotime('-5 minutes'));
    $usuarios[] = $row;
}

// Total de mensagens não lidas
$total_unread = $mysqli->query("SELECT COUNT(*) as total FROM chat_messages WHERE receiver_id = $current_user_id AND is_read = FALSE")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Interno - Sistema Integrado Mais Saúde</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .chat-scroll {
            max-height: calc(100vh - 250px);
            overflow-y: auto;
            scroll-behavior: smooth;
        }
        .chat-scroll::-webkit-scrollbar {
            width: 8px;
        }
        .chat-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .chat-scroll::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #10b981, #059669);
            border-radius: 10px;
            border: 2px solid #f1f5f9;
        }
        .chat-scroll::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #059669, #047857);
        }
        .message-bubble {
            animation: fadeInUp 0.3s ease;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .pulse-dot {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .typing-indicator span {
            animation: typingDot 1.4s infinite;
        }
        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }
        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }
        @keyframes typingDot {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-10px); }
        }
        .emoji-picker {
            max-height: 200px;
            overflow-y: auto;
        }
        .active-chat {
            background: linear-gradient(90deg, #ecfdf5 0%, #d1fae5 100%);
            border-left: 4px solid #10b981;
        }
        .notification-badge {
            animation: bounce 0.5s ease;
        }
        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        .message-sent {
            animation: messageSent 0.3s ease;
        }
        @keyframes messageSent {
            0% { transform: scale(0.9); opacity: 0; }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar: Lista de Conversas -->
        <div class="w-80 bg-white border-r border-gray-200 flex flex-col">
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
                <div class="flex items-center justify-between mb-3">
                    <h1 class="text-xl font-bold text-white flex items-center">
                        <i class="bi bi-chat-dots mr-2"></i>
                        Chat Interno
                    </h1>
                    <a href="dashboard.php" class="text-white hover:bg-white/20 rounded-lg p-2 transition-all">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
                <div class="flex items-center text-white text-sm">
                    <div class="w-2 h-2 bg-green-300 rounded-full mr-2 pulse-dot"></div>
                    <?= htmlspecialchars($current_user_name) ?>
                </div>
            </div>

            <!-- Busca -->
            <div class="p-4 border-b border-gray-200">
                <div class="relative">
                    <i class="bi bi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="searchUsers" placeholder="Buscar contatos..." 
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
            </div>

            <!-- Lista de Usuários -->
            <div class="flex-1 overflow-y-auto">
                <?php foreach ($usuarios as $usuario): ?>
                    <div class="user-item px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 transition-all"
                         onclick="abrirConversa(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['name']) ?>', <?= $usuario['is_online'] ? 'true' : 'false' ?>)"
                         data-user-id="<?= $usuario['id'] ?>"
                         data-user-name="<?= htmlspecialchars($usuario['name']) ?>">
                        <div class="flex items-start">
                            <div class="relative flex-shrink-0">
                                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-green-400 to-emerald-600 flex items-center justify-center text-white font-bold text-lg">
                                    <?= strtoupper(substr($usuario['name'], 0, 1)) ?>
                                </div>
                                <?php if ($usuario['is_online']): ?>
                                    <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 rounded-full border-2 border-white"></div>
                                <?php endif; ?>
                            </div>
                            <div class="ml-3 flex-1 min-w-0">
                                <div class="flex justify-between items-baseline">
                                    <h3 class="font-semibold text-gray-800 truncate"><?= htmlspecialchars($usuario['name']) ?></h3>
                                    <?php if ($usuario['last_message_time']): ?>
                                        <span class="text-xs text-gray-500 ml-2">
                                            <?php
                                            $diff = time() - strtotime($usuario['last_message_time']);
                                            if ($diff < 60) echo 'agora';
                                            elseif ($diff < 3600) echo floor($diff / 60) . 'min';
                                            elseif ($diff < 86400) echo floor($diff / 3600) . 'h';
                                            else echo date('d/m', strtotime($usuario['last_message_time']));
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars($usuario['role']) ?></p>
                                <?php if ($usuario['last_message']): ?>
                                    <p class="text-sm text-gray-600 truncate mt-1"><?= htmlspecialchars(substr($usuario['last_message'], 0, 40)) ?>...</p>
                                <?php endif; ?>
                            </div>
                            <?php if ($usuario['unread_count'] > 0): ?>
                                <div class="ml-2 bg-green-600 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center">
                                    <?= $usuario['unread_count'] ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Área de Conversa -->
        <div class="flex-1 flex flex-col bg-gray-100">
            <div id="chat-empty" class="flex-1 flex items-center justify-center text-gray-400">
                <div class="text-center">
                    <i class="bi bi-chat-text text-6xl mb-4"></i>
                    <p class="text-lg font-semibold">Selecione uma conversa para começar</p>
                </div>
            </div>

            <div id="chat-area" class="hidden flex-1 flex flex-col">
                <!-- Header da Conversa -->
                <div class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="relative">
                            <div id="chat-user-avatar" class="w-10 h-10 rounded-full bg-gradient-to-br from-green-400 to-emerald-600 flex items-center justify-center text-white font-bold"></div>
                            <div id="chat-user-status" class="hidden absolute bottom-0 right-0 w-3 h-3 bg-green-400 rounded-full border-2 border-white"></div>
                        </div>
                        <div class="ml-3">
                            <h2 id="chat-user-name" class="font-bold text-gray-800"></h2>
                            <p id="chat-user-online" class="text-xs text-gray-500"></p>
                        </div>
                    </div>
                </div>

                <!-- Mensagens -->
                <div id="chat-messages" class="flex-1 p-6 overflow-y-auto chat-scroll">
                    <!-- Mensagens serão carregadas aqui -->
                </div>

                <!-- Indicador de digitação -->
                <div id="typing-indicator" class="hidden px-6 py-2 text-sm text-gray-500">
                    <div class="flex items-center gap-2">
                        <div class="typing-indicator flex gap-1">
                            <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                            <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                            <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                        </div>
                        <span id="typing-user"></span> está digitando...
                    </div>
                </div>

                <!-- Input de Mensagem -->
                <div class="bg-white border-t border-gray-200 p-4">
                    <!-- Ferramentas -->
                    <div class="flex items-center gap-2 mb-3">
                        <button type="button" onclick="toggleEmojiPicker()" class="p-2 hover:bg-gray-100 rounded-lg transition text-gray-600 hover:text-gray-800" title="Adicionar emoji">
                            <i class="bi bi-emoji-smile text-xl"></i>
                        </button>
                        <button type="button" onclick="attachFile()" class="p-2 hover:bg-gray-100 rounded-lg transition text-gray-600 hover:text-gray-800" title="Anexar arquivo">
                            <i class="bi bi-paperclip text-xl"></i>
                        </button>
                        <button type="button" onclick="sendVoiceNote()" class="p-2 hover:bg-gray-100 rounded-lg transition text-gray-600 hover:text-gray-800" title="Gravar áudio">
                            <i class="bi bi-mic text-xl"></i>
                        </button>
                        <div class="flex-1"></div>
                        <div id="character-count" class="text-xs text-gray-400">0/1000</div>
                    </div>

                    <!-- Emoji Picker -->
                    <div id="emoji-picker" class="hidden mb-3 bg-gray-50 rounded-lg p-3 emoji-picker border border-gray-200">
                        <div class="grid grid-cols-8 gap-2">
                            <?php 
                            $emojis = ['😀','😃','😄','😁','😅','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🥸','🤩','🥳','😏','😒','😞','😔','😟','😕','🙁','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥','😓','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','👍','👎','👌','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','👇','☝️','👏','🙌','👐','🤲','🤝','🙏','❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝'];
                            foreach ($emojis as $emoji): ?>
                                <button type="button" onclick="insertEmoji('<?= $emoji ?>')" class="text-2xl hover:bg-white rounded p-1 transition"><?= $emoji ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Form -->
                    <form id="form-mensagem" class="flex gap-3">
                        <input type="hidden" id="receiver-id" value="">
                        <div class="flex-1 relative">
                            <textarea id="message-input" placeholder="Digite sua mensagem... (Shift+Enter para nova linha)" 
                                   rows="1"
                                   maxlength="1000"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent resize-none"
                                   oninput="autoResize(this); updateCharCount(); handleTyping()"
                                   onkeydown="handleKeyPress(event)"
                                   required></textarea>
                        </div>
                        <button type="submit" id="send-button" class="bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-6 py-3 rounded-lg font-semibold transition-all shadow-md hover:shadow-lg self-end">
                            <i class="bi bi-send"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentReceiverId = null;
        let messageInterval = null;
        let typingTimeout = null;
        let lastMessageCount = 0;
        let notificationSound = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTGH0fPTgjMGHm7A7+OZUA0PVqzn77BdGAc+ltryxnMpBSuByPDajzsIGGS57OihUBELTKXh8bllHAU2jdXzx3UsBS0'; 
        
        // Tocar som de notificação
        function playNotificationSound() {
            if (document.hidden) {
                notificationSound.play().catch(e => console.log('Notification sound failed:', e));
            }
        }

        // Buscar usuários
        document.getElementById('searchUsers').addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            document.querySelectorAll('.user-item').forEach(item => {
                const name = item.dataset.userName.toLowerCase();
                item.style.display = name.includes(search) ? 'block' : 'none';
            });
        });

        // Marcar conversa ativa
        function markActiveChat(userId) {
            document.querySelectorAll('.user-item').forEach(item => {
                item.classList.remove('active-chat');
            });
            document.querySelector(`.user-item[data-user-id="${userId}"]`)?.classList.add('active-chat');
        }

        // Abrir conversa
        function abrirConversa(userId, userName, isOnline) {
            currentReceiverId = userId;
            markActiveChat(userId);
            
            document.getElementById('chat-empty').classList.add('hidden');
            document.getElementById('chat-area').classList.remove('hidden');
            
            document.getElementById('receiver-id').value = userId;
            document.getElementById('chat-user-name').textContent = userName;
            document.getElementById('chat-user-avatar').textContent = userName.charAt(0).toUpperCase();
            document.getElementById('chat-user-online').textContent = isOnline ? 'Online' : 'Offline';
            
            if (isOnline) {
                document.getElementById('chat-user-status').classList.remove('hidden');
            } else {
                document.getElementById('chat-user-status').classList.add('hidden');
            }
            
            carregarMensagens(userId);
            
            // Marcar mensagens como lidas
            fetch('chat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'acao=marcar_lidas&sender_id=' + userId
            });
            
            // Atualizar contador de não lidas
            document.querySelector(`.user-item[data-user-id="${userId}"] .bg-green-600`)?.remove();
            
            // Auto-refresh das mensagens
            if (messageInterval) clearInterval(messageInterval);
            messageInterval = setInterval(() => carregarMensagens(userId), 3000);
        }

        // Carregar mensagens
        function carregarMensagens(userId, forceScroll = false) {
            fetch(`../../api/chat_messages.php?user_id=${userId}`)
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('chat-messages');
                    const isAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 50;
                    
                    // Detectar novas mensagens
                    const hasNewMessages = data.messages.length > lastMessageCount;
                    lastMessageCount = data.messages.length;
                    
                    container.innerHTML = '';
                    
                    if (data.messages.length === 0) {
                        container.innerHTML = `
                            <div class="flex items-center justify-center h-full text-gray-400">
                                <div class="text-center">
                                    <i class="bi bi-chat-quote text-4xl mb-2"></i>
                                    <p>Nenhuma mensagem ainda</p>
                                    <p class="text-sm">Envie a primeira mensagem!</p>
                                </div>
                            </div>
                        `;
                        return;
                    }
                    
                    let lastDate = '';
                    data.messages.forEach((msg, index) => {
                        const msgDate = new Date(msg.created_at).toLocaleDateString('pt-BR');
                        
                        // Separador de data
                        if (msgDate !== lastDate) {
                            const dateDiv = document.createElement('div');
                            dateDiv.className = 'flex justify-center my-4';
                            dateDiv.innerHTML = `
                                <span class="bg-gray-200 text-gray-600 text-xs px-3 py-1 rounded-full">
                                    ${formatDate(msg.created_at)}
                                </span>
                            `;
                            container.appendChild(dateDiv);
                            lastDate = msgDate;
                        }
                        
                        const isMe = msg.sender_id == <?= $current_user_id ?>;
                        const div = document.createElement('div');
                        div.className = `message-bubble flex ${isMe ? 'justify-end' : 'justify-start'} mb-3`;
                        
                        // Adicionar animação para última mensagem
                        if (index === data.messages.length - 1 && hasNewMessages) {
                            div.classList.add('message-sent');
                            if (!isMe) playNotificationSound();
                        }
                        
                        div.innerHTML = `
                            <div class="max-w-xs lg:max-w-md">
                                <div class="px-4 py-3 rounded-2xl shadow-sm ${isMe ? 'bg-gradient-to-br from-green-600 to-emerald-600 text-white rounded-br-none' : 'bg-white text-gray-800 border border-gray-200 rounded-bl-none'}">
                                    <p class="break-words whitespace-pre-wrap leading-relaxed">${escapeHtml(msg.message)}</p>
                                </div>
                                <div class="flex items-center gap-1 mt-1 px-2 ${isMe ? 'justify-end' : 'justify-start'}">
                                    <span class="text-xs text-gray-500">${formatTime(msg.created_at)}</span>
                                    ${isMe ? `<i class="bi ${msg.is_read ? 'bi-check-all text-blue-500' : 'bi-check text-gray-400'} text-sm"></i>` : ''}
                                </div>
                            </div>
                        `;
                        
                        container.appendChild(div);
                    });
                    
                    // Scroll automático
                    if (isAtBottom || forceScroll || hasNewMessages) {
                        setTimeout(() => {
                            container.scrollTop = container.scrollHeight;
                        }, 100);
                    }
                })
                .catch(err => console.error('Erro ao carregar mensagens:', err));
        }

        // Enviar mensagem
        document.getElementById('form-mensagem').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const input = document.getElementById('message-input');
            const message = input.value.trim();
            const receiverId = document.getElementById('receiver-id').value;
            
            if (!message || !receiverId) return;
            
            const sendButton = document.getElementById('send-button');
            sendButton.disabled = true;
            sendButton.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i>';
            
            fetch('chat.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `acao=enviar_mensagem&receiver_id=${receiverId}&message=${encodeURIComponent(message)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    input.style.height = 'auto';
                    updateCharCount();
                    carregarMensagens(receiverId, true);
                }
            })
            .finally(() => {
                sendButton.disabled = false;
                sendButton.innerHTML = '<i class="bi bi-send"></i>';
                input.focus();
            });
        });

        // Funções adicionais
        function autoResize(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 150) + 'px';
        }

        function updateCharCount() {
            const input = document.getElementById('message-input');
            const count = document.getElementById('character-count');
            const length = input.value.length;
            count.textContent = `${length}/1000`;
            count.className = length > 900 ? 'text-xs text-red-500 font-semibold' : 'text-xs text-gray-400';
        }

        function handleKeyPress(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                document.getElementById('form-mensagem').dispatchEvent(new Event('submit'));
            }
        }

        function handleTyping() {
            if (typingTimeout) clearTimeout(typingTimeout);
            
            typingTimeout = setTimeout(() => {
                // Aqui você pode enviar notificação de "parou de digitar"
            }, 2000);
        }

        function toggleEmojiPicker() {
            const picker = document.getElementById('emoji-picker');
            picker.classList.toggle('hidden');
        }

        function insertEmoji(emoji) {
            const input = document.getElementById('message-input');
            const cursorPos = input.selectionStart;
            const text = input.value;
            input.value = text.substring(0, cursorPos) + emoji + text.substring(cursorPos);
            input.focus();
            updateCharCount();
            autoResize(input);
        }

        function attachFile() {
            alert('Funcionalidade de anexar arquivo em desenvolvimento');
        }

        function sendVoiceNote() {
            alert('Funcionalidade de nota de voz em desenvolvimento');
        }

        function formatDate(datetime) {
            const date = new Date(datetime);
            const today = new Date();
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            
            if (date.toDateString() === today.toDateString()) return 'Hoje';
            if (date.toDateString() === yesterday.toDateString()) return 'Ontem';
            return date.toLocaleDateString('pt-BR', {day: '2-digit', month: 'short'});
        }

        // Helpers
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatTime(datetime) {
            const date = new Date(datetime);
            const now = new Date();
            const diff = (now - date) / 1000;
            
            if (diff < 60) return 'agora';
            if (diff < 3600) return Math.floor(diff / 60) + ' min atrás';
            if (diff < 86400) return date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
            return date.toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit'}) + ' ' + date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
        }

        // Atualizar status online a cada 2 minutos
        setInterval(() => {
            fetch('chat.php', {method: 'POST', body: 'acao=heartbeat'});
        }, 120000);
    </script>
</body>
</html>
