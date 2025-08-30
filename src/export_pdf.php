<?php
session_start();
require_once 'config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Incluir a biblioteca TCPDF (você pode usar FPDF ou outra biblioteca)
// Para este exemplo, vou usar uma implementação básica com HTML para PDF

// Função para buscar dados com filtros (reutilizada do relatorios.php)
function getRelatorioDataComFiltros($pdo, $filtros) {
    $where_conditions = [];
    $params = [];
    
    // Filtro por período ou datas específicas
    if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
        $where_conditions[] = "DATE(p.data_criacao) BETWEEN ? AND ?";
        $params[] = $filtros['data_inicio'];
        $params[] = $filtros['data_fim'];
    } elseif (!empty($filtros['data_inicio'])) {
        $where_conditions[] = "DATE(p.data_criacao) >= ?";
        $params[] = $filtros['data_inicio'];
    } elseif (!empty($filtros['data_fim'])) {
        $where_conditions[] = "DATE(p.data_criacao) <= ?";
        $params[] = $filtros['data_fim'];
    } elseif (!empty($filtros['periodo'])) {
        $where_conditions[] = "p.data_criacao >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = $filtros['periodo'];
    }
    
    // Filtros específicos
    if (!empty($filtros['numero_pedido'])) {
        $where_conditions[] = "p.numero LIKE ?";
        $params[] = '%' . $filtros['numero_pedido'] . '%';
    }
    
    if (!empty($filtros['numero_picking'])) {
        $where_conditions[] = "p.numero_picking LIKE ?";
        $params[] = '%' . $filtros['numero_picking'] . '%';
    }
    
    if (!empty($filtros['numero_nf'])) {
        $where_conditions[] = "c.numero_nf LIKE ?";
        $params[] = '%' . $filtros['numero_nf'] . '%';
    }
    
    if (!empty($filtros['transportadora_id'])) {
        $where_conditions[] = "c.transportadora_id = ?";
        $params[] = $filtros['transportadora_id'];
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Buscar dados principais
    $sql_resumo = "
        SELECT 
            COUNT(DISTINCT p.id) as total_pedidos,
            COUNT(DISTINCT CASE WHEN p.status = 'entregue' THEN p.id END) as pedidos_entregues,
            COUNT(DISTINCT c.id) as total_cotacoes,
            COUNT(DISTINCT CASE WHEN c.status = 'aprovada' THEN c.id END) as cotacoes_aprovadas,
            AVG(CASE WHEN c.valor_frete > 0 THEN c.valor_frete END) as valor_medio_frete
        FROM pedidos p
        LEFT JOIN cotacoes c ON p.id = c.pedido_id
        $where_clause
    ";
    
    $stmt = $pdo->prepare($sql_resumo);
    $stmt->execute($params);
    $resumo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar cotações detalhadas
    $sql_cotacoes = "
        SELECT 
            p.numero as numero_pedido,
            p.numero_picking,
            c.numero_nf,
            c.valor_nf,
            c.peso_nf,
            t.nome as transportadora,
            c.valor_frete,
            c.prazo_entrega,
            c.status,
            c.data_criacao
        FROM pedidos p
        LEFT JOIN cotacoes c ON p.id = c.pedido_id
        LEFT JOIN transportadoras t ON c.transportadora_id = t.id
        $where_clause
        ORDER BY c.data_criacao DESC
        LIMIT 100
    ";
    
    $stmt = $pdo->prepare($sql_cotacoes);
    $stmt->execute($params);
    $cotacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'resumo' => $resumo,
        'cotacoes' => $cotacoes
    ];
}

// Processar filtros
$filtros = [
    'data_inicio' => $_GET['data_inicio'] ?? '',
    'data_fim' => $_GET['data_fim'] ?? '',
    'periodo' => $_GET['periodo'] ?? '',
    'numero_pedido' => $_GET['numero_pedido'] ?? '',
    'numero_picking' => $_GET['numero_picking'] ?? '',
    'numero_nf' => $_GET['numero_nf'] ?? '',
    'transportadora_id' => $_GET['transportadora_id'] ?? ''
];

// Buscar dados
$dados = getRelatorioDataComFiltros($pdo, $filtros);

// Configurar cabeçalhos para PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="relatorio_cotacoes_' . date('Y-m-d_H-i-s') . '.pdf"');

