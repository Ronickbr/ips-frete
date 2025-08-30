<?php
session_start();
require_once '../../config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

// Definir cabeçalho JSON
header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Buscar métricas do dashboard
    
    // Pedidos hoje
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pedidos WHERE DATE(data_pedido) = CURDATE()");
    $stmt->execute();
    $pedidos_hoje = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Cotações realizadas (total)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cotacoes");
    $stmt->execute();
    $cotacoes_realizadas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Faturas pendentes (cotações pendentes)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cotacoes WHERE status = 'pendente'");
    $stmt->execute();
    $faturas_pendentes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Transportadoras ativas
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM transportadoras WHERE ativo = 1");
    $stmt->execute();
    $transportadoras_ativas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Retornar dados em JSON
    echo json_encode([
        'success' => true,
        'pedidos_hoje' => (int)$pedidos_hoje,
        'cotacoes_realizadas' => (int)$cotacoes_realizadas,
        'faturas_pendentes' => (int)$faturas_pendentes,
        'transportadoras_ativas' => (int)$transportadoras_ativas
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>