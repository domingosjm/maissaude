<?php
require_once 'config.php';

echo "Estrutura da tabela services:\n";
echo str_repeat("=", 60) . "\n";

$result = $mysqli->query('DESCRIBE services');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