// Para uma implementação completa, você precisaria de uma biblioteca como TCPDF
// Por enquanto, vou criar um HTML que pode ser convertido para PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Relatório de Cotações</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 30px; }
        .summary { margin-bottom: 30px; }
        .summary-item { display: inline-block; margin: 10px; padding: 10px; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relatório de Cotações</h1>
        <p>Gerado em: <?php echo date('d/m/Y H:i:s'); ?></p>
        <?php if (array_filter($filtros)): ?>
            <p><strong>Filtros aplicados:</strong>
            <?php if (!empty($filtros['data_inicio']) || !empty($filtros['data_fim'])): ?>
                Data: <?php echo $filtros['data_inicio'] ?: 'início'; ?> até <?php echo $filtros['data_fim'] ?: 'hoje'; ?> |
            <?php elseif (!empty($filtros['periodo'])): ?>
                Período: <?php echo $filtros['periodo']; ?> dias |
            <?php endif; ?>
            <?php if (!empty($filtros['numero_pedido'])): ?>
                Pedido: <?php echo htmlspecialchars($filtros['numero_pedido']); ?> |
            <?php endif; ?>
            <?php if (!empty($filtros['numero_picking'])): ?>
                Picking: <?php echo htmlspecialchars($filtros['numero_picking']); ?> |
            <?php endif; ?>
            <?php if (!empty($filtros['numero_nf'])): ?>
                NF: <?php echo htmlspecialchars($filtros['numero_nf']); ?> |
            <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    
    <div class="summary">
        <h2>Resumo Geral</h2>
        <div class="summary-item">
            <strong>Total de Pedidos:</strong><br>
            <?php echo $dados['resumo']['total_pedidos'] ?? 0; ?>
        </div>
        <div class="summary-item">
            <strong>Pedidos Entregues:</strong><br>
            <?php echo $dados['resumo']['pedidos_entregues'] ?? 0; ?>
        </div>
        <div class="summary-item">
            <strong>Total de Cotações:</strong><br>
            <?php echo $dados['resumo']['total_cotacoes'] ?? 0; ?>
        </div>
        <div class="summary-item">
            <strong>Cotações Aprovadas:</strong><br>
            <?php echo $dados['resumo']['cotacoes_aprovadas'] ?? 0; ?>
        </div>
        <div class="summary-item">
            <strong>Valor Médio Frete:</strong><br>
            R$ <?php echo number_format($dados['resumo']['valor_medio_frete'] ?? 0, 2, ',', '.'); ?>
        </div>
    </div>
    
    <h2>Detalhes das Cotações</h2>
    <?php if (empty($dados['cotacoes'])): ?>
        <p>Nenhuma cotação encontrada com os filtros aplicados.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Nº Pedido</th>
                    <th>Nº Picking</th>
                    <th>Nº NF</th>
                    <th>Valor NF</th>
                    <th>Peso NF</th>
                    <th>Transportadora</th>
                    <th>Valor Frete</th>
                    <th>Prazo</th>
                    <th>Status</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dados['cotacoes'] as $cotacao): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cotacao['numero_pedido']); ?></td>
                        <td><?php echo htmlspecialchars($cotacao['numero_picking'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($cotacao['numero_nf'] ?: '-'); ?></td>
                        <td class="text-right">
                            <?php echo $cotacao['valor_nf'] ? 'R$ ' . number_format($cotacao['valor_nf'], 2, ',', '.') : '-'; ?>
                        </td>
                        <td class="text-right">
                            <?php echo $cotacao['peso_nf'] ? number_format($cotacao['peso_nf'], 2, ',', '.') . ' kg' : '-'; ?>
                        </td>
                        <td><?php echo htmlspecialchars($cotacao['transportadora'] ?: '-'); ?></td>
                        <td class="text-right">
                            <?php echo $cotacao['valor_frete'] ? 'R$ ' . number_format($cotacao['valor_frete'], 2, ',', '.') : '-'; ?>
                        </td>
                        <td class="text-center"><?php echo $cotacao['prazo_entrega'] ? $cotacao['prazo_entrega'] . ' dias' : '-'; ?></td>
                        <td class="text-center">
                            <?php 
                            $status_class = '';
                            switch($cotacao['status']) {
                                case 'aprovada': $status_class = 'color: green;'; break;
                                case 'rejeitada': $status_class = 'color: red;'; break;
                                default: $status_class = 'color: orange;';
                            }
                            ?>
                            <span style="<?php echo $status_class; ?>"><?php echo ucfirst($cotacao['status'] ?: 'pendente'); ?></span>
                        </td>
                        <td class="text-center"><?php echo date('d/m/Y', strtotime($cotacao['data_criacao'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <div style="margin-top: 50px; text-align: center; font-size: 10px; color: #666;">
        <p>Relatório gerado pelo Sistema de Cotações - <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Para uma implementação real, você usaria uma biblioteca como TCPDF ou DomPDF
// Por enquanto, vou retornar o HTML que pode ser salvo como PDF pelo navegador
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: inline; filename="relatorio_cotacoes_' . date('Y-m-d_H