<?php
require_once 'config.php';

echo "Verificando usuárias de psicologia...\n\n";

// Emails das profissionais de psicologia
$emails = [
    'tania.belo@maissaude.co.mz',
    'laurinda.canaogai@maissaude.co.mz'
];

// Buscar ID da especialidade Psicologia
$specialty_query = $mysqli->query("SELECT id FROM specialties WHERE name LIKE '%psicolog%' OR name LIKE '%psiquiatr%'");
$specialty = $specialty_query->fetch_assoc();

if (!$specialty) {
    echo "❌ Especialidade Psicologia não encontrada!\n";
    echo "Criando especialidade...\n";
    $mysqli->query("INSERT INTO specialties (name, description) VALUES ('Psicologia', 'Atendimento psicológico e psiquiátrico')");
    $specialty_id = $mysqli->insert_id;
    echo "✅ Especialidade Psicologia criada (ID: $specialty_id)\n\n";
} else {
    $specialty_id = $specialty['id'];
    echo "✅ Especialidade encontrada (ID: $specialty_id)\n\n";
}

foreach ($emails as $email) {
    echo "Processando: $email\n";
    echo str_repeat("-", 60) . "\n";
    
    // Buscar usuária
    $user_query = $mysqli->query("SELECT id, name FROM users WHERE email = '$email'");
    $user = $user_query->fetch_assoc();
    
    if (!$user) {
        echo "❌ Usuária não encontrada no sistema!\n\n";
        continue;
    }
    
    $user_id = $user['id'];
    $user_name = $user['name'];
    
    echo "✅ Usuária encontrada: $user_name (ID: $user_id)\n";
    
    // Verificar se já é profissional
    $prof_query = $mysqli->query("SELECT id FROM professionals WHERE user_id = $user_id");
    $prof = $prof_query->fetch_assoc();
    
    if ($prof) {
        echo "✅ Já cadastrada como profissional (ID: {$prof['id']})\n";
        
        // Atualizar especialidade se necessário
        $mysqli->query("UPDATE professionals SET specialty_id = $specialty_id WHERE id = {$prof['id']}");
        echo "✅ Especialidade atualizada para Psicologia\n\n";
    } else {
        echo "⚠️  Não está na tabela professionals\n";
        echo "Cadastrando como profissional de Psicologia...\n";
        
        $stmt = $mysqli->prepare("INSERT INTO professionals (user_id, specialty_id, crm_crp) VALUES (?, ?, ?)");
        $crm_crp = 'CRP-' . rand(1000, 9999);
        $stmt->bind_param('iis', $user_id, $specialty_id, $crm_crp);
        
        if ($stmt->execute()) {
            echo "✅ Cadastrada como profissional de Psicologia!\n";
            echo "   CRP gerado: $crm_crp\n\n";
        } else {
            echo "❌ Erro ao cadastrar: " . $stmt->error . "\n\n";
        }
        $stmt->close();
    }
}

echo str_repeat("=", 60) . "\n";
echo "Listando todos os profissionais de Psicologia:\n\n";

$result = $mysqli->query("
    SELECT p.id, u.name, u.email, s.name as specialty, p.crm_crp 
    FROM professionals p 
    JOIN users u ON p.user_id = u.id 
    JOIN specialties s ON p.specialty_id = s.id 
    WHERE s.name LIKE '%psicolog%' OR s.name LIKE '%psiquiatr%'
    ORDER BY u.name
");

while ($row = $result->fetch_assoc()) {
    echo "✅ {$row['name']}\n";
    echo "   Email: {$row['email']}\n";
    echo "   Especialidade: {$row['specialty']}\n";
    echo "   CRP: {$row['crm_crp']}\n";
    echo str_repeat("-", 60) . "\n";
}

echo "\n✓ Concluído!\n";
?>
