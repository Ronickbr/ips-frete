<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['pedido_id']) || empty($_GET['pedido_id'])) {
    echo json_encode(['error' => 'ID do pedido não fornecido']);
    exit;
}

$pedido_id = (int)$_GET['pedido_id'];

try {
    // Buscar dados do pedido
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COUNT(m.id) as total_medidas,
               COALESCE(SUM(m.cubagem_m3), 0) as cubagem_total_m3
        FROM pedidos p 
        LEFT JOIN medidas m ON p.id = m.pedido_id 
        WHERE p.id = ? 
        GROUP BY p.id
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        echo json_encode(['error' => 'Pedido não encontrado']);
        exit;
    }
    
    // Buscar medidas do pedido
    $stmt = $pdo->prepare("
        SELECT * FROM medidas 
        WHERE pedido_id = ? 
        ORDER BY id
    ");
    $stmt->execute([$pedido_id]);
    $medidas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar resposta
    $response = [
        'success' => true,
        'pedido' => $pedido,
        'medidas' => $medidas,
        'resumo' => [
            'total_medidas' => $pedido['total_medidas'],
            'cubagem_total' => number_format($pedido['cubagem_total_m3'], 3, ',', '.'),
            'peso_total' => number_format($pedido['peso'], 2, ',', '.'),
            'rota' => $pedido['origem'] . ' → ' . $pedido['destino']
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao buscar dados do pedido: ' . $e->getMessage()]);
}
?>