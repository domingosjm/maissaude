
<?php
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf'] ?? '')) {
        $error = 'Sessão expirada. Atualize a página.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($email === '' || $password === '') {
            $error = 'Preencha todos os campos.';
        } else {
            $stmt = $mysqli->prepare('SELECT id, name, password FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res->fetch_assoc();
            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['username'] = $email;
                
                // Carregar permissões do usuário
                if (file_exists(__DIR__ . '/permissions_helper_simple.php')) {
                    require_once __DIR__ . '/permissions_helper_simple.php';
                    loadUserPermissions($user['id']);
                }
                
                // Redirecionar baseado no email/cargo
                // Médica Evanilda vai para dashboard médico
                if ($email === 'evanilda.ziba@maissaude.co.mz') {
                    header('Location: medico_dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            } else {
                $error = 'E-mail ou senha inválidos.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Sistema Integrado Mais Saúde</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
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
                        'fade-in': 'fadeIn 0.8s ease-in-out',
                        'slide-up': 'slideUp 0.6s ease-out',
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
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect { 
            backdrop-filter: blur(16px); 
            background: rgba(255, 255, 255, 0.95); 
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }
        .dark .glass-effect { 
            background: rgba(17, 24, 39, 0.95); 
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-emerald-400 via-teal-500 to-cyan-600 dark:from-gray-900 dark:via-gray-800 dark:to-slate-900 transition-colors duration-300">
    
    <!-- Particles Background Effect -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute w-96 h-96 bg-white opacity-10 rounded-full blur-3xl -top-20 -left-20 animate-float"></div>
        <div class="absolute w-96 h-96 bg-emerald-300 opacity-10 rounded-full blur-3xl top-40 right-20 animate-float" style="animation-delay: 1s;"></div>
        <div class="absolute w-96 h-96 bg-teal-300 opacity-10 rounded-full blur-3xl bottom-20 left-40 animate-float" style="animation-delay: 2s;"></div>
    </div>

    <!-- Login Card -->
    <div class="glass-effect rounded-3xl shadow-2xl p-8 md:p-10 w-full max-w-md relative z-10 animate-slide-up border-2 border-white/20 dark:border-gray-700/50">
        
        <!-- Logo -->
        <div class="text-center mb-8 animate-fade-in">
            <div class="inline-block mb-4">
                <div class="bg-gradient-to-br from-emerald-500 via-teal-600 to-cyan-600 p-4 rounded-3xl shadow-2xl animate-float">
                    <img src="logo.png" alt="Logo Mais Saúde" class="w-24 h-24 object-contain brightness-0 invert">
                </div>
            </div>
            <h1 class="text-3xl font-black bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 dark:from-emerald-400 dark:via-teal-400 dark:to-cyan-400 bg-clip-text text-transparent mb-2">
                Mais Saúde
            </h1>
            <p class="text-gray-600 dark:text-gray-300 font-medium">Sistema Integrado de Gestão Clínica</p>
        </div>

        <!-- Welcome Message -->
        <div class="text-center mb-6 animate-fade-in" style="animation-delay: 0.2s;">
            <p class="text-gray-700 dark:text-gray-300 flex items-center justify-center">
                <i class="bi bi-shield-lock-fill text-emerald-600 dark:text-emerald-400 mr-2 text-xl"></i>
                <span class="font-semibold">Área de acesso seguro</span>
            </p>
        </div>

        <!-- Login Form -->
        <form method="post" autocomplete="off" class="space-y-5 animate-fade-in" style="animation-delay: 0.3s;">
            
            <?php if ($error): ?>
            <div class="p-4 bg-red-100 dark:bg-red-900 border-l-4 border-red-500 text-red-700 dark:text-red-200 rounded-lg animate-fade-in">
                <div class="flex items-center">
                    <i class="bi bi-exclamation-triangle-fill mr-3 text-xl"></i>
                    <p class="font-medium text-sm"><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Email Field -->
            <div>
                <label for="email" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">
                    <i class="bi bi-envelope-fill mr-2 text-emerald-600 dark:text-emerald-400"></i>
                    E-mail
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    autofocus
                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-200 dark:focus:ring-emerald-900 transition-all duration-300 outline-none"
                    placeholder="seu@email.com">
            </div>

            <!-- Password Field -->
            <div>
                <label for="password" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">
                    <i class="bi bi-lock-fill mr-2 text-emerald-600 dark:text-emerald-400"></i>
                    Senha
                </label>
                <div class="relative">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-200 dark:focus:ring-emerald-900 transition-all duration-300 outline-none"
                        placeholder="••••••••">
                    <button 
                        type="button" 
                        onclick="togglePassword()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                        <i id="password-icon" class="bi bi-eye-fill text-lg"></i>
                    </button>
                </div>
            </div>

            <!-- CSRF Token -->
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

            <!-- Submit Button -->
            <button 
                type="submit" 
                class="w-full px-6 py-4 bg-gradient-to-r from-emerald-500 via-teal-600 to-cyan-600 hover:from-emerald-600 hover:via-teal-700 hover:to-cyan-700 text-white rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-[1.02] font-bold text-lg">
                <i class="bi bi-box-arrow-in-right mr-2"></i>
                Entrar no Sistema
            </button>
        </form>

        <!-- Dark Mode Toggle -->
        <div class="mt-6 text-center">
            <button 
                onclick="toggleDarkMode()" 
                class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-300 text-gray-700 dark:text-gray-300 font-medium text-sm">
                <i class="bi bi-moon-stars dark:bi-sun mr-2"></i>
                <span class="dark:hidden">Modo Escuro</span>
                <span class="hidden dark:inline">Modo Claro</span>
            </button>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-xs text-gray-500 dark:text-gray-400">
            <p>
                <i class="bi bi-shield-check text-emerald-600 dark:text-emerald-400 mr-1"></i>
                Conexão segura e criptografada
            </p>
            <p class="mt-2">© 2025 Mais Saúde, LDA. Todos os direitos reservados.</p>
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

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('bi-eye-fill');
                passwordIcon.classList.add('bi-eye-slash-fill');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('bi-eye-slash-fill');
                passwordIcon.classList.add('bi-eye-fill');
            }
        }
    </script>
</body>
</html>
