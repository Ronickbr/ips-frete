<?php
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID do pedido inválido']);
    exit;
}

$pedido_id = (int)$_GET['id'];

try {
    $pdo = getDBConnection();
    
    // Buscar dados do pedido
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
        exit;
    }
    
    // Buscar medidas do pedido
    $stmt = $pdo->prepare("SELECT * FROM medidas WHERE pedido_id = ? ORDER BY id");
    $stmt->execute([$pedido_id]);
    $medidas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'pedido' => $pedido,
        'medidas' => $medidas
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>