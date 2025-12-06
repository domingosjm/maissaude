<?php
session_start();
require_once __DIR__ . '/config.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Buscar dados do profissional
$professional_query = $mysqli->query("SELECT id FROM professionals WHERE user_id = $user_id");
if (!$professional_query || $professional_query->num_rows === 0) {
    die('Erro: Usuário não está associado a um profissional.');
}
$professional = $professional_query->fetch_assoc();
$professional_id = $professional['id'];

$erro = '';
$sucesso = '';

// INSERIR RESULTADO DO EXAME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inserir_resultado'])) {
    $exam_request_id = (int)$_POST['exam_request_id'];
    $result = trim($_POST['result']);
    $observations = trim($_POST['observations']);
    
    if ($exam_request_id && $result) {
        // Verificar se já existe resultado
        $check = $mysqli->query("SELECT id FROM exam_results WHERE exam_request_id = $exam_request_id");
        
        if ($check && $check->num_rows > 0) {
            // Atualizar resultado existente
            $stmt = $mysqli->prepare("UPDATE exam_results SET result = ?, validation_notes = ?, status = 'pendente' WHERE exam_request_id = ?");
            $stmt->bind_param('ssi', $result, $observations, $exam_request_id);
        } else {
            // Inserir novo resultado
            $stmt = $mysqli->prepare("INSERT INTO exam_results (exam_request_id, result, validation_notes, status) VALUES (?, ?, ?, 'pendente')");
            $stmt->bind_param('iss', $exam_request_id, $result, $observations);
        }
        
        if ($stmt->execute()) {
            $sucesso = 'Resultado inserido com sucesso! Aguardando validação.';
        } else {
            $erro = 'Erro ao inserir resultado: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $erro = 'Resultado é obrigatório.';
    }
}

