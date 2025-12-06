<?php
// Configuração de conexão com o banco de dados MySQL
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'mais_saude';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    die('Erro ao conectar ao banco de dados: ' . $mysqli->connect_error);
}

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
