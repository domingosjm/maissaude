<?php
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';
require_login();

$conn = $mysqli;
$user_name = $_SESSION['user_name'] ?? 'Usuário';
$user_email = $_SESSION['user_email'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Verificar se o usuário é da recepção
$allowed_roles = ['recepcao', 'admin'];
$user_role = $_SESSION['user_role'] ?? '';

if (!in_array($user_role, $allowed_roles)) {
    header('Location: dashboard.php');
    exit;
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_invoice_paid') {
        $invoice_id = $_POST['invoice_id'] ?? 0;
        $payment_method = $_POST['payment_method'] ?? '';
        $paid_amount = $_POST['paid_amount'] ?? 0;
        
        if ($invoice_id <= 0 || $paid_amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos!']);
            exit;
        }
        
        $conn->begin_transaction();
        
        try {
            // Buscar fatura
            $invoice = $conn->query("SELECT * FROM patient_invoices WHERE id = $invoice_id")->fetch_assoc();
            
            if (!$invoice) {
                throw new Exception("Fatura não encontrada!");
            }
            
            $new_paid_amount = $invoice['paid_amount'] + $paid_amount;
            $status = 'parcialmente_pago';
            
            if ($new_paid_amount >= $invoice['total_amount']) {
                $status = 'pago';
                $new_paid_amount = $invoice['total_amount'];
            }
            
            $stmt = $conn->prepare("UPDATE patient_invoices 
                                    SET paid_amount = ?, status = ?, payment_method = ?, paid_at = NOW() 
                                    WHERE id = ?");
            $stmt->bind_param('dssi', $new_paid_amount, $status, $payment_method, $invoice_id);
            $stmt->execute();
            
            // Registrar pagamento
            $stmt2 = $conn->prepare("INSERT INTO invoice_payments 
                                     (invoice_id, amount, payment_method, received_by, received_at) 
                                     VALUES (?, ?, ?, ?, NOW())");
            $stmt2->bind_param('idsi', $invoice_id, $paid_amount, $payment_method, $user_id);
            $stmt2->execute();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Pagamento registrado com sucesso!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Buscar paciente se ID fornecido
$patient_id = $_GET['patient_id'] ?? 0;
$patient = null;
$patient_invoices = [];

if ($patient_id > 0) {
    $patient = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();
    
    // Buscar faturas do paciente
    $query = "SELECT pi.*, 
                     CASE 
                         WHEN pi.invoice_type = 'farmacia' THEN 'Farmácia'
                         WHEN pi.invoice_type = 'consulta' THEN 'Consulta'
                         WHEN pi.invoice_type = 'exames' THEN 'Exames'
                         WHEN pi.invoice_type = 'procedimentos' THEN 'Procedimentos'
                     END as type_label,
                     u.name as created_by_name
              FROM patient_invoices pi
              JOIN users u ON pi.created_by = u.id
              WHERE pi.patient_id = $patient_id
              ORDER BY pi.created_at DESC";
    
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        // Buscar detalhes da fatura se for de farmácia
        if ($row['invoice_type'] === 'farmacia' && $row['prescription_id']) {
            $prescription_id = $row['prescription_id'];
            $details_query = "SELECT md.*, m.name as medication_name 
                             FROM medication_dispensing md 
                             JOIN medications m ON md.medication_id = m.id 
                             WHERE md.prescription_id = $prescription_id";
            $details_result = $conn->query($details_query);
            $row['details'] = [];
            while ($detail = $details_result->fetch_assoc()) {
                $row['details'][] = $detail;
            }
        }
        $patient_invoices[] = $row;
    }
}

// Buscar todos os pacientes para pesquisa
$all_patients = [];
$query = "SELECT id, name, contact_number FROM patients ORDER BY name ASC LIMIT 100";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $all_patients[] = $row;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faturas do Paciente - Recepção</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        .invoice-card {
            border-left: 4px solid #667eea;
        }
        .invoice-card.paid {
            border-left-color: #28a745;
        }
        .invoice-card.pending {
            border-left-color: #ffc107;
        }
        .invoice-card.partial {
            border-left-color: #17a2b8;
        }
        .invoice-card.cancelled {
            border-left-color: #dc3545;
        }
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85em;
        }
        .patient-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .total-summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-hospital me-2"></i>MSLDA - Recepção
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white me-3">
                    <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($user_name); ?>
                </span>
                <a class="btn btn-outline-light btn-sm" href="logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-search me-2"></i>Buscar Paciente</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="patient_invoices.php">
                            <div class="mb-3">
                                <label class="form-label">Selecione o Paciente</label>
                                <select class="form-select" name="patient_id" required onchange="this.form.submit()">
                                    <option value="">Escolha...</option>
                                    <?php foreach ($all_patients as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" <?php echo ($p['id'] == $patient_id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['name']); ?> - <?php echo htmlspecialchars($p['contact_number']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                        
                        <?php if ($patient): ?>
                            <hr>
                            <h6>Ações Rápidas</h6>
                            <div class="d-grid gap-2">
                                <a href="reception.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-calendar-plus me-2"></i>Nova Consulta
                                </a>
                                <a href="export_diagnoses.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-info" target="_blank">
                                    <i class="fas fa-file-pdf me-2"></i>Exportar Diagnósticos
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <?php if ($patient): ?>
                    <div class="patient-info">
                        <div class="row">
                            <div class="col-md-8">
                                <h4><i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($patient['name']); ?></h4>
                                <p class="mb-1"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($patient['contact_number']); ?></p>
                                <p class="mb-0"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($patient['email'] ?? 'Não informado'); ?></p>
                            </div>
                            <div class="col-md-4 text-end">
                                <?php
                                $total_pendente = 0;
                                $total_pago = 0;
                                foreach ($patient_invoices as $inv) {
                                    if ($inv['status'] === 'pendente' || $inv['status'] === 'parcialmente_pago') {
                                        $total_pendente += ($inv['total_amount'] - $inv['paid_amount']);
                                    }
                                    if ($inv['status'] === 'pago') {
                                        $total_pago += $inv['total_amount'];
                                    }
                                }
                                ?>
                                <h5>Saldo Pendente</h5>
                                <h3><?php echo number_format($total_pendente, 2); ?> MZN</h3>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Faturas do Paciente</h5>
                            <span class="badge bg-light text-dark"><?php echo count($patient_invoices); ?> fatura(s)</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($patient_invoices)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>Este paciente não possui faturas registradas.
                                </div>
                            <?php else: ?>
                                <?php foreach ($patient_invoices as $invoice): ?>
                                    <div class="card invoice-card <?php echo $invoice['status']; ?> mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6>
                                                        <i class="fas fa-hashtag me-2"></i>
                                                        <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                                    </h6>
                                                    <p class="mb-1">
                                                        <strong>Tipo:</strong> 
                                                        <span class="badge bg-secondary"><?php echo $invoice['type_label']; ?></span>
                                                    </p>
                                                    <p class="mb-1">
                                                        <strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($invoice['created_at'])); ?>
                                                    </p>
                                                    <p class="mb-1">
                                                        <strong>Criado por:</strong> <?php echo htmlspecialchars($invoice['created_by_name']); ?>
                                                    </p>
                                                </div>
                                                <div class="col-md-3">
                                                    <p class="mb-1"><strong>Valor Total:</strong></p>
                                                    <h5 class="text-primary"><?php echo number_format($invoice['total_amount'], 2); ?> MZN</h5>
                                                    <?php if ($invoice['status'] === 'parcialmente_pago'): ?>
                                                        <p class="mb-1"><strong>Pago:</strong> <?php echo number_format($invoice['paid_amount'], 2); ?> MZN</p>
                                                        <p class="mb-0"><strong>Restante:</strong> <?php echo number_format($invoice['total_amount'] - $invoice['paid_amount'], 2); ?> MZN</p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-3 text-end">
                                                    <?php
                                                    $status_class = [
                                                        'pendente' => 'bg-warning text-dark',
                                                        'pago' => 'bg-success',
                                                        'parcialmente_pago' => 'bg-info',
                                                        'cancelado' => 'bg-danger'
                                                    ];
                                                    $status_label = [
                                                        'pendente' => 'Pendente',
                                                        'pago' => 'Pago',
                                                        'parcialmente_pago' => 'Parcialmente Pago',
                                                        'cancelado' => 'Cancelado'
                                                    ];
                                                    ?>
                                                    <span class="status-badge <?php echo $status_class[$invoice['status']]; ?>">
                                                        <?php echo $status_label[$invoice['status']]; ?>
                                                    </span>
                                                    
                                                    <?php if ($invoice['status'] !== 'pago' && $invoice['status'] !== 'cancelado'): ?>
                                                        <button class="btn btn-success btn-sm mt-2" onclick="registerPayment(<?php echo $invoice['id']; ?>, <?php echo $invoice['total_amount'] - $invoice['paid_amount']; ?>)">
                                                            <i class="fas fa-money-bill-wave me-1"></i>Registrar Pagamento
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($invoice['details'])): ?>
                                                <hr>
                                                <h6>Detalhes dos Medicamentos:</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Medicamento</th>
                                                                <th>Quantidade</th>
                                                                <th>Preço Unit.</th>
                                                                <th>Total</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($invoice['details'] as $detail): ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars($detail['medication_name']); ?></td>
                                                                    <td><?php echo $detail['quantity']; ?></td>
                                                                    <td><?php echo number_format($detail['unit_price'], 2); ?> MZN</td>
                                                                    <td><?php echo number_format($detail['total_price'], 2); ?> MZN</td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($invoice['notes'])): ?>
                                                <div class="alert alert-secondary mt-2 mb-0">
                                                    <strong>Observações:</strong> <?php echo htmlspecialchars($invoice['notes']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="total-summary">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <h6>Total Faturado</h6>
                                            <h5 class="text-primary">
                                                <?php
                                                $total_faturado = array_sum(array_column($patient_invoices, 'total_amount'));
                                                echo number_format($total_faturado, 2);
                                                ?> MZN
                                            </h5>
                                        </div>
                                        <div class="col-md-4">
                                            <h6>Total Pago</h6>
                                            <h5 class="text-success">
                                                <?php echo number_format($total_pago, 2); ?> MZN
                                            </h5>
                                        </div>
                                        <div class="col-md-4">
                                            <h6>Total Pendente</h6>
                                            <h5 class="text-danger">
                                                <?php echo number_format($total_pendente, 2); ?> MZN
                                            </h5>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-user-search fa-5x text-muted mb-3"></i>
                            <h5>Selecione um paciente</h5>
                            <p class="text-muted">Use o menu ao lado para buscar e visualizar as faturas de um paciente.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal para registrar pagamento -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i>Registrar Pagamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="paymentForm">
                        <input type="hidden" name="invoice_id" id="payment_invoice_id">
                        <div class="mb-3">
                            <label class="form-label">Valor Pendente</label>
                            <input type="text" class="form-control" id="pending_amount" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Valor a Pagar *</label>
                            <input type="number" step="0.01" class="form-control" name="paid_amount" id="paid_amount" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Método de Pagamento *</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="">Selecione...</option>
                                <option value="Dinheiro">Dinheiro</option>
                                <option value="MPesa">MPesa</option>
                                <option value="E-Mola">E-Mola</option>
                                <option value="Cartão de Crédito">Cartão de Crédito</option>
                                <option value="Cartão de Débito">Cartão de Débito</option>
                                <option value="Transferência Bancária">Transferência Bancária</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-check me-2"></i>Confirmar Pagamento
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
        
        function registerPayment(invoiceId, pendingAmount) {
            document.getElementById('payment_invoice_id').value = invoiceId;
            document.getElementById('pending_amount').value = pendingAmount.toFixed(2) + ' MZN';
            document.getElementById('paid_amount').value = pendingAmount.toFixed(2);
            document.getElementById('paid_amount').max = pendingAmount;
            paymentModal.show();
        }
        
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'mark_invoice_paid');
            
            fetch('patient_invoices.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            });
        });
    </script>
</body>
</html>