// VALIDAR E LIBERAR RESULTADO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validar_resultado'])) {
    $exam_result_id = (int)$_POST['exam_result_id'];
    $acao = $_POST['acao']; // 'liberar' ou 'rejeitar'
    $validation_notes = trim($_POST['validation_notes']);
    
    if ($exam_result_id && in_array($acao, ['liberar', 'rejeitar'])) {
        $status = $acao === 'liberar' ? 'liberado' : 'rejeitado';
        
        $stmt = $mysqli->prepare("UPDATE exam_results SET status = ?, validated_by = ?, validated_at = NOW(), validation_notes = ? WHERE id = ?");
        $stmt->bind_param('sisi', $status, $user_id, $validation_notes, $exam_result_id);
        
        if ($stmt->execute()) {
            $sucesso = $acao === 'liberar' ? 'Resultado liberado com sucesso!' : 'Resultado rejeitado.';
            
            // Se liberado, criar notificação para o médico solicitante
            if ($acao === 'liberar') {
                // Buscar dados do exame
                $exam_data = $mysqli->query("
                    SELECT er.id as exam_request_id, a.id as appointment_id, a.professional_id
                    FROM exam_results eresult
                    INNER JOIN exam_requests er ON eresult.exam_request_id = er.id
                    INNER JOIN attendances att ON er.attendance_id = att.id
                    INNER JOIN appointments a ON att.appointment_id = a.id
                    WHERE eresult.id = $exam_result_id
                ")->fetch_assoc();
                
                if ($exam_data) {
                    $notif_stmt = $mysqli->prepare("INSERT INTO exam_notifications (exam_result_id, appointment_id, professional_id, notification_type, message) VALUES (?, ?, ?, 'resultado_liberado', 'Resultado de exame liberado e disponível para visualização')");
                    $notif_stmt->bind_param('iii', $exam_result_id, $exam_data['appointment_id'], $exam_data['professional_id']);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                }
            }
        } else {
            $erro = 'Erro ao validar resultado: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// BUSCAR SOLICITAÇÕES DE EXAMES PENDENTES (sem resultado inserido)
$exames_pendentes = $mysqli->query("
    SELECT 
        er.id as exam_request_id,
        e.name as exam_name,
        e.description as exam_description,
        er.requested_at,
        p.name as patient_name,
        p.codigo as patient_code,
        p.birth_date,
        prof.id as requesting_professional_id,
        u.name as requesting_professional_name,
        a.scheduled_at
    FROM exam_requests er
    INNER JOIN exams e ON er.exam_id = e.id
    INNER JOIN attendances att ON er.attendance_id = att.id
    INNER JOIN appointments a ON att.appointment_id = a.id
    INNER JOIN patients p ON a.patient_id = p.id
    LEFT JOIN professionals prof ON a.professional_id = prof.id
    LEFT JOIN users u ON prof.user_id = u.id
    LEFT JOIN exam_results eresult ON er.id = eresult.exam_request_id
    WHERE eresult.id IS NULL
    ORDER BY er.requested_at ASC
");

// BUSCAR RESULTADOS AGUARDANDO VALIDAÇÃO
$resultados_pendentes = $mysqli->query("
    SELECT 
        eresult.id as exam_result_id,
        eresult.result,
        eresult.validation_notes,
        eresult.released_at,
        er.id as exam_request_id,
        er.requested_at,
        e.name as exam_name,
        e.description as exam_description,
        p.name as patient_name,
        p.codigo as patient_code,
        p.birth_date,
        u.name as requesting_professional_name
    FROM exam_results eresult
    INNER JOIN exam_requests er ON eresult.exam_request_id = er.id
    INNER JOIN exams e ON er.exam_id = e.id
    INNER JOIN attendances att ON er.attendance_id = att.id
    INNER JOIN appointments a ON att.appointment_id = a.id
    INNER JOIN patients p ON a.patient_id = p.id
    LEFT JOIN professionals prof ON a.professional_id = prof.id
    LEFT JOIN users u ON prof.user_id = u.id
    WHERE eresult.status = 'pendente'
    ORDER BY eresult.released_at DESC
");

// BUSCAR RESULTADOS LIBERADOS (últimos 50)
$resultados_liberados = $mysqli->query("
    SELECT 
        eresult.id as exam_result_id,
        eresult.result,
        eresult.validation_notes,
        eresult.validated_at,
        eresult.status,
        er.id as exam_request_id,
        er.requested_at,
        e.name as exam_name,
        p.name as patient_name,
        p.codigo as patient_code,
        u.name as requesting_professional_name,
        validator.name as validated_by_name
    FROM exam_results eresult
    INNER JOIN exam_requests er ON eresult.exam_request_id = er.id
    INNER JOIN exams e ON er.exam_id = e.id
    INNER JOIN attendances att ON er.attendance_id = att.id
    INNER JOIN appointments a ON att.appointment_id = a.id
    INNER JOIN patients p ON a.patient_id = p.id
    LEFT JOIN professionals prof ON a.professional_id = prof.id
    LEFT JOIN users u ON prof.user_id = u.id
    LEFT JOIN users validator ON eresult.validated_by = validator.id
    WHERE eresult.status IN ('liberado', 'rejeitado')
    ORDER BY eresult.validated_at DESC
    LIMIT 50
");
?>
<!DOCTYPE html>
<html lang="pt-br" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validação de Exames - Laboratório</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-purple-50 to-pink-50 dark:from-gray-900 dark:via-gray-800 dark:to-slate-900 min-h-screen">

    <!-- Navbar -->
    <nav class="bg-white dark:bg-gray-800 shadow-lg border-b-4 border-purple-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-3">
                    <div class="bg-gradient-to-br from-purple-500 to-pink-600 p-3 rounded-xl shadow-lg">
                        <i class="bi bi-clipboard2-pulse text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800 dark:text-white">Validação de Exames</h1>
                        <p class="text-xs text-gray-500">Laboratório - MSLDA</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-800 dark:text-white"><?= htmlspecialchars($user_name) ?></p>
                        <p class="text-xs text-gray-500">Diretor do Laboratório</p>
                    </div>
                    <a href="dashboard.php" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all text-sm font-medium">
                        <i class="bi bi-house-door mr-1"></i>Dashboard
                    </a>
                    <a href="logout.php" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg shadow-md hover:shadow-lg transition-all text-sm font-medium">
                        <i class="bi bi-box-arrow-right mr-1"></i>Sair
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Alertas -->
        <?php if ($erro): ?>
        <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-lg shadow-md">
            <div class="flex items-center">
                <i class="bi bi-exclamation-triangle-fill text-2xl mr-3"></i>
                <p class="font-semibold"><?= htmlspecialchars($erro) ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($sucesso): ?>
        <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded-lg shadow-md">
            <div class="flex items-center">
                <i class="bi bi-check-circle-fill text-2xl mr-3"></i>
                <p class="font-semibold"><?= htmlspecialchars($sucesso) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-gradient-to-br from-orange-400 to-red-500 rounded-2xl shadow-xl p-6 text-white">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-orange-100 text-sm font-medium mb-1">Sem Resultado</p>
                        <p class="text-4xl font-bold"><?= $exames_pendentes->num_rows ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                        <i class="bi bi-hourglass-split text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-yellow-400 to-orange-500 rounded-2xl shadow-xl p-6 text-white">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-yellow-100 text-sm font-medium mb-1">Aguardando Validação</p>
                        <p class="text-4xl font-bold"><?= $resultados_pendentes->num_rows ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                        <i class="bi bi-clock-history text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-green-400 to-emerald-500 rounded-2xl shadow-xl p-6 text-white">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-green-100 text-sm font-medium mb-1">Liberados</p>
                        <p class="text-4xl font-bold"><?= $resultados_liberados->num_rows ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                        <i class="bi bi-check-circle text-3xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
            <nav class="flex space-x-4" role="tablist">
                <button onclick="switchTab('pendentes')" id="tab-pendentes" class="tab-button px-4 py-3 font-semibold border-b-2 border-orange-500 text-orange-600">
                    <i class="bi bi-hourglass-split mr-2"></i>Sem Resultado (<?= $exames_pendentes->num_rows ?>)
                </button>
                <button onclick="switchTab('validacao')" id="tab-validacao" class="tab-button px-4 py-3 font-semibold border-b-2 border-transparent text-gray-600">
                    <i class="bi bi-clipboard-check mr-2"></i>Aguardando Validação (<?= $resultados_pendentes->num_rows ?>)
                </button>
                <button onclick="switchTab('liberados')" id="tab-liberados" class="tab-button px-4 py-3 font-semibold border-b-2 border-transparent text-gray-600">
                    <i class="bi bi-check-circle mr-2"></i>Liberados/Rejeitados
                </button>
            </nav>
        </div>

        <!-- Tab: Exames Sem Resultado -->
        <div id="content-pendentes" class="tab-content">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                    <i class="bi bi-journal-medical mr-3 text-orange-600"></i>
                    Exames Aguardando Resultado
                </h2>
                
                <?php if ($exames_pendentes->num_rows > 0): ?>
                    <div class="space-y-4">
                        <?php while ($exam = $exames_pendentes->fetch_assoc()): ?>
                        <div class="bg-gradient-to-r from-orange-50 to-red-50 dark:from-gray-700 dark:to-gray-600 rounded-xl p-6 border-l-4 border-orange-500 shadow-md">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?= htmlspecialchars($exam['exam_name']) ?></h3>
                                        <span class="px-3 py-1 bg-orange-500 text-white rounded-full text-xs font-semibold">
                                            <i class="bi bi-clock mr-1"></i>Pendente
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-2 text-sm">
                                        <p class="text-gray-700 dark:text-gray-300">
                                            <i class="bi bi-person-fill mr-2 text-blue-600"></i>
                                            <strong>Paciente:</strong> <?= htmlspecialchars($exam['patient_name']) ?> 
                                            <span class="font-mono bg-blue-100 dark:bg-blue-900 px-2 py-1 rounded ml-2"><?= htmlspecialchars($exam['patient_code']) ?></span>
                                        </p>
                                        <p class="text-gray-700 dark:text-gray-300">
                                            <i class="bi bi-calendar-event mr-2 text-purple-600"></i>
                                            <strong>Nascimento:</strong> <?= date('d/m/Y', strtotime($exam['birth_date'])) ?> 
                                            (<?= floor((time() - strtotime($exam['birth_date'])) / 31536000) ?> anos)
                                        </p>
                                        <p class="text-gray-700 dark:text-gray-300">
                                            <i class="bi bi-person-badge mr-2 text-green-600"></i>
                                            <strong>Solicitante:</strong> <?= htmlspecialchars($exam['requesting_professional_name'] ?? 'N/A') ?>
                                        </p>
                                        <p class="text-gray-700 dark:text-gray-300">
                                            <i class="bi bi-clock-history mr-2 text-orange-600"></i>
                                            <strong>Solicitado em:</strong> <?= date('d/m/Y H:i', strtotime($exam['requested_at'])) ?>
                                        </p>
                                        <?php if ($exam['exam_description']): ?>
                                        <p class="text-gray-600 dark:text-gray-400 text-xs italic mt-2">
                                            <?= htmlspecialchars($exam['exam_description']) ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div>
                                    <form method="POST" class="space-y-4">
                                        <input type="hidden" name="inserir_resultado" value="1">
                                        <input type="hidden" name="exam_request_id" value="<?= $exam['exam_request_id'] ?>">
                                        
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                                <i class="bi bi-file-text mr-1"></i>Resultado do Exame *
                                            </label>
                                            <textarea name="result" required rows="6" 
                                                      class="w-full px-4 py-2 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:ring-4 focus:ring-purple-200 dark:bg-gray-700 dark:text-white"
                                                      placeholder="Digite o resultado detalhado do exame..."></textarea>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                                <i class="bi bi-chat-left-text mr-1"></i>Observações
                                            </label>
                                            <textarea name="observations" rows="3" 
                                                      class="w-full px-4 py-2 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:ring-4 focus:ring-purple-200 dark:bg-gray-700 dark:text-white"
                                                      placeholder="Observações adicionais (opcional)"></textarea>
                                        </div>
                                        
                                        <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-orange-500 to-red-600 hover:from-orange-600 hover:to-red-700 text-white rounded-xl shadow-lg hover:shadow-xl transition-all font-semibold">
                                            <i class="bi bi-save mr-2"></i>Inserir Resultado
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="bi bi-check-circle text-6xl text-green-400 mb-4"></i>
                        <p class="text-gray-600 dark:text-gray-400 text-lg">Todos os exames solicitados possuem resultados inseridos</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: Aguardando Validação -->
        <div id="content-validacao" class="tab-content hidden">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                    <i class="bi bi-shield-check mr-3 text-yellow-600"></i>
                    Resultados Aguardando Validação
                </h2>
                
                <?php if ($resultados_pendentes->num_rows > 0): ?>
                    <div class="space-y-4">
                        <?php while ($result = $resultados_pendentes->fetch_assoc()): ?>
                        <div class="bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-gray-700 dark:to-gray-600 rounded-xl p-6 border-l-4 border-yellow-500 shadow-md">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?= htmlspecialchars($result['exam_name']) ?></h3>
                                        <span class="px-3 py-1 bg-yellow-500 text-white rounded-full text-xs font-semibold">
                                            <i class="bi bi-hourglass mr-1"></i>Validação Pendente
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-2 text-sm mb-4">
                                        <p class="text-gray-700 dark:text-gray-300">
                                            <i class="bi bi-person-fill mr-2 text-blue-600"></i>
                                            <strong>Paciente:</strong> <?= htmlspecialchars($result['patient_name']) ?> 
                                            <span class="font-mono bg-blue-100 dark:bg-blue-900 px-2 py-1 rounded ml-2"><?= htmlspecialchars($result['patient_code']) ?></span>
                                        </p>
                                        <p class="text-gray-700 dark:text-gray-300">
                                            <i class="bi bi-person-badge mr-2 text-green-600"></i>
                                            <strong>Solicitante:</strong> <?= htmlspecialchars($result['requesting_professional_name'] ?? 'N/A') ?>
                                        </p>
                                    </div>
                                    
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-3">
                                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Resultado Inserido:</p>
                                        <p class="text-gray-800 dark:text-white whitespace-pre-line text-sm"><?= nl2br(htmlspecialchars($result['result'])) ?></p>
                                    </div>
                                    
                                    <?php if ($result['validation_notes']): ?>
                                    <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-3">
                                        <p class="text-xs font-semibold text-blue-700 dark:text-blue-300 mb-1">Observações:</p>
                                        <p class="text-blue-800 dark:text-blue-200 text-xs"><?= nl2br(htmlspecialchars($result['validation_notes'])) ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <form method="POST" class="space-y-4">
                                        <input type="hidden" name="validar_resultado" value="1">
                                        <input type="hidden" name="exam_result_id" value="<?= $result['exam_result_id'] ?>">
                                        
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                                <i class="bi bi-chat-quote mr-1"></i>Notas de Validação
                                            </label>
                                            <textarea name="validation_notes" rows="4" 
                                                      class="w-full px-4 py-2 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:ring-4 focus:ring-green-200 dark:bg-gray-700 dark:text-white"
                                                      placeholder="Comentários sobre a validação (opcional)"></textarea>
                                        </div>
                                        
                                        <div class="grid grid-cols-2 gap-3">
                                            <button type="submit" name="acao" value="liberar" 
                                                    class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-xl shadow-lg hover:shadow-xl transition-all font-semibold">
                                                <i class="bi bi-check-circle mr-2"></i>Liberar
                                            </button>
                                            <button type="submit" name="acao" value="rejeitar" 
                                                    onclick="return confirm('Tem certeza que deseja rejeitar este resultado?')"
                                                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 text-white rounded-xl shadow-lg hover:shadow-xl transition-all font-semibold">
                                                <i class="bi bi-x-circle mr-2"></i>Rejeitar
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="bi bi-inbox text-6xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600 dark:text-gray-400 text-lg">Nenhum resultado aguardando validação</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab: Liberados/Rejeitados -->
        <div id="content-liberados" class="tab-content hidden">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                    <i class="bi bi-archive mr-3 text-green-600"></i>
                    Histórico de Validações
                </h2>
                
                <?php if ($resultados_liberados->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-900 dark:to-emerald-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300">Data Validação</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300">Exame</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300">Paciente</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300">Validado Por</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">Status</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php while ($result = $resultados_liberados->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">
                                        <?= date('d/m/Y H:i', strtotime($result['validated_at'])) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-800 dark:text-white">
                                        <?= htmlspecialchars($result['exam_name']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">
                                        <?= htmlspecialchars($result['patient_name']) ?>
                                        <br><span class="text-xs font-mono text-gray-500"><?= htmlspecialchars($result['patient_code']) ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                        <?= htmlspecialchars($result['validated_by_name'] ?? 'N/A') ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <?php if ($result['status'] === 'liberado'): ?>
                                        <span class="px-3 py-1 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full text-xs font-semibold">
                                            <i class="bi bi-check-circle mr-1"></i>Liberado
                                        </span>
                                        <?php else: ?>
                                        <span class="px-3 py-1 bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded-full text-xs font-semibold">
                                            <i class="bi bi-x-circle mr-1"></i>Rejeitado
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="exportar_exames_pdf.php?exam_request_id=<?= $result['exam_request_id'] ?>" 
                                           target="_blank"
                                           class="inline-flex items-center px-3 py-1 bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs font-medium transition-all">
                                            <i class="bi bi-file-pdf mr-1"></i>PDF
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="bi bi-inbox text-6xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600 dark:text-gray-400 text-lg">Nenhum histórico de validação</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script>
        function switchTab(tabName) {
            // Esconder todos os conteúdos
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remover active de todos os botões
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-orange-500', 'text-orange-600', 'border-yellow-500', 'text-yellow-600', 'border-green-500', 'text-green-600');
                button.classList.add('border-transparent', 'text-gray-600');
            });
            
            // Mostrar conteúdo selecionado
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Ativar botão selecionado
            const activeButton = document.getElementById('tab-' + tabName);
            if (tabName === 'pendentes') {
                activeButton.classList.add('border-orange-500', 'text-orange-600');
            } else if (tabName === 'validacao') {
                activeButton.classList.add('border-yellow-500', 'text-yellow-600');
            } else {
                activeButton.classList.add('border-green-500', 'text-green-600');
            }
            activeButton.classList.remove('border-transparent', 'text-gray-600');
        }
    </script>

</body>
</html>
