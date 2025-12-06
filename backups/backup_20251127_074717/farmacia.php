<?php
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['user_id'])) {
	header('Location: index.php');
	exit();
}

$user_name = $_SESSION['user_name'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';

// GESTÃO DE MEDICAMENTOS
$erro_medicamento = '';
$sucesso_medicamento = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
	if ($_POST['acao'] === 'add_medicamento') {
		$nome = trim($_POST['nome_medicamento']);
		$estoque = (int)$_POST['estoque'];
		if ($nome === '') {
			$erro_medicamento = 'Informe o nome do medicamento.';
		} else {
			// Inserir medicamento na tabela medications
			$stmt = $mysqli->prepare('INSERT INTO medications (name) VALUES (?)');
			$stmt->bind_param('s', $nome);
			if ($stmt->execute()) {
				$medication_id = $mysqli->insert_id;
				// Inserir estoque inicial na tabela pharmacy_stock
				$stmt2 = $mysqli->prepare('INSERT INTO pharmacy_stock (medication_id, quantity) VALUES (?, ?)');
				$stmt2->bind_param('ii', $medication_id, $estoque);
				$stmt2->execute();
				$stmt2->close();
				$sucesso_medicamento = 'Medicamento cadastrado com sucesso!';
			} else {
				$erro_medicamento = 'Erro ao cadastrar: ' . $stmt->error;
			}
			$stmt->close();
		}
	}
	if ($_POST['acao'] === 'del_medicamento' && isset($_POST['id'])) {
		$id = (int)$_POST['id'];
		$mysqli->query('DELETE FROM pharmacy_stock WHERE medication_id = ' . $id);
		$mysqli->query('DELETE FROM medications WHERE id = ' . $id);
	}
	if ($_POST['acao'] === 'edit_medicamento' && isset($_POST['id'])) {
		$id = (int)$_POST['id'];
		$nome = trim($_POST['nome_medicamento']);
		$estoque = (int)$_POST['estoque'];
		$stmt = $mysqli->prepare('UPDATE medications SET name=? WHERE id=?');
		$stmt->bind_param('si', $nome, $id);
		$stmt->execute();
		$stmt->close();
		// Atualizar estoque
		$stmt2 = $mysqli->prepare('UPDATE pharmacy_stock SET quantity=? WHERE medication_id=?');
		$stmt2->bind_param('ii', $estoque, $id);
		$stmt2->execute();
		$stmt2->close();
	}
	if ($_POST['acao'] === 'entregar' && isset($_POST['prescription_id'])) {
		$id = (int)$_POST['prescription_id'];
		
		// Buscar dados da prescrição e paciente
		$presc_data = $mysqli->query("
			SELECT ap.patient_id, pat.codigo
			FROM prescriptions p
			JOIN attendances a ON p.attendance_id = a.id
			JOIN appointments ap ON a.appointment_id = ap.id
			JOIN patients pat ON ap.patient_id = pat.id
			WHERE p.id = $id
		")->fetch_assoc();
		
		if ($presc_data) {
			$patient_id = $presc_data['patient_id'];
			
			// Buscar medicamentos da prescrição com preços
			$meds_query = $mysqli->query("
				SELECT DISTINCT
					m.id as medication_id,
					m.name,
					pi.dosage,
					s.id as service_id,
					s.price,
					1 as quantity
				FROM prescription_items pi
				JOIN medications m ON pi.medication_id = m.id
				LEFT JOIN (
					SELECT id, name, price 
					FROM services 
					WHERE category = 'medicamento'
					GROUP BY name
				) s ON s.name COLLATE utf8mb4_unicode_ci = m.name COLLATE utf8mb4_unicode_ci
				WHERE pi.prescription_id = $id
			");
			
			$servicos_fatura = [];
			$medicamentos_entregues = [];
			$medicamentos_processados = []; // Evitar duplicação
			
			while ($med = $meds_query->fetch_assoc()) {
				// Evitar processar o mesmo medicamento múltiplas vezes
				if (in_array($med['medication_id'], $medicamentos_processados)) {
					continue;
				}
				$medicamentos_processados[] = $med['medication_id'];
				
				if ($med['service_id']) {
					$servicos_fatura[] = [
						'service_id' => $med['service_id'],
						'description' => $med['name'] . ' - ' . $med['dosage'],
						'quantity' => 1,
						'price' => $med['price']
					];
				}
				// Sempre armazenar ID do medicamento para atualizar estoque
				$medicamentos_entregues[] = $med['medication_id'];
			}
			
			// Criar fatura se houver medicamentos
			if (!empty($servicos_fatura)) {
				require_once 'config.php';
				
				// Gerar número de fatura
				$prefix_result = $mysqli->query("SELECT setting_value FROM financial_settings WHERE setting_key = 'invoice_prefix'");
				$prefix = $prefix_result && $prefix_result->num_rows > 0 ? $prefix_result->fetch_assoc()['setting_value'] : 'FT';
				$year = date('Y');
				$last_invoice = $mysqli->query("SELECT invoice_number FROM invoices WHERE invoice_number LIKE '$prefix$year%' ORDER BY id DESC LIMIT 1");
				if ($last_invoice && $last_invoice->num_rows > 0) {
					$last_num = $last_invoice->fetch_assoc()['invoice_number'];
					$num = (int)substr($last_num, -6) + 1;
				} else {
					$num = 1;
				}
				$invoice_number = $prefix . $year . str_pad($num, 6, '0', STR_PAD_LEFT);
				
				$issue_date = date('Y-m-d');
				$due_date = date('Y-m-d', strtotime('+30 days'));
				
				// Calcular totais
				$subtotal = 0;
				foreach ($servicos_fatura as $serv) {
					$subtotal += $serv['price'] * $serv['quantity'];
				}
				
				$tax_rate_result = $mysqli->query("SELECT setting_value FROM financial_settings WHERE setting_key = 'tax_rate'");
				$tax_rate = $tax_rate_result && $tax_rate_result->num_rows > 0 ? (float)$tax_rate_result->fetch_assoc()['setting_value'] : 0;
				
				$discount = 0;
				$discount_percent = 0;
				$tax = ($subtotal * $tax_rate) / 100;
				$total = $subtotal + $tax;
				
				// Criar fatura
				$stmt = $mysqli->prepare("INSERT INTO invoices (invoice_number, patient_id, issue_date, due_date, status, subtotal, discount, discount_percent, tax, total, amount_paid, amount_due, notes, created_by) VALUES (?, ?, ?, ?, 'pendente', ?, ?, ?, ?, ?, 0, ?, ?, ?)");
				$notes = "Medicamentos entregues pela farmácia";
				$username = $user_name ?? 'Farmácia';
				$stmt->bind_param('sissddddddss', $invoice_number, $patient_id, $issue_date, $due_date, $subtotal, $discount, $discount_percent, $tax, $total, $total, $notes, $username);
				
				if ($stmt->execute()) {
					$invoice_id = $stmt->insert_id;
					
					// Inserir itens da fatura
					$stmt_item = $mysqli->prepare("INSERT INTO invoice_items (invoice_id, service_id, description, quantity, unit_price, discount, subtotal) VALUES (?, ?, ?, ?, ?, 0, ?)");
					foreach ($servicos_fatura as $serv) {
						$item_subtotal = $serv['price'] * $serv['quantity'];
						$stmt_item->bind_param('iisidd', $invoice_id, $serv['service_id'], $serv['description'], $serv['quantity'], $serv['price'], $item_subtotal);
						$stmt_item->execute();
					}
					$stmt_item->close();
					
					// Registrar no fluxo de caixa
					$mysqli->query("INSERT INTO cash_flow (type, category, description, amount, transaction_date, reference_type, reference_id, created_by) VALUES ('entrada', 'Faturamento', 'Fatura $invoice_number - Medicamentos', $total, NOW(), 'invoice', $invoice_id, '$username')");
					
					// Associar fatura à prescrição para rastreamento
					$mysqli->query("UPDATE prescriptions SET invoice_id = $invoice_id WHERE id = $id");
				}
				$stmt->close();
			}
			
		// Diminuir estoque dos medicamentos entregues (apenas uma vez)
		foreach ($medicamentos_entregues as $medication_id) {
			// Atualizar na tabela medications (stock_quantity) - apenas se houver estoque
			$mysqli->query("UPDATE medications SET stock_quantity = stock_quantity - 1 WHERE id = $medication_id AND IFNULL(stock_quantity, 0) > 0");
			// Também atualizar pharmacy_stock se existir - apenas se houver estoque
			$mysqli->query("UPDATE pharmacy_stock SET quantity = quantity - 1 WHERE medication_id = $medication_id AND quantity > 0");
			// Registrar movimento no stock_movements
			$mysqli->query("INSERT INTO stock_movements (medication_id, quantity, operation, notes, created_by) VALUES ($medication_id, -1, 'remove', 'Entrega pela farmácia', {$_SESSION['user_id']})");
		}
		}
		
		// Marcar como entregue
		$mysqli->query("UPDATE prescriptions SET delivered_at=NOW() WHERE id=$id");
	}
}

// LISTAGEM DE MEDICAMENTOS com estoque
$medicamentos = $mysqli->query('SELECT m.id, m.name as nome, IFNULL(ps.quantity, 0) as estoque FROM medications m LEFT JOIN pharmacy_stock ps ON m.id = ps.medication_id ORDER BY m.name');

// BUSCA por paciente ou código
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$where_busca = '';
if ($busca !== '') {
	$busca_like = $mysqli->real_escape_string($busca);
	$where_busca = " AND (pat.name LIKE '%$busca_like%' OR pat.codigo LIKE '%$busca_like%')";
}

// PRESCRIÇÕES - Obter prescrições através de attendances e appointments
$prescricoes = $mysqli->query("
	SELECT 
		p.id as prescription_id, 
		p.created_at, 
		p.delivered_at,
		p.invoice_id,
		pat.name as patient_name,
		pat.codigo as patient_code
	FROM prescriptions p 
	JOIN attendances a ON p.attendance_id = a.id
	JOIN appointments ap ON a.appointment_id = ap.id
	JOIN patients pat ON ap.patient_id = pat.id
	WHERE 1=1 $where_busca
	ORDER BY p.created_at DESC
");

$itens_prescricao = [];
$res = $mysqli->query('
	SELECT 
		pi.prescription_id, 
		m.name as medicamento, 
		pi.dosage, 
		pi.instructions 
	FROM prescription_items pi
	LEFT JOIN medications m ON pi.medication_id = m.id
');
while ($row = $res->fetch_assoc()) {
	$itens_prescricao[$row['prescription_id']][] = $row;
}

?>
<!DOCTYPE html>
<html lang="pt-br" class="scroll-smooth">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Farmácia - Sistema Integrado Mais Saúde</title>
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
						accent: '#34d399',
					},
					animation: {
						'fade-in': 'fadeIn 0.5s ease-in-out',
						'slide-up': 'slideUp 0.4s ease-out',
						'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
					},
					keyframes: {
						fadeIn: {
							'0%': { opacity: '0' },
							'100%': { opacity: '1' },
						},
						slideUp: {
							'0%': { transform: 'translateY(20px)', opacity: '0' },
							'100%': { transform: 'translateY(0)', opacity: '1' },
						},
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
<body class="bg-gradient-to-br from-emerald-50 via-teal-50 to-green-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 min-h-screen transition-colors duration-300">
	<!-- Navbar Moderna -->
	<nav class="glass-effect shadow-lg border-b border-emerald-200 dark:border-gray-700 sticky top-0 z-50 animate-fade-in">
		<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
			<div class="flex justify-between items-center h-16">
				<a href="dashboard.php" class="flex items-center space-x-3 group">
					<div class="bg-gradient-to-br from-emerald-500 to-teal-600 p-2 rounded-xl shadow-lg group-hover:shadow-xl transition-all duration-300 group-hover:scale-110">
						<i class="bi bi-capsule-pill text-white text-2xl"></i>
					</div>
					<div>
						<h1 class="text-xl font-bold bg-gradient-to-r from-emerald-600 to-teal-600 dark:from-emerald-400 dark:to-teal-400 bg-clip-text text-transparent">Mais Saúde</h1>
						<p class="text-xs text-gray-500 dark:text-gray-400">Farmácia</p>
					</div>
				</a>
				<div class="flex items-center space-x-4">
					<button onclick="toggleDarkMode()" class="p-2 rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-300">
						<i class="bi bi-moon-stars dark:bi-sun text-gray-700 dark:text-gray-300"></i>
					</button>
					<div class="flex items-center space-x-3 bg-emerald-100 dark:bg-gray-700 px-4 py-2 rounded-full">
						<i class="bi bi-person-circle text-emerald-600 dark:text-emerald-400 text-xl"></i>
						<span class="text-sm font-medium text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($user_name); ?></span>
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
		
		function switchTab(tabName) {
			// Esconder todas as tabs
			document.getElementById('prescricoes-tab').style.display = 'none';
			document.getElementById('stock-tab').style.display = 'none';
			
			// Remover active de todos os botões
			document.getElementById('btn-prescricoes').classList.remove('bg-emerald-600', 'text-white');
			document.getElementById('btn-prescricoes').classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
			document.getElementById('btn-stock').classList.remove('bg-emerald-600', 'text-white');
			document.getElementById('btn-stock').classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
			
			// Mostrar tab selecionada e ativar botão
			if (tabName === 'prescricoes') {
				document.getElementById('prescricoes-tab').style.display = 'block';
				document.getElementById('btn-prescricoes').classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
				document.getElementById('btn-prescricoes').classList.add('bg-emerald-600', 'text-white');
			} else if (tabName === 'stock') {
				document.getElementById('stock-tab').style.display = 'block';
				document.getElementById('btn-stock').classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
				document.getElementById('btn-stock').classList.add('bg-emerald-600', 'text-white');
			}
		}
	</script>

	<!-- Container Principal -->
	<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
		
		<!-- Navegação por Abas -->
		<div class="mb-6 flex gap-3 animate-fade-in">
			<button id="btn-prescricoes" onclick="switchTab('prescricoes')" 
					class="px-6 py-3 bg-emerald-600 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 font-semibold flex items-center">
				<i class="bi bi-prescription2 mr-2 text-xl"></i>
				Prescrições e Entregas
			</button>
			<button id="btn-stock" onclick="switchTab('stock')" 
					class="px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 font-semibold flex items-center">
				<i class="bi bi-box-seam mr-2 text-xl"></i>
				Gestão de Stock
			</button>
		</div>
		
		<!-- Tab: Prescrições e Entregas -->
		<div id="prescricoes-tab" style="display: block;">
		<div class="grid grid-cols-1 gap-6">
			<!-- Prescrições e Entregas -->
			<div class="space-y-6">
				<div class="glass-effect rounded-2xl shadow-xl border border-emerald-200 dark:border-gray-700 overflow-hidden animate-slide-up">
					<div class="bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-4">
						<h2 class="text-xl font-bold text-white flex items-center">
							<i class="bi bi-prescription2 mr-3 text-2xl"></i>
							Prescrições e Entregas
						</h2>
					</div>
					<div class="p-6">
						<?php if ($erro_medicamento): ?>
						<div class="mb-4 p-4 bg-red-100 dark:bg-red-900 border-l-4 border-red-500 text-red-700 dark:text-red-200 rounded-lg animate-fade-in">
							<div class="flex items-center">
								<i class="bi bi-exclamation-triangle-fill mr-3 text-xl"></i>
								<p class="font-medium"><?= htmlspecialchars($erro_medicamento) ?></p>
							</div>
						</div>
						<?php endif; ?>
						<?php if ($sucesso_medicamento): ?>
						<div class="mb-4 p-4 bg-emerald-100 dark:bg-emerald-900 border-l-4 border-emerald-500 text-emerald-700 dark:text-emerald-200 rounded-lg animate-fade-in">
							<div class="flex items-center">
								<i class="bi bi-check-circle-fill mr-3 text-xl"></i>
								<p class="font-medium"><?= htmlspecialchars($sucesso_medicamento) ?></p>
							</div>
						</div>
						<?php endif; ?>
						
						<form method="get" class="mb-6">
							<div class="flex gap-3">
								<div class="flex-1">
									<input type="text" name="busca" 
										class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-200 dark:focus:ring-emerald-900 transition-all duration-300" 
										placeholder="🔍 Buscar paciente ou código MS-XXXX..." 
										value="<?php echo htmlspecialchars($_GET['busca'] ?? ''); ?>">
								</div>
								<button type="submit" class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white rounded-xl shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 font-medium">
									<i class="bi bi-search mr-2"></i>Buscar
								</button>
							</div>
						</form>
						<?php
						$historico = [];
						while ($row = $prescricoes->fetch_assoc()) {
							$historico[$row['patient_name']][] = $row;
						}
						?>
						<div class="space-y-4">
							<?php foreach ($historico as $paciente => $prescricoes): ?>
							<div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden">
								<div class="bg-gradient-to-r from-emerald-100 to-teal-100 dark:from-emerald-900 dark:to-teal-900 px-4 py-3 border-b border-emerald-200 dark:border-emerald-700">
									<h3 class="font-bold text-emerald-800 dark:text-emerald-200 flex items-center">
										<i class="bi bi-person-badge-fill mr-2 text-xl"></i>
										<?= htmlspecialchars($paciente) ?>
									</h3>
								</div>
								<div class="overflow-x-auto">
									<table class="w-full">
										<thead class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
											<tr>
												<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">ID</th>
												<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Data</th>
												<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Medicamentos</th>
												<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Status</th>
												<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Ação</th>
											</tr>
										</thead>
										<tbody class="divide-y divide-gray-200 dark:divide-gray-700">
											<?php foreach ($prescricoes as $row): ?>
											<tr class="hover:bg-emerald-50 dark:hover:bg-gray-700 transition-colors duration-200">
												<td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-gray-100">#<?= $row['prescription_id'] ?></td>
												<td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['created_at']))) ?></td>
												<td class="px-4 py-3">
													<?php if (!empty($itens_prescricao[$row['prescription_id']])): ?>
														<ul class="space-y-1">
														<?php foreach ($itens_prescricao[$row['prescription_id']] as $item): ?>
															<li class="text-sm text-gray-800 dark:text-gray-200">
																<i class="bi bi-capsule text-emerald-600 dark:text-emerald-400 mr-1"></i>
																<strong><?= htmlspecialchars($item['medicamento']) ?></strong>
																<?php if ($item['dosage']): ?> 
																	<span class="text-gray-600 dark:text-gray-400">- <?= htmlspecialchars($item['dosage']) ?></span>
																<?php endif; ?>
															</li>
														<?php endforeach; ?>
														</ul>
													<?php else: ?>
														<span class="text-sm text-gray-400 italic">Nenhum medicamento</span>
													<?php endif; ?>
												</td>
												<td class="px-4 py-3">
													<?php if ($row['delivered_at']): ?>
														<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
															<i class="bi bi-check-circle-fill mr-1"></i> Entregue
														</span>
													<?php else: ?>
														<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200 animate-pulse-slow">
															<i class="bi bi-clock-fill mr-1"></i> Pendente
														</span>
													<?php endif; ?>
												</td>
												<td class="px-4 py-3">
													<div class="flex gap-2 flex-wrap">
														<?php if (!$row['delivered_at']): ?>
															<form method="post" class="inline">
																<input type="hidden" name="acao" value="entregar">
																<input type="hidden" name="prescription_id" value="<?= $row['prescription_id'] ?>">
																<button type="submit" class="px-4 py-2 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white text-sm rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 font-medium">
																	<i class="bi bi-check2-circle mr-1"></i>Marcar Entregue
																</button>
															</form>
														<?php else: ?>
															<div class="flex gap-2 items-center">
																<button disabled class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-500 dark:text-gray-400 text-sm rounded-lg cursor-not-allowed font-medium">
																	<i class="bi bi-check2 mr-1"></i>Entregue
																</button>
																<?php if (!empty($row['invoice_id'])): ?>
																	<a href="imprimir_fatura.php?id=<?= $row['invoice_id'] ?>" 
																	   target="_blank"
																	   class="px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white text-sm rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 font-medium">
																		<i class="bi bi-file-earmark-pdf mr-1"></i>Imprimir Fatura
																	</a>
																<?php else: ?>
																	<span class="text-xs text-gray-500 italic">(Sem fatura gerada)</span>
																<?php endif; ?>
															</div>
														<?php endif; ?>
													</div>
												</td>
											</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		</div>
		<!-- Fim Tab Prescrições -->
		
		<!-- Tab: Gestão de Stock -->
		<div id="stock-tab" style="display: none;">
		<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
			
			<!-- Formulário de Adicionar Medicamento -->
			<div class="lg:col-span-1 space-y-6">
				<div class="glass-effect rounded-2xl shadow-xl border border-emerald-200 dark:border-gray-700 overflow-hidden animate-slide-up">
					<div class="bg-gradient-to-r from-teal-500 to-emerald-600 px-6 py-4">
						<h2 class="text-xl font-bold text-white flex items-center">
							<i class="bi bi-plus-circle-fill mr-3 text-2xl"></i>
							Adicionar Medicamento
						</h2>
					</div>
					<div class="p-6">
						<form method="post" class="space-y-4">
							<input type="hidden" name="acao" value="add_medicamento">
							<div>
								<label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
									<i class="bi bi-capsule-pill mr-1"></i> Nome do Medicamento
								</label>
								<input type="text" name="nome_medicamento" required
									placeholder="Ex: Paracetamol 500mg"
									class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-200 dark:focus:ring-emerald-900 transition-all duration-300">
							</div>
							<div>
								<label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
									<i class="bi bi-box-seam mr-1"></i> Estoque Inicial
								</label>
								<input type="number" name="estoque" min="0" value="0" required
									class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-200 dark:focus:ring-emerald-900 transition-all duration-300"
									placeholder="Ex: 50">
							</div>
							<button type="submit" class="w-full px-6 py-3 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 font-semibold">
								<i class="bi bi-plus-lg mr-2"></i>Adicionar Medicamento
							</button>
							</div>
						</form>
					</div>
				</div>
				
				<!-- Estatísticas Rápidas -->
				<div class="glass-effect rounded-2xl shadow-xl border border-orange-200 dark:border-gray-700 overflow-hidden">
					<div class="bg-gradient-to-r from-orange-500 to-red-600 px-6 py-4">
						<h2 class="text-lg font-bold text-white flex items-center">
							<i class="bi bi-graph-up mr-2 text-xl"></i>
							Estatísticas
						</h2>
					</div>
					<div class="p-4 space-y-3">
						<?php 
						$total_meds = $mysqli->query("SELECT COUNT(*) as total FROM medications")->fetch_assoc()['total'];
						$total_stock = $mysqli->query("SELECT SUM(quantity) as total FROM pharmacy_stock")->fetch_assoc()['total'] ?? 0;
						$low_stock = $mysqli->query("SELECT COUNT(*) as total FROM pharmacy_stock WHERE quantity < 10")->fetch_assoc()['total'];
						?>
						<div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 border-l-4 border-blue-500">
							<div class="flex items-center justify-between">
								<div>
									<p class="text-xs text-blue-600 dark:text-blue-400 font-semibold uppercase">Total Medicamentos</p>
									<p class="text-xl font-bold text-blue-800 dark:text-blue-200"><?= $total_meds ?></p>
								</div>
								<i class="bi bi-capsule-pill text-3xl text-blue-500"></i>
							</div>
						</div>
						<div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-3 border-l-4 border-emerald-500">
							<div class="flex items-center justify-between">
								<div>
									<p class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold uppercase">Unidades em Stock</p>
									<p class="text-xl font-bold text-emerald-800 dark:text-emerald-200"><?= $total_stock ?></p>
								</div>
								<i class="bi bi-box-seam text-3xl text-emerald-500"></i>
							</div>
						</div>
						<div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3 border-l-4 border-red-500">
							<div class="flex items-center justify-between">
								<div>
									<p class="text-xs text-red-600 dark:text-red-400 font-semibold uppercase">Stock Baixo (&lt;10)</p>
									<p class="text-xl font-bold text-red-800 dark:text-red-200"><?= $low_stock ?></p>
								</div>
								<i class="bi bi-exclamation-triangle-fill text-3xl text-red-500"></i>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Lista de Medicamentos -->
			<div class="lg:col-span-2 space-y-6">
				<div class="glass-effect rounded-2xl shadow-xl border border-emerald-200 dark:border-gray-700 overflow-hidden">
					<div class="bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-4">
						<h2 class="text-xl font-bold text-white flex items-center">
							<i class="bi bi-list-ul mr-3 text-2xl"></i>
							Inventário de Medicamentos
						</h2>
					</div>
					<div class="p-6">
						<div class="overflow-x-auto rounded-lg border-2 border-gray-200 dark:border-gray-700">
							<table class="w-full">
								<thead class="bg-gradient-to-r from-emerald-100 to-teal-100 dark:from-emerald-900 dark:to-teal-900">
										<tr>
											<th class="px-4 py-3 text-left text-xs font-bold text-emerald-800 dark:text-emerald-200 uppercase tracking-wider">Medicamento</th>
											<th class="px-4 py-3 text-left text-xs font-bold text-emerald-800 dark:text-emerald-200 uppercase tracking-wider">Estoque</th>
											<th class="px-4 py-3 text-center text-xs font-bold text-emerald-800 dark:text-emerald-200 uppercase tracking-wider">Ações</th>
										</tr>
									</thead>
									<tbody class="divide-y divide-gray-200 dark:divide-gray-700">
										<?php while($m = $medicamentos->fetch_assoc()): ?>
										<tr class="hover:bg-emerald-50 dark:hover:bg-gray-700 transition-colors duration-200">
											<form method="post">
												<input type="hidden" name="acao" value="edit_medicamento">
												<input type="hidden" name="id" value="<?= $m['id'] ?>">
												<td class="px-4 py-3">
													<div class="flex items-center space-x-2">
														<input type="text" name="nome_medicamento" value="<?= htmlspecialchars($m['nome']) ?>" required
															class="flex-1 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:focus:ring-emerald-900 transition-all duration-300 text-sm">
														<a href="medicamento_stock.php?med_id=<?= $m['id'] ?>" 
														   class="px-3 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 text-xs font-medium whitespace-nowrap"
														   title="Gestão de Estoque">
															<i class="bi bi-box-seam"></i> Estoque
														</a>
													</div>
												</td>
												<td class="px-4 py-3">
													<div class="flex items-center space-x-2">
														<input type="number" name="estoque" value="<?= $m['estoque'] ?>" min="0" required
															class="w-20 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:focus:ring-emerald-900 transition-all duration-300 text-sm">
														<span class="text-xs text-gray-500 dark:text-gray-400 font-medium">unid.</span>
													</div>
												</td>
												<td class="px-4 py-3">
													<div class="flex items-center justify-center space-x-2">
														<button type="submit" class="px-3 py-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 text-sm font-medium">
															<i class="bi bi-save"></i>
														</button>
											</form>
											<form method="post" class="inline">
												<input type="hidden" name="acao" value="del_medicamento">
												<input type="hidden" name="id" value="<?= $m['id'] ?>">
												<button type="submit" onclick="return confirm('Deseja realmente excluir este medicamento?')" class="px-3 py-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105 text-sm font-medium">
													<i class="bi bi-trash"></i>
												</button>
											</form>
													</div>
												</td>
										</tr>
										<?php endwhile; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Footer -->
		<footer class="mt-12 text-center pb-8">
			<div class="glass-effect rounded-xl px-6 py-4 inline-block shadow-lg border border-emerald-200 dark:border-gray-700">
				<p class="text-sm text-gray-600 dark:text-gray-400">
					<i class="bi bi-heart-pulse-fill text-emerald-600 dark:text-emerald-400 mr-2"></i>
					<strong class="text-emerald-700 dark:text-emerald-300">Sistema Integrado Mais Saúde</strong> — Farmácia
				</p>
				<p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
					© 2025 | Desenvolvido com tecnologia de ponta
				</p>
			</div>
		</footer>
	</div>
</body>
</html>
