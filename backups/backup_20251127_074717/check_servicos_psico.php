<?php
require_once 'config.php';

echo "Serviços de consulta no banco:\n";
echo str_repeat("=", 60) . "\n";

$result = $mysqli->query("SELECT id, name, category, price FROM services WHERE category = 'consulta' OR name LIKE '%psico%' OR name LIKE '%psiqui%'");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}\n";
        echo "Nome: {$row['name']}\n";
        echo "Categoria: {$row['category']}\n";
        echo "Preço: {$row['price']} MT\n";
        echo str_repeat("-", 60) . "\n";
    }
} else {
    echo "Erro: " . $mysqli->error . "\n";
}
?>
