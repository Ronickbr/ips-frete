<?php
/**
 * Página de Relatórios
 * Sistema de Controle de Fretes
 */

// Iniciar sessão
session_start();

// Incluir configurações
require_once 'config/database.php';

// Verificar se o usuário está logado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Verificar se o usuário é administrador
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'administrador';
}

// Redirecionar para login se não estiver logado
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit();
}

// Função para buscar dados dos relatórios com filtros
function getRelatorioDataComFiltros($pdo, $filtros = []) {
    $data = [];
    
    // Construir condições WHERE baseadas nos filtros
    $whereConditions = [];
    $params = [];
    
    // Filtro por período de datas
    if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
        $whereConditions[] = "DATE(p.created_at) BETWEEN ? AND ?";
        $params[] = $filtros['data_inicio'];
        $params[] = $filtros['data_fim'];
    } elseif (!empty($filtros['data_inicio'])) {
        $whereConditions[] = "DATE(p.created_at) >= ?";
        $params[] = $filtros['data_inicio'];
    } elseif (!empty($filtros['data_fim'])) {
        $whereConditions[] = "DATE(p.created_at) <= ?";
        $params[] = $filtros['data_fim'];
    } elseif (!empty($filtros['periodo'])) {
        $whereConditions[] = "p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = $filtros['periodo'];
    }
    
    // Filtro por número de pedido
    if (!empty($filtros['numero_pedido'])) {
        $whereConditions[] = "numero_pedido LIKE ?";
        $params[] = '%' . $filtros['numero_pedido'] . '%';
    }
    
    // Filtro por número de picking
    if (!empty($filtros['numero_picking'])) {
        $whereConditions[] = "numero_picking LIKE ?";
        $params[] = '%' . $filtros['numero_picking'] . '%';
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    try {
        // Pedidos por período
        $pedidosQuery = "
            SELECT 
                DATE(p.created_at) as data,
                COUNT(*) as total_pedidos,
                COUNT(CASE WHEN c.status = 'aprovada' THEN 1 END) as pedidos_entregues
            FROM pedidos p
            LEFT JOIN cotacoes c ON p.id = c.pedido_id
            $whereClause
        ";
        
        // Adicionar filtros específicos para cotações se necessário
        $cotacaoFilters = [];
        $cotacaoParams = $params;
        
        if (!empty($filtros['numero_nf'])) {
            $cotacaoFilters[] = "c.numero_nota_fiscal LIKE ?";
            $cotacaoParams[] = '%' . $filtros['numero_nf'] . '%';
        }
        
        if (!empty($filtros['transportadora_id'])) {
            $cotacaoFilters[] = "c.transportadora_id = ?";
            $cotacaoParams[] = $filtros['transportadora_id'];
        }
        
        if (!empty($cotacaoFilters)) {
            $pedidosQuery .= (!empty($whereConditions) ? ' AND ' : ' WHERE ') . implode(' AND ', $cotacaoFilters);
        }
        
        $pedidosQuery .= " GROUP BY DATE(p.created_at) ORDER BY data DESC";
        
        $stmt = $pdo->prepare($pedidosQuery);
        $stmt->execute($cotacaoParams);
        $data['pedidos_periodo'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cotações por transportadora
        $transportadoraQuery = "
            SELECT 
                t.nome as transportadora,
                COUNT(c.id) as total_cotacoes,
                AVG(COALESCE(c.valor_frete_calculado, c.valor_frete)) as valor_medio,
                SUM(CASE WHEN c.status = 'aprovada' THEN 1 ELSE 0 END) as cotacoes_aprovadas
            FROM transportadoras t
            LEFT JOIN cotacoes c ON t.id = c.transportadora_id
            LEFT JOIN pedidos p ON c.pedido_id = p.id
            WHERE t.ativo = 1
        ";
        
        $transportadoraParams = [];
        $transportadoraConditions = [];
        
        // Aplicar filtros de data
        if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
            $transportadoraConditions[] = "DATE(c.data_cotacao) BETWEEN ? AND ?";
            $transportadoraParams[] = $filtros['data_inicio'];
            $transportadoraParams[] = $filtros['data_fim'];
        } elseif (!empty($filtros['data_inicio'])) {
            $transportadoraConditions[] = "DATE(c.data_cotacao) >= ?";
            $transportadoraParams[] = $filtros['data_inicio'];
        } elseif (!empty($filtros['data_fim'])) {
            $transportadoraConditions[] = "DATE(c.data_cotacao) <= ?";
            $transportadoraParams[] = $filtros['data_fim'];
        } elseif (!empty($filtros['periodo'])) {
            $transportadoraConditions[] = "c.data_cotacao >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            $transportadoraParams[] = $filtros['periodo'];
        }
        
        // Aplicar outros filtros
        if (!empty($filtros['numero_pedido'])) {
            $transportadoraConditions[] = "p.numero_pedido LIKE ?";
            $transportadoraParams[] = '%' . $filtros['numero_pedido'] . '%';
        }
        
        if (!empty($filtros['numero_picking'])) {
            $transportadoraConditions[] = "p.numero_picking LIKE ?";
            $transportadoraParams[] = '%' . $filtros['numero_picking'] . '%';
        }
        
        if (!empty($filtros['numero_nf'])) {
            $transportadoraConditions[] = "c.numero_nota_fiscal LIKE ?";
            $transportadoraParams[] = '%' . $filtros['numero_nf'] . '%';
        }
        
        if (!empty($filtros['transportadora_id'])) {
            $transportadoraConditions[] = "t.id = ?";
            $transportadoraParams[] = $filtros['transportadora_id'];
        }
        
        if (!empty($transportadoraConditions)) {
            $transportadoraQuery .= ' AND ' . implode(' AND ', $transportadoraConditions);
        }
        
        $transportadoraQuery .= " GROUP BY t.id, t.nome ORDER BY total_cotacoes DESC LIMIT 10";
        
        $stmt = $pdo->prepare($transportadoraQuery);
        $stmt->execute($transportadoraParams);
        $data['cotacoes_transportadora'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Resumo geral com filtros
        $resumoQuery = "
            SELECT 
                COUNT(DISTINCT p.id) as total_pedidos,
                COUNT(CASE WHEN c.status = 'aprovada' THEN 1 END) as pedidos_entregues,
                COUNT(DISTINCT c.id) as total_cotacoes,
                SUM(CASE WHEN c.status = 'aprovada' THEN 1 ELSE 0 END) as cotacoes_aprovadas,
                (SELECT COUNT(*) FROM transportadoras WHERE ativo = 1) as transportadoras_ativas,
                AVG(CASE WHEN c.status = 'aprovada' THEN COALESCE(c.valor_frete_calculado, c.valor_frete) END) as valor_medio_frete
            FROM pedidos p
            LEFT JOIN cotacoes c ON p.id = c.pedido_id
            $whereClause
        ";
        
        // Adicionar filtros de cotação ao resumo
        $resumoParams = $params;
        if (!empty($cotacaoFilters)) {
            $resumoQuery .= (!empty($whereConditions) ? ' AND ' : ' WHERE ') . implode(' AND ', $cotacaoFilters);
            $resumoParams = $cotacaoParams;
        }
        
        $stmt = $pdo->prepare($resumoQuery);
        $stmt->execute($resumoParams);
        $resumoResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Garantir que os valores não sejam nulos
        $data['resumo'] = [
            'total_pedidos' => $resumoResult['total_pedidos'] ?? 0,
            'pedidos_entregues' => $resumoResult['pedidos_entregues'] ?? 0,
            'total_cotacoes' => $resumoResult['total_cotacoes'] ?? 0,
            'cotacoes_aprovadas' => $resumoResult['cotacoes_aprovadas'] ?? 0,
            'transportadoras_ativas' => $resumoResult['transportadoras_ativas'] ?? 0,
            'valor_medio_frete' => $resumoResult['valor_medio_frete'] ?? 0
        ];
        
        // Faturamento por mês com filtros
        $faturamentoQuery = "
            SELECT 
                DATE_FORMAT(c.data_cotacao, '%Y-%m') as mes,
                SUM(COALESCE(c.valor_frete_calculado, c.valor_frete)) as faturamento,
                COUNT(c.id) as total_cotacoes
            FROM cotacoes c
            LEFT JOIN pedidos p ON c.pedido_id = p.id
            WHERE c.status = 'aprovada'
        ";
        
        $faturamentoParams = [];
        $faturamentoConditions = [];
        
        // Aplicar filtros de data (padrão: últimos 12 meses se não especificado)
        if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
            $faturamentoConditions[] = "DATE(c.data_cotacao) BETWEEN ? AND ?";
            $faturamentoParams[] = $filtros['data_inicio'];
            $faturamentoParams[] = $filtros['data_fim'];
        } elseif (!empty($filtros['data_inicio'])) {
            $faturamentoConditions[] = "DATE(c.data_cotacao) >= ?";
            $faturamentoParams[] = $filtros['data_inicio'];
        } elseif (!empty($filtros['data_fim'])) {
            $faturamentoConditions[] = "DATE(c.data_cotacao) <= ?";
            $faturamentoParams[] = $filtros['data_fim'];
        } else {
            $faturamentoConditions[] = "c.data_cotacao >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
        }
        
        // Aplicar outros filtros
        if (!empty($filtros['numero_pedido'])) {
            $faturamentoConditions[] = "p.numero_pedido LIKE ?";
            $faturamentoParams[] = '%' . $filtros['numero_pedido'] . '%';
        }
        
        if (!empty($filtros['numero_picking'])) {
            $faturamentoConditions[] = "p.numero_picking LIKE ?";
            $faturamentoParams[] = '%' . $filtros['numero_picking'] . '%';
        }
        
        if (!empty($filtros['numero_nf'])) {
            $faturamentoConditions[] = "c.numero_nota_fiscal LIKE ?";
            $faturamentoParams[] = '%' . $filtros['numero_nf'] . '%';
        }
        
        if (!empty($filtros['transportadora_id'])) {
            $faturamentoConditions[] = "c.transportadora_id = ?";
            $faturamentoParams[] = $filtros['transportadora_id'];
        }
        
        if (!empty($faturamentoConditions)) {
            $faturamentoQuery .= ' AND ' . implode(' AND ', $faturamentoConditions);
        }
        
        $faturamentoQuery .= " GROUP BY DATE_FORMAT(c.data_cotacao, '%Y-%m') ORDER BY mes DESC";
        
        $stmt = $pdo->prepare($faturamentoQuery);
        $stmt->execute($faturamentoParams);
        $data['faturamento_mensal'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        // Log do erro para debug
        error_log("Erro na função getRelatorioDataComFiltros: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        // Em caso de erro, retornar dados vazios
        $data = [
            'pedidos_periodo' => [],
            'cotacoes_transportadora' => [],
            'resumo' => [
                'total_pedidos' => 0,
                'pedidos_entregues' => 0,
                'total_cotacoes' => 0,
                'cotacoes_aprovadas' => 0,
                'transportadoras_ativas' => 0,
                'valor_medio_frete' => 0
            ],
            'faturamento_mensal' => [],
            'erro' => $e->getMessage() // Adicionar erro para debug
        ];
    }
    
    return $data;
}

// Processar filtros
$filtros = [
    'data_inicio' => $_GET['data_inicio'] ?? '',
    'data_fim' => $_GET['data_fim'] ?? '',
    'numero_pedido' => $_GET['numero_pedido'] ?? '',
    'numero_picking' => $_GET['numero_picking'] ?? '',
    'numero_nf' => $_GET['numero_nf'] ?? '',
    'transportadora_id' => $_GET['transportadora_id'] ?? '',
    'periodo' => $_GET['periodo'] ?? '30'
];

// Buscar dados para os relatórios
try {
    $pdo = getDBConnection();
    $relatorio_data = getRelatorioDataComFiltros($pdo, $filtros);
    
    // Buscar transportadoras para o filtro
    $stmt = $pdo->query("SELECT id, nome FROM transportadoras WHERE ativo = 1 ORDER BY nome");
    $transportadoras = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Log do erro para debug
    error_log("Erro ao buscar dados do relatório: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $relatorio_data = [
        'erro' => $e->getMessage()
    ];
    $transportadoras = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Sistema de Controle de Fretes</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- CSS Personalizado -->
    <link href="/assets/css/modern-theme.css" rel="stylesheet">
</head>
<body class="modern-body">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Conteúdo Principal -->
            <main class="col-md-9 col-lg-10 ms-sm-auto px-md-4">
                <div class="modern-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2"><i class="fas fa-chart-bar text-primary me-2"></i>Relatórios e Análises</h1>
                        <p class="text-muted mb-0">Análise completa de dados e métricas do sistema</p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#filtrosAvancados">
                                <i class="fas fa-filter"></i> Filtros
                            </button>
                            <button type="button" class="btn btn-outline-primary" onclick="exportarPDF()">
                                <i class="fas fa-file-pdf"></i> PDF
                            </button>
                            <button type="button" class="btn btn-outline-primary" onclick="exportarExcel()">
                                <i class="fas fa-file-excel"></i> Excel
                            </button>
                        </div>
                    </div>
                </div>
                
        <!-- Seção de Filtros Avançados -->
        <div class="collapse mb-4" id="filtrosAvancados">
            <div class="modern-card fade-in">
                <div class="card-header">
                    <h6 class="m-0"><i class="fas fa-filter"></i> Filtros Avançados</h6>
                </div>
                <div class="card-body">
                            <form method="GET" id="formFiltros">
                                <div class="row">
                                    <!-- Filtros de Data -->
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Data Início</label>
                                        <input type="date" class="form-control" name="data_inicio" value="<?php echo htmlspecialchars($filtros['data_inicio']); ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Data Fim</label>
                                        <input type="date" class="form-control" name="data_fim" value="<?php echo htmlspecialchars($filtros['data_fim']); ?>">
                                    </div>
                                    
                                    <!-- Período Rápido -->
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Período Rápido</label>
                                        <select class="form-select" name="periodo" onchange="limparDatasPersonalizadas()">
                                            <option value="">Selecione...</option>
                                            <option value="7" <?php echo $filtros['periodo'] == '7' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                                            <option value="30" <?php echo $filtros['periodo'] == '30' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                                            <option value="90" <?php echo $filtros['periodo'] == '90' ? 'selected' : ''; ?>>Últimos 90 dias</option>
                                            <option value="365" <?php echo $filtros['periodo'] == '365' ? 'selected' : ''; ?>>Último ano</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Transportadora -->
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Transportadora</label>
                                        <select class="form-select" name="transportadora_id">
                                            <option value="">Todas</option>
                                            <?php foreach ($transportadoras as $transportadora): ?>
                                                <option value="<?php echo $transportadora['id']; ?>" <?php echo $filtros['transportadora_id'] == $transportadora['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($transportadora['nome']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <!-- Número do Pedido -->
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Nº Pedido</label>
                                        <input type="text" class="form-control" name="numero_pedido" value="<?php echo htmlspecialchars($filtros['numero_pedido']); ?>" placeholder="Digite o número...">
                                    </div>
                                    
                                    <!-- Número do Picking -->
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Nº Picking</label>
                                        <input type="text" class="form-control" name="numero_picking" value="<?php echo htmlspecialchars($filtros['numero_picking']); ?>" placeholder="Digite o número...">
                                    </div>
                                    
                                    <!-- Número da Nota Fiscal -->
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Nº Nota Fiscal</label>
                                        <input type="text" class="form-control" name="numero_nf" value="<?php echo htmlspecialchars($filtros['numero_nf']); ?>" placeholder="Digite o número...">
                                    </div>
                                    
                                    <!-- Botões -->
                                    <div class="col-md-3 mb-3 d-flex align-items-end">
                                        <button type="submit" class="modern-btn me-2">
                                            <i class="fas fa-search"></i> Aplicar
                                        </button>
                                        <button type="button" class="modern-btn" onclick="limparFiltros()">
                                            <i class="fas fa-times"></i> Limpar
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Indicadores de Filtros Ativos -->
                                <?php if (array_filter($filtros)): ?>
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="alert alert-info d-flex align-items-center">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <span class="me-2">Filtros ativos:</span>
                                                <?php if (!empty($filtros['data_inicio']) || !empty($filtros['data_fim'])): ?>
                                                    <span class="badge bg-primary me-1">
                                                        Data: <?php echo $filtros['data_inicio'] ?: 'início'; ?> até <?php echo $filtros['data_fim'] ?: 'hoje'; ?>
                                                    </span>
                                                <?php elseif (!empty($filtros['periodo'])): ?>
                                                    <span class="badge bg-primary me-1">Período: <?php echo $filtros['periodo']; ?> dias</span>
                                                <?php endif; ?>
                                                <?php if (!empty($filtros['numero_pedido'])): ?>
                                                    <span class="badge bg-secondary me-1">Pedido: <?php echo htmlspecialchars($filtros['numero_pedido']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($filtros['numero_picking'])): ?>
                                                    <span class="badge bg-secondary me-1">Picking: <?php echo htmlspecialchars($filtros['numero_picking']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($filtros['numero_nf'])): ?>
                                                    <span class="badge bg-secondary me-1">NF: <?php echo htmlspecialchars($filtros['numero_nf']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($filtros['transportadora_id'])): ?>
                                                    <?php 
                                                    $transportadoraSelecionada = array_filter($transportadoras, function($t) use ($filtros) {
                                                        return $t['id'] == $filtros['transportadora_id'];
                                                    });
                                                    $nomeTransportadora = !empty($transportadoraSelecionada) ? reset($transportadoraSelecionada)['nome'] : 'ID: ' . $filtros['transportadora_id'];
                                                    ?>
                                                    <span class="badge bg-warning me-1">Transportadora: <?php echo htmlspecialchars($nomeTransportadora); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                
        <!-- Cards de Resumo -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card fade-in">
                    <div class="metric-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-label">Total de Pedidos</div>
                        <div class="metric-value"><?php echo $relatorio_data['resumo']['total_pedidos'] ?? 0; ?></div>
                        <div class="metric-change">Todos os pedidos registrados</div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card fade-in">
                    <div class="metric-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-label">Pedidos Entregues</div>
                        <div class="metric-value"><?php echo $relatorio_data['resumo']['pedidos_entregues'] ?? 0; ?></div>
                        <div class="metric-change">Entregas concluídas</div>
                    </div>
                </div>
            </div>
                    
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card fade-in">
                    <div class="metric-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-label">Cotações Aprovadas</div>
                        <div class="metric-value"><?php echo $relatorio_data['resumo']['cotacoes_aprovadas'] ?? 0; ?></div>
                        <div class="metric-change">Cotações confirmadas</div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="metric-card fade-in">
                    <div class="metric-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-label">Valor Médio Frete</div>
                        <div class="metric-value">R$ <?php echo number_format($relatorio_data['resumo']['valor_medio_frete'] ?? 0, 2, ',', '.'); ?></div>
                        <div class="metric-change">Média dos fretes aprovados</div>
                    </div>
                </div>
            </div>
        </div>
                
                <!-- Gráficos -->
                <div class="row mb-4">
                    <!-- Gráfico de Pedidos por Período -->
                    <div class="col-xl-8 col-lg-7">
                        <div class="modern-card fade-in mb-4">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-chart-line me-2"></i>Pedidos por Período</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="pedidosChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gráfico de Cotações por Transportadora -->
                    <div class="col-xl-4 col-lg-5">
                        <div class="modern-card fade-in mb-4">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-chart-pie me-2"></i>Top Transportadoras</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="transportadorasChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabelas de Dados -->
                <div class="row">
                    <!-- Faturamento Mensal -->
                    <div class="col-lg-6 mb-4">
                        <div class="modern-card fade-in">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-chart-line me-2"></i>Faturamento Mensal</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($relatorio_data['faturamento_mensal'])): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Nenhum dado de faturamento encontrado.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table modern-table">
                                            <thead>
                                                <tr>
                                                    <th>Mês</th>
                                                    <th>Cotações</th>
                                                    <th>Faturamento</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($relatorio_data['faturamento_mensal'] as $faturamento): ?>
                                                    <tr>
                                                        <td><?php echo date('m/Y', strtotime($faturamento['mes'] . '-01')); ?></td>
                                                        <td><span class="badge bg-info"><?php echo $faturamento['total_cotacoes']; ?></span></td>
                                                        <td><strong>R$ <?php echo number_format($faturamento['faturamento'], 2, ',', '.'); ?></strong></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Performance das Transportadoras -->
                    <div class="col-lg-6 mb-4">
                        <div class="modern-card fade-in">
                            <div class="card-header">
                                <h5 class="card-title"><i class="fas fa-shipping-fast me-2"></i>Performance das Transportadoras</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($relatorio_data['cotacoes_transportadora'])): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-shipping-fast fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Nenhum dado de transportadora encontrado.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table modern-table">
                                            <thead>
                                                <tr>
                                                    <th>Transportadora</th>
                                                    <th>Cotações</th>
                                                    <th>Aprovadas</th>
                                                    <th>Valor Médio</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($relatorio_data['cotacoes_transportadora'] as $transportadora): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($transportadora['transportadora']); ?></td>
                                                        <td><span class="badge bg-primary"><?php echo $transportadora['total_cotacoes']; ?></span></td>
                                                        <td><span class="badge bg-success"><?php echo $transportadora['cotacoes_aprovadas']; ?></span></td>
                                                        <td>
                                                            <?php if ($transportadora['valor_medio']): ?>
                                                                <strong>R$ <?php echo number_format($transportadora['valor_medio'], 2, ',', '.'); ?></strong>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript Personalizado -->
    <script src="/assets/js/main.js"></script>
    
    <!-- Scripts dos Gráficos -->
    <script>
        // Dados para os gráficos
        const pedidosData = <?php echo json_encode($relatorio_data['pedidos_periodo'] ?? []); ?>;
        const transportadorasData = <?php echo json_encode($relatorio_data['cotacoes_transportadora'] ?? []); ?>;
        
        // Gráfico de Pedidos por Período
        if (pedidosData.length > 0) {
            const ctx1 = document.getElementById('pedidosChart').getContext('2d');
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: pedidosData.map(item => {
                        const date = new Date(item.data);
                        return date.toLocaleDateString('pt-BR');
                    }),
                    datasets: [{
                        label: 'Total de Pedidos',
                        data: pedidosData.map(item => item.total_pedidos),
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    }, {
                        label: 'Pedidos Entregues',
                        data: pedidosData.map(item => item.pedidos_entregues),
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Gráfico de Transportadoras
        if (transportadorasData.length > 0) {
            const ctx2 = document.getElementById('transportadorasChart').getContext('2d');
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: transportadorasData.slice(0, 5).map(item => item.transportadora),
                    datasets: [{
                        data: transportadorasData.slice(0, 5).map(item => item.total_cotacoes),
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 205, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Funções para os filtros
        function limparDatasPersonalizadas() {
            const periodoSelect = document.querySelector('select[name="periodo"]');
            if (periodoSelect.value) {
                document.querySelector('input[name="data_inicio"]').value = '';
                document.querySelector('input[name="data_fim"]').value = '';
            }
        }
        
        function limparFiltros() {
            // Limpar todos os campos do formulário
            document.getElementById('formFiltros').reset();
            
            // Redirecionar para a página sem parâmetros
            window.location.href = window.location.pathname;
        }
        
        // Funções de exportação
        function exportarPDF() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'pdf');
            window.open('export_pdf.php?' + params.toString(), '_blank');
        }
        
        function exportarExcel() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            window.location.href = 'export_excel.php?' + params.toString();
        }
        
        // Validação de datas
        document.addEventListener('DOMContentLoaded', function() {
            const dataInicio = document.querySelector('input[name="data_inicio"]');
            const dataFim = document.querySelector('input[name="data_fim"]');
            const periodo = document.querySelector('select[name="periodo"]');
            
            // Limpar período quando datas personalizadas são selecionadas
            [dataInicio, dataFim].forEach(input => {
                input.addEventListener('change', function() {
                    if (this.value) {
                        periodo.value = '';
                    }
                });
            });
            
            // Validar que data fim não seja anterior à data início
            dataFim.addEventListener('change', function() {
                if (dataInicio.value && dataFim.value && dataFim.value < dataInicio.value) {
                    alert('A data fim não pode ser anterior à data início.');
                    dataFim.value = '';
                }
            });
        });
        
        // Animações modernas
        document.addEventListener('DOMContentLoaded', function() {
            // Fade-in animation
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            });
            
            document.querySelectorAll('.fade-in').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(el);
            });
            
            // Hover effects for cards
            document.querySelectorAll('.modern-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.15)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
                });
            });
            
            // Table row hover effects
            document.querySelectorAll('.modern-table tbody tr').forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                    this.style.backgroundColor = 'rgba(74, 144, 226, 0.1)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.backgroundColor = '';
                });
            });
        });
    </script>
</body>
</html>