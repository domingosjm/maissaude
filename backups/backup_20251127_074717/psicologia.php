<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? 'Usuário';

// Buscar o professional_id do usuário logado
$prof_query = $mysqli->query("SELECT id FROM professionals WHERE user_id = $user_id");
$professional = $prof_query->fetch_assoc();
$professional_id = $professional['id'] ?? null;

if (!$professional_id) {
    die("Erro: Profissional não encontrado para este usuário.");
}

$erro = '';
$sucesso = '';

// CRIAR SESSÃO PSICOLÓGICA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar_sessao') {
    $attendance_id = (int)$_POST['attendance_id'];
    $observacoes = trim($_POST['observacoes']);
    $diagnostico = trim($_POST['diagnostico']);
    $plano_tratamento = trim($_POST['plano_tratamento']);
    
    if ($observacoes) {
        $stmt = $mysqli->prepare("INSERT INTO psychology_sessions (attendance_id, observations, diagnosis, treatment_plan, session_date) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param('isss', $attendance_id, $observacoes, $diagnostico, $plano_tratamento);
        
        if ($stmt->execute()) {
            $sucesso = 'Sessão psicológica registrada com sucesso!';
        } else {
            $erro = 'Erro ao registrar sessão: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $erro = 'Observações são obrigatórias.';
    }
}

// INICIAR ATENDIMENTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'iniciar_atendimento') {
    $appointment_id = (int)$_POST['appointment_id'];
    
    // Verificar se já existe um atendimento para esta consulta
    $check = $mysqli->query("SELECT id FROM attendances WHERE appointment_id = $appointment_id");
    
    if ($check && $check->num_rows > 0) {
        // Já existe atendimento, apenas atualizar status
        $mysqli->query("UPDATE appointments SET consultation_status='em_atendimento' WHERE id=$appointment_id");
        $sucesso = 'Atendimento retomado!';
    } else {
        // Atualizar status da consulta
        if ($mysqli->query("UPDATE appointments SET consultation_status='em_atendimento' WHERE id=$appointment_id")) {
            // Criar atendimento
            $stmt = $mysqli->prepare('INSERT INTO attendances (appointment_id, notes) VALUES (?, "")');
            $stmt->bind_param('i', $appointment_id);
            
            if ($stmt->execute()) {
                // Registrar no histórico
                $stmt2 = $mysqli->prepare('INSERT INTO consultation_history (appointment_id, professional_id, action_type, notes) VALUES (?, ?, "inicio_atendimento", "Atendimento psicológico iniciado")');
                $stmt2->bind_param('ii', $appointment_id, $professional_id);
                $stmt2->execute();
                $stmt2->close();
                
                $sucesso = 'Atendimento iniciado com sucesso!';
            } else {
                $erro = 'Erro ao criar atendimento: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $erro = 'Erro ao atualizar status: ' . $mysqli->error;
        }
    }
    
    // Redirecionar após processar
    if (!$erro) {
        header('Location: psicologia.php');
        exit;
    }
}

// FINALIZAR ATENDIMENTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'finalizar_atendimento') {
    $appointment_id = (int)$_POST['appointment_id'];
    $notes = trim($_POST['notes']);
    
    $mysqli->query("UPDATE appointments SET consultation_status='concluido', concluded_by=$user_id, concluded_at=NOW() WHERE id=$appointment_id");
    $mysqli->query("UPDATE attendances SET notes='$notes' WHERE appointment_id=$appointment_id");
    
    // Registrar no histórico
    $stmt = $mysqli->prepare('INSERT INTO consultation_history (appointment_id, professional_id, action_type, notes) VALUES (?, ?, "conclusao", ?)');
    $stmt->bind_param('iis', $appointment_id, $professional_id, $notes);
    $stmt->execute();
    $stmt->close();
    
    $sucesso = 'Atendimento finalizado com sucesso!';
    header('Location: psicologia.php');
    exit;
}

