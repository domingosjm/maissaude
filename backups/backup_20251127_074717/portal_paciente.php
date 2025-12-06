<?php
session_start();
require_once __DIR__ . '/config.php';

$erro = '';
$sucesso = '';

// LOGIN DO PACIENTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_paciente'])) {
    $codigo = strtoupper(trim($_POST['codigo']));
    $data_nascimento = $_POST['data_nascimento'];
    
    // Buscar paciente
    $stmt = $mysqli->prepare("SELECT id, name, codigo, birth_date, phone FROM patients WHERE codigo = ? AND birth_date = ?");
    $stmt->bind_param('ss', $codigo, $data_nascimento);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $paciente = $result->fetch_assoc();
        $_SESSION['portal_patient_id'] = $paciente['id'];
        $_SESSION['portal_patient_name'] = $paciente['name'];
        $_SESSION['portal_patient_code'] = $paciente['codigo'];
        header('Location: portal_paciente.php');
        exit;
    } else {
        $erro = 'Código ou data de nascimento incorretos.';
    }
}

// LOGOUT
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: portal_paciente.php');
    exit;
}

// VERIFICAR SE ESTÁ LOGADO
$logado = isset($_SESSION['portal_patient_id']);
$patient_id = $_SESSION['portal_patient_id'] ?? null;
$patient_name = $_SESSION['portal_patient_name'] ?? '';
$patient_code = $_SESSION['portal_patient_code'] ?? '';

