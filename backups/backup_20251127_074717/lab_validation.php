<?php
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';
require_login();

$conn = $mysqli;
$user_name = $_SESSION['user_name'] ?? 'Usuário';
$user_email = $_SESSION['user_email'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Verificar se é o Diretor Técnico (Cláudio)
$is_director = ($user_email === 'claudio.diogo@maissaude.co.mz');

if (!$is_director) {
    header('Location: dashboard.php');
    exit;
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'validate_result') {
        $result_id = $_POST['result_id'] ?? '';
        $status = $_POST['status'] ?? 'liberado';
        $notes = $_POST['notes'] ?? '';
        
        if (empty($result_id)) {
            echo json_encode(['success' => false, 'message' => 'Resultado inválido!']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE exam_results 
                                SET status = ?, validated_by = ?, validated_at = NOW(), validation_notes = ? 
                                WHERE id = ?");
        $stmt->bind_param('sisi', $status, $user_id, $notes, $result_id);
        
        if ($stmt->execute()) {
            // Buscar informações do exame para notificar o médico
            $result = $conn->query("SELECT er.*, eq.appointment_id, eq.professional_id 
                                    FROM exam_results er 
                                    JOIN exam_requests eq ON er.exam_request_id = eq.id 
                                    WHERE er.id = $result_id")->fetch_assoc();
            
            if ($result && $status === 'liberado') {
                // Criar notificação para o médico
                $message = "Resultado de exame liberado e disponível para análise";
                $stmt2 = $conn->prepare("INSERT INTO exam_notifications (exam_result_id, appointment_id, professional_id, notification_type, message) 
                                         VALUES (?, ?, ?, 'resultado_liberado', ?)");
                $stmt2->bind_param('iiis', $result_id, $result['appointment_id'], $result['professional_id'], $message);
                $stmt2->execute();
                
                // Atualizar status da consulta para aguardando análise de exames
                if ($result['appointment_id']) {
                    $conn->query("UPDATE appointments SET consultation_status = 'aguardando_exames' WHERE id = " . $result['appointment_id']);
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Resultado ' . ($status === 'liberado' ? 'liberado' : 'rejeitado') . ' com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao validar resultado: ' . $conn->error]);
        }
        exit;
    }
}

// Buscar resultados de exames pendentes de validação
$pending_results = [];
$query = "SELECT er.*, eq.exam_id, eq.patient_id, eq.professional_id, eq.appointment_id,
                 e.name as exam_name, p.name as patient_name, pr.user_id,
                 u.name as professional_name
          FROM exam_results er
          JOIN exam_requests eq ON er.exam_request_id = eq.id
          JOIN exams e ON eq.exam_id = e.id
          JOIN patients p ON eq.patient_id = p.id
          JOIN professionals pr ON eq.professional_id = pr.id
          JOIN users u ON pr.user_id = u.id
          WHERE er.status = 'pendente'
          ORDER BY er.released_at DESC";

$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $pending_results[] = $row;
}

// Buscar resultados já validados (histórico)
$validated_results = [];
$query2 = "SELECT er.*, eq.exam_id, eq.patient_id, eq.professional_id,
                  e.name as exam_name, p.name as patient_name,
                  u.name as professional_name, v.name as validator_name
           FROM exam_results er
           JOIN exam_requests eq ON er.exam_request_id = eq.id
           JOIN exams e ON eq.exam_id = e.id
           JOIN patients p ON eq.patient_id = p.id
           JOIN professionals pr ON eq.professional_id = pr.id
           JOIN users u ON pr.user_id = u.id
           LEFT JOIN users v ON er.validated_by = v.id
           WHERE er.status IN ('liberado', 'rejeitado')
           ORDER BY er.validated_at DESC
           LIMIT 20";

$result2 = $conn->query($query2);
while ($row = $result2->fetch_assoc()) {
    $validated_results[] = $row;
}

// Estatísticas
$stats = [
    'pending' => count($pending_results),
    'released_today' => $conn->query("SELECT COUNT(*) as count FROM exam_results WHERE status = 'liberado' AND DATE(validated_at) = CURDATE()")->fetch_assoc()['count'],
    'rejected_today' => $conn->query("SELECT COUNT(*) as count FROM exam_results WHERE status = 'rejeitado' AND DATE(validated_at) = CURDATE()")->fetch_assoc()['count'],
    'total_released' => $conn->query("SELECT COUNT(*) as count FROM exam_results WHERE status = 'liberado'")->fetch_assoc()['count']
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validação de Resultados | Mais Saúde</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .glass-effect { backdrop-filter: blur(16px); background: rgba(255, 255, 255, 0.9); }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-50 via-indigo-50 to-blue-50 min-h-screen">
    <!-- Navbar -->
    <nav class="glass-effect shadow-xl border-b border-purple-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="flex items-center space-x-3">
                        <div class="bg-gradient-to-br from-purple-500 to-indigo-600 p-2 rounded-xl shadow-lg">
                            <i class="bi bi-shield-check text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold bg-gradient-to-r from-purple-600 to-indigo-600 bg-clip-text text-transparent">
                                Validação de Resultados
                            </h1>
                            <p class="text-xs text-gray-500">Diretor Técnico - Controle de Liberação</p>
                        </div>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="text-xs text-gray-500">Diretor Técnico</p>
                        <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($user_name) ?></p>
                    </div>
                    <a href="dashboard.php" class="px-4 py-2 bg-gradient-to-r from-purple-500 to-indigo-600 text-white rounded-lg hover:from-purple-600 hover:to-indigo-700 transition-all shadow-lg">
                        <i class="bi bi-arrow-left mr-2"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="glass-effect rounded-2xl shadow-xl border-2 border-red-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-semibold">Pendentes de Validação</p>
                        <p class="text-3xl font-bold text-red-600 mt-2"><?= $stats['pending'] ?></p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-700 rounded-2xl flex items-center justify-center shadow-lg">
                        <i class="bi bi-exclamation-triangle-fill text-white text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="glass-effect rounded-2xl shadow-xl border-2 border-green-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-semibold">Liberados Hoje</p>
                        <p class="text-3xl font-bold text-green-600 mt-2"><?= $stats['released_today'] ?></p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-green-700 rounded-2xl flex items-center justify-center shadow-lg">
                        <i class="bi bi-check-circle-fill text-white text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="glass-effect rounded-2xl shadow-xl border-2 border-orange-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-semibold">Rejeitados Hoje</p>
                        <p class="text-3xl font-bold text-orange-600 mt-2"><?= $stats['rejected_today'] ?></p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-orange-500 to-orange-700 rounded-2xl flex items-center justify-center shadow-lg">
                        <i class="bi bi-x-circle-fill text-white text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="glass-effect rounded-2xl shadow-xl border-2 border-blue-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-semibold">Total Liberados</p>
                        <p class="text-3xl font-bold text-blue-600 mt-2"><?= $stats['total_released'] ?></p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl flex items-center justify-center shadow-lg">
                        <i class="bi bi-clipboard-data text-white text-3xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="glass-effect rounded-2xl shadow-xl border-2 border-purple-200 p-2 mb-6">
            <div class="flex space-x-2">
                <button onclick="switchTab('pending')" class="tab-btn flex-1 px-6 py-3 rounded-xl bg-red-100 text-red-800 font-semibold transition-all" data-tab="pending">
                    <i class="bi bi-exclamation-circle mr-2"></i>Pendentes (<?= $stats['pending'] ?>)
                </button>
                <button onclick="switchTab('validated')" class="tab-btn flex-1 px-6 py-3 rounded-xl hover:bg-gray-100 font-semibold transition-all" data-tab="validated">
                    <i class="bi bi-clock-history mr-2"></i>Histórico
                </button>
            </div>
        </div>

        <!-- Tab: Pendentes -->
        <div id="pending" class="tab-content active">
            <div class="glass-effect rounded-2xl shadow-xl border-2 border-purple-200 p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                    <i class="bi bi-shield-check mr-3 text-purple-600"></i>
                    Resultados Pendentes de Validação
                </h2>
                
                <div class="space-y-4">
                    <?php foreach ($pending_results as $result): ?>
                    <div class="bg-white rounded-xl border-2 border-gray-200 p-6 hover:border-purple-300 transition-all">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-3">
                                    <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-bold">
                                        <i class="bi bi-clock-history mr-1"></i>Pendente
                                    </span>
                                    <h3 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($result['exam_name']) ?></h3>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <p class="text-xs text-gray-500 font-semibold">Paciente</p>
                                        <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($result['patient_name']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 font-semibold">Solicitado por</p>
                                        <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($result['professional_name']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 font-semibold">Data do Resultado</p>
                                        <p class="text-sm font-bold text-gray-800"><?= date('d/m/Y H:i', strtotime($result['released_at'])) ?></p>
                                    </div>
                                </div>
                                
                                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                    <p class="text-xs text-gray-500 font-semibold mb-2">Resultado</p>
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($result['result'])) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-3 pt-4 border-t">
                            <button onclick='validateResult(<?= $result['id'] ?>, "liberado")' 
                                    class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl hover:from-green-600 hover:to-emerald-700 transition-all shadow-lg font-semibold flex-1">
                                <i class="bi bi-check-circle-fill mr-2"></i>Liberar Resultado
                            </button>
                            <button onclick='validateResult(<?= $result['id'] ?>, "rejeitado")' 
                                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-xl hover:from-red-600 hover:to-pink-700 transition-all shadow-lg font-semibold flex-1">
                                <i class="bi bi-x-circle-fill mr-2"></i>Rejeitar
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($pending_results)): ?>
                    <div class="text-center py-12">
                        <i class="bi bi-check-circle text-6xl text-green-300 mb-4 block"></i>
                        <p class="text-lg font-semibold text-gray-500">Nenhum resultado pendente de validação</p>
                        <p class="text-sm text-gray-400 mt-2">Todos os resultados estão em dia! ✅</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab: Histórico -->
        <div id="validated" class="tab-content">
            <div class="glass-effect rounded-2xl shadow-xl border-2 border-purple-200 p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                    <i class="bi bi-clock-history mr-3 text-purple-600"></i>
                    Histórico de Validações
                </h2>
                
                <div class="space-y-4">
                    <?php foreach ($validated_results as $result): ?>
                    <div class="bg-white rounded-xl border-2 border-gray-200 p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-3">
                                    <?php if ($result['status'] === 'liberado'): ?>
                                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-bold">
                                            <i class="bi bi-check-circle-fill mr-1"></i>Liberado
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-bold">
                                            <i class="bi bi-x-circle-fill mr-1"></i>Rejeitado
                                        </span>
                                    <?php endif; ?>
                                    <h3 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($result['exam_name']) ?></h3>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-3">
                                    <div>
                                        <p class="text-xs text-gray-500 font-semibold">Paciente</p>
                                        <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($result['patient_name']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 font-semibold">Solicitado por</p>
                                        <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($result['professional_name']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 font-semibold">Validado por</p>
                                        <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($result['validator_name'] ?? 'Sistema') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 font-semibold">Data da Validação</p>
                                        <p class="text-sm font-bold text-gray-800"><?= $result['validated_at'] ? date('d/m/Y H:i', strtotime($result['validated_at'])) : '-' ?></p>
                                    </div>
                                </div>
                                
                                <?php if ($result['validation_notes']): ?>
                                <div class="bg-blue-50 border-l-4 border-blue-500 p-3 rounded">
                                    <p class="text-xs text-blue-700 font-semibold mb-1">Observações da Validação:</p>
                                    <p class="text-sm text-blue-900"><?= nl2br(htmlspecialchars($result['validation_notes'])) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($validated_results)): ?>
                    <div class="text-center py-12">
                        <i class="bi bi-inbox text-6xl text-gray-300 mb-4 block"></i>
                        <p class="text-lg font-semibold text-gray-500">Nenhum histórico de validações</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabId) {
            // Esconder todos os conteúdos
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remover classe active de todos os botões
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('bg-red-100', 'text-red-800', 'bg-gray-100');
            });
            
            // Mostrar o conteúdo selecionado
            document.getElementById(tabId).classList.add('active');
            
            // Adicionar classe active ao botão clicado
            const activeBtn = document.querySelector(`[data-tab="${tabId}"]`);
            if (tabId === 'pending') {
                activeBtn.classList.add('bg-red-100', 'text-red-800');
            } else {
                activeBtn.classList.add('bg-gray-100');
            }
        }
        
        async function validateResult(resultId, status) {
            const notes = prompt(status === 'liberado' ? 'Observações (opcional):' : 'Motivo da rejeição:');
            if (status === 'rejeitado' && !notes) {
                alert('❌ Para rejeitar um resultado, você deve informar o motivo!');
                return;
            }
            
            if (!confirm(`Confirma a ${status === 'liberado' ? 'liberação' : 'rejeição'} deste resultado?`)) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'validate_result');
                formData.append('result_id', resultId);
                formData.append('status', status);
                formData.append('notes', notes || '');
                
                const response = await fetch('lab_validation.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ ' + result.message);
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Erro: ' + error.message);
            }
        }
    </script>
</body>
</html>
