<?php
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['user_name'] ?? '';
$user_id = $_SESSION['user_id'];

// Obter ID do profissional (psiquiatra) logado
$prof_query = $mysqli->query("SELECT id FROM professionals WHERE user_id = $user_id");
$professional = $prof_query->fetch_assoc();
$professional_id = $professional['id'] ?? null;

if (!$professional_id) {
    die('Erro: Profissional não encontrado para este usuário.');
}

// Processar ações
$erro = '';
$sucesso = '';

// CRIAR SESSÃO PSIQUIÁTRICA COMPLETA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar_sessao') {
    $attendance_id = (int)$_POST['attendance_id'];
    $observacoes = trim($_POST['observacoes']);
    $diagnostico = trim($_POST['diagnostico']);
    $mental_status = trim($_POST['mental_status']);
    $plano_tratamento = trim($_POST['plano_tratamento']);
    
    if ($observacoes) {
        $stmt = $mysqli->prepare("INSERT INTO psychiatry_sessions (attendance_id, observations, diagnosis, mental_status_exam, treatment_plan, session_date) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('issss', $attendance_id, $observacoes, $diagnostico, $mental_status, $plano_tratamento);
        
        if ($stmt->execute()) {
            $sucesso = 'Sessão psiquiátrica registrada com sucesso!';
        } else {
            $erro = 'Erro ao registrar sessão: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $erro = 'Observações são obrigatórias.';
    }
}

// CRIAR PRESCRIÇÃO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar_prescricao') {
    $attendance_id = (int)$_POST['attendance_id'];
    
    // Inserir prescrição
    $stmt = $mysqli->prepare('INSERT INTO prescriptions (attendance_id, created_at) VALUES (?, NOW())');
    $stmt->bind_param('i', $attendance_id);
    
    if ($stmt->execute()) {
        $prescription_id = $mysqli->insert_id;
        
        // Adicionar itens da prescrição
        if (!empty($_POST['medications']) && is_array($_POST['medications'])) {
            foreach ($_POST['medications'] as $index => $medication_id) {
                if (!empty($medication_id)) {
                    $dosage = $_POST['dosages'][$index] ?? '';
                    $instructions = $_POST['instructions'][$index] ?? '';
                    
                    $stmt2 = $mysqli->prepare('INSERT INTO prescription_items (prescription_id, medication_id, dosage, instructions) VALUES (?, ?, ?, ?)');
                    $stmt2->bind_param('iiss', $prescription_id, $medication_id, $dosage, $instructions);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }
        }
        
        $sucesso = 'Prescrição criada com sucesso!';
    } else {
        $erro = 'Erro ao criar prescrição: ' . $stmt->error;
    }
    $stmt->close();
}

// CRIAR PRESCRIÇÃO RÁPIDA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar_prescricao_rapida') {
    $appointment_id = (int)$_POST['appointment_id'];
    
    // Verificar se já existe atendimento
    $check = $mysqli->query("SELECT id FROM attendances WHERE appointment_id = $appointment_id");
    
    if ($check && $check->num_rows > 0) {
        $attendance = $check->fetch_assoc();
        $attendance_id = $attendance['id'];
    } else {
        // Criar atendimento
        $stmt = $mysqli->prepare('INSERT INTO attendances (appointment_id, notes) VALUES (?, "Prescrição psiquiátrica")');
        $stmt->bind_param('i', $appointment_id);
        $stmt->execute();
        $attendance_id = $mysqli->insert_id;
        $stmt->close();
        
        // Atualizar status da consulta
        $mysqli->query("UPDATE appointments SET consultation_status='em_atendimento' WHERE id=$appointment_id");
    }
    
    // Inserir prescrição
    $stmt = $mysqli->prepare('INSERT INTO prescriptions (attendance_id, created_at) VALUES (?, NOW())');
    $stmt->bind_param('i', $attendance_id);
    
    if ($stmt->execute()) {
        $prescription_id = $mysqli->insert_id;
        
        // Adicionar medicamentos
        if (!empty($_POST['medications']) && is_array($_POST['medications'])) {
            foreach ($_POST['medications'] as $index => $medication_id) {
                if (!empty($medication_id)) {
                    $dosage = $_POST['dosages'][$index] ?? '';
                    $instructions = $_POST['instructions'][$index] ?? '';
                    
                    $stmt2 = $mysqli->prepare('INSERT INTO prescription_items (prescription_id, medication_id, dosage, instructions) VALUES (?, ?, ?, ?)');
                    $stmt2->bind_param('iiss', $prescription_id, $medication_id, $dosage, $instructions);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }
        }
        
        $sucesso = 'Prescrição criada com sucesso!';
    } else {
        $erro = 'Erro ao criar prescrição: ' . $stmt->error;
    }
    $stmt->close();
}

// CRIAR DIAGNÓSTICO RÁPIDO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar_diagnostico_rapido') {
    $appointment_id = (int)$_POST['appointment_id'];
    $observacoes = trim($_POST['observacoes']);
    $diagnostico = trim($_POST['diagnostico']);
    $mental_status = trim($_POST['mental_status']);
    
    // Verificar se já existe atendimento
    $check = $mysqli->query("SELECT id FROM attendances WHERE appointment_id = $appointment_id");
    
    if ($check && $check->num_rows > 0) {
        $attendance = $check->fetch_assoc();
        $attendance_id = $attendance['id'];
    } else {
        // Criar atendimento
        $stmt = $mysqli->prepare('INSERT INTO attendances (appointment_id, notes) VALUES (?, "Consulta psiquiátrica")');
        $stmt->bind_param('i', $appointment_id);
        $stmt->execute();
        $attendance_id = $mysqli->insert_id;
        $stmt->close();
        
        // Atualizar status da consulta
        $mysqli->query("UPDATE appointments SET consultation_status='em_atendimento' WHERE id=$appointment_id");
    }
    
    // Criar sessão psiquiátrica
    $stmt = $mysqli->prepare("INSERT INTO psychiatry_sessions (attendance_id, observations, diagnosis, mental_status_exam, session_date) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param('isss', $attendance_id, $observacoes, $diagnostico, $mental_status);
    
    if ($stmt->execute()) {
        $sucesso = 'Diagnóstico registrado com sucesso!';
    } else {
        $erro = 'Erro ao registrar diagnóstico: ' . $stmt->error;
    }
    $stmt->close();
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
                $stmt2 = $mysqli->prepare('INSERT INTO consultation_history (appointment_id, professional_id, action_type, notes) VALUES (?, ?, "inicio_atendimento", "Atendimento psiquiátrico iniciado")');
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
        header('Location: psiquiatria.php');
        exit;
    }
}

