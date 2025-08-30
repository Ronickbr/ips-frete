<?php
session_start();
require_once 'config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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

// Configurar cabeçalhos para Excel (CSV)
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="relatorio_cotacoes_' . date('Y-m-d_H-i-s') . '.csv"');
header('Cache-Control: max-age=0');

// Adicionar BOM para UTF-8 (para que o Excel reconheça corretamente os caracteres especiais)
echo "\xEF\xBB\xBF";

// Função para escapar dados CSV
function escapeCsv($data) {
    if (strpos($data, ',') !== false || strpos($data, '"') !== false || strpos($data, "\n") !== false) {
        return '"' . str_replace('"', '""', $data) . '"';
    }
    return $data;
}

// Cabeçalho do relatório
echo "RELATÓRIO DE COTAÇÕES\n";
echo "Gerado em: " . date('d/m/Y H:i:s') . "\n";

// Filtros aplicados
if (array_filter($filtros)) {
    echo "\nFiltros aplicados:\n";
    if (!empty($filtros['data_inicio']) || !empty($filtros['data_fim'])) {
        echo "Data: " . ($filtros['data_inicio'] ?: 'início') . " até " . ($filtros['data_fim'] ?: 'hoje') . "\n";
    } elseif (!empty($filtros['periodo'])) {
        echo "Período: " . $filtros['periodo'] . " dias\n";
    }
    if (!empty($filtros['numero_pedido'])) {
        echo "Pedido: " . $filtros['numero_pedido'] . "\n";
    }
    if (!empty($filtros['numero_picking'])) {
        echo "Picking: " . $filtros['numero_picking'] . "\n";
    }
    if (!empty($filtros['numero_nf'])) {
        echo "NF: " . $filtros['numero_nf'] . "\n";
    }
}

// Resumo geral
echo "\n\nRESUMO GERAL\n";
echo "Total de Pedidos," . ($dados['resumo']['total_pedidos'] ?? 0) . "\n";
echo "Pedidos Entregues," . ($dados['resumo']['pedidos_entregues'] ?? 0) . "\n";
echo "Total de Cotações," . ($dados['resumo']['total_cotacoes'] ?? 0) . "\n";
echo "Cotações Aprovadas," . ($dados['resumo']['cotacoes_aprovadas'] ?? 0) . "\n";
echo "Valor Médio Frete,R$ " . number_format($dados['resumo']['valor_medio_frete'] ?? 0, 2, ',', '.') . "\n";

// Cabeçalhos das colunas
echo "\n\nDETALHES DAS COTAÇÕES\n";
echo "Nº Pedido,Nº Picking,Nº NF,Valor NF,Peso NF,Transportadora,Valor Frete,Prazo Entrega,Status,Data Criação\n";

// Dados das cotações
if (!empty($dados['cotacoes'])) {
    foreach ($dados['cotacoes'] as $cotacao) {
        $linha = [];
        
        // Nº Pedido
        $linha[] = escapeCsv($cotacao['numero_pedido'] ?? '');
        
        // Nº Picking
        $linha[] = escapeCsv($cotacao['numero_picking'] ?? '');
        
        // Nº NF
        $linha[] = escapeCsv($cotacao['numero_nf'] ?? '');
        
        // Valor NF
        $valor_nf = '';
        if ($cotacao['valor_nf']) {
            $valor_nf = 'R$ ' . number_format($cotacao['valor_nf'], 2, ',', '.');
        }
        $linha[] = escapeCsv($valor_nf);
        
        // Peso NF
        $peso_nf = '';
        if ($cotacao['peso_nf']) {
            $peso_nf = number_format($cotacao['peso_nf'], 2, ',', '.') . ' kg';
        }
        $linha[] = escapeCsv($peso_nf);
        
        // Transportadora
        $linha[] = escapeCsv($cotacao['transportadora'] ?? '');
        
        // Valor Frete
        $valor_frete = '';
        if ($cotacao['valor_frete']) {
            $valor_frete = 'R$ ' . number_format($cotacao['valor_frete'], 2, ',', '.');
        }
        $linha[] = escapeCsv($valor_frete);
        
        // Prazo Entrega
        $prazo = '';
        if ($cotacao['prazo_entrega']) {
            $prazo = $cotacao['prazo_entrega'] . ' dias';
        }
        $linha[] = escapeCsv($prazo);
        
        // Status
        $linha[] = escapeCsv(ucfirst($cotacao['status'] ?? 'pendente'));
        
        // Data Criação
        $data_criacao = '';
        if ($cotacao['data_criacao']) {
            $data_criacao = date('d/m/Y H:i', strtotime($cotacao['data_criacao']));
        }
        $linha[] = escapeCsv($data_criacao);
        
        echo implode(',', $linha) . "\n";
    }
} else {
    echo "Nenhuma cotação encontrada com os filtros aplicados.\n";
}

// Rodapé
echo "\n\nRelatório gerado pelo Sistema de Cotações em " . date('d/m/Y H:i:s') . "\n";
?>