// BUSCAR DADOS DO PACIENTE LOGADO
if ($logado) {
    // Exames liberados
    $exames = $mysqli->query("
        SELECT 
            eresult.id,
            e.name as exam_name,
            eresult.result,
            eresult.validation_notes as observations,
            eresult.released_at,
            ereq.requested_at as created_at,
            u.name as professional_name,
            ereq.id as exam_request_id
        FROM exam_results eresult
        INNER JOIN exam_requests ereq ON eresult.exam_request_id = ereq.id
        INNER JOIN exams e ON ereq.exam_id = e.id
        INNER JOIN attendances att ON ereq.attendance_id = att.id
        INNER JOIN appointments a ON att.appointment_id = a.id
        LEFT JOIN users u ON eresult.validated_by = u.id
        WHERE a.patient_id = $patient_id
        AND eresult.status = 'liberado'
        ORDER BY eresult.released_at DESC
    ");
    
    // Prescrições
    $prescricoes = $mysqli->query("
        SELECT 
            p.id as prescription_id,
            p.created_at,
            p.delivered_at,
            u.name as doctor_name,
            s.name as specialty
        FROM prescriptions p
        INNER JOIN attendances att ON p.attendance_id = att.id
        INNER JOIN appointments a ON att.appointment_id = a.id
        LEFT JOIN professionals prof ON a.professional_id = prof.id
        LEFT JOIN users u ON prof.user_id = u.id
        LEFT JOIN specialties s ON prof.specialty_id = s.id
        WHERE a.patient_id = $patient_id
        ORDER BY p.created_at DESC
    ");
    
    // Buscar medicamentos de cada prescrição
    $medicamentos_prescricoes = [];
    if ($prescricoes->num_rows > 0) {
        $prescricoes->data_seek(0);
        while ($presc = $prescricoes->fetch_assoc()) {
            $presc_id = $presc['prescription_id'];
            $meds = $mysqli->query("
                SELECT m.name, pi.dosage, pi.instructions
                FROM prescription_items pi
                LEFT JOIN medications m ON pi.medication_id = m.id
                WHERE pi.prescription_id = $presc_id
            ");
            $medicamentos_prescricoes[$presc_id] = $meds->fetch_all(MYSQLI_ASSOC);
        }
        $prescricoes->data_seek(0);
    }
    
    // Histórico de consultas
    $consultas = $mysqli->query("
        SELECT 
            a.scheduled_at,
            a.status,
            u.name as professional_name,
            sp.name as specialty
        FROM appointments a
        LEFT JOIN professionals prof ON a.professional_id = prof.id
        LEFT JOIN users u ON prof.user_id = u.id
        LEFT JOIN specialties sp ON prof.specialty_id = sp.id
        WHERE a.patient_id = $patient_id
        ORDER BY a.scheduled_at DESC
        LIMIT 20
    ");
    
    // Faturas
    $faturas = $mysqli->query("
        SELECT 
            i.id,
            i.invoice_number,
            i.issue_date,
            i.status,
            i.total,
            i.amount_paid,
            i.amount_due
        FROM invoices i
        WHERE i.patient_id = $patient_id
        ORDER BY i.issue_date DESC
        LIMIT 10
    ");
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal do Paciente - Mais Saúde</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .glass-effect { backdrop-filter: blur(16px); background: rgba(255, 255, 255, 0.95); }
        .dark .glass-effect { background: rgba(17, 24, 39, 0.95); }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-slate-900 min-h-screen">

    <?php if (!$logado): ?>
    <!-- TELA DE LOGIN -->
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full">
            <!-- Logo e Título -->
            <div class="text-center mb-8">
                <div class="inline-block bg-gradient-to-br from-blue-500 to-indigo-600 p-4 rounded-2xl shadow-2xl mb-4">
                    <i class="bi bi-person-badge text-white text-5xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-2">Portal do Paciente</h1>
                <p class="text-gray-600 dark:text-gray-400">Acesse seus exames e informações de saúde</p>
            </div>
            
            <!-- Card de Login -->
            <div class="glass-effect rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 p-8">
                <?php if ($erro): ?>
                <div class="mb-6 p-4 bg-red-100 dark:bg-red-900 border-l-4 border-red-500 text-red-700 dark:text-red-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="bi bi-exclamation-triangle-fill mr-3 text-xl"></i>
                        <p class="font-medium"><?= htmlspecialchars($erro) ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="login_paciente" value="1">
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            <i class="bi bi-shield-lock mr-2"></i>Código do Paciente
                        </label>
                        <input type="text" name="codigo" required 
                               placeholder="MS-0001" 
                               pattern="MS-[0-9]+" 
                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-blue-500 focus:ring-4 focus:ring-blue-200 dark:focus:ring-blue-900 transition-all uppercase"
                               style="text-transform: uppercase;">
                        <p class="mt-1 text-xs text-gray-500">Ex: MS-0001, MS-0123</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            <i class="bi bi-calendar-event mr-2"></i>Data de Nascimento
                        </label>
                        <input type="date" name="data_nascimento" required 
                               max="<?= date('Y-m-d') ?>"
                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-blue-500 focus:ring-4 focus:ring-blue-200 dark:focus:ring-blue-900 transition-all">
                    </div>
                    
                    <button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 font-semibold">
                        <i class="bi bi-box-arrow-in-right mr-2"></i>Acessar Portal
                    </button>
                </form>
                
                <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-800">
                    <div class="flex items-start">
                        <i class="bi bi-info-circle-fill text-blue-600 dark:text-blue-400 mr-3 mt-1"></i>
                        <div class="text-xs text-blue-800 dark:text-blue-300">
                            <p class="font-semibold mb-1">Primeira vez no portal?</p>
                            <p>Use seu <strong>código de paciente</strong> (fornecido pela recepção) e sua <strong>data de nascimento</strong> para acessar.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-6">
                <a href="index.php" class="text-sm text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">
                    <i class="bi bi-arrow-left mr-1"></i>Voltar para o sistema
                </a>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- PORTAL DO PACIENTE LOGADO -->
    
    <!-- Navbar -->
    <nav class="glass-effect shadow-lg border-b border-gray-200 dark:border-gray-700 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-3">
                    <div class="bg-gradient-to-br from-blue-500 to-indigo-600 p-2 rounded-xl">
                        <i class="bi bi-person-badge text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800 dark:text-white">Portal do Paciente</h1>
                        <p class="text-xs text-gray-500">Mais Saúde - MSLDA</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-800 dark:text-white"><?= htmlspecialchars($patient_name) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($patient_code) ?></p>
                    </div>
                    <a href="?logout=1" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm font-medium transition-all">
                        <i class="bi bi-box-arrow-right mr-1"></i>Sair
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Tabs -->
        <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
            <nav class="flex space-x-4" role="tablist">
                <button onclick="switchTab('exames')" id="tab-exames" class="tab-button px-4 py-2 font-semibold border-b-2 transition-all active">
                    <i class="bi bi-file-medical mr-1"></i>Meus Exames
                </button>
                <button onclick="switchTab('prescricoes')" id="tab-prescricoes" class="tab-button px-4 py-2 font-semibold border-b-2 transition-all">
                    <i class="bi bi-prescription2 mr-1"></i>Prescrições
                </button>
                <button onclick="switchTab('consultas')" id="tab-consultas" class="tab-button px-4 py-2 font-semibold border-b-2 transition-all">
                    <i class="bi bi-calendar-check mr-1"></i>Consultas
                </button>
                <button onclick="switchTab('faturas')" id="tab-faturas" class="tab-button px-4 py-2 font-semibold border-b-2 transition-all">
                    <i class="bi bi-receipt mr-1"></i>Faturas
                </button>
            </nav>
        </div>
        
        <!-- Conteúdo: Exames -->
        <div id="content-exames" class="tab-content">
            <div class="glass-effect rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                    <i class="bi bi-clipboard-pulse mr-3 text-blue-600"></i>
                    Resultados de Exames
                </h2>
                
                <?php if ($exames->num_rows > 0): ?>
                    <div class="space-y-4">
                        <?php while ($exame = $exames->fetch_assoc()): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border-l-4 border-green-500 shadow-md">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800 dark:text-white"><?= htmlspecialchars($exame['exam_name']) ?></h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        <i class="bi bi-calendar-check mr-1"></i>
                                        Liberado em: <?= date('d/m/Y H:i', strtotime($exame['released_at'])) ?>
                                    </p>
                                    <?php if ($exame['professional_name']): ?>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        <i class="bi bi-person-check mr-1"></i>
                                        Validado por: <?= htmlspecialchars($exame['professional_name']) ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <a href="exportar_exames_pdf.php?exam_request_id=<?= $exame['exam_request_id'] ?>" 
                                   target="_blank"
                                   class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg shadow-md transition-all text-sm font-medium">
                                    <i class="bi bi-file-pdf mr-1"></i>Baixar PDF
                                </a>
                            </div>
                            
                            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-3">
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Resultado:</p>
                                <p class="text-gray-800 dark:text-white whitespace-pre-line"><?= nl2br(htmlspecialchars($exame['result'])) ?></p>
                            </div>
                            
                            <?php if ($exame['observations']): ?>
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                                <p class="text-sm font-semibold text-blue-700 dark:text-blue-300 mb-2">
                                    <i class="bi bi-info-circle mr-1"></i>Observações:
                                </p>
                                <p class="text-blue-800 dark:text-blue-200 text-sm"><?= nl2br(htmlspecialchars($exame['observations'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="bi bi-inbox text-6xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600 dark:text-gray-400">Nenhum exame liberado no momento</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Conteúdo: Prescrições -->
        <div id="content-prescricoes" class="tab-content hidden">
            <div class="glass-effect rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                    <i class="bi bi-capsule-pill mr-3 text-purple-600"></i>
                    Minhas Prescrições Médicas
                </h2>
                
                <?php if ($prescricoes->num_rows > 0): ?>
                    <div class="space-y-4">
                        <?php while ($presc = $prescricoes->fetch_assoc()): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border-l-4 border-purple-500 shadow-md">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                                        Prescrição #<?= $presc['prescription_id'] ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        <i class="bi bi-calendar mr-1"></i>
                                        <?= date('d/m/Y', strtotime($presc['created_at'])) ?>
                                    </p>
                                    <?php if ($presc['doctor_name']): ?>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        <i class="bi bi-person-badge mr-1"></i>
                                        Dr(a). <?= htmlspecialchars($presc['doctor_name']) ?>
                                        <?php if ($presc['specialty']): ?>
                                        - <?= htmlspecialchars($presc['specialty']) ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($presc['delivered_at']): ?>
                                <span class="px-3 py-1 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full text-xs font-semibold">
                                    <i class="bi bi-check-circle mr-1"></i>Entregue
                                </span>
                                <?php else: ?>
                                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 rounded-full text-xs font-semibold">
                                    <i class="bi bi-clock mr-1"></i>Pendente
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Medicamentos Prescritos:</p>
                                <?php foreach ($medicamentos_prescricoes[$presc['prescription_id']] as $med): ?>
                                <div class="mb-3 pb-3 border-b border-gray-200 dark:border-gray-700 last:border-0">
                                    <p class="font-semibold text-gray-800 dark:text-white"><?= htmlspecialchars($med['name']) ?></p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        <strong>Dosagem:</strong> <?= htmlspecialchars($med['dosage']) ?>
                                    </p>
                                    <?php if ($med['instructions']): ?>
                                    <p class="text-sm text-blue-600 dark:text-blue-400 mt-1">
                                        <i class="bi bi-info-circle mr-1"></i><?= htmlspecialchars($med['instructions']) ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="bi bi-inbox text-6xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600 dark:text-gray-400">Nenhuma prescrição registrada</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Conteúdo: Consultas -->
        <div id="content-consultas" class="tab-content hidden">
            <div class="glass-effect rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                    <i class="bi bi-calendar-heart mr-3 text-teal-600"></i>
                    Histórico de Consultas
                </h2>
                
                <?php if ($consultas->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-teal-100 to-cyan-100 dark:from-teal-900 dark:to-cyan-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300">Data/Hora</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300">Profissional</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 dark:text-gray-300">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php while ($consulta = $consultas->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">
                                        <?= date('d/m/Y H:i', strtotime($consulta['scheduled_at'])) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                        <?= htmlspecialchars($consulta['professional_name'] ?? '-') ?>
                                        <?php if ($consulta['specialty']): ?>
                                        <br><span class="text-xs"><?= htmlspecialchars($consulta['specialty']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <?php
                                        $status_colors = [
                                            'agendado' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                            'confirmado' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                            'cancelado' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                            'concluido' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
                                        ];
                                        $color = $status_colors[$consulta['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $color ?>">
                                            <?= htmlspecialchars(ucfirst($consulta['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="bi bi-inbox text-6xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600 dark:text-gray-400">Nenhuma consulta registrada</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Conteúdo: Faturas -->
        <div id="content-faturas" class="tab-content hidden">
            <div class="glass-effect rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                    <i class="bi bi-receipt-cutoff mr-3 text-amber-600"></i>
                    Minhas Faturas
                </h2>
                
                <?php if ($faturas->num_rows > 0): ?>
                    <div class="space-y-4">
                        <?php while ($fatura = $faturas->fetch_assoc()): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border-l-4 <?= $fatura['status'] === 'paga' ? 'border-green-500' : 'border-orange-500' ?> shadow-md">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                                        Fatura #<?= htmlspecialchars($fatura['invoice_number']) ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        <i class="bi bi-calendar mr-1"></i>
                                        <?= date('d/m/Y', strtotime($fatura['issue_date'])) ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <?php
                                    $status_colors = [
                                        'paga' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        'pendente' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                        'parcial' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                    ];
                                    $color = $status_colors[$fatura['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $color ?>">
                                        <?= htmlspecialchars(ucfirst($fatura['status'])) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-4 bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                                <div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Total</p>
                                    <p class="text-lg font-bold text-gray-800 dark:text-white">
                                        <?= number_format($fatura['total'], 2) ?> MT
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Pago</p>
                                    <p class="text-lg font-bold text-green-600 dark:text-green-400">
                                        <?= number_format($fatura['amount_paid'], 2) ?> MT
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">A Pagar</p>
                                    <p class="text-lg font-bold text-orange-600 dark:text-orange-400">
                                        <?= number_format($fatura['amount_due'], 2) ?> MT
                                    </p>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <a href="imprimir_fatura.php?id=<?= $fatura['id'] ?>" 
                                   target="_blank"
                                   class="inline-flex items-center px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg shadow-md transition-all text-sm font-medium">
                                    <i class="bi bi-printer mr-2"></i>Imprimir Fatura
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="bi bi-inbox text-6xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600 dark:text-gray-400">Nenhuma fatura registrada</p>
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
                button.classList.remove('active', 'border-blue-500', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-600');
            });
            
            // Mostrar conteúdo selecionado
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Ativar botão selecionado
            const activeButton = document.getElementById('tab-' + tabName);
            activeButton.classList.add('active', 'border-blue-500', 'text-blue-600');
            activeButton.classList.remove('border-transparent', 'text-gray-600');
        }
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            switchTab('exames');
        });
    </script>
    
    <?php endif; ?>
    
</body>
</html>
