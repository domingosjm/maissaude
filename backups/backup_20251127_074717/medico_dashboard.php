<?php
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['user_name'] ?? '';
$user_id = $_SESSION['user_id'];

// Obter ID do profissional (médico) logado
$prof_query = $mysqli->query("SELECT id FROM professionals WHERE user_id = $user_id");
$professional = $prof_query->fetch_assoc();
$professional_id = $professional['id'] ?? null;

if (!$professional_id) {
    die('Erro: Profissional não encontrado para este usuário.');
}

// Processar ações
$erro = '';
$sucesso = '';

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

// SOLICITAR EXAME(S)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'solicitar_exame') {
    $attendance_id = (int)$_POST['attendance_id'];
    $exam_ids = $_POST['exam_ids'] ?? [];
    
    if (empty($exam_ids)) {
        $erro = 'Selecione pelo menos um exame!';
    } else {
        $contador = 0;
        $stmt = $mysqli->prepare('INSERT INTO exam_requests (attendance_id, exam_id) VALUES (?, ?)');
        
        foreach ($exam_ids as $exam_id) {
            $exam_id = (int)$exam_id;
            if ($exam_id > 0) {
                $stmt->bind_param('ii', $attendance_id, $exam_id);
                if ($stmt->execute()) {
                    $contador++;
                }
            }
        }
        
        $stmt->close();
        
        if ($contador > 0) {
            $sucesso = $contador == 1 ? 'Exame solicitado com sucesso!' : "$contador exames solicitados com sucesso!";
        } else {
            $erro = 'Erro ao solicitar exames!';
        }
    }
}

// CRIAR PRESCRIÇÃO RÁPIDA (sem iniciar atendimento completo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar_prescricao_rapida') {
    $appointment_id = (int)$_POST['appointment_id'];
    
    // Verificar se já existe atendimento
    $check = $mysqli->query("SELECT id FROM attendances WHERE appointment_id = $appointment_id");
    
    if ($check && $check->num_rows > 0) {
        $attendance = $check->fetch_assoc();
        $attendance_id = $attendance['id'];
    } else {
        // Criar atendimento
        $stmt = $mysqli->prepare('INSERT INTO attendances (appointment_id, notes) VALUES (?, "Prescrição rápida")');
        $stmt->bind_param('i', $appointment_id);
        $stmt->execute();
        $attendance_id = $mysqli->insert_id;
        $stmt->close();
        
        // Atualizar consultation_status da consulta
        $mysqli->query("UPDATE appointments SET consultation_status='em_atendimento' WHERE id=$appointment_id");
    }
    
    // Criar prescrição
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

// SOLICITAR EXAMES RÁPIDO (sem iniciar atendimento completo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'solicitar_exames_rapido') {
    $appointment_id = (int)$_POST['appointment_id'];
    $exam_ids = $_POST['exam_ids'] ?? [];
    
    if (!empty($exam_ids)) {
        // Verificar se já existe atendimento
        $check = $mysqli->query("SELECT id FROM attendances WHERE appointment_id = $appointment_id");
        
        if ($check && $check->num_rows > 0) {
            $attendance = $check->fetch_assoc();
            $attendance_id = $attendance['id'];
        } else {
            // Criar atendimento
            $stmt = $mysqli->prepare('INSERT INTO attendances (appointment_id, notes) VALUES (?, "Solicitação de exames")');
            $stmt->bind_param('i', $appointment_id);
            $stmt->execute();
            $attendance_id = $mysqli->insert_id;
            $stmt->close();
            
            // Atualizar consultation_status da consulta para aguardando exames
            $mysqli->query("UPDATE appointments SET consultation_status='aguardando_exames' WHERE id=$appointment_id");
        }
        
        // Inserir solicitações de exames
        $stmt = $mysqli->prepare('INSERT INTO exam_requests (attendance_id, exam_id) VALUES (?, ?)');
        $count = 0;
        foreach ($exam_ids as $exam_id) {
            $exam_id = (int)$exam_id;
            $stmt->bind_param('ii', $attendance_id, $exam_id);
            if ($stmt->execute()) {
                $count++;
            }
        }
        $stmt->close();
        
        $sucesso = "$count exame(s) solicitado(s) com sucesso!";
    } else {
        $erro = 'Selecione pelo menos um exame.';
    }
}

