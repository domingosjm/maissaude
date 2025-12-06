<?php
// Configuração de conexão com o banco de dados MySQL
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'mais_saude');
}

$DB_HOST = DB_HOST;
$DB_USER = DB_USER;
$DB_PASS = DB_PASS;
$DB_NAME = DB_NAME;

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    die('Erro ao conectar ao banco de dados: ' . $mysqli->connect_error);
}

// Configurar charset UTF-8
$mysqli->set_charset("utf8mb4");

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    // Configurar tempo de vida da sessão dinamicamente
    $session_lifetime = 1800; // Padrão: 30 minutos
    ini_set('session.gc_maxlifetime', $session_lifetime);
    session_set_cookie_params($session_lifetime);
    
    session_start();
}

// Criar tabela de configurações se não existir
$mysqli->query("CREATE TABLE IF NOT EXISTS system_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Verificar modo de manutenção (exceto para admins)
if (isset($_SESSION['user_id'])) {
    // Verificar timeout de sessão
    if (!isset($_SESSION['is_login_page'])) {
        require_once __DIR__ . '/functions.php';
        
        // Verificar timeout
        check_session_timeout();
        
        // Verificar modo manutenção
        $maintenance = $mysqli->query("SELECT config_value FROM system_configs WHERE config_key = 'maintenance_mode'");
        if ($maintenance && $maintenance->num_rows > 0) {
            $maint_row = $maintenance->fetch_assoc();
            if ($maint_row['config_value'] == '1') {
                // Verificar se não é admin
                $user_check = $mysqli->query("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = {$_SESSION['user_id']}");
                if ($user_check) {
                    $user_data = $user_check->fetch_assoc();
                    if ($user_data && !in_array($user_data['role_name'], ['admin', 'Administrador'])) {
                        // Mostrar página de manutenção
                        if (basename($_SERVER['PHP_SELF']) != 'logout.php') {
                            http_response_code(503);
                            die('
                            <!DOCTYPE html>
                            <html lang="pt">
                            <head>
                                <meta charset="UTF-8">
                                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                <title>Sistema em Manutenção</title>
                                <script src="https://cdn.tailwindcss.com"></script>
                                <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
                            </head>
                            <body class="bg-gray-100 flex items-center justify-center min-h-screen">
                                <div class="text-center">
                                    <i class="bi bi-tools text-yellow-500 text-6xl mb-4"></i>
                                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Sistema em Manutenção</h1>
                                    <p class="text-gray-600 mb-4">Estamos realizando melhorias. Voltaremos em breve.</p>
                                    <a href="logout.php" class="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition inline-block">
                                        <i class="bi bi-box-arrow-right mr-2"></i>Sair
                                    </a>
                                </div>
                            </body>
                            </html>
                            ');
                        }
                    }
                }
            }
        }
    }
}
