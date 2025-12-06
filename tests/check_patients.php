<?php
require 'config.php';

echo "TABELA: patients\n";
$result = $mysqli->query("DESCRIBE patients");
while($row = $result->fetch_assoc()) {
    echo "  {$row['Field']} - {$row['Type']}\n";
}
