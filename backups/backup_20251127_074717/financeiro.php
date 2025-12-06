<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$erro = '';
$sucesso = '';
$username = $_SESSION['username'] ?? 'Usuário';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    // Criar Nova Fatura
    if ($acao === 'criar_fatura') {
        $patient_id = (int)$_POST['patient_id'];
        $issue_date = $_POST['issue_date'];
        $payment_terms = (int)($_POST['payment_terms'] ?? 30);
        $due_date = date('Y-m-d', strtotime($issue_date . " + $payment_terms days"));
        $discount_percent = (float)($_POST['discount_percent'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        
        // Gerar número da fatura
        $prefix_result = $mysqli->query("SELECT setting_value FROM financial_settings WHERE setting_key = 'invoice_prefix'");
        $prefix = $prefix_result->fetch_assoc()['setting_value'] ?? 'FT';
        $year = date('Y');
        $last_invoice = $mysqli->query("SELECT invoice_number FROM invoices WHERE invoice_number LIKE '$prefix$year%' ORDER BY id DESC LIMIT 1");
        if ($last_invoice && $last_invoice->num_rows > 0) {
            $last_num = $last_invoice->fetch_assoc()['invoice_number'];
            $num = (int)substr($last_num, -6) + 1;
        } else {
            $num = 1;
        }
        $invoice_number = $prefix . $year . str_pad($num, 6, '0', STR_PAD_LEFT);
        
        // Calcular totais
        $subtotal = 0;
        $items = $_POST['items'] ?? [];
        foreach ($items as $item) {
            if (!empty($item['description']) && !empty($item['quantity']) && !empty($item['unit_price'])) {
                $qty = (int)$item['quantity'];
                $price = (float)$item['unit_price'];
                $item_discount = (float)($item['discount'] ?? 0);
                $subtotal += ($qty * $price) - $item_discount;
            }
        }
        
        $discount = ($subtotal * $discount_percent) / 100;
        $tax_rate_result = $mysqli->query("SELECT setting_value FROM financial_settings WHERE setting_key = 'tax_rate'");
        $tax_rate = (float)($tax_rate_result->fetch_assoc()['setting_value'] ?? 0);
        $tax = (($subtotal - $discount) * $tax_rate) / 100;
        $total = $subtotal - $discount + $tax;
        
        $stmt = $mysqli->prepare("INSERT INTO invoices (invoice_number, patient_id, issue_date, due_date, status, subtotal, discount, discount_percent, tax, total, amount_paid, amount_due, notes, created_by) VALUES (?, ?, ?, ?, 'pendente', ?, ?, ?, ?, ?, 0, ?, ?, ?)");
        $stmt->bind_param('sissdddddds', $invoice_number, $patient_id, $issue_date, $due_date, $subtotal, $discount, $discount_percent, $tax, $total, $total, $notes, $username);
        
        if ($stmt->execute()) {
            $invoice_id = $stmt->insert_id;
            
            // Inserir itens
            foreach ($items as $item) {
                if (!empty($item['description']) && !empty($item['quantity']) && !empty($item['unit_price'])) {
                    $service_id = !empty($item['service_id']) ? (int)$item['service_id'] : null;
                    $description = $item['description'];
                    $qty = (int)$item['quantity'];
                    $price = (float)$item['unit_price'];
                    $item_discount = (float)($item['discount'] ?? 0);
                    $item_subtotal = ($qty * $price) - $item_discount;
                    
                    $stmt2 = $mysqli->prepare("INSERT INTO invoice_items (invoice_id, service_id, description, quantity, unit_price, discount, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt2->bind_param('iisiddd', $invoice_id, $service_id, $description, $qty, $price, $item_discount, $item_subtotal);
                    $stmt2->execute();
                }
            }
            
            // Registrar no fluxo de caixa
            $mysqli->query("INSERT INTO cash_flow (type, category, description, amount, transaction_date, reference_type, reference_id, created_by) VALUES ('entrada', 'Faturamento', 'Fatura $invoice_number criada', $total, NOW(), 'invoice', $invoice_id, '$username')");
            
            $sucesso = "Fatura $invoice_number criada com sucesso!";
        } else {
            $erro = "Erro ao criar fatura: " . $stmt->error;
        }
    }
    
    // Registrar Pagamento
    if ($acao === 'registrar_pagamento') {
        $invoice_id = (int)$_POST['invoice_id'];
        $amount = (float)$_POST['amount'];
        $payment_method = $_POST['payment_method'];
        $payment_date = $_POST['payment_date'] . ' ' . date('H:i:s');
        $reference = $_POST['reference'] ?? '';
        $payment_notes = $_POST['payment_notes'] ?? '';
        
        // Validar dados
        if ($invoice_id <= 0) {
            $erro = "ID de fatura inválido!";
        } elseif ($amount <= 0) {
            $erro = "Valor do pagamento deve ser maior que zero!";
        } else {
            // Verificar se a fatura existe
            $invoice_check = $mysqli->query("SELECT id, total, amount_paid, amount_due, status FROM invoices WHERE id = $invoice_id");
            
            if ($invoice_check->num_rows == 0) {
                $erro = "Fatura não encontrada!";
            } else {
                $invoice = $invoice_check->fetch_assoc();
                
                // Verificar se o valor não excede o pendente
                if ($amount > $invoice['amount_due']) {
                    $erro = "Valor do pagamento (" . number_format($amount, 2) . " MT) excede o valor pendente (" . number_format($invoice['amount_due'], 2) . " MT)!";
                } else {
                    $stmt = $mysqli->prepare("INSERT INTO payments (invoice_id, payment_date, amount, payment_method, reference, notes, received_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('isdssss', $invoice_id, $payment_date, $amount, $payment_method, $reference, $payment_notes, $username);
                    
                    if ($stmt->execute()) {
                        $payment_id = $mysqli->insert_id;
                        
                        // Atualizar fatura
                        $new_amount_paid = $invoice['amount_paid'] + $amount;
                        $new_amount_due = $invoice['total'] - $new_amount_paid;
                        
                        if ($new_amount_due <= 0.01) { // Tolerância para arredondamento
                            $new_status = 'paga';
                            $new_amount_due = 0;
                        } elseif ($new_amount_paid > 0) {
                            $new_status = 'parcial';
                        } else {
                            $new_status = 'pendente';
                        }
                        
                        $mysqli->query("UPDATE invoices SET amount_paid = $new_amount_paid, amount_due = $new_amount_due, status = '$new_status' WHERE id = $invoice_id");
                        
                        // Registrar no fluxo de caixa
                        $invoice_number = $mysqli->query("SELECT invoice_number FROM invoices WHERE id = $invoice_id")->fetch_assoc()['invoice_number'];
                        $mysqli->query("INSERT INTO cash_flow (type, category, description, amount, transaction_date, payment_method, reference_type, reference_id, created_by) VALUES ('entrada', 'Pagamento', 'Pagamento fatura $invoice_number', $amount, '$payment_date', '$payment_method', 'payment', $payment_id, '$username')");
                        
                        $sucesso = "Pagamento de " . number_format($amount, 2) . " MT registrado com sucesso! Fatura: $invoice_number";
                    } else {
                        $erro = "Erro ao registrar pagamento: " . $stmt->error;
                    }
                }
            }
        }
    }
    
    // Registrar Despesa
    if ($acao === 'registrar_despesa') {
        $category = $_POST['expense_category'];
        $description = $_POST['expense_description'];
        $amount = (float)$_POST['expense_amount'];
        $expense_date = $_POST['expense_date'];
        $payment_method = $_POST['expense_payment_method'];
        $supplier = $_POST['supplier'] ?? '';
        $reference = $_POST['expense_reference'] ?? '';
        $expense_notes = $_POST['expense_notes'] ?? '';
        $status = $_POST['expense_status'] ?? 'paga';
        
        $stmt = $mysqli->prepare("INSERT INTO expenses (category, description, amount, expense_date, payment_method, supplier, reference, notes, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssdsssssss', $category, $description, $amount, $expense_date, $payment_method, $supplier, $reference, $expense_notes, $status, $username);
        
        if ($stmt->execute()) {
            $expense_id = $stmt->insert_id;
            
            // Registrar no fluxo de caixa se foi paga
            if ($status === 'paga') {
                $mysqli->query("INSERT INTO cash_flow (type, category, description, amount, transaction_date, payment_method, reference_type, reference_id, created_by) VALUES ('saida', '$category', '$description', $amount, '$expense_date " . date('H:i:s') . "', '$payment_method', 'expense', $expense_id, '$username')");
            }
            
            $sucesso = "Despesa registrada com sucesso!";
        } else {
            $erro = "Erro ao registrar despesa: " . $stmt->error;
        }
    }
    
    // Criar/Editar Serviço
    if ($acao === 'salvar_servico') {
        $service_id = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
        $name = trim($_POST['service_name'] ?? '');
        $code = !empty($_POST['service_code']) ? trim($_POST['service_code']) : null;
        $category = $_POST['service_category'] ?? 'outros';
        $description = $_POST['service_description'] ?? '';
        $price = (float)($_POST['service_price'] ?? 0);
        $cost = (float)($_POST['service_cost'] ?? 0);
        $tax_rate = isset($_POST['service_tax_rate']) ? (float)$_POST['service_tax_rate'] : 16;
        $active = 1; // Sempre ativo ao salvar
        
        // Validar dados
        if (empty($name)) {
            $erro = "Nome do serviço é obrigatório!";
        } elseif ($price < 0) {
            $erro = "Preço não pode ser negativo!";
        } else {
            // Verificar se o código já existe (para outro serviço)
            $erro_codigo = false;
            if (!empty($code)) {
                $check_id = $service_id ?? 0;
                $check_code = $mysqli->prepare("SELECT id FROM services WHERE code = ? AND id != ?");
                $check_code->bind_param('si', $code, $check_id);
                $check_code->execute();
                if ($check_code->get_result()->num_rows > 0) {
                    $erro = "Código já existe para outro serviço!";
                    $erro_codigo = true;
                }
            }
            
            if (!$erro_codigo) {
                if ($service_id && $service_id > 0) {
                    // Atualizar serviço existente
                    $stmt = $mysqli->prepare("UPDATE services SET name = ?, code = ?, category = ?, description = ?, price = ?, cost = ?, tax_rate = ?, active = ? WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param('ssssdddii', $name, $code, $category, $description, $price, $cost, $tax_rate, $active, $service_id);
                        if ($stmt->execute()) {
                            $sucesso = "Serviço atualizado com sucesso!";
                            
                            // Se for medicamento, atualizar também na tabela medications
                            if ($category === 'medicamento') {
                                $check_med = $mysqli->query("SELECT id FROM medications WHERE name = '" . $mysqli->real_escape_string($name) . "'");
                                if ($check_med && $check_med->num_rows > 0) {
                                    // Atualizar medicamento existente
                                    $med_id = $check_med->fetch_assoc()['id'];
                                    $mysqli->query("UPDATE medications SET price = $price, description = '" . $mysqli->real_escape_string($description) . "' WHERE id = $med_id");
                                }
                            }
                        } else {
                            $erro = "Erro ao atualizar serviço: " . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $erro = "Erro ao preparar statement: " . $mysqli->error;
                    }
                } else {
                    // Criar novo serviço
                    $stmt = $mysqli->prepare("INSERT INTO services (name, code, category, description, price, cost, tax_rate, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param('ssssdddi', $name, $code, $category, $description, $price, $cost, $tax_rate, $active);
                        if ($stmt->execute()) {
                            $sucesso = "Serviço criado com sucesso!";
                            $new_service_id = $stmt->insert_id;
                            
                            // Se for medicamento, adicionar também na tabela medications para gestão de stock
                            if ($category === 'medicamento') {
                                // Verificar se já existe medicamento com mesmo nome
                                $check_med = $mysqli->query("SELECT id FROM medications WHERE name = '" . $mysqli->real_escape_string($name) . "'");
                                if (!$check_med || $check_med->num_rows === 0) {
                                    // Criar novo medicamento no estoque
                                    $stmt_med = $mysqli->prepare("INSERT INTO medications (name, category, description, price, stock_quantity, minimum_stock) VALUES (?, 'Medicamento', ?, ?, 0, 10)");
                                    if ($stmt_med) {
                                        $stmt_med->bind_param('ssd', $name, $description, $price);
                                        $stmt_med->execute();
                                        $stmt_med->close();
                                        $sucesso .= " Medicamento adicionado ao estoque com quantidade inicial 0.";
                                    }
                                }
                            }
                        } else {
                            $erro = "Erro ao criar serviço: " . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $erro = "Erro ao preparar statement: " . $mysqli->error;
                    }
                }
            }
        }
    }
    
    // Desativar/Ativar Serviço
    if ($acao === 'toggle_servico') {
        $service_id = (int)$_POST['service_id'];
        $current_status = (int)$_POST['current_status'];
        $new_status = $current_status ? 0 : 1;
        
        $mysqli->query("UPDATE services SET active = $new_status WHERE id = $service_id");
        $sucesso = $new_status ? "Serviço ativado!" : "Serviço desativado!";
    }
    
    // Excluir Serviço
    if ($acao === 'excluir_servico') {
        $service_id = (int)$_POST['service_id'];
        
        // Verificar se o serviço está sendo usado
        $em_uso = $mysqli->query("SELECT COUNT(*) as total FROM invoice_items WHERE service_id = $service_id")->fetch_assoc()['total'];
        
        if ($em_uso > 0) {
            $erro = "Este serviço não pode ser excluído pois está sendo usado em faturas. Desative-o ao invés disso.";
        } else {
            $mysqli->query("DELETE FROM services WHERE id = $service_id");
            $sucesso = "Serviço excluído com sucesso!";
        }
    }
    
    // Cancelar Fatura
    if ($acao === 'cancelar_fatura') {
        $invoice_id = (int)$_POST['invoice_id'];
        
        // Verificar se tem pagamentos
        $tem_pagamento = $mysqli->query("SELECT COUNT(*) as total FROM payments WHERE invoice_id = $invoice_id")->fetch_assoc()['total'];
        
        if ($tem_pagamento > 0) {
            $erro = "Esta fatura não pode ser cancelada pois já possui pagamentos registrados.";
        } else {
            $mysqli->query("UPDATE invoices SET status = 'cancelada' WHERE id = $invoice_id");
            $mysqli->query("DELETE FROM cash_flow WHERE reference_type = 'invoice' AND reference_id = $invoice_id");
            $sucesso = "Fatura cancelada com sucesso!";
        }
    }
    
    // Editar Despesa
    if ($acao === 'editar_despesa') {
        $expense_id = (int)$_POST['expense_id'];
        $category = $_POST['expense_category'];
        $description = $_POST['expense_description'];
        $amount = (float)$_POST['expense_amount'];
        $expense_date = $_POST['expense_date'];
        $payment_method = $_POST['expense_payment_method'];
        $supplier = $_POST['supplier'] ?? '';
        $reference = $_POST['expense_reference'] ?? '';
        $expense_notes = $_POST['expense_notes'] ?? '';
        $status = $_POST['expense_status'] ?? 'paga';
        
        $stmt = $mysqli->prepare("UPDATE expenses SET category = ?, description = ?, amount = ?, expense_date = ?, payment_method = ?, supplier = ?, reference = ?, notes = ?, status = ? WHERE id = ?");
        $stmt->bind_param('ssdssssssi', $category, $description, $amount, $expense_date, $payment_method, $supplier, $reference, $expense_notes, $status, $expense_id);
        
        if ($stmt->execute()) {
            // Atualizar fluxo de caixa
            $mysqli->query("UPDATE cash_flow SET category = '$category', description = '$description', amount = $amount, transaction_date = '$expense_date' WHERE reference_type = 'expense' AND reference_id = $expense_id");
            $sucesso = "Despesa atualizada com sucesso!";
        } else {
            $erro = "Erro ao atualizar despesa: " . $stmt->error;
        }
    }
    
    // Excluir Despesa
    if ($acao === 'excluir_despesa') {
        $expense_id = (int)$_POST['expense_id'];
        
        $mysqli->query("DELETE FROM expenses WHERE id = $expense_id");
        $mysqli->query("DELETE FROM cash_flow WHERE reference_type = 'expense' AND reference_id = $expense_id");
        $sucesso = "Despesa excluída com sucesso!";
    }
}

// Buscar dados do dashboard
$hoje = date('Y-m-d');
$mes_atual = date('Y-m');
$ano_atual = date('Y');

// Receitas do mês
$receitas_mes = $mysqli->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM invoices WHERE DATE_FORMAT(issue_date, '%Y-%m') = '$mes_atual'")->fetch_assoc()['total'];

// Despesas do mês
$despesas_mes = $mysqli->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = '$mes_atual' AND status = 'paga'")->fetch_assoc()['total'];

// Faturas pendentes
$faturas_pendentes = $mysqli->query("SELECT COUNT(*) as total FROM invoices WHERE status IN ('pendente', 'parcial')")->fetch_assoc()['total'];

// Total a receber
$total_receber = $mysqli->query("SELECT COALESCE(SUM(amount_due), 0) as total FROM invoices WHERE status IN ('pendente', 'parcial')")->fetch_assoc()['total'];

// Receitas hoje
$receitas_hoje = $mysqli->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE(payment_date) = '$hoje'")->fetch_assoc()['total'];

// Despesas hoje
$despesas_hoje = $mysqli->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE expense_date = '$hoje' AND status = 'paga'")->fetch_assoc()['total'];

// Buscar serviços ativos
$servicos = $mysqli->query("SELECT * FROM services WHERE active = 1 ORDER BY category, name");

// Buscar pacientes
$pacientes = $mysqli->query("SELECT id, name, codigo FROM patients ORDER BY name LIMIT 100");

?>
<!DOCTYPE html>
<html lang="pt-br" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financeiro | Integrada Mais Saúde</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .animate-fadeIn { animation: fadeIn 0.6s ease-out; }
        .animate-slideIn { animation: slideIn 0.5s ease-out; }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .dark .glass-effect {
            background: rgba(31, 41, 55, 0.95);
        }
        
        .hover-lift {
            transition: all 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .gradient-gold {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 50%, #b45309 100%);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-orange-50 via-amber-50 to-yellow-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen">
    
    <!-- Navbar -->
    <nav class="glass-effect border-b border-orange-200 dark:border-gray-700 sticky top-0 z-50 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="flex items-center space-x-3 hover:opacity-80 transition-opacity">
                        <div class="w-12 h-12 gradient-gold rounded-xl flex items-center justify-center shadow-lg">
                            <i class="bi bi-cash-coin text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-800 dark:text-white">Integrada Mais Saúde</h1>
                            <p class="text-xs text-orange-600 dark:text-orange-400 font-medium">Módulo Financeiro</p>
                        </div>
                    </a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <button onclick="toggleDarkMode()" class="p-3 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-all duration-300">
                        <i class="bi bi-moon-stars-fill dark:bi-sun-fill text-gray-700 dark:text-yellow-400 text-xl"></i>
                    </button>
                    <div class="flex items-center space-x-3 px-4 py-2 rounded-xl bg-orange-50 dark:bg-orange-900/30">
                        <i class="bi bi-person-circle text-orange-600 dark:text-orange-400 text-2xl"></i>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200"><?= htmlspecialchars($username) ?></span>
                    </div>
                    <a href="logout.php" class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                        <i class="bi bi-box-arrow-right mr-2"></i>Sair
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Container Principal -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <?php if ($erro): ?>
            <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg animate-fadeIn">
                <div class="flex items-center">
                    <i class="bi bi-exclamation-triangle-fill text-2xl mr-3"></i>
                    <p><?= htmlspecialchars($erro) ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($sucesso): ?>
            <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg animate-fadeIn">
                <div class="flex items-center">
                    <i class="bi bi-check-circle-fill text-2xl mr-3"></i>
                    <p><?= htmlspecialchars($sucesso) ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Cards de Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Receitas do Mês -->
            <div class="glass-effect rounded-2xl shadow-xl border border-green-200 dark:border-gray-700 p-6 hover-lift animate-fadeIn">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Receitas do Mês</p>
                        <p class="text-3xl font-bold text-green-600 dark:text-green-400"><?= number_format($receitas_mes, 2, ',', '.') ?> MT</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            <i class="bi bi-calendar-month mr-1"></i><?= date('F Y') ?>
                        </p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="bi bi-arrow-up-circle-fill text-white text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Despesas do Mês -->
            <div class="glass-effect rounded-2xl shadow-xl border border-red-200 dark:border-gray-700 p-6 hover-lift animate-fadeIn" style="animation-delay: 0.1s;">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Despesas do Mês</p>
                        <p class="text-3xl font-bold text-red-600 dark:text-red-400"><?= number_format($despesas_mes, 2, ',', '.') ?> MT</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            <i class="bi bi-calendar-month mr-1"></i><?= date('F Y') ?>
                        </p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-rose-600 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="bi bi-arrow-down-circle-fill text-white text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Saldo do Mês -->
            <div class="glass-effect rounded-2xl shadow-xl border border-blue-200 dark:border-gray-700 p-6 hover-lift animate-fadeIn" style="animation-delay: 0.2s;">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Saldo do Mês</p>
                        <p class="text-3xl font-bold <?= ($receitas_mes - $despesas_mes) >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400' ?>">
                            <?= number_format($receitas_mes - $despesas_mes, 2, ',', '.') ?> MT
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            <i class="bi bi-graph-up mr-1"></i>Resultado Líquido
                        </p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="bi bi-wallet2 text-white text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Total a Receber -->
            <div class="glass-effect rounded-2xl shadow-xl border border-amber-200 dark:border-gray-700 p-6 hover-lift animate-fadeIn" style="animation-delay: 0.3s;">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Total a Receber</p>
                        <p class="text-3xl font-bold text-amber-600 dark:text-amber-400"><?= number_format($total_receber, 2, ',', '.') ?> MT</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            <i class="bi bi-hourglass-split mr-1"></i><?= $faturas_pendentes ?> faturas pendentes
                        </p>
                    </div>
                    <div class="w-16 h-16 gradient-gold rounded-xl flex items-center justify-center shadow-lg">
                        <i class="bi bi-clock-history text-white text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Receitas Hoje -->
            <div class="glass-effect rounded-2xl shadow-xl border border-teal-200 dark:border-gray-700 p-6 hover-lift animate-fadeIn" style="animation-delay: 0.4s;">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Receitas Hoje</p>
                        <p class="text-3xl font-bold text-teal-600 dark:text-teal-400"><?= number_format($receitas_hoje, 2, ',', '.') ?> MT</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            <i class="bi bi-calendar-day mr-1"></i><?= date('d/m/Y') ?>
                        </p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="bi bi-cash-stack text-white text-3xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Despesas Hoje -->
            <div class="glass-effect rounded-2xl shadow-xl border border-purple-200 dark:border-gray-700 p-6 hover-lift animate-fadeIn" style="animation-delay: 0.5s;">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Despesas Hoje</p>
                        <p class="text-3xl font-bold text-purple-600 dark:text-purple-400"><?= number_format($despesas_hoje, 2, ',', '.') ?> MT</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            <i class="bi bi-calendar-day mr-1"></i><?= date('d/m/Y') ?>
                        </p>
                    </div>
                    <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-violet-600 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="bi bi-cart3 text-white text-3xl"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Abas de Navegação -->
        <div class="glass-effect rounded-2xl shadow-xl border border-orange-200 dark:border-gray-700 overflow-hidden mb-8">
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex space-x-2 p-4 overflow-x-auto">
                    <button onclick="switchTab('dashboard')" id="tab-dashboard" class="tab-button px-6 py-3 rounded-lg font-semibold transition-all duration-300 bg-gradient-to-r from-orange-500 to-amber-500 text-white shadow-md">
                        <i class="bi bi-speedometer2 mr-2"></i>Dashboard
                    </button>
                    <button onclick="switchTab('faturas')" id="tab-faturas" class="tab-button px-6 py-3 rounded-lg font-semibold transition-all duration-300 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <i class="bi bi-file-earmark-text mr-2"></i>Faturas
                    </button>
                    <button onclick="switchTab('pagamentos')" id="tab-pagamentos" class="tab-button px-6 py-3 rounded-lg font-semibold transition-all duration-300 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <i class="bi bi-credit-card mr-2"></i>Pagamentos
                    </button>
                    <button onclick="switchTab('despesas')" id="tab-despesas" class="tab-button px-6 py-3 rounded-lg font-semibold transition-all duration-300 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <i class="bi bi-cart-dash mr-2"></i>Despesas
                    </button>
                    <button onclick="switchTab('servicos')" id="tab-servicos" class="tab-button px-6 py-3 rounded-lg font-semibold transition-all duration-300 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <i class="bi bi-list-check mr-2"></i>Serviços
                    </button>
                    <button onclick="switchTab('relatorios')" id="tab-relatorios" class="tab-button px-6 py-3 rounded-lg font-semibold transition-all duration-300 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <i class="bi bi-graph-up-arrow mr-2"></i>Relatórios
                    </button>
                </nav>
            </div>
            
            <!-- Conteúdo das Abas -->
            <div class="p-6">
                <!-- Dashboard -->
                <div id="content-dashboard" class="tab-content">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                        <i class="bi bi-speedometer2 mr-3 text-orange-600"></i>
                        Dashboard Financeiro
                    </h2>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Gráfico de Receitas vs Despesas -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Receitas vs Despesas (Últimos 6 Meses)</h3>
                            <canvas id="chartReceitasDespesas"></canvas>
                        </div>
                        
                        <!-- Gráfico de Métodos de Pagamento -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Métodos de Pagamento (Este Mês)</h3>
                            <canvas id="chartMetodosPagamento"></canvas>
                        </div>
                    </div>
                    
                    <!-- Faturas Recentes -->
                    <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Faturas Recentes</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600 dark:text-gray-400">Nº Fatura</th>
                                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600 dark:text-gray-400">Paciente</th>
                                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600 dark:text-gray-400">Data</th>
                                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600 dark:text-gray-400">Total</th>
                                        <th class="text-center py-3 px-4 text-sm font-semibold text-gray-600 dark:text-gray-400">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $faturas_recentes = $mysqli->query("SELECT i.*, p.name as patient_name FROM invoices i JOIN patients p ON i.patient_id = p.id ORDER BY i.created_at DESC LIMIT 10");
                                    while ($fatura = $faturas_recentes->fetch_assoc()):
                                        $status_colors = [
                                            'pendente' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                            'paga' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                            'parcial' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                            'cancelada' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                            'vencida' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
                                        ];
                                        $status_class = $status_colors[$fatura['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                        <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="py-3 px-4 font-mono text-sm"><?= htmlspecialchars($fatura['invoice_number']) ?></td>
                                            <td class="py-3 px-4 text-sm"><?= htmlspecialchars($fatura['patient_name']) ?></td>
                                            <td class="py-3 px-4 text-sm"><?= date('d/m/Y', strtotime($fatura['issue_date'])) ?></td>
                                            <td class="py-3 px-4 text-sm text-right font-semibold"><?= number_format($fatura['total'], 2, ',', '.') ?> MT</td>
                                            <td class="py-3 px-4 text-center">
                                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $status_class ?>">
                                                    <?= ucfirst($fatura['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Faturas -->
                <div id="content-faturas" class="tab-content hidden">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center">
                            <i class="bi bi-file-earmark-text mr-3 text-orange-600"></i>
                            Gestão de Faturas
                        </h2>
                        <button onclick="openModal('modalNovaFatura')" class="px-6 py-3 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 font-semibold">
                            <i class="bi bi-plus-circle mr-2"></i>Nova Fatura
                        </button>
                    </div>
                    
                    <!-- Filtros -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                                <select id="filtro-status-fatura" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                                    <option value="">Todos</option>
                                    <option value="pendente">Pendente</option>
                                    <option value="parcial">Parcial</option>
                                    <option value="paga">Paga</option>
                                    <option value="cancelada">Cancelada</option>
                                    <option value="vencida">Vencida</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Data Início</label>
                                <input type="date" id="filtro-data-inicio" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Data Fim</label>
                                <input type="date" id="filtro-data-fim" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                            </div>
                            <div class="flex items-end">
                                <button onclick="filtrarFaturas()" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-all duration-300">
                                    <i class="bi bi-search mr-2"></i>Filtrar
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lista de Faturas -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Nº Fatura</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Paciente</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Data Emissão</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Vencimento</th>
                                        <th class="text-right py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Total</th>
                                        <th class="text-right py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Pago</th>
                                        <th class="text-right py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Pendente</th>
                                        <th class="text-center py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Status</th>
                                        <th class="text-center py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $todas_faturas = $mysqli->query("SELECT i.*, p.name as patient_name, p.codigo FROM invoices i JOIN patients p ON i.patient_id = p.id ORDER BY i.created_at DESC LIMIT 50");
                                    while ($fat = $todas_faturas->fetch_assoc()):
                                        $status_colors = [
                                            'pendente' => 'bg-yellow-100 text-yellow-800',
                                            'paga' => 'bg-green-100 text-green-800',
                                            'parcial' => 'bg-blue-100 text-blue-800',
                                            'cancelada' => 'bg-red-100 text-red-800',
                                            'vencida' => 'bg-red-100 text-red-800'
                                        ];
                                    ?>
                                        <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="py-4 px-6 font-mono text-sm font-semibold"><?= htmlspecialchars($fat['invoice_number']) ?></td>
                                            <td class="py-4 px-6 text-sm">
                                                <div class="font-medium"><?= htmlspecialchars($fat['patient_name']) ?></div>
                                                <div class="text-xs text-gray-500"><?= htmlspecialchars($fat['codigo']) ?></div>
                                            </td>
                                            <td class="py-4 px-6 text-sm"><?= date('d/m/Y', strtotime($fat['issue_date'])) ?></td>
                                            <td class="py-4 px-6 text-sm"><?= date('d/m/Y', strtotime($fat['due_date'])) ?></td>
                                            <td class="py-4 px-6 text-sm text-right font-semibold"><?= number_format($fat['total'], 2, ',', '.') ?> MT</td>
                                            <td class="py-4 px-6 text-sm text-right text-green-600"><?= number_format($fat['amount_paid'], 2, ',', '.') ?> MT</td>
                                            <td class="py-4 px-6 text-sm text-right text-red-600"><?= number_format($fat['amount_due'], 2, ',', '.') ?> MT</td>
                                            <td class="py-4 px-6 text-center">
                                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $status_colors[$fat['status']] ?>">
                                                    <?= ucfirst($fat['status']) ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6 text-center">
                                                <div class="flex items-center justify-center space-x-2">
                                                    <button onclick="verFatura(<?= $fat['id'] ?>)" class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition-all" title="Ver Detalhes">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <?php if ($fat['status'] !== 'paga' && $fat['status'] !== 'cancelada'): ?>
                                                        <button onclick="registrarPagamentoFatura(<?= $fat['id'] ?>, '<?= htmlspecialchars($fat['invoice_number']) ?>', <?= $fat['total'] ?>, <?= $fat['amount_paid'] ?>)" class="p-2 text-green-600 hover:bg-green-100 rounded-lg transition-all" title="Registrar Pagamento">
                                                            <i class="bi bi-cash-stack"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button onclick="imprimirFatura(<?= $fat['id'] ?>)" class="p-2 text-purple-600 hover:bg-purple-100 rounded-lg transition-all" title="Imprimir">
                                                        <i class="bi bi-printer"></i>
                                                    </button>
                                                    <?php if ($fat['status'] !== 'cancelada' && $fat['amount_paid'] == 0): ?>
                                                        <button onclick="cancelarFatura(<?= $fat['id'] ?>, '<?= htmlspecialchars($fat['invoice_number']) ?>')" class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition-all" title="Cancelar Fatura">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Pagamentos -->
                <div id="content-pagamentos" class="tab-content hidden">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center">
                            <i class="bi bi-credit-card mr-3 text-orange-600"></i>
                            Histórico de Pagamentos
                        </h2>
                    </div>
                    
                    <!-- Lista de Pagamentos -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Data</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Nº Fatura</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Paciente</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Método</th>
                                        <th class="text-right py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Valor</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Referência</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Recebido por</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $pagamentos = $mysqli->query("
                                        SELECT pm.*, i.invoice_number, p.name as patient_name 
                                        FROM payments pm 
                                        JOIN invoices i ON pm.invoice_id = i.id 
                                        JOIN patients p ON i.patient_id = p.id 
                                        ORDER BY pm.payment_date DESC 
                                        LIMIT 100
                                    ");
                                    while ($pag = $pagamentos->fetch_assoc()):
                                        $metodo_icons = [
                                            'dinheiro' => 'bi-cash',
                                            'mpesa' => 'bi-phone',
                                            'emola' => 'bi-phone',
                                            'mkesh' => 'bi-phone',
                                            'ponto24' => 'bi-credit-card',
                                            'multicaixa' => 'bi-credit-card',
                                            'visa' => 'bi-credit-card-2-front',
                                            'mastercard' => 'bi-credit-card-2-front',
                                            'transferencia_bancaria' => 'bi-bank',
                                            'cheque' => 'bi-receipt',
                                            'outros' => 'bi-three-dots'
                                        ];
                                        $icon = $metodo_icons[$pag['payment_method']] ?? 'bi-cash';
                                    ?>
                                        <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="py-4 px-6 text-sm"><?= date('d/m/Y H:i', strtotime($pag['payment_date'])) ?></td>
                                            <td class="py-4 px-6 font-mono text-sm"><?= htmlspecialchars($pag['invoice_number']) ?></td>
                                            <td class="py-4 px-6 text-sm"><?= htmlspecialchars($pag['patient_name']) ?></td>
                                            <td class="py-4 px-6 text-sm">
                                                <span class="inline-flex items-center">
                                                    <i class="bi <?= $icon ?> mr-2"></i>
                                                    <?= ucfirst(str_replace('_', ' ', $pag['payment_method'])) ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6 text-sm text-right font-bold text-green-600"><?= number_format($pag['amount'], 2, ',', '.') ?> MT</td>
                                            <td class="py-4 px-6 text-sm text-gray-600"><?= htmlspecialchars($pag['reference'] ?? '-') ?></td>
                                            <td class="py-4 px-6 text-sm"><?= htmlspecialchars($pag['received_by']) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Despesas -->
                <div id="content-despesas" class="tab-content hidden">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center">
                            <i class="bi bi-cart-dash mr-3 text-orange-600"></i>
                            Gestão de Despesas
                        </h2>
                        <button onclick="openModal('modalNovaDespesa')" class="px-6 py-3 bg-gradient-to-r from-red-500 to-rose-500 hover:from-red-600 hover:to-rose-600 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 font-semibold">
                            <i class="bi bi-plus-circle mr-2"></i>Nova Despesa
                        </button>
                    </div>
                    
                    <!-- Lista de Despesas -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Data</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Categoria</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Descrição</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Fornecedor</th>
                                        <th class="text-right py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Valor</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Método</th>
                                        <th class="text-center py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Status</th>
                                        <th class="text-center py-4 px-6 text-sm font-semibold text-gray-700 dark:text-gray-300">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $despesas_list = $mysqli->query("SELECT * FROM expenses ORDER BY expense_date DESC LIMIT 100");
                                    while ($desp = $despesas_list->fetch_assoc()):
                                        $cat_colors = [
                                            'salarios' => 'text-purple-600',
                                            'fornecedores' => 'text-blue-600',
                                            'aluguel' => 'text-yellow-600',
                                            'utilidades' => 'text-teal-600',
                                            'equipamentos' => 'text-indigo-600',
                                            'manutencao' => 'text-orange-600',
                                            'marketing' => 'text-pink-600',
                                            'impostos' => 'text-red-600',
                                            'outros' => 'text-gray-600'
                                        ];
                                    ?>
                                        <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="py-4 px-6 text-sm"><?= date('d/m/Y', strtotime($desp['expense_date'])) ?></td>
                                            <td class="py-4 px-6 text-sm">
                                                <span class="font-semibold <?= $cat_colors[$desp['category']] ?>">
                                                    <?= ucfirst($desp['category']) ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6 text-sm"><?= htmlspecialchars($desp['description']) ?></td>
                                            <td class="py-4 px-6 text-sm"><?= htmlspecialchars($desp['supplier'] ?? '-') ?></td>
                                            <td class="py-4 px-6 text-sm text-right font-bold text-red-600"><?= number_format($desp['amount'], 2, ',', '.') ?> MT</td>
                                            <td class="py-4 px-6 text-sm"><?= ucfirst(str_replace('_', ' ', $desp['payment_method'])) ?></td>
                                            <td class="py-4 px-6 text-center">
                                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $desp['status'] === 'paga' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                                    <?= ucfirst($desp['status']) ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6 text-center">
                                                <div class="flex items-center justify-center space-x-2">
                                                    <button onclick='editarDespesa(<?= json_encode($desp) ?>)' class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition-all" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button onclick="excluirDespesa(<?= $desp['id'] ?>)" class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition-all" title="Excluir">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Serviços -->
                <div id="content-servicos" class="tab-content hidden">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center">
                            <i class="bi bi-list-check mr-3 text-orange-600"></i>
                            Catálogo de Serviços
                        </h2>
                        <div class="flex gap-3 w-full md:w-auto">
                            <div class="relative flex-1 md:w-80">
                                <i class="bi bi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input type="text" id="busca-servico" onkeyup="filtrarServicos()" placeholder="Buscar serviços..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500">
                            </div>
                            <button onclick="openModal('modalNovoServico')" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 font-semibold whitespace-nowrap">
                                <i class="bi bi-plus-circle mr-2"></i>Novo Serviço
                            </button>
                        </div>
                    </div>
                    
                    <!-- Grid de Serviços por Categoria -->
                    <?php
                    $categorias = ['consulta', 'exame', 'procedimento', 'medicamento', 'outros'];
                    $cat_labels = [
                        'consulta' => ['nome' => 'Consultas', 'icon' => 'bi-hospital', 'color' => 'blue'],
                        'exame' => ['nome' => 'Exames', 'icon' => 'bi-clipboard2-pulse', 'color' => 'purple'],
                        'procedimento' => ['nome' => 'Procedimentos', 'icon' => 'bi-bandaid', 'color' => 'green'],
                        'medicamento' => ['nome' => 'Medicamentos', 'icon' => 'bi-capsule', 'color' => 'red'],
                        'outros' => ['nome' => 'Outros', 'icon' => 'bi-three-dots', 'color' => 'gray']
                    ];
                    
                    foreach ($categorias as $cat):
                        $servicos_cat = $mysqli->query("SELECT * FROM services WHERE category = '$cat' AND active = 1 ORDER BY name");
                        if ($servicos_cat->num_rows == 0) continue;
                        $info = $cat_labels[$cat];
                    ?>
                        <div class="mb-8">
                            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                                <i class="bi <?= $info['icon'] ?> mr-2 text-<?= $info['color'] ?>-600"></i>
                                <?= $info['nome'] ?>
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php while ($serv = $servicos_cat->fetch_assoc()): ?>
                                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 hover-lift border-l-4 border-<?= $info['color'] ?>-500">
                                        <div class="flex justify-between items-start mb-3">
                                            <h4 class="font-bold text-gray-800 dark:text-white text-lg"><?= htmlspecialchars($serv['name']) ?></h4>
                                            <i class="bi <?= $info['icon'] ?> text-2xl text-<?= $info['color'] ?>-600"></i>
                                        </div>
                                        <?php if ($serv['description']): ?>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4"><?= htmlspecialchars($serv['description']) ?></p>
                                        <?php endif; ?>
                                        <div class="flex justify-between items-center pt-4 border-t border-gray-200 dark:border-gray-700">
                                            <div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">Preço</div>
                                                <div class="text-2xl font-bold text-<?= $info['color'] ?>-600"><?= number_format($serv['price'], 2, ',', '.') ?></div>
                                                <div class="text-xs text-gray-500">MT</div>
                                            </div>
                                            <div class="flex space-x-1">
                                                <button onclick='editarServico(<?= json_encode($serv) ?>)' class="p-2 text-blue-600 hover:bg-blue-100 dark:hover:bg-blue-900 rounded-lg transition-all" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button onclick='duplicarServico(<?= json_encode($serv) ?>)' class="p-2 text-purple-600 hover:bg-purple-100 dark:hover:bg-purple-900 rounded-lg transition-all" title="Duplicar">
                                                    <i class="bi bi-files"></i>
                                                </button>
                                                <button onclick="toggleServico(<?= $serv['id'] ?>, <?= $serv['active'] ?>)" class="p-2 text-orange-600 hover:bg-orange-100 dark:hover:bg-orange-900 rounded-lg transition-all" title="<?= $serv['active'] ? 'Desativar' : 'Ativar' ?>">
                                                    <i class="bi bi-<?= $serv['active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                                                </button>
                                                <button onclick="excluirServico(<?= $serv['id'] ?>)" class="p-2 text-red-600 hover:bg-red-100 dark:hover:bg-red-900 rounded-lg transition-all" title="Excluir">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Relatórios -->
                <div id="content-relatorios" class="tab-content hidden">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center">
                        <i class="bi bi-graph-up-arrow mr-3 text-orange-600"></i>
                        Relatórios Financeiros
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Relatório de Receitas -->
                        <button onclick="gerarRelatorio('receitas')" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 hover-lift text-left">
                            <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center mb-4">
                                <i class="bi bi-arrow-up-circle text-white text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Relatório de Receitas</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Análise detalhada das receitas por período</p>
                        </button>
                        
                        <!-- Relatório de Despesas -->
                        <button onclick="gerarRelatorio('despesas')" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 hover-lift text-left">
                            <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-rose-600 rounded-xl flex items-center justify-center mb-4">
                                <i class="bi bi-arrow-down-circle text-white text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Relatório de Despesas</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Análise detalhada das despesas por categoria</p>
                        </button>
                        
                        <!-- Fluxo de Caixa -->
                        <button onclick="gerarRelatorio('fluxo')" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 hover-lift text-left">
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center mb-4">
                                <i class="bi bi-cash-stack text-white text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Fluxo de Caixa</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Movimentações de entrada e saída</p>
                        </button>
                        
                        <!-- Contas a Receber -->
                        <button onclick="gerarRelatorio('receber')" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 hover-lift text-left">
                            <div class="w-16 h-16 gradient-gold rounded-xl flex items-center justify-center mb-4">
                                <i class="bi bi-clock-history text-white text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Contas a Receber</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Faturas pendentes e parciais</p>
                        </button>
                        
                        <!-- Demonstrativo -->
                        <button onclick="gerarRelatorio('demonstrativo')" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 hover-lift text-left">
                            <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-violet-600 rounded-xl flex items-center justify-center mb-4">
                                <i class="bi bi-file-earmark-bar-graph text-white text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Demonstrativo</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Resultado financeiro completo</p>
                        </button>
                        
                        <!-- Serviços Mais Vendidos -->
                        <button onclick="gerarRelatorio('servicos')" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 hover-lift text-left">
                            <div class="w-16 h-16 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-xl flex items-center justify-center mb-4">
                                <i class="bi bi-graph-up text-white text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Serviços Mais Vendidos</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Ranking de serviços por faturamento</p>
                        </button>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <!-- Modais -->
    
    <!-- Modal Nova Fatura -->
    <div id="modalNovaFatura" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-gradient-to-r from-orange-500 to-amber-500 px-6 py-4 flex justify-between items-center rounded-t-2xl">
                <h3 class="text-2xl font-bold text-white flex items-center">
                    <i class="bi bi-file-earmark-plus mr-3"></i>Nova Fatura
                </h3>
                <button onclick="closeModal('modalNovaFatura')" class="text-white hover:bg-white/20 rounded-lg p-2 transition-all">
                    <i class="bi bi-x-lg text-2xl"></i>
                </button>
            </div>
            
            <form method="POST" class="p-6">
                <input type="hidden" name="acao" value="criar_fatura">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Paciente *</label>
                        <select name="patient_id" required class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-orange-500">
                            <option value="">Selecione o paciente</option>
                            <?php
                            $pacientes_modal = $mysqli->query("SELECT id, name, codigo FROM patients ORDER BY name");
                            while ($pac = $pacientes_modal->fetch_assoc()):
                            ?>
                                <option value="<?= $pac['id'] ?>"><?= htmlspecialchars($pac['name']) ?> (<?= htmlspecialchars($pac['codigo']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Data de Emissão *</label>
                        <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" required class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-orange-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Prazo de Pagamento (dias)</label>
                        <input type="number" name="payment_terms" value="30" min="0" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-orange-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Desconto (%)</label>
                        <input type="number" name="discount_percent" value="0" min="0" max="100" step="0.01" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-orange-500">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Observações</label>
                    <textarea name="notes" rows="2" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-orange-500"></textarea>
                </div>
                
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <label class="text-lg font-bold text-gray-800 dark:text-white">Itens da Fatura</label>
                        <button type="button" onclick="adicionarItemFatura()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-all">
                            <i class="bi bi-plus-circle mr-2"></i>Adicionar Item
                        </button>
                    </div>
                    
                    <div id="itens-fatura" class="space-y-3">
                        <div class="item-fatura bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                                <div class="md:col-span-2">
                                    <select name="items[0][service_id]" onchange="preencherServico(this, 0)" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                                        <option value="">Selecione um serviço</option>
                                        <?php
                                        $servicos_modal = $mysqli->query("SELECT * FROM services WHERE active = 1 ORDER BY category, name");
                                        while ($serv = $servicos_modal->fetch_assoc()):
                                        ?>
                                            <option value="<?= $serv['id'] ?>" data-price="<?= $serv['price'] ?>" data-description="<?= htmlspecialchars($serv['name']) ?>">
                                                <?= htmlspecialchars($serv['name']) ?> - <?= number_format($serv['price'], 2, ',', '.') ?> MT
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div>
                                    <input type="text" name="items[0][description]" placeholder="Descrição" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm item-description">
                                </div>
                                <div>
                                    <input type="number" name="items[0][quantity]" placeholder="Qtd" value="1" min="1" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                                </div>
                                <div>
                                    <input type="number" name="items[0][unit_price]" placeholder="Preço" step="0.01" min="0" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm item-price">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeModal('modalNovaFatura')" class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-all font-semibold">
                        Cancelar
                    </button>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white rounded-lg transition-all font-semibold">
                        <i class="bi bi-check-circle mr-2"></i>Criar Fatura
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Registrar Pagamento -->
    <div id="modalPagamento" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-2xl w-full">
            <div class="bg-gradient-to-r from-green-500 to-emerald-500 px-6 py-4 flex justify-between items-center rounded-t-2xl">
                <h3 class="text-2xl font-bold text-white flex items-center">
                    <i class="bi bi-cash-stack mr-3"></i>Registrar Pagamento
                </h3>
                <button onclick="closeModal('modalPagamento')" class="text-white hover:bg-white/20 rounded-lg p-2 transition-all">
                    <i class="bi bi-x-lg text-2xl"></i>
                </button>
            </div>
            
            <form method="POST" class="p-6">
                <input type="hidden" name="acao" value="registrar_pagamento">
                <input type="hidden" name="invoice_id" id="payment_invoice_id">
                
                <div class="mb-6 bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Fatura:</span>
                            <span class="font-bold text-gray-800 dark:text-white ml-2" id="payment_invoice_number"></span>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Total:</span>
                            <span class="font-bold text-gray-800 dark:text-white ml-2" id="payment_invoice_total"></span>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Pago:</span>
                            <span class="font-bold text-green-600 ml-2" id="payment_invoice_paid"></span>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Pendente:</span>
                            <span class="font-bold text-red-600 ml-2" id="payment_invoice_due"></span>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Valor do Pagamento *</label>
                        <input type="number" name="amount" step="0.01" min="0.01" required class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-green-500" id="payment_amount">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Data do Pagamento *</label>
                        <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Método de Pagamento *</label>
                        <select name="payment_method" required class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-green-500">
                            <option value="dinheiro">Dinheiro</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="emola">e-Mola</option>
                            <option value="mkesh">Mkesh</option>
                            <option value="ponto24">Ponto24</option>
                            <option value="multicaixa">Multicaixa</option>
                            <option value="visa">Visa</option>
                            <option value="mastercard">Mastercard</option>
                            <option value="transferencia_bancaria">Transferência Bancária</option>
                            <option value="cheque">Cheque</option>
                            <option value="outros">Outros</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Referência</label>
                        <input type="text" name="reference" placeholder="Nº transação, cheque, etc." class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-green-500">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Observações</label>
                    <textarea name="payment_notes" rows="2" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-green-500"></textarea>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeModal('modalPagamento')" class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-all font-semibold">
                        Cancelar
                    </button>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white rounded-lg transition-all font-semibold">
                        <i class="bi bi-check-circle mr-2"></i>Registrar Pagamento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Nova Despesa -->
    <div id="modalNovaDespesa" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-2xl w-full">
            <div class="bg-gradient-to-r from-red-500 to-rose-500 px-6 py-4 flex justify-between items-center rounded-t-2xl">
                <h3 id="despesa-modal-titulo" class="text-2xl font-bold text-white flex items-center">
                    <i class="bi bi-cart-dash mr-3"></i>Nova Despesa
                </h3>
                <button onclick="closeModal('modalNovaDespesa')" class="text-white hover:bg-white/20 rounded-lg p-2 transition-all">
                    <i class="bi bi-x-lg text-2xl"></i>
                </button>
            </div>
            
            <form method="POST" class="p-6">
                <input type="hidden" name="acao" value="registrar_despesa" id="despesa-acao">
                <input type="hidden" name="expense_id" id="despesa-id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Categoria *</label>
                        <select name="expense_category" id="despesa-categoria" required class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-red-500">
                            <option value="salarios">Salários</option>
                            <option value="fornecedores">Fornecedores</option>
                            <option value="aluguel">Aluguel</option>
                            <option value="utilidades">Utilidades (Água, Luz, etc)</option>
                            <option value="equipamentos">Equipamentos</option>
                            <option value="manutencao">Manutenção</option>
                            <option value="marketing">Marketing</option>
                            <option value="impostos">Impostos</option>
                            <option value="outros">Outros</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Valor *</label>
                        <input type="number" name="expense_amount" id="despesa-valor" step="0.01" min="0.01" required class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-red-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Data da Despesa *</label>
                        <input type="date" name="expense_date" id="despesa-data" value="<?= date('Y-m-d') ?>" required class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-red-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Método de Pagamento *</label>
                        <select name="expense_payment_method" id="despesa-metodo" required class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-red-500">
                            <option value="dinheiro">Dinheiro</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="emola">e-Mola</option>
                            <option value="mkesh">Mkesh</option>
                            <option value="multicaixa">Multicaixa</option>
                            <option value="transferencia_bancaria">Transferência Bancária</option>
                            <option value="cheque">Cheque</option>
                            <option value="outros">Outros</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Fornecedor</label>
                        <input type="text" name="supplier" id="despesa-fornecedor" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-red-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Referência</label>
                        <input type="text" name="expense_reference" id="despesa-referencia" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-red-500">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Descrição *</label>
                    <textarea name="expense_description" id="despesa-descricao" rows="2" required class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-red-500"></textarea>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Observações</label>
                    <textarea name="expense_notes" id="despesa-notas" rows="2" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-red-500"></textarea>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Status *</label>
                    <select name="expense_status" id="despesa-status" required class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-red-500">
                        <option value="paga">Paga</option>
                        <option value="pendente">Pendente</option>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeModal('modalNovaDespesa')" class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-all font-semibold">
                        Cancelar
                    </button>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-red-500 to-rose-500 hover:from-red-600 hover:to-rose-600 text-white rounded-lg transition-all font-semibold">
                        <i class="bi bi-check-circle mr-2"></i>Registrar Despesa
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Novo Serviço -->
    <div id="modalNovoServico" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-2xl w-full">
            <div class="bg-gradient-to-r from-blue-500 to-indigo-500 px-6 py-4 flex justify-between items-center rounded-t-2xl">
                <h3 id="servico-modal-titulo" class="text-2xl font-bold text-white flex items-center">
                    <i class="bi bi-plus-circle mr-3"></i>Novo Serviço
                </h3>
                <button onclick="closeModal('modalNovoServico')" class="text-white hover:bg-white/20 rounded-lg p-2 transition-all">
                    <i class="bi bi-x-lg text-2xl"></i>
                </button>
            </div>
            
            <form method="POST" class="p-6">
                <input type="hidden" name="acao" value="salvar_servico">
                <input type="hidden" name="service_id" id="servico-id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Nome do Serviço *</label>
                        <input type="text" name="service_name" id="servico-nome" required class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Código/SKU</label>
                        <input type="text" name="service_code" id="servico-codigo" placeholder="Ex: SRV001" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Categoria *</label>
                        <select name="service_category" id="servico-categoria" required class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500">
                            <option value="consulta">Consulta</option>
                            <option value="exame">Exame</option>
                            <option value="procedimento">Procedimento</option>
                            <option value="medicamento">Medicamento</option>
                            <option value="outros">Outros</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Preço (MT) *</label>
                        <input type="number" name="service_price" id="servico-preco" step="0.01" min="0" required oninput="calcularMargem()" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Custo (MT)</label>
                        <input type="number" name="service_cost" id="servico-custo" step="0.01" min="0" value="0" oninput="calcularMargem()" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Margem: <span id="margem-lucro" class="font-semibold">0%</span></p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Taxa de Imposto (%)</label>
                        <input type="number" name="service_tax_rate" id="servico-taxa" step="0.01" min="0" max="100" value="16" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Descrição</label>
                    <textarea name="service_description" id="servico-descricao" rows="3" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeModal('modalNovoServico')" class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-all font-semibold">
                        Cancelar
                    </button>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600 text-white rounded-lg transition-all font-semibold">
                        <i class="bi bi-check-circle mr-2"></i>Salvar Serviço
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Confirmação de Exclusão de Serviço -->
    <div id="modalConfirmarExclusaoServico" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="bi bi-exclamation-triangle-fill text-red-600 dark:text-red-400 text-3xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Confirmar Exclusão</h3>
                <p class="text-gray-600 dark:text-gray-400">Tem certeza que deseja excluir este serviço? Esta ação não pode ser desfeita.</p>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="excluir_servico">
                <input type="hidden" name="service_id" id="excluir-servico-id">
                <div class="flex space-x-3">
                    <button type="button" onclick="closeModal('modalConfirmarExclusaoServico')" class="flex-1 px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg font-semibold">
                        Cancelar
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold">
                        Excluir
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Dark Mode
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
        }
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
        
        // Troca de Abas
        function switchTab(tabName) {
            // Ocultar todos os conteúdos
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remover estilo ativo de todos os botões
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('bg-gradient-to-r', 'from-orange-500', 'to-amber-500', 'text-white', 'shadow-md');
                button.classList.add('text-gray-700', 'dark:text-gray-300');
            });
            
            // Mostrar conteúdo selecionado
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Adicionar estilo ativo ao botão selecionado
            const activeButton = document.getElementById('tab-' + tabName);
            activeButton.classList.add('bg-gradient-to-r', 'from-orange-500', 'to-amber-500', 'text-white', 'shadow-md');
            activeButton.classList.remove('text-gray-700', 'dark:text-gray-300');
        }
        
        // Modal Functions
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) {
                console.error('Modal não encontrado:', modalId);
                return;
            }
            
            // Resetar o formulário ao abrir para novo
            if (modalId === 'modalNovoServico') {
                const form = modal.querySelector('form');
                if (form) form.reset();
                document.getElementById('servico-modal-titulo').innerHTML = '<i class="bi bi-plus-circle mr-3"></i>Novo Serviço';
                document.getElementById('servico-id').value = '';
                if (document.getElementById('margem-lucro')) {
                    document.getElementById('margem-lucro').textContent = '0%';
                    document.getElementById('margem-lucro').className = 'font-semibold';
                }
            }
            
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.body.style.overflow = 'auto';
            
            // Reset forms e títulos
            const modal = document.getElementById(modalId);
            const form = modal.querySelector('form');
            if (form) form.reset();
            
            // Resetar títulos e ações para modo "novo"
            if (modalId === 'modalNovaDespesa') {
                document.getElementById('despesa-modal-titulo').innerHTML = '<i class="bi bi-cart-dash mr-3"></i>Nova Despesa';
                if (document.getElementById('despesa-acao')) {
                    document.getElementById('despesa-acao').value = 'registrar_despesa';
                }
                if (document.getElementById('despesa-id')) {
                    document.getElementById('despesa-id').value = '';
                }
            } else if (modalId === 'modalNovoServico') {
                document.getElementById('servico-modal-titulo').innerHTML = '<i class="bi bi-plus-circle mr-3"></i>Novo Serviço';
                document.getElementById('servico-id').value = '';
                if (document.getElementById('margem-lucro')) {
                    document.getElementById('margem-lucro').textContent = '0%';
                }
            }
        }

        // Nova Fatura Functions
        let itemCounter = 1;
        
        function adicionarItemFatura() {
            const container = document.getElementById('itens-fatura');
            const newItem = document.createElement('div');
            newItem.className = 'item-fatura bg-gray-50 dark:bg-gray-700 p-4 rounded-lg relative';
            newItem.innerHTML = `
                <button type="button" onclick="removerItemFatura(this)" class="absolute top-2 right-2 text-red-600 hover:text-red-800">
                    <i class="bi bi-trash"></i>
                </button>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <div class="md:col-span-2">
                        <select name="items[${itemCounter}][service_id]" onchange="preencherServico(this, ${itemCounter})" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                            <option value="">Selecione um serviço</option>
                        </select>
                    </div>
                    <div>
                        <input type="text" name="items[${itemCounter}][description]" placeholder="Descrição" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm item-description">
                    </div>
                    <div>
                        <input type="number" name="items[${itemCounter}][quantity]" placeholder="Qtd" value="1" min="1" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                    </div>
                    <div>
                        <input type="number" name="items[${itemCounter}][unit_price]" placeholder="Preço" step="0.01" min="0" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm item-price">
                    </div>
                </div>
            `;
            container.appendChild(newItem);
            itemCounter++;
        }

        function removerItemFatura(button) {
            const items = document.querySelectorAll('.item-fatura');
            if (items.length > 1) {
                button.closest('.item-fatura').remove();
            } else {
                alert('A fatura deve ter pelo menos um item.');
            }
        }

        function preencherServico(select, index) {
            const selectedOption = select.options[select.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            const description = selectedOption.getAttribute('data-description');
            
            const itemContainer = select.closest('.item-fatura');
            const descriptionInput = itemContainer.querySelector('.item-description');
            const priceInput = itemContainer.querySelector('.item-price');
            
            if (price && description) {
                descriptionInput.value = description;
                priceInput.value = price;
            }
        }

        // Registrar Pagamento de Fatura
        function registrarPagamentoFatura(invoiceId, invoiceNumber, total, paid) {
            console.log('Abrindo modal de pagamento:', {invoiceId, invoiceNumber, total, paid});
            
            document.getElementById('payment_invoice_id').value = invoiceId;
            document.getElementById('payment_invoice_number').textContent = invoiceNumber;
            document.getElementById('payment_invoice_total').textContent = parseFloat(total).toFixed(2) + ' MT';
            document.getElementById('payment_invoice_paid').textContent = parseFloat(paid).toFixed(2) + ' MT';
            
            const due = parseFloat(total) - parseFloat(paid);
            document.getElementById('payment_invoice_due').textContent = due.toFixed(2) + ' MT';
            document.getElementById('payment_amount').value = due > 0 ? due.toFixed(2) : '0.00';
            
            openModal('modalPagamento');
        }

        // Ver Fatura Detalhada
        function verFatura(invoiceId) {
            window.location.href = 'ver_fatura.php?id=' + invoiceId;
        }

        // Imprimir Fatura
        function imprimirFatura(invoiceId) {
            window.open('imprimir_fatura.php?id=' + invoiceId, '_blank');
        }

        // Editar Serviço
        function editarServico(servico) {
            document.getElementById('servico-modal-titulo').innerHTML = '<i class="bi bi-pencil mr-3"></i>Editar Serviço';
            document.getElementById('servico-id').value = servico.id;
            document.getElementById('servico-nome').value = servico.name;
            document.getElementById('servico-codigo').value = servico.code || '';
            document.getElementById('servico-categoria').value = servico.category;
            document.getElementById('servico-descricao').value = servico.description || '';
            document.getElementById('servico-preco').value = servico.price;
            document.getElementById('servico-custo').value = servico.cost || 0;
            document.getElementById('servico-taxa').value = servico.tax_rate || 16;
            
            calcularMargem();
            openModal('modalNovoServico');
        }

        // Duplicar Serviço
        function duplicarServico(servico) {
            document.getElementById('servico-modal-titulo').innerHTML = '<i class="bi bi-files mr-3"></i>Duplicar Serviço';
            document.getElementById('servico-id').value = '';
            document.getElementById('servico-nome').value = servico.name + ' (Cópia)';
            document.getElementById('servico-codigo').value = '';
            document.getElementById('servico-categoria').value = servico.category;
            document.getElementById('servico-descricao').value = servico.description || '';
            document.getElementById('servico-preco').value = servico.price;
            document.getElementById('servico-custo').value = servico.cost || 0;
            document.getElementById('servico-taxa').value = servico.tax_rate || 16;
            
            calcularMargem();
            openModal('modalNovoServico');
        }

        // Calcular Margem de Lucro
        function calcularMargem() {
            const preco = parseFloat(document.getElementById('servico-preco').value) || 0;
            const custo = parseFloat(document.getElementById('servico-custo').value) || 0;
            
            if (preco > 0 && custo > 0) {
                const margem = ((preco - custo) / preco) * 100;
                document.getElementById('margem-lucro').textContent = margem.toFixed(2) + '%';
                document.getElementById('margem-lucro').classList.add(margem > 30 ? 'text-green-600' : margem > 15 ? 'text-yellow-600' : 'text-red-600');
            } else {
                document.getElementById('margem-lucro').textContent = '0%';
            }
        }

        // Filtrar Serviços
        function filtrarServicos() {
            const busca = document.getElementById('busca-servico').value.toLowerCase();
            const cards = document.querySelectorAll('#content-servicos .grid .bg-white');
            
            cards.forEach(card => {
                const nome = card.querySelector('h4').textContent.toLowerCase();
                const descricao = card.querySelector('p') ? card.querySelector('p').textContent.toLowerCase() : '';
                
                if (nome.includes(busca) || descricao.includes(busca)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Toggle Serviço (Ativar/Desativar)
        function toggleServico(serviceId, currentStatus) {
            if (confirm('Deseja ' + (currentStatus ? 'desativar' : 'ativar') + ' este serviço?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="toggle_servico">
                    <input type="hidden" name="service_id" value="${serviceId}">
                    <input type="hidden" name="current_status" value="${currentStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Excluir Serviço
        function excluirServico(serviceId) {
            if (confirm('Tem certeza que deseja excluir este serviço? Esta ação não pode ser desfeita.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="excluir_servico">
                    <input type="hidden" name="service_id" value="${serviceId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Cancelar Fatura
        function cancelarFatura(invoiceId, invoiceNumber) {
            if (confirm('Tem certeza que deseja cancelar a fatura ' + invoiceNumber + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="cancelar_fatura">
                    <input type="hidden" name="invoice_id" value="${invoiceId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Editar Despesa
        function editarDespesa(despesa) {
            document.getElementById('despesa-modal-titulo').innerHTML = '<i class="bi bi-pencil mr-3"></i>Editar Despesa';
            document.getElementById('despesa-acao').value = 'editar_despesa';
            document.getElementById('despesa-id').value = despesa.id;
            document.getElementById('despesa-categoria').value = despesa.category;
            document.getElementById('despesa-valor').value = despesa.amount;
            document.getElementById('despesa-data').value = despesa.expense_date;
            document.getElementById('despesa-metodo').value = despesa.payment_method;
            document.getElementById('despesa-fornecedor').value = despesa.supplier || '';
            document.getElementById('despesa-referencia').value = despesa.reference || '';
            document.getElementById('despesa-descricao').value = despesa.description;
            document.getElementById('despesa-notas').value = despesa.notes || '';
            document.getElementById('despesa-status').value = despesa.status;
            
            openModal('modalNovaDespesa');
        }

        // Excluir Despesa
        function excluirDespesa(expenseId) {
            if (confirm('Tem certeza que deseja excluir esta despesa? Esta ação não pode ser desfeita.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="excluir_despesa">
                    <input type="hidden" name="expense_id" value="${expenseId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Gerar Relatório
        function gerarRelatorio(tipo) {
            let url = 'relatorios_financeiro.php?tipo=' + tipo;
            
            // Adicionar filtros de data se necessário
            const hoje = new Date().toISOString().split('T')[0];
            const mesAtual = hoje.substring(0, 7);
            
            switch(tipo) {
                case 'faturas':
                case 'pagamentos':
                case 'despesas':
                case 'fluxo_caixa':
                    url += '&data_inicio=' + mesAtual + '-01&data_fim=' + hoje;
                    break;
            }
            
            window.open(url, '_blank');
        }
        
        // Filtrar Faturas (implementação básica)
        function filtrarFaturas() {
            const status = document.getElementById('filtro-status-fatura').value;
            const dataInicio = document.getElementById('filtro-data-inicio').value;
            const dataFim = document.getElementById('filtro-data-fim').value;
            
            let url = 'financeiro.php?tab=faturas';
            if (status) url += '&status=' + status;
            if (dataInicio) url += '&data_inicio=' + dataInicio;
            if (dataFim) url += '&data_fim=' + dataFim;
            
            window.location.href = url;
        }
        
        // Gráficos
        <?php
        // Dados para gráfico de receitas vs despesas (últimos 6 meses)
        $meses_labels = [];
        $receitas_data = [];
        $despesas_data = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $mes = date('Y-m', strtotime("-$i months"));
            $mes_nome = date('M/y', strtotime("-$i months"));
            $meses_labels[] = $mes_nome;
            
            $rec = $mysqli->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM invoices WHERE DATE_FORMAT(issue_date, '%Y-%m') = '$mes'")->fetch_assoc()['total'];
            $desp = $mysqli->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = '$mes' AND status = 'paga'")->fetch_assoc()['total'];
            
            $receitas_data[] = $rec;
            $despesas_data[] = $desp;
        }
        
        // Dados para gráfico de métodos de pagamento
        $metodos_pagamento = $mysqli->query("SELECT payment_method, SUM(amount) as total FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$mes_atual' GROUP BY payment_method");
        $metodos_labels = [];
        $metodos_data = [];
        while ($metodo = $metodos_pagamento->fetch_assoc()) {
            $metodos_labels[] = ucfirst(str_replace('_', ' ', $metodo['payment_method']));
            $metodos_data[] = $metodo['total'];
        }
        ?>
        
        // Gráfico Receitas vs Despesas
        const ctxReceitasDespesas = document.getElementById('chartReceitasDespesas').getContext('2d');
        new Chart(ctxReceitasDespesas, {
            type: 'bar',
            data: {
                labels: <?= json_encode($meses_labels) ?>,
                datasets: [{
                    label: 'Receitas',
                    data: <?= json_encode($receitas_data) ?>,
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                    borderColor: 'rgba(34, 197, 94, 1)',
                    borderWidth: 2
                }, {
                    label: 'Despesas',
                    data: <?= json_encode($despesas_data) ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('pt-MZ') + ' MT';
                            }
                        }
                    }
                }
            }
        });
        
        // Gráfico Métodos de Pagamento
        const ctxMetodosPagamento = document.getElementById('chartMetodosPagamento').getContext('2d');
        new Chart(ctxMetodosPagamento, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($metodos_labels) ?>,
                datasets: [{
                    data: <?= json_encode($metodos_data) ?>,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                        'rgba(20, 184, 166, 0.8)',
                        'rgba(251, 146, 60, 0.8)',
                        'rgba(99, 102, 241, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(107, 114, 128, 0.8)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'right'
                    }
                }
            }
        });

        
        function filtrarFaturas() {
            const status = document.getElementById('filtro-status-fatura').value;
            const dataInicio = document.getElementById('filtro-data-inicio').value;
            const dataFim = document.getElementById('filtro-data-fim').value;
            
            let url = '?';
            if (status) url += 'status=' + status + '&';
            if (dataInicio) url += 'data_inicio=' + dataInicio + '&';
            if (dataFim) url += 'data_fim=' + dataFim;
            
            window.location.href = url;
        }
    </script>
</body>
</html>
