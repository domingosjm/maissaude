<?php
require_once 'config.php';

// Verificar se a tabela invoices existe
$tables = $mysqli->query("SHOW TABLES LIKE 'invoices'");

if ($tables && $tables->num_rows > 0) {
    echo "<h2>Estrutura da tabela 'invoices':</h2>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Padrão</th></tr>";
    
    $columns = $mysqli->query("SHOW COLUMNS FROM invoices");
    while ($col = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . $col['Field'] . "</strong></td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h3>Query sugerida:</h3>";
    
    // Verificar quais colunas existem
    $cols = [];
    $columns = $mysqli->query("SHOW COLUMNS FROM invoices");
    while ($col = $columns->fetch_assoc()) {
        $cols[] = $col['Field'];
    }
    
    // Determinar coluna de valor
    $value_col = 'NULL';
    if (in_array('total_amount', $cols)) $value_col = 'total_amount';
    elseif (in_array('amount', $cols)) $value_col = 'amount';
    elseif (in_array('total', $cols)) $value_col = 'total';
    elseif (in_array('value', $cols)) $value_col = 'value';
    
    // Determinar coluna de status
    $status_col = 'status';
    $status_condition = "status = 'pendente'";
    if (in_array('payment_status', $cols)) {
        $status_condition = "(payment_status = 'pendente' OR status = 'pendente')";
    }
    
    echo "<pre>";
    echo "SELECT COUNT(*) as total, COALESCE(SUM($value_col), 0) as total_value\n";
    echo "FROM invoices\n";
    echo "WHERE $status_condition";
    echo "</pre>";
    
} else {
    echo "<h2>Tabela 'invoices' não existe!</h2>";
    echo "<p>A tabela precisa ser criada.</p>";
}

echo "<hr>";
echo "<p><a href='system_alerts.php'>← Voltar para Alertas</a></p>";
?>