// FINALIZAR ATENDIMENTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'finalizar_atendimento') {
    $appointment_id = (int)$_POST['appointment_id'];
    $notes = trim($_POST['notes'] ?? '');
    $attendance_id = (int)$_POST['attendance_id'];
    
    // Atualizar notas do atendimento
    $stmt = $mysqli->prepare('UPDATE attendances SET notes=? WHERE id=?');
    $stmt->bind_param('si', $notes, $attendance_id);
    $stmt->execute();
    $stmt->close();
    
    // Atualizar status da consulta
    $mysqli->query("UPDATE appointments SET consultation_status='concluido', concluded_by=$user_id, concluded_at=NOW() WHERE id=$appointment_id");
    
    // Registrar no histórico
    $stmt = $mysqli->prepare('INSERT INTO consultation_history (appointment_id, professional_id, action_type, notes) VALUES (?, ?, "conclusao", ?)');
    $stmt->bind_param('iis', $appointment_id, $professional_id, $notes);
    $stmt->execute();
    $stmt->close();
    
    $sucesso = 'Atendimento finalizado com sucesso!';
    header('Location: psiquiatria.php');
    exit;
}

// Buscar consultas agendadas
$appointments_query = "
    SELECT 
        a.id,
        a.scheduled_at,
        a.patient_id,
        a.consultation_status,
        p.name as patient_name,
        p.codigo as patient_code
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.professional_id = $professional_id
    AND a.consultation_status = 'agendado'
    ORDER BY a.scheduled_at ASC
";
$appointments = $mysqli->query($appointments_query);

// Buscar atendimentos ativos
$attendances_query = "
    SELECT 
        att.id as attendance_id,
        a.id as appointment_id,
        a.scheduled_at,
        a.patient_id,
        a.consultation_status,
        p.name as patient_name,
        p.codigo as patient_code
    FROM appointments a
    LEFT JOIN attendances att ON att.appointment_id = a.id
    JOIN patients p ON a.patient_id = p.id
    WHERE a.professional_id = $professional_id
    AND a.consultation_status IN ('agendado', 'em_atendimento', 'aguardando_exames')
    ORDER BY a.scheduled_at DESC
