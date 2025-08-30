<?php
/**
 * Arquivo de configuração principal
 * Sistema de Controle de Fretes
 */

require_once 'config/database.php';

// Inicializar conexão com banco de dados
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    error_log("Erro ao conectar com banco de dados: " . $e->getMessage());
    die("Erro de conexão com banco de dados");
}

// Configurações gerais
date_default_timezone_set('America/Sao_Paulo');

// Configurações de sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Função para verificar se usuário está logado
function verificarLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Função para formatar valores monetários
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função para formatar peso
function formatarPeso($peso) {
    return number_format($peso, 2, ',', '.') . ' kg';
}

// Função para formatar cubagem
function formatarCubagem($cubagem) {
    return number_format($cubagem, 3, ',', '.') . ' m³';
}
?>