// INICIAR ATENDIMENTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'iniciar_atendimento') {
    $appointment_id = (int)$_POST['appointment_id'];
    
    // Verificar se já existe um atendimento para esta consulta
    $check = $mysqli->query("SELECT id FROM attendances WHERE appointment_id = $appointment_id");
    
    if ($check && $check->num_rows > 0) {
        // Já existe atendimento, apenas atualizar consultation_status
        $mysqli->query("UPDATE appointments SET consultation_status='em_atendimento' WHERE id=$appointment_id");
        $sucesso = 'Atendimento retomado!';
    } else {
        // Atualizar consultation_status da consulta
        if ($mysqli->query("UPDATE appointments SET consultation_status='em_atendimento' WHERE id=$appointment_id")) {
            // Criar atendimento
            $stmt = $mysqli->prepare('INSERT INTO attendances (appointment_id, notes) VALUES (?, "")');
            $stmt->bind_param('i', $appointment_id);
            
            if ($stmt->execute()) {
                // Registrar no histórico de consultas
                $attendance_id = $mysqli->insert_id;
                $mysqli->query("INSERT INTO consultation_history (appointment_id, professional_id, action_type, description) 
                                VALUES ($appointment_id, $professional_id, 'observacao', 'Atendimento iniciado')");
                
                $sucesso = 'Atendimento iniciado com sucesso! Agora você pode prescrever medicamentos ou solicitar exames.';
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
        header('Location: medico_dashboard.php');
        exit;
    }
}

// FINALIZAR ATENDIMENTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'finalizar_atendimento') {
    $appointment_id = (int)$_POST['appointment_id'];
    $notes = trim($_POST['notes'] ?? '');
    $attendance_id = (int)($_POST['attendance_id'] ?? 0);
    
    // Atualizar notas do atendimento se existir
    if ($attendance_id > 0) {
        $stmt = $mysqli->prepare('UPDATE attendances SET notes=? WHERE id=?');
        $stmt->bind_param('si', $notes, $attendance_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Atualizar consultation_status da consulta para concluída
    $stmt2 = $mysqli->prepare("UPDATE appointments SET consultation_status='concluido', concluded_by=?, concluded_at=NOW(), notes=? WHERE id=?");
    $stmt2->bind_param('isi', $user_id, $notes, $appointment_id);
    $stmt2->execute();
    $stmt2->close();
    
    // Registrar no histórico
    $mysqli->query("INSERT INTO consultation_history (appointment_id, professional_id, action_type, description) 
                    VALUES ($appointment_id, $professional_id, 'observacao', 'Atendimento finalizado')");
    
    $sucesso = 'Atendimento finalizado com sucesso!';
}

// Buscar consultas agendadas para o médico
$appointments = $mysqli->query("
    SELECT 
        a.id, 
        a.scheduled_at, 
        a.status,
        p.name as patient_name,
        p.codigo as patient_code,
        p.birth_date
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.professional_id = $professional_id
    ORDER BY a.scheduled_at DESC
    LIMIT 20
");

// Buscar atendimentos ativos (usando consultation_status)
$attendances_query = "
    SELECT 
        att.id as attendance_id,
        att.notes,
        a.id as appointment_id,
        a.scheduled_at,
        a.patient_id,
        a.consultation_status,
        a.notes as appointment_notes,
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

if (!$attendances) {
    die("Erro na query de atendimentos: " . $mysqli->error);
}

$total_atendimentos_medico = $attendances->num_rows;

// Buscar lista de medicamentos
$medications = $mysqli->query("SELECT id, name FROM medications ORDER BY name");

// Buscar lista de exames
$exams = $mysqli->query("SELECT id, name FROM exams ORDER BY name");

?>
<!DOCTYPE html>
<html lang="pt-br" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Médico - Sistema Integrado Mais Saúde</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#1d4ed8',
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
<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen transition-colors duration-300">
    
    <!-- Navbar -->
    <nav class="glass-effect shadow-lg border-b border-blue-200 dark:border-gray-700 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="dashboard.php" class="flex items-center space-x-3 group">
                    <div class="bg-gradient-to-br from-blue-500 to-indigo-600 p-2 rounded-xl shadow-lg group-hover:shadow-xl transition-all duration-300 group-hover:scale-110">
                        <i class="bi bi-clipboard-pulse text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-800 dark:text-white">Dashboard Médico</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Sistema Integrado Mais Saúde</p>
                    </div>
                </a>
                <div class="flex items-center space-x-4">
                    <button onclick="toggleDarkMode()" class="p-2 rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-300">
                        <i class="bi bi-moon-stars dark:hidden text-gray-700"></i>
                        <i class="bi bi-sun hidden dark:inline text-gray-300"></i>
                    </button>
                    <div class="flex items-center space-x-3 bg-blue-100 dark:bg-gray-700 px-4 py-2 rounded-full">
                        <i class="bi bi-person-circle text-blue-600 dark:text-blue-400 text-xl"></i>
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
                    <select name="medications[]" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-300 text-sm">
                        <option value="">Selecione...</option>
                        <?php 
                        $medications->data_seek(0);
                        while($med = $medications->fetch_assoc()): ?>
                            <option value="<?= $med['id'] ?>"><?= htmlspecialchars($med['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-span-3">
                    <input type="text" name="dosages[]" placeholder="Dosagem" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-300 text-sm">
                </div>
                <div class="col-span-3">
                    <input type="text" name="instructions[]" placeholder="Instruções" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-300 text-sm">
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
                <div class="glass-effect rounded-2xl shadow-xl border border-blue-200 dark:border-gray-700 overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4">
                        <h2 class="text-xl font-bold text-white flex items-center">
                            <i class="bi bi-calendar-check mr-3 text-2xl"></i>
                            Consultas Agendadas
                        </h2>
                    </div>
                    <div class="p-4 space-y-3 max-h-[600px] overflow-y-auto">
                        <?php while($apt = $appointments->fetch_assoc()): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border-2 border-gray-200 dark:border-gray-700 hover:border-blue-400 dark:hover:border-blue-600 transition-all duration-300">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-bold text-gray-800 dark:text-white"><?= htmlspecialchars($apt['patient_name']) ?></h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Código: <?= htmlspecialchars($apt['patient_code']) ?></p>
                                </div>
                                <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $apt['status'] === 'agendado' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' ?>">
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
                                        class="w-full px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 font-medium text-sm">
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
                                            <p class="text-xs opacity-90 mt-1">Prescrição + Exames + Diagnóstico</p>
                                        </button>
                                    </form>
                                    
                                    <button type="button" onclick="openPrescriptionQuick<?= $apt['id'] ?>()" 
                                            class="w-full px-4 py-2 bg-gradient-to-r from-blue-500 to-cyan-600 hover:from-blue-600 hover:to-cyan-700 text-white rounded-lg shadow-md transition-all duration-300 font-medium text-sm text-left">
                                        <i class="bi bi-prescription2 mr-2"></i>Apenas Prescrição
                                        <p class="text-xs opacity-90 mt-1">Receita médica rápida</p>
                                    </button>
                                    
                                    <button type="button" onclick="openExamQuick<?= $apt['id'] ?>()" 
                                            class="w-full px-4 py-2 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700 text-white rounded-lg shadow-md transition-all duration-300 font-medium text-sm text-left">
                                        <i class="bi bi-flask mr-2"></i>Solicitar Exames
                                        <p class="text-xs opacity-90 mt-1">Pedido de exames laboratoriais</p>
                                    </button>
                                </div>
                            </div>
                            
                            <script>
                                function toggleOptions<?= $apt['id'] ?>() {
                                    const menu = document.getElementById('options<?= $apt['id'] ?>');
                                    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
                                }
                                
                                function openPrescriptionQuick<?= $apt['id'] ?>() {
                                    document.getElementById('quickModal<?= $apt['id'] ?>').style.display = 'flex';
                                    document.getElementById('prescriptionTab<?= $apt['id'] ?>').style.display = 'block';
                                    document.getElementById('examTab<?= $apt['id'] ?>').style.display = 'none';
                                }
                                
                                function openExamQuick<?= $apt['id'] ?>() {
                                    document.getElementById('quickModal<?= $apt['id'] ?>').style.display = 'flex';
                                    document.getElementById('prescriptionTab<?= $apt['id'] ?>').style.display = 'none';
                                    document.getElementById('examTab<?= $apt['id'] ?>').style.display = 'block';
                                }
                                
                                function closeModal<?= $apt['id'] ?>() {
                                    document.getElementById('quickModal<?= $apt['id'] ?>').style.display = 'none';
                                }
                            </script>
                            
                            <!-- Modal Rápido -->
                            <div id="quickModal<?= $apt['id'] ?>" style="display: none;" 
                                 class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" 
                                 onclick="if(event.target === this) closeModal<?= $apt['id'] ?>()">
                                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                                    <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4 flex justify-between items-center">
                                        <h3 class="text-xl font-bold text-white">
                                            <?= htmlspecialchars($apt['patient_name']) ?>
                                        </h3>
                                        <button onclick="closeModal<?= $apt['id'] ?>()" class="text-white hover:text-gray-200 text-2xl">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="p-6">
                                        <!-- Prescrição Rápida -->
                                        <div id="prescriptionTab<?= $apt['id'] ?>" style="display: none;">
                                            <h4 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                                                <i class="bi bi-prescription2 mr-2 text-blue-600"></i>
                                                Prescrição Médica
                                            </h4>
                                            <form method="post" onsubmit="return confirmQuickAction<?= $apt['id'] ?>('prescrição')">
                                                <input type="hidden" name="acao" value="criar_prescricao_rapida">
                                                <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                                
                                                <div id="quickMeds<?= $apt['id'] ?>" class="space-y-3 mb-4">
                                                    <div class="grid grid-cols-12 gap-2">
                                                        <div class="col-span-6">
                                                            <select name="medications[]" required class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
                                                                <option value="">Medicamento...</option>
                                                                <?php $medications->data_seek(0); while($med = $medications->fetch_assoc()): ?>
                                                                    <option value="<?= $med['id'] ?>"><?= htmlspecialchars($med['name']) ?></option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-span-3">
                                                            <input type="text" name="dosages[]" placeholder="Dosagem" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
                                                        </div>
                                                        <div class="col-span-3">
                                                            <input type="text" name="instructions[]" placeholder="Ex: 8/8h" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <button type="button" onclick="addQuickMed<?= $apt['id'] ?>()" class="mb-4 px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-lg text-sm">
                                                    <i class="bi bi-plus-lg mr-1"></i>Adicionar Medicamento
                                                </button>
                                                
                                                <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white rounded-lg font-semibold">
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
                                                        <div class="col-span-3">
                                                            <input type="text" name="dosages[]" placeholder="Dosagem" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
                                                        </div>
                                                        <div class="col-span-2">
                                                            <input type="text" name="instructions[]" placeholder="Ex: 8/8h" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
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
                                        
                                        <!-- Solicitação de Exames Rápida -->
                                        <div id="examTab<?= $apt['id'] ?>" style="display: none;">
                                            <h4 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                                                <i class="bi bi-flask mr-2 text-purple-600"></i>
                                                Solicitação de Exames
                                            </h4>
                                            <form method="post" onsubmit="return confirmQuickAction<?= $apt['id'] ?>('exames')">
                                                <input type="hidden" name="acao" value="solicitar_exames_rapido">
                                                <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                                
                                                <div class="space-y-3 mb-4">
                                                    <?php $exams->data_seek(0); while($exam = $exams->fetch_assoc()): ?>
                                                    <label class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-purple-50 dark:hover:bg-purple-900/20 cursor-pointer">
                                                        <input type="checkbox" name="exam_ids[]" value="<?= $exam['id'] ?>" class="w-5 h-5 text-purple-600 rounded">
                                                        <span class="text-gray-800 dark:text-white font-medium"><?= htmlspecialchars($exam['name']) ?></span>
                                                    </label>
                                                    <?php endwhile; ?>
                                                </div>
                                                
                                                <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700 text-white rounded-lg font-semibold">
                                                    <i class="bi bi-check-circle mr-2"></i>Solicitar Exames Selecionados
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <script>
                                function confirmQuickAction<?= $apt['id'] ?>(tipo) {
                                    return confirm('Confirma a criação desta ' + tipo + '? O atendimento será automaticamente iniciado.');
                                }
                            </script>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Coluna Direita: Atendimentos Ativos -->
            <div class="lg:col-span-2 space-y-6">
                <?php if($total_atendimentos_medico === 0): ?>
                    <div class="glass-effect rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
                        <i class="bi bi-inbox text-6xl text-gray-400 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-700 dark:text-gray-300 mb-2">Nenhum atendimento ativo</h3>
                        <p class="text-gray-600 dark:text-gray-400">Inicie um atendimento para começar a prescrever medicamentos ou solicitar exames.</p>
                        <p class="text-xs text-gray-500 mt-4">Debug: <?= $total_atendimentos_medico ?> atendimentos encontrados | Prof ID: <?= $professional_id ?></p>
                    </div>
                <?php endif; ?>
                
                <?php while($att = $attendances->fetch_assoc()): ?>
                <div class="glass-effect rounded-2xl shadow-xl border border-blue-200 dark:border-gray-700 overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-6 py-4">
                        <h2 class="text-xl font-bold text-white flex items-center justify-between">
                            <span><i class="bi bi-person-check mr-3 text-2xl"></i>Atendimento: <?= htmlspecialchars($att['patient_name']) ?></span>
                            <span class="text-sm font-normal"><?= htmlspecialchars($att['patient_code']) ?></span>
                        </h2>
                    </div>
                    <div class="p-6">
                        
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
                                            <select name="medications[]" required class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-300 text-sm">
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
                                            <input type="text" name="dosages[]" placeholder="Ex: 500mg" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-300 text-sm">
                                        </div>
                                        <div class="col-span-3">
                                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Instruções</label>
                                            <input type="text" name="instructions[]" placeholder="Ex: 8/8h" class="w-full px-3 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-300 text-sm">
                                        </div>
                                        <div class="col-span-1">
                                            <button type="button" onclick="addMedicationRow()" class="w-full px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-all duration-300 text-sm">
                                                <i class="bi bi-plus-lg"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 font-semibold">
                                    <i class="bi bi-prescription2 mr-2"></i>Criar Prescrição
                                </button>
                            </form>
                        </div>

                        <hr class="border-gray-300 dark:border-gray-700 my-6">

                        <!-- Solicitar Exames -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                                <i class="bi bi-flask mr-2 text-purple-600"></i>
                                Solicitar Exames
                            </h3>
                            <form method="post" id="form-exames-<?= $att['attendance_id'] ?>">
                                <input type="hidden" name="acao" value="solicitar_exame">
                                <input type="hidden" name="attendance_id" value="<?= $att['attendance_id'] ?>">
                                
                                <!-- Campo de Busca -->
                                <div class="mb-4">
                                    <div class="relative">
                                        <i class="bi bi-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                        <input type="text" 
                                               id="search-exam-<?= $att['attendance_id'] ?>" 
                                               placeholder="Digite para buscar exames (hemograma, perfil renal, etc)..." 
                                               class="w-full pl-12 pr-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-purple-500 focus:ring-2 focus:ring-purple-200 transition-all duration-300">
                                    </div>
                                </div>

                                <!-- Lista de Exames com Checkboxes -->
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-4 max-h-96 overflow-y-auto border-2 border-gray-200 dark:border-gray-700" id="exams-list-<?= $att['attendance_id'] ?>">
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        <?php 
                                        $exams->data_seek(0);
                                        while($exam = $exams->fetch_assoc()): ?>
                                            <label class="exam-item flex items-start p-3 rounded-lg hover:bg-white dark:hover:bg-gray-700 cursor-pointer transition-all duration-200 border border-transparent hover:border-purple-300" data-exam-name="<?= strtolower($exam['name']) ?>">
                                                <input type="checkbox" 
                                                       name="exam_ids[]" 
                                                       value="<?= $exam['id'] ?>" 
                                                       class="mt-1 w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                                                <span class="ml-3 text-sm text-gray-700 dark:text-gray-300"><?= htmlspecialchars($exam['name']) ?></span>
                                            </label>
                                        <?php endwhile; ?>
                                    </div>
                                    <div class="no-results hidden text-center py-8 text-gray-500">
                                        <i class="bi bi-search text-4xl mb-2"></i>
                                        <p>Nenhum exame encontrado</p>
                                    </div>
                                </div>

                                <!-- Contador e Botões -->
                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        <span class="font-semibold text-purple-600" id="count-<?= $att['attendance_id'] ?>">0</span> exame(s) selecionado(s)
                                    </div>
                                    <div class="flex gap-3">
                                        <button type="button" onclick="desmarcarTodos<?= $att['attendance_id'] ?>()" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-white transition-all">
                                            <i class="bi bi-x-circle mr-1"></i>Limpar
                                        </button>
                                        <button type="submit" class="px-6 py-3 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 font-semibold disabled:opacity-50 disabled:cursor-not-allowed" id="btn-submit-<?= $att['attendance_id'] ?>" disabled>
                                            <i class="bi bi-flask mr-2"></i>Solicitar Exames
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <script>
                            (function() {
                                const attId = <?= $att['attendance_id'] ?>;
                                const searchInput = document.getElementById('search-exam-' + attId);
                                const examsList = document.getElementById('exams-list-' + attId);
                                const examItems = examsList.querySelectorAll('.exam-item');
                                const noResults = examsList.querySelector('.no-results');
                                const countSpan = document.getElementById('count-' + attId);
                                const submitBtn = document.getElementById('btn-submit-' + attId);
                                const checkboxes = examsList.querySelectorAll('input[type="checkbox"]');

                                // Busca de exames
                                searchInput.addEventListener('input', function() {
                                    const searchTerm = this.value.toLowerCase().trim();
                                    let visibleCount = 0;

                                    examItems.forEach(item => {
                                        const examName = item.dataset.examName;
                                        if (examName.includes(searchTerm)) {
                                            item.style.display = 'flex';
                                            visibleCount++;
                                        } else {
                                            item.style.display = 'none';
                                        }
                                    });

                                    noResults.classList.toggle('hidden', visibleCount > 0);
                                });

                                // Contador de selecionados
                                function updateCount() {
                                    const count = Array.from(checkboxes).filter(cb => cb.checked).length;
                                    countSpan.textContent = count;
                                    submitBtn.disabled = count === 0;
                                }

                                checkboxes.forEach(cb => {
                                    cb.addEventListener('change', updateCount);
                                });

                                // Função para desmarcar todos
                                window['desmarcarTodos' + attId] = function() {
                                    checkboxes.forEach(cb => cb.checked = false);
                                    updateCount();
                                };
                            })();
                            </script>
                        </div>

                        <hr class="border-gray-300 dark:border-gray-700 my-6">

                        <!-- Observações e Finalizar Atendimento -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                                <i class="bi bi-journal-medical mr-2 text-green-600"></i>
                                Observações e Finalizar Atendimento
                            </h3>
                            <form method="post">
                                <input type="hidden" name="acao" value="finalizar_atendimento">
                                <input type="hidden" name="attendance_id" value="<?= $att['attendance_id'] ?>">
                                <input type="hidden" name="appointment_id" value="<?= $att['appointment_id'] ?>">
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                        Observações do Atendimento
                                    </label>
                                    <textarea 
                                        name="notes" 
                                        rows="4" 
                                        placeholder="Digite aqui as observações sobre o atendimento, diagnóstico, orientações, etc..."
                                        class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-green-500 focus:ring-2 focus:ring-green-200 dark:focus:ring-green-900 transition-all duration-300 resize-none"><?= htmlspecialchars($att['notes']) ?></textarea>
                                </div>
                                
                                <button 
                                    type="submit" 
                                    onclick="return confirm('Tem certeza que deseja finalizar este atendimento?')"
                                    class="w-full px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 font-semibold">
                                    <i class="bi bi-check-circle-fill mr-2"></i>Finalizar Atendimento
                                </button>
                            </form>
                        </div>

                    </div>
                </div>
                <?php endwhile; ?>
                
                <?php if ($attendances->num_rows === 0): ?>
                <div class="glass-effect rounded-2xl shadow-xl border border-blue-200 dark:border-gray-700 p-12 text-center">
                    <i class="bi bi-info-circle text-6xl text-blue-400 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Nenhum Atendimento Ativo</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        Inicie um atendimento na lista de consultas agendadas para prescrever medicamentos ou solicitar exames.
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-12 text-center pb-8">
            <div class="glass-effect rounded-xl px-6 py-4 inline-block shadow-lg border border-blue-200 dark:border-gray-700">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <i class="bi bi-heart-pulse-fill text-blue-600 dark:text-blue-400 mr-2"></i>
                    <strong class="text-blue-700 dark:text-blue-300">Sistema Integrado Mais Saúde</strong> — Dashboard Médico
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                    © 2025 | Desenvolvido com tecnologia de ponta
                </p>
            </div>
        </footer>
    </div>
</body>
</html>
