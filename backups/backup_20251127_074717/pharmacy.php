<?php
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';
require_login();

$conn = $mysqli;
$user_name = $_SESSION['user_name'] ?? 'Usuário';
$user_email = $_SESSION['user_email'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Verificar se o usuário tem permissão para acessar a farmácia
$allowed_roles = ['farmaceutico', 'admin'];
$user_role = $_SESSION['user_role'] ?? '';

if (!in_array($user_role, $allowed_roles)) {
    header('Location: dashboard.php');
    exit;
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'dispense_medication') {
        $prescription_id = $_POST['prescription_id'] ?? 0;
        $patient_id = $_POST['patient_id'] ?? 0;
        $medications = $_POST['medications'] ?? [];
        
        if (empty($prescription_id) || empty($patient_id) || empty($medications)) {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos!']);
            exit;
        }
        
        $conn->begin_transaction();
        
        try {
            $total_amount = 0;
            
            // Processar cada medicamento
            foreach ($medications as $med) {
                $medication_id = $med['id'] ?? 0;
                $quantity = $med['quantity'] ?? 0;
                $price = $med['price'] ?? 0;
                
                if ($medication_id <= 0 || $quantity <= 0) {
                    continue;
                }
                
                // Verificar stock disponível
                $stock_check = $conn->query("SELECT stock_quantity FROM medications WHERE id = $medication_id")->fetch_assoc();
                
                if (!$stock_check || $stock_check['stock_quantity'] < $quantity) {
                    throw new Exception("Stock insuficiente para o medicamento ID: $medication_id");
                }
                
                // Atualizar stock
                $conn->query("UPDATE medications SET stock_quantity = stock_quantity - $quantity WHERE id = $medication_id");
                
                // Registrar saída de medicamento
                $stmt = $conn->prepare("INSERT INTO medication_dispensing 
                                        (prescription_id, medication_id, patient_id, quantity, unit_price, total_price, dispensed_by, dispensed_at) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $total_price = $quantity * $price;
                $total_amount += $total_price;
                $stmt->bind_param('iiidddi', $prescription_id, $medication_id, $patient_id, $quantity, $price, $total_price, $user_id);
                $stmt->execute();
            }
            
            // Criar fatura para o paciente
            $invoice_number = 'FT-' . date('Ymd') . '-' . str_pad($patient_id, 6, '0', STR_PAD_LEFT) . '-' . time();
            $stmt = $conn->prepare("INSERT INTO patient_invoices 
                                    (invoice_number, patient_id, prescription_id, total_amount, status, invoice_type, created_by, created_at) 
                                    VALUES (?, ?, ?, ?, 'pendente', 'farmacia', ?, NOW())");
            $stmt->bind_param('siidi', $invoice_number, $patient_id, $prescription_id, $total_amount, $user_id);
            $stmt->execute();
            
            // Atualizar status da prescrição
            $conn->query("UPDATE prescriptions SET status = 'dispensado', dispensed_at = NOW() WHERE id = $prescription_id");
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Medicamentos dispensados e faturados com sucesso!', 'invoice' => $invoice_number]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'add_medication') {
        $name = $_POST['name'] ?? '';
        $generic_name = $_POST['generic_name'] ?? '';
        $category = $_POST['category'] ?? '';
        $price = $_POST['price'] ?? 0;
        $stock_quantity = $_POST['stock_quantity'] ?? 0;
        $minimum_stock = $_POST['minimum_stock'] ?? 10;
        $description = $_POST['description'] ?? '';
        
        if (empty($name) || $price <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos!']);
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO medications 
                                (name, generic_name, category, price, stock_quantity, minimum_stock, description, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('sssdiis', $name, $generic_name, $category, $price, $stock_quantity, $minimum_stock, $description);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Medicamento adicionado com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao adicionar medicamento: ' . $conn->error]);
        }
        exit;
    }
    
    if ($action === 'update_stock') {
        $medication_id = $_POST['medication_id'] ?? 0;
        $quantity = $_POST['quantity'] ?? 0;
        $operation = $_POST['operation'] ?? 'add'; // add or remove
        $notes = $_POST['notes'] ?? '';
        
        if ($medication_id <= 0 || $quantity <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dados inválidos!']);
            exit;
        }
        
        $conn->begin_transaction();
        
        try {
            if ($operation === 'add') {
                $conn->query("UPDATE medications SET stock_quantity = stock_quantity + $quantity WHERE id = $medication_id");
            } else {
                $conn->query("UPDATE medications SET stock_quantity = stock_quantity - $quantity WHERE id = $medication_id");
            }
            
            // Registrar movimento de stock
            $stmt = $conn->prepare("INSERT INTO stock_movements 
                                    (medication_id, quantity, operation, notes, created_by, created_at) 
                                    VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('iissi', $medication_id, $quantity, $operation, $notes, $user_id);
            $stmt->execute();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Stock atualizado com sucesso!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Buscar prescrições pendentes
$pending_prescriptions = [];
$query = "SELECT p.*, pt.name as patient_name, pt.id as patient_id, u.name as doctor_name
          FROM prescriptions p
          JOIN appointments a ON p.appointment_id = a.id
          JOIN patients pt ON a.patient_id = pt.id
          JOIN professionals pr ON a.professional_id = pr.id
          JOIN users u ON pr.user_id = u.id
          WHERE p.status = 'pendente'
          ORDER BY p.created_at DESC";

$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    // Buscar medicamentos da prescrição
    $prescription_id = $row['id'];
    $meds_query = "SELECT pm.*, m.name, m.price, m.stock_quantity 
                   FROM prescription_medications pm 
                   JOIN medications m ON pm.medication_id = m.id 
                   WHERE pm.prescription_id = $prescription_id";
    $meds_result = $conn->query($meds_query);
    $row['medications'] = [];
    while ($med = $meds_result->fetch_assoc()) {
        $row['medications'][] = $med;
    }
    $pending_prescriptions[] = $row;
}

// Buscar medicamentos com stock baixo
$low_stock_medications = [];
$query = "SELECT * FROM medications WHERE stock_quantity <= minimum_stock ORDER BY stock_quantity ASC";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $low_stock_medications[] = $row;
}

// Buscar todos os medicamentos
$all_medications = [];
$query = "SELECT * FROM medications ORDER BY name ASC";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $all_medications[] = $row;
}

// Buscar histórico de dispensações
$dispensing_history = [];
$query = "SELECT md.*, m.name as medication_name, p.name as patient_name, u.name as dispensed_by_name
          FROM medication_dispensing md
          JOIN medications m ON md.medication_id = m.id
          JOIN patients p ON md.patient_id = p.id
          JOIN users u ON md.dispensed_by = u.id
          ORDER BY md.dispensed_at DESC
          LIMIT 50";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $dispensing_history[] = $row;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmácia - MSLDA</title>
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
        .stat-card {
            padding: 20px;
            border-radius: 10px;
            color: white;
            margin-bottom: 20px;
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card.success {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .low-stock {
            background-color: #fff3cd;
        }
        .medication-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .medication-item:last-child {
            border-bottom: none;
        }
        .btn-dispense {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .btn-dispense:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-pills me-2"></i>MSLDA - Farmácia
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
                <div class="stat-card info">
                    <h4><i class="fas fa-prescription me-2"></i><?php echo count($pending_prescriptions); ?></h4>
                    <p class="mb-0">Prescrições Pendentes</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <h4><i class="fas fa-exclamation-triangle me-2"></i><?php echo count($low_stock_medications); ?></h4>
                    <p class="mb-0">Medicamentos em Falta</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success">
                    <h4><i class="fas fa-pills me-2"></i><?php echo count($all_medications); ?></h4>
                    <p class="mb-0">Total de Medicamentos</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card info">
                    <h4><i class="fas fa-file-invoice-dollar me-2"></i><?php echo count($dispensing_history); ?></h4>
                    <p class="mb-0">Dispensações Recentes</p>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-4" id="pharmacyTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="prescriptions-tab" data-bs-toggle="tab" data-bs-target="#prescriptions" type="button">
                    <i class="fas fa-prescription me-2"></i>Prescrições
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="medications-tab" data-bs-toggle="tab" data-bs-target="#medications" type="button">
                    <i class="fas fa-pills me-2"></i>Gestão de Medicamentos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock" type="button">
                    <i class="fas fa-boxes me-2"></i>Gestão de Stock
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button">
                    <i class="fas fa-history me-2"></i>Histórico
                </button>
            </li>
        </ul>

        <div class="tab-content" id="pharmacyTabsContent">
            <!-- Tab de Prescrições -->
            <div class="tab-pane fade show active" id="prescriptions" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-prescription me-2"></i>Prescrições Pendentes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_prescriptions)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>Não há prescrições pendentes no momento.
                            </div>
                        <?php else: ?>
                            <?php foreach ($pending_prescriptions as $prescription): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-user me-2"></i>Paciente: <?php echo htmlspecialchars($prescription['patient_name']); ?></h6>
                                                <p class="mb-1"><strong>Médico:</strong> <?php echo htmlspecialchars($prescription['doctor_name']); ?></p>
                                                <p class="mb-1"><strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($prescription['created_at'])); ?></p>
                                            </div>
                                            <div class="col-md-6 text-end">
                                                <button class="btn btn-dispense" onclick="dispensePrescription(<?php echo $prescription['id']; ?>, <?php echo $prescription['patient_id']; ?>)">
                                                    <i class="fas fa-check me-2"></i>Dispensar Medicamentos
                                                </button>
                                            </div>
                                        </div>
                                        <hr>
                                        <h6>Medicamentos Prescritos:</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Medicamento</th>
                                                        <th>Dosagem</th>
                                                        <th>Frequência</th>
                                                        <th>Duração</th>
                                                        <th>Quantidade</th>
                                                        <th>Preço Unit.</th>
                                                        <th>Stock</th>
                                                        <th>Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $total = 0;
                                                    foreach ($prescription['medications'] as $med): 
                                                        $subtotal = $med['quantity'] * $med['price'];
                                                        $total += $subtotal;
                                                        $low_stock_class = ($med['stock_quantity'] < $med['quantity']) ? 'low-stock' : '';
                                                    ?>
                                                        <tr class="<?php echo $low_stock_class; ?>">
                                                            <td><?php echo htmlspecialchars($med['name']); ?></td>
                                                            <td><?php echo htmlspecialchars($med['dosage']); ?></td>
                                                            <td><?php echo htmlspecialchars($med['frequency']); ?></td>
                                                            <td><?php echo htmlspecialchars($med['duration']); ?></td>
                                                            <td><?php echo $med['quantity']; ?></td>
                                                            <td><?php echo number_format($med['price'], 2); ?> MZN</td>
                                                            <td>
                                                                <?php if ($med['stock_quantity'] < $med['quantity']): ?>
                                                                    <span class="badge bg-danger"><?php echo $med['stock_quantity']; ?> (Insuficiente)</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-success"><?php echo $med['stock_quantity']; ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><strong><?php echo number_format($subtotal, 2); ?> MZN</strong></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    <tr>
                                                        <td colspan="7" class="text-end"><strong>Total:</strong></td>
                                                        <td><strong><?php echo number_format($total, 2); ?> MZN</strong></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php if (!empty($prescription['notes'])): ?>
                                            <div class="alert alert-info mt-2">
                                                <strong>Observações:</strong> <?php echo htmlspecialchars($prescription['notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tab de Gestão de Medicamentos -->
            <div class="tab-pane fade" id="medications" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-pills me-2"></i>Gestão de Medicamentos</h5>
                        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addMedicationModal">
                            <i class="fas fa-plus me-2"></i>Adicionar Medicamento
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Nome Genérico</th>
                                        <th>Categoria</th>
                                        <th>Preço</th>
                                        <th>Stock</th>
                                        <th>Stock Mínimo</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_medications as $med): ?>
                                        <tr class="<?php echo ($med['stock_quantity'] <= $med['minimum_stock']) ? 'low-stock' : ''; ?>">
                                            <td><?php echo htmlspecialchars($med['name']); ?></td>
                                            <td><?php echo htmlspecialchars($med['generic_name']); ?></td>
                                            <td><?php echo htmlspecialchars($med['category']); ?></td>
                                            <td><?php echo number_format($med['price'], 2); ?> MZN</td>
                                            <td><?php echo $med['stock_quantity']; ?></td>
                                            <td><?php echo $med['minimum_stock']; ?></td>
                                            <td>
                                                <?php if ($med['stock_quantity'] <= 0): ?>
                                                    <span class="badge bg-danger">Sem Stock</span>
                                                <?php elseif ($med['stock_quantity'] <= $med['minimum_stock']): ?>
                                                    <span class="badge bg-warning">Stock Baixo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Disponível</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="editMedication(<?php echo $med['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab de Gestão de Stock -->
            <div class="tab-pane fade" id="stock" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Gestão de Stock Geral</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Medicamentos com Stock Baixo</h6>
                            <?php if (empty($low_stock_medications)): ?>
                                <p class="mb-0">Todos os medicamentos estão com stock adequado.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Medicamento</th>
                                                <th>Stock Atual</th>
                                                <th>Stock Mínimo</th>
                                                <th>Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($low_stock_medications as $med): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($med['name']); ?></td>
                                                    <td><span class="badge bg-danger"><?php echo $med['stock_quantity']; ?></span></td>
                                                    <td><?php echo $med['minimum_stock']; ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-success" onclick="updateStock(<?php echo $med['id']; ?>, 'add')">
                                                            <i class="fas fa-plus me-1"></i>Adicionar Stock
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <h6 class="mt-4">Atualização Rápida de Stock</h6>
                        <form id="stockUpdateForm" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Medicamento</label>
                                <select class="form-select" name="medication_id" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($all_medications as $med): ?>
                                        <option value="<?php echo $med['id']; ?>"><?php echo htmlspecialchars($med['name']); ?> (Stock: <?php echo $med['stock_quantity']; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Quantidade</label>
                                <input type="number" class="form-control" name="quantity" min="1" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Operação</label>
                                <select class="form-select" name="operation" required>
                                    <option value="add">Adicionar</option>
                                    <option value="remove">Remover</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Observações</label>
                                <input type="text" class="form-control" name="notes" placeholder="Ex: Compra, Devolução">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab de Histórico -->
            <div class="tab-pane fade" id="history" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Histórico de Dispensações</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Paciente</th>
                                        <th>Medicamento</th>
                                        <th>Quantidade</th>
                                        <th>Preço Unit.</th>
                                        <th>Total</th>
                                        <th>Dispensado Por</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dispensing_history as $item): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($item['dispensed_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($item['patient_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['medication_name']); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td><?php echo number_format($item['unit_price'], 2); ?> MZN</td>
                                            <td><strong><?php echo number_format($item['total_price'], 2); ?> MZN</strong></td>
                                            <td><?php echo htmlspecialchars($item['dispensed_by_name']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para adicionar medicamento -->
    <div class="modal fade" id="addMedicationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Adicionar Medicamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addMedicationForm">
                        <div class="mb-3">
                            <label class="form-label">Nome Comercial *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nome Genérico</label>
                            <input type="text" class="form-control" name="generic_name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Categoria</label>
                            <select class="form-select" name="category">
                                <option value="Antibiótico">Antibiótico</option>
                                <option value="Analgésico">Analgésico</option>
                                <option value="Anti-inflamatório">Anti-inflamatório</option>
                                <option value="Antitérmico">Antitérmico</option>
                                <option value="Antihipertensivo">Antihipertensivo</option>
                                <option value="Antidiabético">Antidiabético</option>
                                <option value="Vitamina">Vitamina</option>
                                <option value="Suplemento">Suplemento</option>
                                <option value="Outro">Outro</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Preço (MZN) *</label>
                                    <input type="number" step="0.01" class="form-control" name="price" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Stock Inicial</label>
                                    <input type="number" class="form-control" name="stock_quantity" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stock Mínimo</label>
                            <input type="number" class="form-control" name="minimum_stock" value="10">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Salvar Medicamento
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dispensar medicamentos
        function dispensePrescription(prescriptionId, patientId) {
            if (!confirm('Confirma a dispensação dos medicamentos desta prescrição?')) {
                return;
            }
            
            const medications = [];
            const table = event.target.closest('.card').querySelector('table tbody');
            const rows = table.querySelectorAll('tr:not(:last-child)');
            
            rows.forEach((row, index) => {
                const cells = row.cells;
                if (cells.length >= 8) {
                    medications.push({
                        id: parseInt(row.dataset.medicationId || (index + 1)),
                        quantity: parseInt(cells[4].textContent),
                        price: parseFloat(cells[5].textContent.replace(' MZN', ''))
                    });
                }
            });
            
            fetch('pharmacy.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'dispense_medication',
                    prescription_id: prescriptionId,
                    patient_id: patientId,
                    medications: JSON.stringify(medications)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message + '\nFatura: ' + data.invoice);
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            });
        }
        
        // Adicionar medicamento
        document.getElementById('addMedicationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add_medication');
            
            fetch('pharmacy.php', {
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
        
        // Atualizar stock
        document.getElementById('stockUpdateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'update_stock');
            
            fetch('pharmacy.php', {
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
