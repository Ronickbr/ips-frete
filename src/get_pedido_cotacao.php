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
        SELECT p.id, p.numero_pedido, p.numero_picking, p.usuario_id, p.created_at, p.updated_at,
               COUNT(m.id) as total_medidas,
               COALESCE(SUM(m.cubagem_m3), 0) as cubagem_total_m3,
               COALESCE(SUM(m.quantidade_volumes * (m.comprimento * m.altura * m.largura) / 1000000 * 300), 0) as peso
        FROM pedidos p 
        LEFT JOIN medidas m ON p.id = m.pedido_id 
        WHERE p.id = ? 
        GROUP BY p.id, p.numero_pedido, p.numero_picking, p.usuario_id, p.created_at, p.updated_at
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
    
    // Adicionar campos padrão para compatibilidade
    $pedido['cliente'] = 'Cliente não informado';
    $pedido['origem'] = 'Origem não informada';
    $pedido['destino'] = 'Destino não informado';
    
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