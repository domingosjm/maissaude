<?php
require_once 'config.php';

echo "<h2>Corrigindo estrutura da tabela medications...</h2>";

// Verificar e adicionar coluna category
$check_category = $mysqli->query("SHOW COLUMNS FROM medications LIKE 'category'");
if ($check_category && $check_category->num_rows === 0) {
    echo "<p>Adicionando coluna 'category'...</p>";
    $result = $mysqli->query("ALTER TABLE medications ADD COLUMN category VARCHAR(100) NULL AFTER name");
    if ($result) {
        echo "<p style='color: green;'>✓ Coluna 'category' adicionada com sucesso!</p>";
    } else {
        echo "<p style='color: red;'>✗ Erro ao adicionar coluna 'category': " . $mysqli->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>✓ Coluna 'category' já existe!</p>";
}

// Verificar e adicionar coluna price
$check_price = $mysqli->query("SHOW COLUMNS FROM medications LIKE 'price'");
if ($check_price && $check_price->num_rows === 0) {
    echo "<p>Adicionando coluna 'price'...</p>";
    $result = $mysqli->query("ALTER TABLE medications ADD COLUMN price DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER description");
    if ($result) {
        echo "<p style='color: green;'>✓ Coluna 'price' adicionada com sucesso!</p>";
    } else {
        echo "<p style='color: red;'>✗ Erro ao adicionar coluna 'price': " . $mysqli->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>✓ Coluna 'price' já existe!</p>";
}

// Verificar e adicionar coluna stock_quantity
$check_stock = $mysqli->query("SHOW COLUMNS FROM medications LIKE 'stock_quantity'");
if ($check_stock && $check_stock->num_rows === 0) {
    echo "<p>Adicionando coluna 'stock_quantity'...</p>";
    $result = $mysqli->query("ALTER TABLE medications ADD COLUMN stock_quantity INT NOT NULL DEFAULT 0 AFTER price");
    if ($result) {
        echo "<p style='color: green;'>✓ Coluna 'stock_quantity' adicionada com sucesso!</p>";
    } else {
        echo "<p style='color: red;'>✗ Erro ao adicionar coluna 'stock_quantity': " . $mysqli->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>✓ Coluna 'stock_quantity' já existe!</p>";
}

// Verificar e adicionar coluna minimum_stock
$check_min = $mysqli->query("SHOW COLUMNS FROM medications LIKE 'minimum_stock'");
if ($check_min && $check_min->num_rows === 0) {
    echo "<p>Adicionando coluna 'minimum_stock'...</p>";
    $result = $mysqli->query("ALTER TABLE medications ADD COLUMN minimum_stock INT NOT NULL DEFAULT 10 AFTER stock_quantity");
    if ($result) {
        echo "<p style='color: green;'>✓ Coluna 'minimum_stock' adicionada com sucesso!</p>";
    } else {
        echo "<p style='color: red;'>✗ Erro ao adicionar coluna 'minimum_stock': " . $mysqli->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>✓ Coluna 'minimum_stock' já existe!</p>";
}

echo "<h3 style='color: green; margin-top: 20px;'>Estrutura da tabela corrigida!</h3>";
echo "<p><a href='financeiro.php'>← Voltar para Financeiro</a></p>";
