<?php
require_once 'config.php';

// Lista de medicamentos a serem adicionados
$medicamentos = [
    ['Ibuprofeno 400 mg comp.', 570],
    ['Metronidazol 250 mg comp.', 311],
    ['Co-trimoxazol 480 mg comp.', 268],
    ['Multivitamina (30 comp.)', 482],
    ['Norfloxacina + Metronidazol 400 mg', 786],
    ['Diclofenac gel 10 mg/g', 423],
    ['Paracetamol 500 mg comp.', 148],
    ['Lansoprazol 30 mg', 798],
    ['Creme vaginal 1%', 587],
    ['Diclofenac gel 1%', 423],
    ['Microginon', 900],
    ['Spray nebulizador gotas', 342],
    ['Desloratadina 5 mg', 304],
    ['Fosfato de dexametasona colírio 5 mL', 580],
    ['Clotrimazol creme 1%', 189],
    ['Durex', 490],
    ['Salbutamol xarope 2 mg/5 mL', 210],
    ['Salbutamol suspensão 2 mg/5 mL', 210],
    ['Multivitamina / Vitamina B xarope', 688],
    ['Panado (Paracetamol) suspensão 100 mL', 505],
    ['Benuron xarope 40 mg/mL', 747],
    ['Vitamina C 500 mg', 430],
    ['Ambroxol xarope 30 mg/5 mL', 740],
    ['Ácido Fusídico creme 2%', 420],
    ['Sulfato ferroso + ácido fólico (300 comp.)', 295],
    ['Multivitamina (30 comp.)', 482],
    ['Aciclovir pomada oftálmica 3% (10 tubos)', 225],
    ['Valproato de sódio 200 mg (60 comp.)', 1529],
    ['Preservativo masculino (4 unidades)', 490],
    ['Atorvastatina 10 mg (30 comp.)', 1269],
    ['Alprazolam 0,5 mg (60 comp.)', 482],
    ['Vitaminas Complexo B (Ciavita) 100 comp.', 482],
    ['Clotrimazol 1% (1 tubo)', 189],
    ['Cadnil (Clopidogrel 75 mg) 20 comp.', 347],
    ['Colecalciferol 5000 UI (60)', 459],
    ['Multivitamina xarope', 688],
    ['Bacisept (Pomada de Zinco + Bacitracina)', 420],
    ['Ácido acetilsalicílico 500 mg (100 comp.)', 430],
    ['Amoxicilina + ácido clavulânico 228 mg/5 mL susp.', 509],
    ['Enalapril 20 mg (30 comp.)', 137],
    ['Metoclopramida 5 mg (80 comp.)', 190],
    ['Amlodipina 5 mg (100 comp.)', 456],
    ['Butilescopolamina (Bioscine) inj.', 498],
    ['Betametasona + Clotrimazol + Gentamicina creme', 520],
    ['Ácido benzóico pomada 30 g', 447]
];

$contador = 0;
$atualizados = 0;
$erros = [];

echo "Iniciando inserção de medicamentos nos serviços...\n\n";

foreach ($medicamentos as $medicamento) {
    $nome = $medicamento[0];
    $preco = $medicamento[1];
    
    // Verificar se já existe
    $check_stmt = $mysqli->prepare("SELECT id, price FROM services WHERE name = ? AND category = 'medicamento'");
    $check_stmt->bind_param('s', $nome);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Já existe - atualizar preço se diferente
        $existing = $result->fetch_assoc();
        if ($existing['price'] != $preco) {
            $update_stmt = $mysqli->prepare("UPDATE services SET price = ? WHERE id = ?");
            $update_stmt->bind_param('di', $preco, $existing['id']);
            if ($update_stmt->execute()) {
                $atualizados++;
                echo "🔄 Atualizado: $nome - $preco MT (era " . $existing['price'] . " MT)\n";
            }
            $update_stmt->close();
        } else {
            echo "⚠️  Já existe: $nome - $preco MT\n";
        }
        $check_stmt->close();
        continue;
    }
    $check_stmt->close();
    
    // Inserir novo medicamento
    $stmt = $mysqli->prepare("INSERT INTO services (name, category, price, active) VALUES (?, 'medicamento', ?, 1)");
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

echo "\n" . str_repeat("=", 60) . "\n";
echo "Resumo:\n";
echo "Total de medicamentos processados: " . count($medicamentos) . "\n";
echo "Medicamentos inseridos: $contador\n";
echo "Medicamentos atualizados: $atualizados\n";

if (!empty($erros)) {
    echo "\nErros encontrados:\n";
    foreach ($erros as $erro) {
        echo "- $erro\n";
    }
}

echo "\n✓ Concluído!\n";
?>