// Buscar consultas agendadas
$appointments = $mysqli->query("
    SELECT 
        a.id,
        a.scheduled_at,
        a.consultation_status,
        p.name as patient_name,
        p.codigo as patient_code
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.professional_id = $professional_id
    AND a.consultation_status = 'agendado'
    ORDER BY a.scheduled_at ASC
    LIMIT 20
");

// Buscar atendimentos ativos
$attendances_query = "
    SELECT 
        att.id as attendance_id,
        att.notes,
        a.id as appointment_id,
        a.scheduled_at,
        a.patient_id,
        a.consultation_status,
        p.name as patient_name,
        p.codigo as patient_code,
        p.birth_date
    FROM appointments a
    LEFT JOIN attendances att ON att.appointment_id = a.id
    JOIN patients p ON a.patient_id = p.id
    WHERE a.professional_id = $professional_id
    AND a.consultation_status IN ('agendado', 'em_atendimento', 'aguardando_exames')
    ORDER BY a.scheduled_at DESC
";
$attendances = $mysqli->query($attendances_query);

if (!$attendances) {
    die("Erro na query de atendimentos: " . $mysqli->error);
}

$total_atendimentos = $attendances->num_rows;
?>
<!DOCTYPE html>
<html lang="pt-br" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Psicologia | Integrada Mais Saúde</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse-soft {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        .animate-fadeIn { animation: fadeIn 0.6s ease-out; }
        .animate-slideUp { animation: slideUp 0.8s ease-out; }
        .animate-pulse-soft { animation: pulse-soft 2s ease-in-out infinite; }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .dark .glass-effect {
            background: rgba(31, 41, 55, 0.9);
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 50%, #b45309 100%);
        }
        
        .hover-lift {
            transition: all 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(245, 158, 11, 0.3);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-amber-50 via-orange-50 to-yellow-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen">
    
    <!-- Navbar -->
    <nav class="glass-effect border-b border-amber-200 dark:border-gray-700 sticky top-0 z-50 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="flex items-center space-x-3 hover:opacity-80 transition-opacity">
                        <div class="w-12 h-12 bg-gradient-to-br from-amber-500 to-orange-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="bi bi-heart-pulse-fill text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800 dark:text-white">Integrada Mais Saúde</h1>
                            <p class="text-xs text-amber-600 dark:text-amber-400 font-medium">Módulo Psicologia</p>
                        </div>
                    </a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <button onclick="toggleDarkMode()" class="p-3 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-all duration-300">
                        <i class="bi bi-moon-stars-fill dark:bi-sun-fill text-gray-700 dark:text-yellow-400 text-xl"></i>
                    </button>
                    <div class="flex items-center space-x-3 px-4 py-2 rounded-xl bg-amber-50 dark:bg-amber-900/30">
                        <i class="bi bi-person-circle text-amber-600 dark:text-amber-400 text-2xl"></i>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Psic. <?php echo htmlspecialchars($user_name); ?></span>
                    </div>
                    <a href="logout.php" class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                        <i class="bi bi-box-arrow-right mr-2"></i>Sair
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <script>
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
        }
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>

    <!-- Container Principal -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Alertas -->
        <?php if ($erro): ?>
            <div class="mb-6 bg-red-50 dark:bg-red-900/30 border-l-4 border-red-500 rounded-lg p-4 animate-fadeIn">
                <div class="flex items-center">
                    <i class="bi bi-exclamation-triangle-fill text-red-500 text-xl mr-3"></i>
                    <p class="text-red-800 dark:text-red-200 font-medium"><?= htmlspecialchars($erro) ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($sucesso): ?>
            <div class="mb-6 bg-emerald-50 dark:bg-emerald-900/30 border-l-4 border-emerald-500 rounded-lg p-4 animate-fadeIn">
                <div class="flex items-center">
                    <i class="bi bi-check-circle-fill text-emerald-500 text-xl mr-3"></i>
                    <p class="text-emerald-800 dark:text-emerald-200 font-medium"><?= htmlspecialchars($sucesso) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Sidebar: Consultas Agendadas -->
            <div class="lg:col-span-1">
                <div class="glass-effect rounded-2xl shadow-xl border border-amber-200 dark:border-gray-700 overflow-hidden hover-lift">
                    <div class="bg-gradient-to-r from-amber-500 to-orange-600 px-6 py-4">
                        <h3 class="text-lg font-bold text-white flex items-center">
                            <i class="bi bi-calendar-check mr-3 text-xl"></i>
                            Consultas Agendadas
                        </h3>
                    </div>
                    <div class="p-4 max-h-[700px] overflow-y-auto space-y-3">
                        <?php if ($appointments && $appointments->num_rows > 0):
                            while($apt = $appointments->fetch_assoc()): ?>
                                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-l-4 border-amber-500 shadow-md hover:shadow-lg transition-all duration-300">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="font-semibold text-gray-800 dark:text-white">
                                            <?= htmlspecialchars($apt['patient_name']) ?>
                                        </div>
                                        <span class="text-xs font-mono bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200 px-2 py-1 rounded">
                                            <?= htmlspecialchars($apt['patient_code']) ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        <i class="bi bi-clock mr-1"></i>
                                        <?= date('d/m/Y H:i', strtotime($apt['scheduled_at'])) ?>
                                    </div>
                                    <?php if ($apt['consultation_status'] === 'agendado'): ?>
                                    <form method="post">
                                        <input type="hidden" name="acao" value="iniciar_atendimento">
                                        <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                        <button type="submit" class="w-full px-4 py-2 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 font-medium text-sm">
                                            <i class="bi bi-play-circle mr-2"></i>Iniciar Sessão
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile;
                        else: ?>
                            <div class="text-center py-8">
                                <i class="bi bi-inbox text-5xl text-gray-400 mb-3"></i>
                                <p class="text-gray-500 dark:text-gray-400 text-sm">Nenhuma consulta agendada</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Área Principal: Atendimentos Ativos -->
            <div class="lg:col-span-2 space-y-6">
                <?php if($total_atendimentos === 0): ?>
                    <div class="glass-effect rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                        <i class="bi bi-emoji-smile text-6xl text-gray-400 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-700 dark:text-gray-300 mb-2">Nenhuma sessão ativa</h3>
                        <p class="text-gray-600 dark:text-gray-400">Inicie uma sessão para começar o atendimento psicológico.</p>
                        <p class="text-xs text-gray-500 mt-4">Debug: <?= $total_atendimentos ?> atendimentos encontrados</p>
                    </div>
                <?php endif; ?>
                
                <?php while($att = $attendances->fetch_assoc()): 
                    $idade = date_diff(date_create($att['birth_date']), date_create('today'))->y;
                    
                    // Buscar histórico de sessões do paciente
                    $patient_id_query = $mysqli->query("SELECT patient_id FROM appointments WHERE id = {$att['appointment_id']}");
                    $patient_data = $patient_id_query->fetch_assoc();
                    $patient_id = $patient_data['patient_id'];
                    
                    $historico = $mysqli->query("
                        SELECT ps.*, ps.session_date, a.scheduled_at 
                        FROM psychology_sessions ps
                        JOIN attendances att ON ps.attendance_id = att.id
                        JOIN appointments a ON att.appointment_id = a.id
                        WHERE a.patient_id = $patient_id
                        ORDER BY ps.session_date DESC
                        LIMIT 5
                    ");
                ?>
                <div class="glass-effect rounded-2xl shadow-xl border border-amber-200 dark:border-gray-700 overflow-hidden">
                    <div class="bg-gradient-to-r from-amber-500 to-orange-600 px-6 py-4">
                        <h2 class="text-xl font-bold text-white flex items-center justify-between">
                            <span><i class="bi bi-person-heart mr-3 text-2xl"></i>Sessão: <?= htmlspecialchars($att['patient_name']) ?></span>
                            <div class="flex items-center gap-3">
                                <a href="exportar_relatorio_psicologia.php?attendance_id=<?= $att['attendance_id'] ?>" 
                                   target="_blank"
                                   class="px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 text-sm font-normal">
                                    <i class="bi bi-file-earmark-pdf mr-1"></i>Exportar PDF
                                </a>
                                <span class="text-sm font-normal"><?= htmlspecialchars($att['patient_code']) ?> | <?= $idade ?> anos</span>
                            </div>
                        </h2>
                    </div>
                    <div class="p-6 space-y-6">
                        
                        <!-- Histórico de Sessões -->
                        <?php if ($historico && $historico->num_rows > 0): ?>
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 border-l-4 border-blue-500">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-3 flex items-center">
                                <i class="bi bi-clock-history mr-2 text-blue-600"></i>
                                Histórico de Sessões Anteriores
                            </h3>
                            <div class="space-y-3 max-h-60 overflow-y-auto">
                                <?php while($hist = $historico->fetch_assoc()): ?>
                                <div class="bg-white dark:bg-gray-800 rounded-lg p-3 shadow">
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            <i class="bi bi-calendar3 mr-1"></i>
                                            <?= date('d/m/Y H:i', strtotime($hist['session_date'])) ?>
                                        </span>
                                        <a href="exportar_relatorio_psicologia.php?attendance_id=<?= $att['attendance_id'] ?>" 
                                           target="_blank"
                                           class="px-3 py-1 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white text-xs rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                                            <i class="bi bi-file-earmark-pdf mr-1"></i>PDF
                                        </a>
                                    </div>
                                    <?php if ($hist['diagnosis']): ?>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                        <strong>Diagnóstico:</strong> <?= htmlspecialchars(substr($hist['diagnosis'], 0, 100)) ?><?= strlen($hist['diagnosis']) > 100 ? '...' : '' ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-500 dark:text-gray-500">
                                        <strong>Observações:</strong> <?= htmlspecialchars(substr($hist['observations'], 0, 80)) ?><?= strlen($hist['observations']) > 80 ? '...' : '' ?>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Registrar Sessão Psicológica -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                                <i class="bi bi-journal-medical mr-2 text-amber-600"></i>
                                Registrar Sessão
                            </h3>
                            <form method="post" class="space-y-4">
                                <input type="hidden" name="acao" value="criar_sessao">
                                <input type="hidden" name="attendance_id" value="<?= $att['attendance_id'] ?>">
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="bi bi-chat-left-text mr-1"></i>
                                        Observações da Sessão *
                                    </label>
                                    <textarea name="observacoes" rows="5" required 
                                              placeholder="Descreva o que foi observado durante a sessão, comportamentos, respostas emocionais, etc."
                                              class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-amber-500 focus:ring-2 focus:ring-amber-200 transition-all duration-300"></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="bi bi-clipboard-pulse mr-1"></i>
                                        Diagnóstico / Hipóteses
                                    </label>
                                    <textarea name="diagnostico" rows="3" 
                                              placeholder="Impressões diagnósticas, hipóteses clínicas ou avaliações preliminares"
                                              class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-amber-500 focus:ring-2 focus:ring-amber-200 transition-all duration-300"></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="bi bi-calendar-heart mr-1"></i>
                                        Plano de Tratamento
                                    </label>
                                    <textarea name="plano_tratamento" rows="3" 
                                              placeholder="Intervenções planejadas, objetivos terapêuticos, próximas etapas"
                                              class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-amber-500 focus:ring-2 focus:ring-amber-200 transition-all duration-300"></textarea>
                                </div>
                                
                                <button type="submit" 
                                        class="w-full px-6 py-3 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-semibold rounded-lg shadow-md hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                                    <i class="bi bi-save mr-2"></i>
                                    Salvar Registro da Sessão
                                </button>
                            </form>
                        </div>

                        <!-- Finalizar Atendimento -->
                        <div class="border-t-2 border-gray-200 dark:border-gray-700 pt-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                                <i class="bi bi-check-circle mr-2 text-emerald-600"></i>
                                Finalizar Atendimento
                            </h3>
                            <form method="post" class="space-y-4">
                                <input type="hidden" name="acao" value="finalizar_atendimento">
                                <input type="hidden" name="appointment_id" value="<?= $att['appointment_id'] ?>">
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="bi bi-sticky mr-1"></i>
                                        Observações Finais
                                    </label>
                                    <textarea name="notes" rows="3" 
                                              placeholder="Anotações finais sobre o atendimento"
                                              class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all duration-300"></textarea>
                                </div>
                                
                                <button type="submit" 
                                        class="w-full px-6 py-3 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white font-semibold rounded-lg shadow-md hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                                    <i class="bi bi-check-circle-fill mr-2"></i>
                                    Finalizar Atendimento
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

</body>
</html>
