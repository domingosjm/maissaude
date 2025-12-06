<?php
/**
 * Sistema de Rotas - MSLDA
 * Gerencia URLs amigáveis e organização de módulos
 */

session_start();
require_once 'config.php';

// Verificar autenticação
function verificarAutenticacao() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /MSLDA/index.php');
        exit;
    }
}

// Obter rota da URL
$request = $_SERVER['REQUEST_URI'];
$base_path = '/MSLDA/';
$path = str_replace($base_path, '', parse_url($request, PHP_URL_PATH));

// Remover barra inicial/final
$path = trim($path, '/');

// Rotas públicas (não requerem autenticação)
$rotasPublicas = [
    '' => 'index.php',
    'login' => 'index.php',
];

// Rotas de módulos (requerem autenticação)
$rotasModulos = [
    // Dashboard
    'dashboard' => 'dashboard.php',
    
    // Recepção
    'recepcao' => 'modules/recepcao/index.php',
    'recepcao/pacientes' => 'modules/recepcao/pacientes.php',
    'recepcao/agendamentos' => 'modules/recepcao/agendamentos.php',
    'recepcao/faturamento' => 'modules/recepcao/faturamento.php',
    
    // Laboratório
    'laboratorio' => 'modules/laboratorio/index.php',
    'laboratorio/protocolos' => 'modules/laboratorio/protocolos.php',
    'laboratorio/resultados' => 'modules/laboratorio/resultados.php',
    'laboratorio/sysmex' => 'modules/laboratorio/sysmex_integration.php',
    
    // Farmácia
    'farmacia' => 'modules/farmacia/index.php',
    'farmacia/estoque' => 'modules/farmacia/estoque.php',
    'farmacia/prescricoes' => 'modules/farmacia/prescricoes.php',
    
    // Consultórios
    'consultorio/medico' => 'modules/consultorios/medico_dashboard.php',
    'consultorio/psicologia' => 'modules/consultorios/psicologia.php',
    'consultorio/psiquiatria' => 'modules/consultorios/psiquiatria.php',
    
    // Financeiro
    'financeiro' => 'modules/financeiro/index.php',
    'financeiro/relatorios' => 'modules/financeiro/relatorios.php',
    'financeiro/faturas' => 'modules/financeiro/faturas.php',
    'financeiro/caixa' => 'modules/financeiro/caixa.php',
    
    // Administração
    'admin/usuarios' => 'modules/admin/usuarios.php',
    'admin/servicos' => 'modules/admin/servicos.php',
    'admin/configuracoes' => 'modules/admin/configuracoes.php',
    
    // Chat
    'chat' => 'chat.php',
    
    // Logout
    'logout' => 'logout.php',
];

// API Endpoints
$rotasAPI = [
    'api/chat/messages' => 'api/chat_messages.php',
    'api/chat/send' => 'api/chat_send.php',
    'api/pacientes/search' => 'api/pacientes_search.php',
];

// Processar rota
if (array_key_exists($path, $rotasPublicas)) {
    // Rota pública
    require_once $rotasPublicas[$path];
    exit;
}

// Verificar autenticação para rotas protegidas
verificarAutenticacao();

// Verificar rotas de API
if (array_key_exists($path, $rotasAPI)) {
    require_once $rotasAPI[$path];
    exit;
}

// Verificar rotas de módulos
if (array_key_exists($path, $rotasModulos)) {
    $arquivo = $rotasModulos[$path];
    
    // Verificar se o arquivo existe
    if (file_exists($arquivo)) {
        require_once $arquivo;
    } else {
        // Se não existe no novo local, tentar na raiz (compatibilidade)
        $nomeArquivo = basename($arquivo);
        if (file_exists($nomeArquivo)) {
            require_once $nomeArquivo;
        } else {
            http_response_code(404);
            echo "Página não encontrada: $path";
        }
    }
    exit;
}

// Rota não encontrada
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página não encontrada</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center">
        <div class="text-center">
            <i class="bi bi-exclamation-triangle text-red-500 text-9xl"></i>
            <h1 class="text-6xl font-bold text-gray-800 mt-4">404</h1>
            <p class="text-2xl text-gray-600 mt-2">Página não encontrada</p>
            <p class="text-gray-500 mt-4">A rota <strong><?= htmlspecialchars($path) ?></strong> não existe.</p>
            <a href="/MSLDA/dashboard" class="mt-6 inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                <i class="bi bi-house-door mr-2"></i>Voltar ao Dashboard
            </a>
        </div>
    </div>
</body>
</html>