";
$attendances = $mysqli->query($attendances_query);
$total_atendimentos = $attendances->num_rows;

// Buscar lista de medicamentos
$medications = $mysqli->query("SELECT id, name FROM medications ORDER BY name");

?>
<!DOCTYPE html>
<html lang="pt-br" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Psiquiatria - Sistema Integrado Mais Saúde</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        secondary: '#4f46e5',
                    },
                },
            },
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .glass-effect { backdrop-filter: blur(10px); background: rgba(255, 255, 255, 0.9); }
        .dark .glass-effect { background: rgba(17, 24, 39, 0.9); }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen transition-colors duration-300">
    
    <!-- Navbar -->
    <nav class="glass-effect shadow-lg border-b border-indigo-200 dark:border-gray-700 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="dashboard.php" class="flex items-center space-x-3 group">
                    <div class="bg-gradient-to-br from-indigo-500 to-purple-600 p-2 rounded-xl shadow-lg group-hover:shadow-xl transition-all duration-300 group-hover:scale-110">
                        <i class="bi bi-brain text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 dark:from-indigo-400 dark:to-purple-400 bg-clip-text text-transparent">Mais Saúde</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Dashboard Psiquiatria</p>
                    </div>
                </a>
                <div class="flex items-center space-x-4">
                    <button onclick="toggleDarkMode()" class="p-2 rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-300">
                        <i class="bi bi-moon-stars dark:bi-sun text-gray-700 dark:text-gray-300"></i>
                    </button>
                    <div class="flex items-center space-x-3 bg-indigo-100 dark:bg-gray-700 px-4 py-2 rounded-full">
                        <i class="bi bi-person-circle text-indigo-600 dark:text-indigo-400 text-xl"></i>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Dr(a). <?php echo htmlspecialchars($user_name); ?></span>
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
        
        function addMedicationRow() {
            const container = document.getElementById('medications-container');
            const row = document.createElement('div');
            row.className = 'grid grid-cols-12 gap-3 items-end';
            row.innerHTML = `
                <div class="col-span-5">
                    <select name="medications[]" required class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-300 text-sm">
                        <option value="">Selecione...</option>
                        <?php 
                        $medications->data_seek(0);
                        while($med = $medications->fetch_assoc()): ?>
                            <option value="<?= $med['id'] ?>"><?= htmlspecialchars($med['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-span-3">
                    <input type="text" name="dosages[]" placeholder="Ex: 500mg" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-300 text-sm">
                </div>
                <div class="col-span-3">
                    <input type="text" name="instructions[]" placeholder="Ex: 8/8h" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-300 text-sm">
                </div>
                <div class="col-span-1">
                    <button type="button" onclick="this.parentElement.parentElement.remove()" class="w-full px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-all duration-300 text-sm">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(row);
        }
    </script>

    <!-- Container Principal -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <?php if ($erro): ?>
        <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 border-l-4 border-red-500 text-red-700 dark:text-red-200 rounded-lg">
            <div class="flex items-center">
                <i class="bi bi-exclamation-triangle-fill mr-3 text-xl"></i>
                <p class="font-medium"><?= htmlspecialchars($erro) ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($sucesso): ?>
        <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border-l-4 border-green-500 text-green-700 dark:text-green-200 rounded-lg">
            <div class="flex items-center">
                <i class="bi bi-check-circle-fill mr-3 text-xl"></i>
                <p class="font-medium"><?= htmlspecialchars($sucesso) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Coluna Esquerda: Consultas Agendadas -->
            <div class="lg:col-span-1 space-y-6">
                <div class="glass-effect rounded-2xl shadow-xl border border-indigo-200 dark:border-gray-700 overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4">
                        <h2 class="text-xl font-bold text-white flex items-center">
                            <i class="bi bi-calendar-check mr-3 text-2xl"></i>
                            Consultas Agendadas
                        </h2>
                    </div>
                    <div class="p-4 space-y-3 max-h-[600px] overflow-y-auto">
                        <?php while($apt = $appointments->fetch_assoc()): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-400 dark:hover:border-indigo-600 transition-all duration-300">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-bold text-gray-800 dark:text-white"><?= htmlspecialchars($apt['patient_name']) ?></h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Código: <?= htmlspecialchars($apt['patient_code']) ?></p>
                                </div>
                                <span class="px-2 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                    <?= htmlspecialchars($apt['status']) ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                <i class="bi bi-clock mr-1"></i>
                                <?= date('d/m/Y H:i', strtotime($apt['scheduled_at'])) ?>
                            </p>
                            <?php if($apt['status'] === 'agendado'): ?>
                            <!-- Opções de Ação -->
                            <div class="space-y-2">
                                <button type="button" onclick="toggleOptions<?= $apt['id'] ?>()" 
                                        class="w-full px-4 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 font-medium text-sm">
                                    <i class="bi bi-play-circle mr-2"></i>Iniciar Atendimento
                                    <i class="bi bi-chevron-down ml-2"></i>
                                </button>
                                
                                <!-- Menu de Opções -->
                                <div id="options<?= $apt['id'] ?>" style="display: none;" class="space-y-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <form method="post">
                                        <input type="hidden" name="acao" value="iniciar_atendimento">
                                        <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                        <button type="submit" class="w-full px-4 py-2 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white rounded-lg shadow-md transition-all duration-300 font-medium text-sm text-left">
                                            <i class="bi bi-clipboard-pulse mr-2"></i>Atendimento Completo
                                            <p class="text-xs opacity-90 mt-1">Diagnóstico + Prescrição + Avaliação Mental</p>
                                        </button>
                                    </form>
                                    
                                    <button type="button" onclick="openDiagnosisQuick<?= $apt['id'] ?>()" 
                                            class="w-full px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white rounded-lg shadow-md transition-all duration-300 font-medium text-sm text-left">
                                        <i class="bi bi-file-medical mr-2"></i>Fazer Diagnóstico
                                        <p class="text-xs opacity-90 mt-1">Avaliação psiquiátrica rápida</p>
                                    </button>
                                    
                                    <button type="button" onclick="openPrescriptionQuick<?= $apt['id'] ?>()" 
                                            class="w-full px-4 py-2 bg-gradient-to-r from-blue-500 to-cyan-600 hover:from-blue-600 hover:to-cyan-700 text-white rounded-lg shadow-md transition-all duration-300 font-medium text-sm text-left">
                                        <i class="bi bi-prescription2 mr-2"></i>Apenas Prescrição
                                        <p class="text-xs opacity-90 mt-1">Receita de medicamento controlado</p>
                                    </button>
                                </div>
                            </div>
                            
                            <script>
                                function toggleOptions<?= $apt['id'] ?>() {
                                    const menu = document.getElementById('options<?= $apt['id'] ?>');
                                    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
                                }
                                
                                function openDiagnosisQuick<?= $apt['id'] ?>() {
                                    document.getElementById('quickModal<?= $apt['id'] ?>').style.display = 'flex';
                                    document.getElementById('diagnosisTab<?= $apt['id'] ?>').style.display = 'block';
                                    document.getElementById('prescriptionTab<?= $apt['id'] ?>').style.display = 'none';
                                }
                                
                                function openPrescriptionQuick<?= $apt['id'] ?>() {
                                    document.getElementById('quickModal<?= $apt['id'] ?>').style.display = 'flex';
                                    document.getElementById('diagnosisTab<?= $apt['id'] ?>').style.display = 'none';
                                    document.getElementById('prescriptionTab<?= $apt['id'] ?>').style.display = 'block';
                                }
                                
                                function closeModal<?= $apt['id'] ?>() {
                                    document.getElementById('quickModal<?= $apt['id'] ?>').style.display = 'none';
                                }
                            </script>
                            
                            <!-- Modal Rápido -->
                            <div id="quickModal<?= $apt['id'] ?>" style="display: none;" 
                                 class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" 
                                 onclick="if(event.target === this) closeModal<?= $apt['id'] ?>()">
                                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
                                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4 flex justify-between items-center sticky top-0">
                                        <h3 class="text-xl font-bold text-white">
                                            <?= htmlspecialchars($apt['patient_name']) ?>
                                        </h3>
                                        <button onclick="closeModal<?= $apt['id'] ?>()" class="text-white hover:text-gray-200 text-2xl">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="p-6">
                                        <!-- Diagnóstico Rápido -->
                                        <div id="diagnosisTab<?= $apt['id'] ?>" style="display: none;">
                                            <h4 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                                                <i class="bi bi-file-medical mr-2 text-purple-600"></i>
                                                Avaliação Psiquiátrica
                                            </h4>
                                            <form method="post" onsubmit="return confirm('Confirma o registro deste diagnóstico?')">
                                                <input type="hidden" name="acao" value="criar_diagnostico_rapido">
                                                <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                                
                                                <div class="space-y-4">
                                                    <div>
                                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                                            <i class="bi bi-chat-left-text mr-1"></i>
                                                            Observações da Consulta *
                                                        </label>
                                                        <textarea name="observacoes" required rows="4" 
                                                                  class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                                                  placeholder="Descreva as observações da consulta..."></textarea>
                                                    </div>
                                                    
                                                    <div>
                                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                                            <i class="bi bi-clipboard2-pulse mr-1"></i>
                                                            Diagnóstico/CID
                                                        </label>
                                                        <textarea name="diagnostico" rows="3" 
                                                                  class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                                                  placeholder="Ex: F32.1 - Episódio depressivo moderado"></textarea>
                                                    </div>
                                                    
                                                    <div>
                                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                                            <i class="bi bi-emoji-neutral mr-1"></i>
                                                            Exame do Estado Mental
                                                        </label>
                                                        <textarea name="mental_status" rows="3" 
                                                                  class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                                                  placeholder="Consciência, orientação, humor, afeto, pensamento, percepção..."></textarea>
                                                    </div>
                                                </div>
                                                
                                                <button type="submit" class="w-full mt-6 px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white rounded-lg font-semibold shadow-lg hover:shadow-xl transition-all">
                                                    <i class="bi bi-check-circle mr-2"></i>Registrar Diagnóstico
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <!-- Prescrição Rápida -->
                                        <div id="prescriptionTab<?= $apt['id'] ?>" style="display: none;">
                                            <h4 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                                                <i class="bi bi-prescription2 mr-2 text-blue-600"></i>
                                                Prescrição Medicamentosa
                                            </h4>
                                            <form method="post" onsubmit="return confirm('Confirma a criação desta prescrição?')">
                                                <input type="hidden" name="acao" value="criar_prescricao_rapida">
                                                <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                                
                                                <div id="quickMeds<?= $apt['id'] ?>" class="space-y-3 mb-4">
                                                    <div class="grid grid-cols-12 gap-2">
                                                        <div class="col-span-6">
                                                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Medicamento</label>
                                                            <select name="medications[]" required class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
                                                                <option value="">Selecione...</option>
                                                                <?php $medications->data_seek(0); while($med = $medications->fetch_assoc()): ?>
                                                                    <option value="<?= $med['id'] ?>"><?= htmlspecialchars($med['name']) ?></option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-span-3">
                                                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Dosagem</label>
                                                            <input type="text" name="dosages[]" placeholder="Ex: 10mg" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
                                                        </div>
                                                        <div class="col-span-3">
                                                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Posologia</label>
                                                            <input type="text" name="instructions[]" placeholder="1x/dia" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <button type="button" onclick="addQuickMed<?= $apt['id'] ?>()" class="mb-4 px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-lg text-sm font-medium">
                                                    <i class="bi bi-plus-lg mr-1"></i>Adicionar Medicamento
                                                </button>
                                                
                                                <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-cyan-600 hover:from-blue-600 hover:to-cyan-700 text-white rounded-lg font-semibold shadow-lg hover:shadow-xl transition-all">
                                                    <i class="bi bi-check-circle mr-2"></i>Criar Prescrição
                                                </button>
                                            </form>
                                            
                                            <script>
                                                function addQuickMed<?= $apt['id'] ?>() {
                                                    const container = document.getElementById('quickMeds<?= $apt['id'] ?>');
                                                    const row = document.createElement('div');
                                                    row.className = 'grid grid-cols-12 gap-2';
                                                    row.innerHTML = `
                                                        <div class="col-span-6">
                                                            <select name="medications[]" required class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
                                                                <option value="">Medicamento...</option>
                                                                <?php $medications->data_seek(0); while($med = $medications->fetch_assoc()): ?>
                                                                    <option value="<?= $med['id'] ?>"><?= htmlspecialchars($med['name']) ?></option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-span-2">
                                                            <input type="text" name="dosages[]" placeholder="Dosagem" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
                                                        </div>
                                                        <div class="col-span-3">
                                                            <input type="text" name="instructions[]" placeholder="Posologia" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
                                                        </div>
                                                        <div class="col-span-1">
                                                            <button type="button" onclick="this.parentElement.parentElement.remove()" class="w-full px-2 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    `;
                                                    container.appendChild(row);
                                                }
                                            </script>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Coluna Direita: Atendimentos Ativos -->
            <div class="lg:col-span-2 space-y-6">
                <?php if($total_atendimentos === 0): ?>
                    <div class="glass-effect rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
                        <i class="bi bi-inbox text-6xl text-gray-400 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-700 dark:text-gray-300 mb-2">Nenhum atendimento ativo</h3>
                        <p class="text-gray-600 dark:text-gray-400">Inicie um atendimento para registrar diagnósticos e prescrições.</p>
                    </div>
                <?php endif; ?>
                
                <?php while($att = $attendances->fetch_assoc()): ?>
                <div class="glass-effect rounded-2xl shadow-xl border border-indigo-200 dark:border-gray-700 overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4">
                        <h2 class="text-xl font-bold text-white flex items-center justify-between">
                            <span><i class="bi bi-person-check mr-3 text-2xl"></i>Atendimento: <?= htmlspecialchars($att['patient_name']) ?></span>
                            <div class="flex items-center gap-3">
                                <a href="exportar_relatorio_psiquiatria.php?attendance_id=<?= $att['attendance_id'] ?>" 
                                   target="_blank"
                                   class="px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 text-sm font-normal">
                                    <i class="bi bi-file-earmark-pdf mr-1"></i>Exportar PDF
                                </a>
                                <span class="text-sm font-normal"><?= htmlspecialchars($att['patient_code']) ?></span>
                            </div>
                        </h2>
                    </div>
                    <div class="p-6">
                        
                        <!-- Avaliação Psiquiátrica -->
                        <div class="mb-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                                <i class="bi bi-file-medical mr-2 text-purple-600"></i>
                                Avaliação Psiquiátrica
                            </h3>
                            <form method="post" class="space-y-4">
                                <input type="hidden" name="acao" value="criar_sessao">
                                <input type="hidden" name="attendance_id" value="<?= $att['attendance_id'] ?>">
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="bi bi-chat-left-text mr-1"></i>
                                        Observações da Consulta *
                                    </label>
                                    <textarea name="observacoes" required rows="3" 
                                              class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                              placeholder="Descreva as observações da consulta..."></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="bi bi-clipboard2-pulse mr-1"></i>
                                        Diagnóstico/CID
                                    </label>
                                    <textarea name="diagnostico" rows="2" 
                                              class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                              placeholder="Ex: F32.1 - Episódio depressivo moderado"></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="bi bi-emoji-neutral mr-1"></i>
                                        Exame do Estado Mental
                                    </label>
                                    <textarea name="mental_status" rows="3" 
                                              class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                              placeholder="Consciência, orientação, humor, afeto, pensamento, percepção, memória, atenção..."></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="bi bi-heart-pulse mr-1"></i>
                                        Plano de Tratamento
                                    </label>
                                    <textarea name="plano_tratamento" rows="3" 
                                              class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                              placeholder="Descreva o plano terapêutico proposto..."></textarea>
                                </div>
                                
                                <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 font-semibold">
                                    <i class="bi bi-save mr-2"></i>Registrar Avaliação
                                </button>
                            </form>
                        </div>

                        <hr class="border-gray-300 dark:border-gray-700 my-6">

                        <!-- Prescrever Medicamentos -->
                        <div class="mb-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                                <i class="bi bi-capsule-pill mr-2 text-blue-600"></i>
                                Prescrever Medicamentos
                            </h3>
                            <form method="post" class="space-y-4">
                                <input type="hidden" name="acao" value="criar_prescricao">
                                <input type="hidden" name="attendance_id" value="<?= $att['attendance_id'] ?>">
                                
                                <div id="medications-container" class="space-y-3">
                                    <div class="grid grid-cols-12 gap-3 items-end">
                                        <div class="col-span-5">
                                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Medicamento</label>
                                            <select name="medications[]" required class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-300 text-sm">
                                                <option value="">Selecione...</option>
                                                <?php 
                                                $medications->data_seek(0);
                                                while($med = $medications->fetch_assoc()): ?>
                                                    <option value="<?= $med['id'] ?>"><?= htmlspecialchars($med['name']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-span-3">
                                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Dosagem</label>
                                            <input type="text" name="dosages[]" placeholder="Ex: 10mg" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-300 text-sm">
                                        </div>
                                        <div class="col-span-3">
                                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Posologia</label>
                                            <input type="text" name="instructions[]" placeholder="1x/dia" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-300 text-sm">
                                        </div>
                                        <div class="col-span-1">
                                            <button type="button" onclick="addMedicationRow()" class="w-full px-3 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg transition-all duration-300 text-sm">
                                                <i class="bi bi-plus-lg"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-cyan-600 hover:from-blue-600 hover:to-cyan-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 font-semibold">
                                    <i class="bi bi-prescription2 mr-2"></i>Criar Prescrição
                                </button>
                            </form>
                        </div>

                        <hr class="border-gray-300 dark:border-gray-700 my-6">

                        <!-- Histórico de Sessões -->
                        <?php
                        $sessions_history = $mysqli->query("
                            SELECT ps.*, ps.session_date
                            FROM psychiatry_sessions ps
                            WHERE ps.attendance_id = {$att['attendance_id']}
                            ORDER BY ps.session_date DESC
                        ");
                        
                        if ($sessions_history && $sessions_history->num_rows > 0):
                        ?>
                        <div class="mb-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                                <i class="bi bi-clock-history mr-2 text-blue-600"></i>
                                Histórico de Sessões Registradas
                            </h3>
                            <div class="space-y-3">
                                <?php while($sess = $sessions_history->fetch_assoc()): ?>
                                <div class="bg-blue-50 dark:bg-gray-700 rounded-lg p-4 border-l-4 border-blue-500">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <span class="text-sm font-semibold text-blue-800 dark:text-blue-300">
                                                <i class="bi bi-calendar-check mr-1"></i>
                                                <?= date('d/m/Y H:i', strtotime($sess['session_date'])) ?>
                                            </span>
                                        </div>
                                        <a href="exportar_relatorio_psiquiatria.php?attendance_id=<?= $att['attendance_id'] ?>" 
                                           target="_blank"
                                           class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 text-sm font-semibold">
                                            <i class="bi bi-file-earmark-pdf mr-2"></i>Exportar PDF
                                        </a>
                                    </div>
                                    <div class="text-sm text-gray-700 dark:text-gray-300 mt-3">
                                        <p class="font-semibold mb-1"><i class="bi bi-chat-left-text mr-1"></i>Observações:</p>
                                        <p class="ml-5 text-gray-600 dark:text-gray-400"><?= nl2br(htmlspecialchars(substr($sess['observations'], 0, 200))) ?><?= strlen($sess['observations']) > 200 ? '...' : '' ?></p>
                                        
                                        <?php if (!empty($sess['diagnosis'])): ?>
                                        <p class="font-semibold mt-2 mb-1"><i class="bi bi-clipboard2-pulse mr-1"></i>Diagnóstico:</p>
                                        <p class="ml-5 text-gray-600 dark:text-gray-400"><?= htmlspecialchars($sess['diagnosis']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        <hr class="border-gray-300 dark:border-gray-700 my-6">
                        <?php endif; ?>

                        <!-- Finalizar Atendimento -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                                <i class="bi bi-journal-medical mr-2 text-green-600"></i>
                                Observações Gerais e Finalizar
                            </h3>
                            <form method="post">
                                <input type="hidden" name="acao" value="finalizar_atendimento">
                                <input type="hidden" name="attendance_id" value="<?= $att['attendance_id'] ?>">
                                <input type="hidden" name="appointment_id" value="<?= $att['appointment_id'] ?>">
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="bi bi-sticky mr-1"></i>
                                        Anotações Adicionais
                                    </label>
                                    <textarea name="notes" rows="3" 
                                              class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-green-500 focus:ring-2 focus:ring-green-200 transition-all duration-300"
                                              placeholder="Observações gerais sobre a consulta..."></textarea>
                                </div>
                                
                                <button type="submit" 
                                        onclick="return confirm('Deseja finalizar este atendimento?')"
                                        class="w-full px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 font-semibold">
                                    <i class="bi bi-check-circle mr-2"></i>Finalizar Atendimento
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
