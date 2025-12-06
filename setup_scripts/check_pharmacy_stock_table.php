<?php
require_once 'config.php';

echo "Estrutura da tabela pharmacy_stock:\n";
echo str_repeat("=", 60) . "\n";

$result = $mysqli->query('DESCRIBE pharmacy_stock');
if ($result) {
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Erro: " . $mysqli->error . "\n";
}
?>
