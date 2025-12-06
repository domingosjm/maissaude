<?php
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';
require_login();
?>
<?php $user_name = $_SESSION['user_name'] ?? 'Usuário'; ?>
<!DOCTYPE html>
<html lang="pt-br" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Sistema Integrado Mais Saúde</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#10b981',
                        secondary: '#059669',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.6s ease-in-out',
                        'slide-up': 'slideUp 0.5s ease-out',
                        'float': 'float 3s ease-in-out infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(30px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-10px)' },
                        },
                    },
                },
            },
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .glass-effect { backdrop-filter: blur(16px); background: rgba(255, 255, 255, 0.85); }
        .dark .glass-effect { background: rgba(17, 24, 39, 0.85); }
        .module-card { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .module-card:hover { transform: translateY(-8px) scale(1.02); }
    </style>
</head>
<body class="bg-gradient-to-br from-emerald-50 via-teal-50 to-cyan-50 dark:from-gray-900 dark:via-gray-800 dark:to-slate-900 min-h-screen transition-colors duration-300">
    <!-- Navbar Premium -->
    <nav class="glass-effect shadow-2xl border-b border-emerald-200 dark:border-gray-700 sticky top-0 z-50 animate-fade-in">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center space-x-4">
                    <div class="bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-600 p-3 rounded-2xl shadow-xl animate-float">
                        <img src="logo.png" alt="Logo" class="w-10 h-10 object-contain brightness-0 invert">
                    </div>
                    <div>
                        <h1 class="text-2xl font-black bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 dark:from-emerald-400 dark:via-teal-400 dark:to-cyan-400 bg-clip-text text-transparent">
                            Mais Saúde
                        </h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Sistema Integrado de Gestão Clínica</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="toggleDarkMode()" class="p-3 rounded-xl bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-300 shadow-md hover:shadow-lg transform hover:scale-110">
                        <i class="bi bi-moon-stars dark:bi-sun text-gray-700 dark:text-gray-300 text-lg"></i>
                    </button>
                    <div class="flex items-center space-x-3 bg-gradient-to-r from-emerald-100 to-teal-100 dark:from-gray-700 dark:to-gray-600 px-5 py-3 rounded-full shadow-md">
                        <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-full flex items-center justify-center shadow-lg">
                            <i class="bi bi-person-fill text-white text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Bem-vindo</p>
                            <p class="text-sm font-bold text-gray-800 dark:text-gray-100"><?= htmlspecialchars($user_name) ?></p>
                        </div>
                    </div>
                    <a href="logout.php" class="px-5 py-3 bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 font-semibold">
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

    <!-- Hero Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center mb-12 animate-slide-up">
            <h2 class="text-4xl md:text-5xl font-black text-gray-800 dark:text-white mb-4">
                Bem-vindo ao <span class="bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 bg-clip-text text-transparent">Mais Saúde</span>
            </h2>
            <p class="text-lg text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                Escolha o módulo que deseja acessar para gerenciar seus atendimentos e processos clínicos
            </p>
        </div>

        <!-- Módulos Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

            <!-- 2. Recepção -->
            <a href="recepcao" class="module-card glass-effect rounded-2xl shadow-xl border-2 border-emerald-200 dark:border-gray-700 overflow-hidden group animate-slide-up" style="animation-delay: 0.1s;">
                <div class="p-8 text-center">
                    <div class="mb-6 inline-block">
                        <div class="w-20 h-20 bg-gradient-to-br from-pink-500 to-rose-700 rounded-2xl flex items-center justify-center shadow-2xl transform group-hover:rotate-12 transition-all duration-300">
                            <i class="bi bi-people-fill text-white text-4xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Recepção</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Cadastro e agendamentos</p>
                </div>
                <div class="h-2 bg-gradient-to-r from-pink-500 to-rose-700 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300"></div>
            </a>

            <!-- 3. Consultas -->
            <a href="consultorio/medico" class="module-card glass-effect rounded-2xl shadow-xl border-2 border-emerald-200 dark:border-gray-700 overflow-hidden group animate-slide-up" style="animation-delay: 0.15s;">
                <div class="p-8 text-center">
                    <div class="mb-6 inline-block">
                        <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl flex items-center justify-center shadow-2xl transform group-hover:rotate-12 transition-all duration-300">
                            <i class="bi bi-person-bounding-box text-white text-4xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Consultas</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Atendimentos médicos</p>
                </div>
                <div class="h-2 bg-gradient-to-r from-blue-500 to-blue-700 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300"></div>
            </a>

            <!-- 6. Laboratório -->
            <a href="laboratorio" class="module-card glass-effect rounded-2xl shadow-xl border-2 border-emerald-200 dark:border-gray-700 overflow-hidden group animate-slide-up" style="animation-delay: 0.3s;">
                <div class="p-8 text-center">
                    <div class="mb-6 inline-block">
                        <div class="w-20 h-20 bg-gradient-to-br from-purple-500 to-purple-700 rounded-2xl flex items-center justify-center shadow-2xl transform group-hover:rotate-12 transition-all duration-300">
                            <i class="bi bi-droplet-fill text-white text-4xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Laboratório</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Exames e resultados</p>
                </div>
                <div class="h-2 bg-gradient-to-r from-purple-500 to-purple-700 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300"></div>
            </a>

            <!-- 7. Farmácia -->
            <a href="farmacia" class="module-card glass-effect rounded-2xl shadow-xl border-2 border-emerald-200 dark:border-gray-700 overflow-hidden group animate-slide-up" style="animation-delay: 0.35s;">
                <div class="p-8 text-center">
                    <div class="mb-6 inline-block">
                        <div class="w-20 h-20 bg-gradient-to-br from-green-500 to-emerald-700 rounded-2xl flex items-center justify-center shadow-2xl transform group-hover:rotate-12 transition-all duration-300">
                            <i class="bi bi-capsule-pill text-white text-4xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Farmácia</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Medicamentos e dispensação</p>
                </div>
                <div class="h-2 bg-gradient-to-r from-green-500 to-emerald-700 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300"></div>
            </a>

            <!-- 8. Psicologia -->
            <a href="psicologia.php" class="module-card glass-effect rounded-2xl shadow-xl border-2 border-emerald-200 dark:border-gray-700 overflow-hidden group animate-slide-up" style="animation-delay: 0.4s;">
                <div class="p-8 text-center">
                    <div class="mb-6 inline-block">
                        <div class="w-20 h-20 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-2xl flex items-center justify-center shadow-2xl transform group-hover:rotate-12 transition-all duration-300">
                            <i class="bi bi-emoji-smile-fill text-white text-4xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Psicologia</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Atendimentos psicológicos</p>
                </div>
                <div class="h-2 bg-gradient-to-r from-yellow-500 to-orange-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300"></div>
            </a>

  <!-- 8. Psiquiatria -->
            <a href="psiquiatria.php" class="module-card glass-effect rounded-2xl shadow-xl border-2 border-emerald-200 dark:border-gray-700 overflow-hidden group animate-slide-up" style="animation-delay: 0.4s;">
                <div class="p-8 text-center">
                    <div class="mb-6 inline-block">
                        <div class="w-20 h-20 bg-gradient-to-br from-yellow-500 to-orange-600 rounded-2xl flex items-center justify-center shadow-2xl transform group-hover:rotate-12 transition-all duration-300">
                            <i class="bi bi-emoji-angry text-white text-4xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Psiquiatria</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Atendimentos psiquiátricos</p>
                </div>
                <div class="h-2 bg-gradient-to-r from-yellow-500 to-orange-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300"></div>
            </a>

            <!-- 9. Financeiro -->
            <a href="financeiro" class="module-card glass-effect rounded-2xl shadow-xl border-2 border-emerald-200 dark:border-gray-700 overflow-hidden group animate-slide-up" style="animation-delay: 0.45s;">
                <div class="p-8 text-center">
                    <div class="mb-6 inline-block">
                        <div class="w-20 h-20 bg-gradient-to-br from-amber-500 to-yellow-700 rounded-2xl flex items-center justify-center shadow-2xl transform group-hover:rotate-12 transition-all duration-300">
                            <i class="bi bi-cash-coin text-white text-4xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Financeiro</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Faturamento e pagamentos</p>
                </div>
                <div class="h-2 bg-gradient-to-r from-amber-500 to-yellow-700 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300"></div>
            </a>

            <!-- Stocks/Farmácia -->
            <a href="stocks.php" class="module-card glass-effect rounded-2xl shadow-xl border-2 border-emerald-200 dark:border-gray-700 overflow-hidden group animate-slide-up" style="animation-delay: 0.475s;">
                <div class="p-8 text-center">
                    <div class="mb-6 inline-block">
                        <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-2xl transform group-hover:rotate-12 transition-all duration-300">
                            <i class="bi bi-capsule text-white text-4xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Stocks</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Gestão de medicamentos</p>
                </div>
                <div class="h-2 bg-gradient-to-r from-blue-500 to-indigo-600 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300"></div>
            </a>

            <!-- 10. Relatórios -->
            <a href="relatorios.php" class="module-card glass-effect rounded-2xl shadow-xl border-2 border-emerald-200 dark:border-gray-700 overflow-hidden group animate-slide-up" style="animation-delay: 0.5s;">
                <div class="p-8 text-center">
                    <div class="mb-6 inline-block">
                        <div class="w-20 h-20 bg-gradient-to-br from-slate-500 to-gray-700 rounded-2xl flex items-center justify-center shadow-2xl transform group-hover:rotate-12 transition-all duration-300">
                            <i class="bi bi-file-earmark-bar-graph text-white text-4xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Relatórios</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Análises e indicadores</p>
                </div>
                <div class="h-2 bg-gradient-to-r from-slate-500 to-gray-700 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300"></div>
            </a>

  <!-- 10. Validação -->
            <a href="laboratorio_validacao.php" class="module-card glass-effect rounded-2xl shadow-xl border-2 border-emerald-200 dark:border-gray-700 overflow-hidden group animate-slide-up" style="animation-delay: 0.5s;">
                <div class="p-8 text-center">
                    <div class="mb-6 inline-block">
                        <div class="w-20 h-20 bg-gradient-to-br from-slate-500 to-blue-700 rounded-2xl flex items-center justify-center shadow-2xl transform group-hover:rotate-12 transition-all duration-300">
                            <i class="bi bi-clipboard-pulse text-white text-4xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Validação</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Validação de diagnosticos laboratoriais </p>
                </div>
                <div class="h-2 bg-gradient-to-r from-slate-500 to-gray-700 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300"></div>
            </a>

            <!-- 11. Configurações -->
            <a href="configuracoes.php" class="module-card glass-effect rounded-2xl shadow-xl border-2 border-emerald-200 dark:border-gray-700 overflow-hidden group animate-slide-up" style="animation-delay: 0.55s;">
                <div class="p-8 text-center">
                    <div class="mb-6 inline-block">
                        <div class="w-20 h-20 bg-gradient-to-br from-indigo-500 to-purple-700 rounded-2xl flex items-center justify-center shadow-2xl transform group-hover:rotate-12 transition-all duration-300">
                            <i class="bi bi-gear-fill text-white text-4xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Configurações</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Parâmetros do sistema</p>
                </div>
                <div class="h-2 bg-gradient-to-r from-indigo-500 to-purple-700 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300"></div>
            </a>

            <!-- 12. Usuários -->
            <a href="usuarios.php" class="module-card glass-effect rounded-2xl shadow-xl border-2 border-emerald-200 dark:border-gray-700 overflow-hidden group animate-slide-up" style="animation-delay: 0.6s;">
                <div class="p-8 text-center">
                    <div class="mb-6 inline-block">
                        <div class="w-20 h-20 bg-gradient-to-br from-violet-500 to-fuchsia-700 rounded-2xl flex items-center justify-center shadow-2xl transform group-hover:rotate-12 transition-all duration-300">
                            <i class="bi bi-person-badge-fill text-white text-4xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Usuários</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Gestão de acessos</p>
                </div>
                <div class="h-2 bg-gradient-to-r from-violet-500 to-fuchsia-700 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300"></div>
            </a>
        </div>

        <!-- Footer -->
        <footer class="mt-16 text-center pb-8 animate-fade-in" style="animation-delay: 0.5s;">
            <div class="glass-effect rounded-2xl px-8 py-6 inline-block shadow-2xl border-2 border-emerald-200 dark:border-gray-700">
                <div class="flex items-center justify-center space-x-3 mb-2">
                    <i class="bi bi-heart-pulse-fill text-3xl bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent"></i>
                    <h3 class="text-xl font-black bg-gradient-to-r from-emerald-600 to-teal-600 dark:from-emerald-400 dark:to-teal-400 bg-clip-text text-transparent">
                        Sistema Integrado Mais Saúde
                    </h3>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    © 2025 | Tecnologia de ponta para gestão clínica 
                </p>
            </div>
        </footer>
    </div>
</body>
</html>
