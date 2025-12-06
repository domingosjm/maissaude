<?php
require_once __DIR__ . '/../../config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: /MSLDA/index.php');
    exit;
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

// Buscar paciente se selecionado
$patient_id = isset($_GET['patient']) ? (int)$_GET['patient'] : null;
$patient_data = null;

if ($patient_id) {
    $patient_query = $mysqli->query("SELECT * FROM patients WHERE id = $patient_id");
    $patient_data = $patient_query->fetch_assoc();
    
    // Buscar histórico completo de consultas
    $history_query = $mysqli->query("
        SELECT 
            a.id as appointment_id,
            a.scheduled_at,
            a.consultation_status,
            a.notes as appointment_notes,
            a.concluded_at,
            att.id as attendance_id,
            att.notes as attendance_notes,
            u.name as concluded_by_name
        FROM appointments a
        LEFT JOIN attendances att ON att.appointment_id = a.id
        LEFT JOIN users u ON a.concluded_by = u.id
        WHERE a.patient_id = $patient_id
        AND a.professional_id = $professional_id
        ORDER BY a.scheduled_at DESC
    ");
    
    // Buscar prescrições do paciente
    $prescriptions_query = $mysqli->query("
        SELECT 
            p.id,
            p.created_at,
            a.scheduled_at,
            GROUP_CONCAT(
                CONCAT(m.name, ' - ', pi.dosage, ' (', pi.instructions, ')')
                SEPARATOR '|||'
            ) as medications
        FROM prescriptions p
        JOIN attendances att ON p.attendance_id = att.id
        JOIN appointments a ON att.appointment_id = a.id
        JOIN prescription_items pi ON p.id = pi.prescription_id
        JOIN medications m ON pi.medication_id = m.id
        WHERE a.patient_id = $patient_id
        AND a.professional_id = $professional_id
        GROUP BY p.id, p.created_at, a.scheduled_at
        ORDER BY p.created_at DESC
    ");
    
    // Buscar exames solicitados
    $exams_query = $mysqli->query("
        SELECT 
            er.id,
            er.created_at,
            e.name as exam_name,
            er.result_status,
            a.scheduled_at
        FROM exam_requests er
        JOIN exams e ON er.exam_id = e.id
        JOIN attendances att ON er.attendance_id = att.id
        JOIN appointments a ON att.appointment_id = a.id
        WHERE a.patient_id = $patient_id
        AND a.professional_id = $professional_id
        ORDER BY er.created_at DESC
    ");
}

// Buscar lista de pacientes do médico
$patients_query = $mysqli->query("
    SELECT DISTINCT
        p.id,
        p.name,
        p.codigo,
        p.birth_date,
        p.phone,
        COUNT(a.id) as total_appointments
    FROM patients p
    JOIN appointments a ON a.patient_id = p.id
    WHERE a.professional_id = $professional_id
    GROUP BY p.id, p.name, p.codigo, p.birth_date, p.phone
    ORDER BY p.name ASC
");

?>
<!DOCTYPE html>
<html lang="pt-br" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Pacientes - MedixOne</title>
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
                        <i class="bi bi-folder-fill text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-800 dark:text-white">Histórico de Pacientes</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400">MedixOne</p>
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
                    <a href="logout" class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                        <i class="bi bi-box-arrow-right mr-2"></i>Sair
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Menu de Navegação -->
    <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center space-x-1 overflow-x-auto py-3">
                <a href="consultorio/medico" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all duration-300">
                    <i class="bi bi-calendar-check mr-2"></i>Consultas
                </a>
                <a href="consultorio/historico" class="flex items-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-indigo-600 rounded-lg shadow-md transition-all duration-300">
                    <i class="bi bi-folder-fill mr-2"></i>Histórico de Pacientes
                </a>
                <a href="consultorio/psicologia" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all duration-300">
                    <i class="bi bi-person-hearts mr-2"></i>Psicologia
                </a>
                <a href="consultorio/psiquiatria" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all duration-300">
                    <i class="bi bi-brain mr-2"></i>Psiquiatria
                </a>
                <a href="dashboard" class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all duration-300">
                    <i class="bi bi-house-door mr-2"></i>Dashboard
                </a>
            </div>
        </div>
    </div>

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
        
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            
            <!-- Coluna Esquerda: Lista de Pacientes -->
            <div class="lg:col-span-1">
                <div class="glass-effect rounded-2xl shadow-xl border border-blue-200 dark:border-gray-700 overflow-hidden sticky top-24">
                    <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4">
                        <h2 class="text-lg font-bold text-white flex items-center">
                            <i class="bi bi-people-fill mr-2"></i>
                            Meus Pacientes
                        </h2>
                    </div>
                    
                    <!-- Campo de Busca -->
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="relative">
                            <i class="bi bi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" id="searchPatient" placeholder="Buscar paciente..." class="w-full pl-10 pr-4 py-2 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-blue-500 text-sm">
                        </div>
                    </div>
                    
                    <div class="p-4 space-y-2 max-h-[600px] overflow-y-auto">
                        <?php while($patient = $patients_query->fetch_assoc()): ?>
                        <a href="?patient=<?= $patient['id'] ?>" 
                           class="patient-item block p-3 rounded-lg transition-all duration-300 <?= $patient_id == $patient['id'] ? 'bg-blue-100 dark:bg-blue-900 border-2 border-blue-500' : 'bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600' ?>"
                           data-name="<?= strtolower($patient['name']) ?>">
                            <div class="font-bold text-gray-800 dark:text-white text-sm"><?= htmlspecialchars($patient['name']) ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <i class="bi bi-person-badge mr-1"></i><?= htmlspecialchars($patient['codigo']) ?>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <i class="bi bi-calendar-check mr-1"></i><?= $patient['total_appointments'] ?> consulta(s)
                            </div>
                        </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Coluna Direita: Ficha do Paciente -->
            <div class="lg:col-span-3">
                <?php if (!$patient_data): ?>
                    <div class="glass-effect rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                        <i class="bi bi-person-lines-fill text-6xl text-blue-400 mb-4"></i>
                        <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">Selecione um Paciente</h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Escolha um paciente da lista ao lado para visualizar seu histórico médico completo.
                        </p>
                    </div>
                <?php else: ?>
                    <!-- Cabeçalho do Paciente -->
                    <div class="glass-effect rounded-2xl shadow-xl border border-blue-200 dark:border-gray-700 overflow-hidden mb-6">
                        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h2 class="text-2xl font-bold text-white mb-2"><?= htmlspecialchars($patient_data['name']) ?></h2>
                                    <div class="grid grid-cols-2 gap-4 text-white text-sm">
                                        <div><i class="bi bi-person-badge mr-2"></i>Código: <?= htmlspecialchars($patient_data['codigo']) ?></div>
                                        <div><i class="bi bi-calendar mr-2"></i>Nasc: <?= date('d/m/Y', strtotime($patient_data['birth_date'])) ?></div>
                                        <div><i class="bi bi-telephone mr-2"></i><?= htmlspecialchars($patient_data['phone'] ?? 'Não informado') ?></div>
                                        <div><i class="bi bi-envelope mr-2"></i><?= htmlspecialchars($patient_data['email'] ?? 'Não informado') ?></div>
                                    </div>
                                </div>
                                <div class="bg-white bg-opacity-20 px-6 py-3 rounded-lg">
                                    <div class="text-white text-center">
                                        <div class="text-3xl font-bold"><?= $history_query->num_rows ?></div>
                                        <div class="text-xs opacity-90">Consultas</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Abas de Histórico -->
                    <div class="glass-effect rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="border-b border-gray-200 dark:border-gray-700">
                            <div class="flex space-x-1 p-2">
                                <button onclick="showTab('consultas')" id="tab-consultas" class="tab-btn flex-1 px-4 py-3 rounded-lg font-semibold transition-all duration-300 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300">
                                    <i class="bi bi-clipboard-pulse mr-2"></i>Histórico de Consultas
                                </button>
                                <button onclick="showTab('prescricoes')" id="tab-prescricoes" class="tab-btn flex-1 px-4 py-3 rounded-lg font-semibold transition-all duration-300 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="bi bi-prescription2 mr-2"></i>Prescrições
                                </button>
                                <button onclick="showTab('exames')" id="tab-exames" class="tab-btn flex-1 px-4 py-3 rounded-lg font-semibold transition-all duration-300 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="bi bi-flask mr-2"></i>Exames Solicitados
                                </button>
                            </div>
                        </div>

                        <!-- Conteúdo das Abas -->
                        <div class="p-6">
                            
                            <!-- Aba: Histórico de Consultas -->
                            <div id="content-consultas" class="tab-content">
                                <div class="space-y-4">
                                    <?php while($hist = $history_query->fetch_assoc()): ?>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border-2 border-gray-200 dark:border-gray-700 hover:border-blue-400 transition-all">
                                        <div class="flex items-start justify-between mb-3">
                                            <div>
                                                <h4 class="font-bold text-gray-800 dark:text-white text-lg">
                                                    <?= date('d/m/Y H:i', strtotime($hist['scheduled_at'])) ?>
                                                </h4>
                                                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold mt-1 <?php
                                                    echo match($hist['consultation_status']) {
                                                        'agendado' => 'bg-blue-100 text-blue-800',
                                                        'em_atendimento' => 'bg-green-100 text-green-800',
                                                        'aguardando_exames' => 'bg-purple-100 text-purple-800',
                                                        'concluido' => 'bg-gray-100 text-gray-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
                                                ?>">
                                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $hist['consultation_status']))) ?>
                                                </span>
                                            </div>
                                            <?php if($hist['concluded_at']): ?>
                                            <div class="text-right text-xs text-gray-500">
                                                <div><i class="bi bi-check-circle mr-1"></i>Concluído</div>
                                                <div><?= date('d/m/Y H:i', strtotime($hist['concluded_at'])) ?></div>
                                                <div>por <?= htmlspecialchars($hist['concluded_by_name'] ?? 'N/A') ?></div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if($hist['attendance_notes'] || $hist['appointment_notes']): ?>
                                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                            <div class="font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                                <i class="bi bi-journal-text mr-2 text-blue-600"></i>Observações:
                                            </div>
                                            <p class="text-gray-600 dark:text-gray-400 text-sm whitespace-pre-wrap">
                                                <?= htmlspecialchars($hist['attendance_notes'] ?: $hist['appointment_notes']) ?>
                                            </p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endwhile; ?>
                                    
                                    <?php if($history_query->num_rows === 0): ?>
                                    <div class="text-center py-12 text-gray-500">
                                        <i class="bi bi-inbox text-5xl mb-3"></i>
                                        <p>Nenhuma consulta registrada</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Aba: Prescrições -->
                            <div id="content-prescricoes" class="tab-content hidden">
                                <div class="space-y-4">
                                    <?php while($presc = $prescriptions_query->fetch_assoc()): ?>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border-2 border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="font-bold text-gray-800 dark:text-white">
                                                <i class="bi bi-prescription2 text-blue-600 mr-2"></i>
                                                Prescrição de <?= date('d/m/Y', strtotime($presc['created_at'])) ?>
                                            </h4>
                                            <span class="text-xs text-gray-500">Consulta: <?= date('d/m/Y', strtotime($presc['scheduled_at'])) ?></span>
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <?php 
                                            $meds = explode('|||', $presc['medications']);
                                            foreach($meds as $med): 
                                            ?>
                                            <div class="flex items-start bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                                                <i class="bi bi-capsule-pill text-blue-600 mr-3 mt-1"></i>
                                                <span class="text-gray-700 dark:text-gray-300 text-sm"><?= htmlspecialchars($med) ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                    
                                    <?php if($prescriptions_query->num_rows === 0): ?>
                                    <div class="text-center py-12 text-gray-500">
                                        <i class="bi bi-inbox text-5xl mb-3"></i>
                                        <p>Nenhuma prescrição registrada</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Aba: Exames Solicitados -->
                            <div id="content-exames" class="tab-content hidden">
                                <div class="space-y-4">
                                    <?php while($exam = $exams_query->fetch_assoc()): ?>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border-2 border-gray-200 dark:border-gray-700 flex items-start justify-between">
                                        <div class="flex items-start">
                                            <i class="bi bi-flask text-purple-600 mr-3 mt-1 text-xl"></i>
                                            <div>
                                                <h4 class="font-bold text-gray-800 dark:text-white"><?= htmlspecialchars($exam['exam_name']) ?></h4>
                                                <p class="text-xs text-gray-500">Solicitado em: <?= date('d/m/Y H:i', strtotime($exam['created_at'])) ?></p>
                                                <p class="text-xs text-gray-500">Consulta: <?= date('d/m/Y', strtotime($exam['scheduled_at'])) ?></p>
                                            </div>
                                        </div>
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $exam['result_status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                            <?= $exam['result_status'] === 'completed' ? 'Concluído' : 'Pendente' ?>
                                        </span>
                                    </div>
                                    <?php endwhile; ?>
                                    
                                    <?php if($exams_query->num_rows === 0): ?>
                                    <div class="text-center py-12 text-gray-500">
                                        <i class="bi bi-inbox text-5xl mb-3"></i>
                                        <p>Nenhum exame solicitado</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Footer -->
        <footer class="mt-12 text-center pb-8">
            <div class="glass-effect rounded-xl px-6 py-4 inline-block shadow-lg border border-blue-200 dark:border-gray-700">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <i class="bi bi-heart-pulse-fill text-blue-600 dark:text-blue-400 mr-2"></i>
                    <strong class="text-blue-700 dark:text-blue-300">MedixOne</strong> — Histórico de Pacientes
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                    © 2025 | A Gestão que Liga Saúde, Tecnologia e Excelência
                </p>
            </div>
        </footer>
    </div>

    <script>
        // Sistema de Abas
        function showTab(tabName) {
            // Esconder todos os conteúdos
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remover estilo ativo de todos os botões
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('bg-blue-100', 'dark:bg-blue-900', 'text-blue-700', 'dark:text-blue-300');
                btn.classList.add('text-gray-700', 'dark:text-gray-300');
            });
            
            // Mostrar conteúdo selecionado
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Ativar botão selecionado
            const btn = document.getElementById('tab-' + tabName);
            btn.classList.remove('text-gray-700', 'dark:text-gray-300');
            btn.classList.add('bg-blue-100', 'dark:bg-blue-900', 'text-blue-700', 'dark:text-blue-300');
        }

        // Busca de pacientes
        document.getElementById('searchPatient').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.patient-item').forEach(item => {
                const name = item.dataset.name;
                item.style.display = name.includes(searchTerm) ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>
