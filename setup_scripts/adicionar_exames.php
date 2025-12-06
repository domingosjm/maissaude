<?php
require_once 'config.php';

// Lista de exames a serem adicionados
$exames = [
    // Perfil Cardíaco
    ['Perfil Cardíaco - CK-MB', 750],
    ['Perfil Cardíaco - Troponina', 1100],
    
    // Perfil Hepático
    ['Perfil Hepático - AST', 480],
    ['Perfil Hepático - ALT', 470],
    ['Perfil Hepático - Albumina', 330],
    ['Perfil Hepático - Proteínas totais', 330],
    ['Perfil Hepático - Fosfatase Alcalina', 330],
    ['Perfil Hepático - GGT', 410],
    ['Perfil Hepático - Bilirrubina total', 400],
    
    // Perfil Renal
    ['Perfil Renal - Ureia', 410],
    ['Perfil Renal - Creatinina', 330],
    ['Perfil Renal - Ácido úrico', 490],
    ['Perfil Renal - Sódio', 330],
    ['Perfil Renal - Potássio', 330],
    ['Perfil Renal - Cloro', 330],
    
    // Exames Hematológicos
    ['Hemograma', 850],
    ['Pesquisa de plasmodium', 800],
    ['Esfregaço sanguíneo', 1000],
    ['VHS', 550],
    ['Pesquisa de plasmodium - teste rápido', 650],
    
    // Exames Parasitológicos
    ['Exame parasitológico de Urina', 550],
    ['Exame parasitológico de fezes', 640]
];

$contador = 0;
$erros = [];

echo "Iniciando inserção de exames...\n\n";

foreach ($exames as $exame) {
    $nome = $exame[0];
    $preco = $exame[1];
    
    // Verificar se já existe
    $check_stmt = $mysqli->prepare("SELECT id FROM services WHERE name = ? AND category = 'exame'");
    $check_stmt->bind_param('s', $nome);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "⚠️  Já existe: $nome\n";
        $check_stmt->close();
        continue;
    }
    $check_stmt->close();
    
    // Inserir novo exame
    $stmt = $mysqli->prepare("INSERT INTO services (name, category, price, active) VALUES (?, 'exame', ?, 1)");
    $stmt->bind_param('sd', $nome, $preco);
    
    if ($stmt->execute()) {
        $contador++;
        echo "✅ Inserido: $nome - $preco MT\n";
    } else {
        $erros[] = "Erro ao inserir $nome: " . $stmt->error;
        echo "❌ Erro: $nome\n";
    }
    
    $stmt->close();
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Resumo:\n";
echo "Total de exames processados: " . count($exames) . "\n";
echo "Exames inseridos com sucesso: $contador\n";

if (!empty($erros)) {
    echo "\nErros encontrados:\n";
    foreach ($erros as $erro) {
        echo "- $erro\n";
    }
}

echo "\n✓ Concluído!\n";
?>
