<?php
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /MSLDA/index.php');
    exit;
}

$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$appointment_id) {
    die('ID da consulta não fornecido');
}

// Buscar dados da consulta
$query = $mysqli->query("
    SELECT 
        a.id,
        a.scheduled_at,
        a.concluded_at,
        a.notes,
        a.consultation_status,
        p.name as patient_name,
        p.codigo as patient_code,
        p.birth_date,
        p.phone,
        p.email,
        p.address,
        TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) as idade,
        u_prof.name as professional_name,
        u_concl.name as concluded_by_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN professionals prof ON a.professional_id = prof.id
    JOIN users u_prof ON prof.user_id = u_prof.id
    LEFT JOIN users u_concl ON a.concluded_by = u_concl.id
    WHERE a.id = $appointment_id
");

if (!$query || $query->num_rows === 0) {
    die('Consulta não encontrada');
}

$consulta = $query->fetch_assoc();

// Buscar atendimento relacionado
$attendance_query = $mysqli->query("SELECT id, notes FROM attendances WHERE appointment_id = $appointment_id");
$attendance = $attendance_query ? $attendance_query->fetch_assoc() : null;

// Buscar prescrições
$prescriptions_query = $mysqli->query("
    SELECT 
        pr.id,
        pr.created_at,
        pri.medication_id,
        m.name as medication_name,
        pri.dosage,
        pri.instructions
    FROM prescriptions pr
    LEFT JOIN prescription_items pri ON pr.id = pri.prescription_id
    LEFT JOIN medications m ON pri.medication_id = m.id
    WHERE pr.attendance_id = " . ($attendance['id'] ?? 0) . "
    ORDER BY pr.created_at DESC
");

// Buscar exames solicitados
$exams_query = $mysqli->query("
    SELECT 
        er.id,
        e.name as exam_name,
        res.released_at as result_date,
        res.result as results,
        res.status as result_status
    FROM exam_requests er
    JOIN exams e ON er.exam_id = e.id
    LEFT JOIN exam_results res ON er.id = res.exam_request_id
    WHERE er.attendance_id = " . ($attendance['id'] ?? 0) . "
    ORDER BY er.id DESC
");

// Buscar histórico de ações
$history_query = $mysqli->query("
    SELECT 
        ch.action_type,
        ch.description,
        ch.created_at,
        u.name as professional_name
    FROM consultation_history ch
    LEFT JOIN professionals prof ON ch.professional_id = prof.id
    LEFT JOIN users u ON prof.user_id = u.id
    WHERE ch.appointment_id = $appointment_id
    ORDER BY ch.created_at DESC
");

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Consulta - <?= htmlspecialchars($consulta['patient_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .glass-effect { backdrop-filter: blur(10px); background: rgba(255, 255, 255, 0.95); }
        @media print {
            .no-print { display: none; }
            body { background: white; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 min-h-screen p-8">
    
    <!-- Header -->
    <div class="max-w-6xl mx-auto mb-6 flex justify-between items-center no-print">
        <h1 class="text-3xl font-bold text-gray-800 flex items-center">
            <i class="bi bi-file-medical-fill text-blue-600 mr-3"></i>
            Detalhes da Consulta
        </h1>
        <div class="flex gap-3">
            <button onclick="window.print()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-md transition">
                <i class="bi bi-printer mr-2"></i>Imprimir
            </button>
            <button onclick="window.close()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg shadow-md transition">
                <i class="bi bi-x-lg mr-2"></i>Fechar
            </button>
        </div>
    </div>

    <div class="max-w-6xl mx-auto space-y-6">
        
        <!-- Informações da Consulta -->
        <div class="glass-effect rounded-2xl shadow-xl border border-blue-200 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="bi bi-calendar-check mr-3"></i>
                    Informações da Consulta
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Paciente</p>
                        <p class="text-lg font-bold text-gray-800"><?= htmlspecialchars($consulta['patient_name']) ?></p>
                        <p class="text-sm text-gray-600">Código: <?= htmlspecialchars($consulta['patient_code']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Profissional</p>
                        <p class="text-lg font-bold text-gray-800"><?= htmlspecialchars($consulta['professional_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Data Agendamento</p>
                        <p class="text-lg font-semibold text-gray-800">
                            <i class="bi bi-calendar3 text-blue-600 mr-2"></i>
                            <?= date('d/m/Y H:i', strtotime($consulta['scheduled_at'])) ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Data Finalização</p>
                        <p class="text-lg font-semibold text-gray-800">
                            <i class="bi bi-check-circle text-green-600 mr-2"></i>
                            <?= $consulta['concluded_at'] ? date('d/m/Y H:i', strtotime($consulta['concluded_at'])) : '-' ?>
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-500 mb-1">Status</p>
                        <span class="inline-block px-4 py-2 rounded-full text-sm font-semibold
                            <?= $consulta['consultation_status'] === 'concluido' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                            <?= htmlspecialchars($consulta['consultation_status']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dados do Paciente -->
        <div class="glass-effect rounded-2xl shadow-xl border border-blue-200 overflow-hidden">
            <div class="bg-gradient-to-r from-purple-500 to-indigo-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="bi bi-person-vcard mr-3"></i>
                    Dados do Paciente
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-xs text-gray-600 mb-1">Idade</p>
                        <p class="text-xl font-bold text-gray-800"><?= $consulta['idade'] ?> anos</p>
                    </div>
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-xs text-gray-600 mb-1">Data Nascimento</p>
                        <p class="text-xl font-bold text-gray-800"><?= date('d/m/Y', strtotime($consulta['birth_date'])) ?></p>
                    </div>
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-xs text-gray-600 mb-1">Telefone</p>
                        <p class="text-xl font-bold text-gray-800"><?= $consulta['phone'] ?: '-' ?></p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Email</p>
                        <p class="font-semibold text-gray-800"><?= $consulta['email'] ?: '-' ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Endereço</p>
                        <p class="font-semibold text-gray-800"><?= $consulta['address'] ?: '-' ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Observações do Atendimento -->
        <?php if($consulta['notes']): ?>
        <div class="glass-effect rounded-2xl shadow-xl border border-blue-200 overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="bi bi-clipboard-check mr-3"></i>
                    Observações do Atendimento
                </h2>
            </div>
            <div class="p-6">
                <p class="text-gray-800 whitespace-pre-wrap leading-relaxed"><?= htmlspecialchars($consulta['notes']) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Prescrições -->
        <?php if($prescriptions_query && $prescriptions_query->num_rows > 0): ?>
        <div class="glass-effect rounded-2xl shadow-xl border border-blue-200 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-500 to-cyan-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="bi bi-prescription2 mr-3"></i>
                    Medicamentos Prescritos
                </h2>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-100 border-b-2 border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Medicamento</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Dosagem</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Instruções</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while($presc = $prescriptions_query->fetch_assoc()): ?>
                            <tr>
                                <td class="px-4 py-3 font-semibold text-gray-800"><?= htmlspecialchars($presc['medication_name']) ?></td>
                                <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($presc['dosage']) ?></td>
                                <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($presc['instructions']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Exames Solicitados -->
        <?php if($exams_query && $exams_query->num_rows > 0): ?>
        <div class="glass-effect rounded-2xl shadow-xl border border-blue-200 overflow-hidden">
            <div class="bg-gradient-to-r from-purple-500 to-indigo-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="bi bi-flask mr-3"></i>
                    Exames Solicitados
                </h2>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-100 border-b-2 border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Exame</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Data Resultado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while($exam = $exams_query->fetch_assoc()): ?>
                            <tr>
                                <td class="px-4 py-3 font-semibold text-gray-800"><?= htmlspecialchars($exam['exam_name']) ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold
                                        <?= $exam['result_status'] === 'validado' ? 'bg-green-100 text-green-800' : 
                                           ($exam['result_status'] === 'pendente' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                                        <?= $exam['result_status'] ?: 'Aguardando' ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <?= $exam['result_date'] ? date('d/m/Y', strtotime($exam['result_date'])) : '-' ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Histórico de Ações -->
        <?php if($history_query && $history_query->num_rows > 0): ?>
        <div class="glass-effect rounded-2xl shadow-xl border border-blue-200 overflow-hidden">
            <div class="bg-gradient-to-r from-gray-500 to-gray-600 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="bi bi-clock-history mr-3"></i>
                    Histórico de Ações
                </h2>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <?php while($hist = $history_query->fetch_assoc()): ?>
                    <div class="flex items-start border-l-4 border-blue-500 bg-blue-50 p-4 rounded">
                        <div class="flex-shrink-0">
                            <i class="bi bi-circle-fill text-blue-500 text-xs"></i>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($hist['description']) ?></p>
                            <p class="text-xs text-gray-600 mt-1">
                                <?= date('d/m/Y H:i', strtotime($hist['created_at'])) ?> 
                                <?= $hist['professional_name'] ? '- ' . htmlspecialchars($hist['professional_name']) : '' ?>
                            </p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Footer -->
    <div class="max-w-6xl mx-auto mt-8 text-center text-gray-500 text-sm no-print">
        <p>Sistema Integrado Mais Saúde - Detalhes da Consulta</p>
        <p>Impresso em: <?= date('d/m/Y H:i') ?></p>
    </div>

</body>
</html>
