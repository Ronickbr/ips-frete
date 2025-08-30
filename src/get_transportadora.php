<?php
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$id = (int)$_GET['id'];

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT t.*, 
               COUNT(c.id) as total_cotacoes,
               AVG(c.valor_frete_calculado) as valor_medio_frete
        FROM transportadoras t
        LEFT JOIN cotacoes c ON t.id = c.transportadora_id
        WHERE t.id = ?
        GROUP BY t.id
    ");
    
    $stmt->execute([$id]);
    $transportadora = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($transportadora) {
        echo json_encode([
            'success' => true,
            'transportadora' => $transportadora
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Transportadora não encontrada'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar transportadora: ' . $e->getMessage()
    ]);
}
?>