<?php
/**
 * Página de Gerenciamento de Cotações
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

// Função para converter valor formatado brasileiro para decimal
function converterValorParaDecimal($valor) {
    if (empty($valor)) {
        return 0;
    }
    // Remove espaços e substitui vírgula por ponto
    $valor = str_replace([' ', '.'], '', trim($valor));
    $valor = str_replace(',', '.', $valor);
    return floatval($valor);
}

// Processar formulário de nova cotação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nova_cotacao') {
    try {
        $pdo = getDBConnection();
        
        // Converter valores formatados para decimal
        $valor_nota_fiscal = converterValorParaDecimal($_POST['valor_nota_fiscal']);
        $peso_nota_fiscal = converterValorParaDecimal($_POST['peso_nota_fiscal']);
        $valor_frete = converterValorParaDecimal($_POST['valor_frete']);
        $valor_frete_calculado = converterValorParaDecimal($_POST['valor_frete_calculado']);
        $cubagem_total = converterValorParaDecimal($_POST['cubagem_total']);
        
        // Verificar se já existe cotação para o mesmo pedido ou número de nota fiscal
        $stmt_check = $pdo->prepare("
            SELECT id FROM cotacoes 
            WHERE pedido_id = ? OR numero_nota_fiscal = ?
        ");
        $stmt_check->execute([$_POST['pedido_id'], $_POST['numero_nota_fiscal']]);
        $cotacao_existente = $stmt_check->fetch();
        
        if ($cotacao_existente) {
            // Sobrescrever cotação existente
            $stmt = $pdo->prepare("
                UPDATE cotacoes SET 
                    transportadora_id = ?, 
                    numero_nota_fiscal = ?,
                    valor_nota_fiscal = ?,
                    peso_nota_fiscal = ?,
                    valor_frete = ?, 
                    valor_frete_calculado = ?,
                    cubagem_total = ?,
                    prazo_entrega = ?, 
                    observacoes = ?, 
                    status = 'pendente',
                    data_cotacao = NOW()
                WHERE pedido_id = ? OR numero_nota_fiscal = ?
            ");
            
            $stmt->execute([
                $_POST['transportadora_id'],
                $_POST['numero_nota_fiscal'],
                $valor_nota_fiscal,
                $peso_nota_fiscal,
                $valor_frete,
                $valor_frete_calculado,
                $cubagem_total,
                $_POST['prazo_entrega'],
                $_POST['observacoes'],
                $_POST['pedido_id'],
                $_POST['numero_nota_fiscal']
            ]);
            
            $success_message = "Cotação atualizada com sucesso!";
        } else {
            // Inserir nova cotação
            $stmt = $pdo->prepare("
                INSERT INTO cotacoes (
                    pedido_id, transportadora_id, numero_nota_fiscal, valor_nota_fiscal, 
                    peso_nota_fiscal, valor_frete, valor_frete_calculado, cubagem_total,
                    prazo_entrega, observacoes, status, data_cotacao
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', NOW())
            ");
            
            $stmt->execute([
                $_POST['pedido_id'],
                $_POST['transportadora_id'],
                $_POST['numero_nota_fiscal'],
                $valor_nota_fiscal,
                $peso_nota_fiscal,
                $valor_frete,
                $valor_frete_calculado,
                $cubagem_total,
                $_POST['prazo_entrega'],
                $_POST['observacoes']
            ]);
            
            $success_message = "Cotação realizada com sucesso!";
        }
    } catch (Exception $e) {
        $error_message = "Erro ao processar cotação: " . $e->getMessage();
    }
}

// Processar atualização de status da cotação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'atualizar_status') {
    header('Content-Type: application/json');
    
    try {
        $pdo = getDBConnection();
        $cotacao_id = $_POST['cotacao_id'];
        $status = $_POST['status'];
        
        // Validar status
        if (!in_array($status, ['pendente', 'aprovada', 'rejeitada'])) {
            throw new Exception('Status inválido');
        }
        
        // Atualizar status da cotação
        $stmt = $pdo->prepare("UPDATE cotacoes SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $cotacao_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Cotação não encontrada']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Buscar cotações existentes
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT c.*, p.numero_pedido, p.numero_picking, t.nome as transportadora_nome
        FROM cotacoes c 
        LEFT JOIN pedidos p ON c.pedido_id = p.id 
        LEFT JOIN transportadoras t ON c.transportadora_id = t.id 
        ORDER BY c.data_cotacao DESC LIMIT 20
    ");
    $cotacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $cotacoes = [];
    error_log("Erro ao buscar cotações: " . $e->getMessage());
}

// Buscar pedidos para o formulário
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT p.id, p.numero_pedido, p.numero_picking,
               COALESCE(SUM(m.cubagem_m3), 0) as cubagem_total,
               COALESCE(SUM(m.quantidade_volumes * (m.comprimento * m.altura * m.largura) / 1000000 * 300), 0) as peso
        FROM pedidos p 
        LEFT JOIN medidas m ON p.id = m.pedido_id 
        GROUP BY p.id, p.numero_pedido, p.numero_picking
        ORDER BY p.numero_pedido
    ");
    $pedidos_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: verificar dados dos pedidos
    error_log("Pedidos encontrados: " . count($pedidos_result));
    error_log("Dados dos pedidos: " . json_encode($pedidos_result));
    
    // Adicionar campos padrão para compatibilidade
    $pedidos = [];
    foreach ($pedidos_result as $pedido) {
        $pedido['cliente'] = 'Cliente não informado';
        $pedido['origem'] = 'Origem não informada';
        $pedido['destino'] = 'Destino não informado';
        $pedidos[] = $pedido;
    }
} catch (Exception $e) {
    $pedidos = [];
    error_log("Erro ao buscar pedidos: " . $e->getMessage());
}

// Buscar transportadoras para o formulário
try {
    // Buscar transportadoras com os nomes corretos das colunas
    $query = "SELECT id, nome, 
                     COALESCE(frete_por_tonelada, 0) as frete_tonelada,
                     COALESCE(frete_minimo, 0) as frete_minimo,
                     COALESCE(frete_valor_percentual, 0) as frete_valor,
                     COALESCE(pedagio, 0) as pedagio_peso_cubico
              FROM transportadoras 
              WHERE ativo = 1 
              ORDER BY nome";
    
    $stmt = $pdo->query($query);
    $transportadoras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: verificar dados das transportadoras
    error_log("Transportadoras carregadas: " . json_encode($transportadoras));
} catch (Exception $e) {
    $transportadoras = [];
    error_log("Erro ao buscar transportadoras: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotações - Sistema de Controle de Fretes</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- CSS Personalizado -->
    <link href="/assets/css/modern-theme.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Conteúdo Principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 modern-main">
                <div class="modern-header fade-in">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="header-title">Gerenciamento de Cotações</h1>
                            <p class="header-description">Gerencie e controle todas as cotações de frete</p>
                        </div>
                        <div>
                            <button type="button" class="modern-btn" data-bs-toggle="modal" data-bs-target="#novaCotacaoModal">
                                <i class="fas fa-plus"></i> Nova Cotação
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Mensagens -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Cards de Resumo -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="metric-card fade-in">
                            <div class="metric-icon">
                                <i class="fas fa-calculator"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Cotações Hoje</div>
                                <div class="metric-value"><?php echo count(array_filter($cotacoes, function($c) { return date('Y-m-d', strtotime($c['data_cotacao'])) === date('Y-m-d'); })); ?></div>
                                <div class="metric-change">+12% vs ontem</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="metric-card fade-in">
                            <div class="metric-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Cotações Aprovadas</div>
                                <div class="metric-value"><?php echo count(array_filter($cotacoes, function($c) { return $c['status'] === 'aprovada'; })); ?></div>
                                <div class="metric-change">+8% vs semana passada</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="metric-card fade-in">
                            <div class="metric-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Cotações Pendentes</div>
                                <div class="metric-value"><?php echo count(array_filter($cotacoes, function($c) { return $c['status'] === 'pendente'; })); ?></div>
                                <div class="metric-change">-5% vs semana passada</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="metric-card fade-in">
                            <div class="metric-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Valor Médio</div>
                                <div class="metric-value">
                                    R$ <?php 
                                    $valores = array_column($cotacoes, 'valor_frete');
                                    echo $valores ? number_format(array_sum($valores) / count($valores), 2, ',', '.') : '0,00';
                                    ?>
                                </div>
                                <div class="metric-change">+15% vs mês passado</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Lista de Cotações -->
                <div class="modern-card fade-in">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Cotações Recentes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cotacoes)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calculator fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Nenhuma cotação encontrada. Clique em "Nova Cotação" para começar.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table modern-table">
                                    <thead>
                                        <tr>
                                            <th>Pedido</th>
                                            <th>Picking</th>
                                            <th>Nº NF</th>
                                            <th>Valor NF</th>
                                            <th>Peso NF</th>
                                            <th>Transportadora</th>
                                            <th>Frete Calc.</th>
                                            <th>Valor Frete</th>
                                            <th>Prazo</th>
                                            <th>Status</th>
                                            <th>Data</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cotacoes as $cotacao): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($cotacao['numero_pedido'] ?? 'N/A'); ?></strong></td>
                                                <td><?php echo htmlspecialchars($cotacao['numero_picking'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($cotacao['numero_nota_fiscal'] ?? '-'); ?></td>
                                                <td>
                                                    <?php if ($cotacao['valor_nota_fiscal']): ?>
                                                        R$ <?php echo number_format($cotacao['valor_nota_fiscal'], 2, ',', '.'); ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($cotacao['peso_nota_fiscal']): ?>
                                                        <?php echo number_format($cotacao['peso_nota_fiscal'], 2, ',', '.'); ?> kg
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($cotacao['transportadora_nome'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php if ($cotacao['valor_frete_calculado']): ?>
                                                        <span class="text-muted">R$ <?php echo number_format($cotacao['valor_frete_calculado'], 2, ',', '.'); ?></span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong>R$ <?php echo number_format($cotacao['valor_frete'], 2, ',', '.'); ?></strong></td>
                                                <td><?php echo $cotacao['prazo_entrega']; ?> dias</td>
                                                <td>
                                                    <?php 
                                                    $status_class = '';
                                                    switch($cotacao['status']) {
                                                        case 'pendente': $status_class = 'bg-warning'; break;
                                                        case 'aprovada': $status_class = 'bg-success'; break;
                                                        case 'rejeitada': $status_class = 'bg-danger'; break;
                                                        case 'enviada': $status_class = 'bg-info'; break;
                                                        default: $status_class = 'bg-secondary';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($cotacao['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($cotacao['data_cotacao'])); ?></td>
                                                <td>
                                                    <?php if ($cotacao['status'] === 'pendente'): ?>
                                                        <button class="modern-btn-sm success" title="Aprovar" 
                                                                onclick="return aprovarCotacao(<?php echo $cotacao['id']; ?>)" 
                                                                data-id="<?php echo $cotacao['id']; ?>">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="modern-btn-sm danger" title="Rejeitar" 
                                                                onclick="return rejeitarCotacao(<?php echo $cotacao['id']; ?>)" 
                                                                data-id="<?php echo $cotacao['id']; ?>">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php elseif ($cotacao['status'] === 'aprovada'): ?>
                                                        <button class="modern-btn-sm success" disabled title="Já aprovada">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="modern-btn-sm secondary" title="Rejeitar" 
                                                                onclick="return rejeitarCotacao(<?php echo $cotacao['id']; ?>)" 
                                                                data-id="<?php echo $cotacao['id']; ?>">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php elseif ($cotacao['status'] === 'rejeitada'): ?>
                                                        <button class="modern-btn-sm secondary" title="Aprovar" 
                                                                onclick="return aprovarCotacao(<?php echo $cotacao['id']; ?>)" 
                                                                data-id="<?php echo $cotacao['id']; ?>">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="modern-btn-sm danger" disabled title="Já rejeitada">
                                                            <i class="fas fa-times"></i>
                                                        </button>
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
            </main>
        </div>
    </div>
    
    <!-- Modal Nova Cotação -->
    <div class="modal fade" id="novaCotacaoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Nova Cotação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="nova_cotacao">
                        
                        <!-- Filtro por Pedido/Picking -->
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="filtro_pedido" class="form-label">Buscar por Nº Pedido ou Picking</label>
                                <input type="text" class="form-control" id="filtro_pedido" placeholder="Digite o número do pedido ou picking..." onkeyup="filtrarPedidos()">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="pedido_id" class="form-label">Pedido *</label>
                                <select class="form-control" id="pedido_id" name="pedido_id" required onchange="carregarDadosPedido()">
                                    <option value="">Selecione um pedido...</option>
                                    <?php foreach ($pedidos as $pedido): ?>
                                        <option value="<?php echo $pedido['id']; ?>" 
                                                data-numero="<?php echo htmlspecialchars($pedido['numero_pedido'] ?? ''); ?>"
                                                data-picking="<?php echo htmlspecialchars($pedido['numero_picking'] ?? ''); ?>"
                                                data-cliente="<?php echo htmlspecialchars($pedido['cliente'] ?? ''); ?>"
                                                data-origem="<?php echo htmlspecialchars($pedido['origem'] ?? ''); ?>"
                                                data-destino="<?php echo htmlspecialchars($pedido['destino'] ?? ''); ?>"
                                                data-peso="<?php echo $pedido['peso'] ?? 0; ?>"
                                                data-cubagem="<?php echo $pedido['cubagem_total'] ?? 0; ?>">
                                            <?php echo htmlspecialchars(($pedido['numero_pedido'] ?? '') . ' - ' . ($pedido['cliente'] ?? '')); ?>
                                            <?php if (!empty($pedido['numero_picking'])): ?>
                                                (Picking: <?php echo htmlspecialchars($pedido['numero_picking']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="transportadora_id" class="form-label">Transportadora *</label>
                                <select class="form-control" id="transportadora_id" name="transportadora_id" required onchange="calcularFrete()">
                                    <option value="">Selecione uma transportadora...</option>
                                    <?php foreach ($transportadoras as $transportadora): ?>
                                        <option value="<?php echo $transportadora['id']; ?>"
                                                data-frete-tonelada="<?php echo $transportadora['frete_tonelada'] ?? 0; ?>"
                                                data-frete-minimo="<?php echo $transportadora['frete_minimo'] ?? 0; ?>"
                                                data-frete-valor="<?php echo $transportadora['frete_valor'] ?? 0; ?>"
                                                data-pedagio-peso-cubico="<?php echo $transportadora['pedagio_peso_cubico'] ?? 0; ?>">
                                            <?php echo htmlspecialchars($transportadora['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Informações do Pedido Selecionado -->
                        <div id="info_pedido" class="alert alert-info" style="display: none;">
                            <h6><i class="fas fa-info-circle"></i> Informações do Pedido</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Cliente:</strong> <span id="info_cliente">-</span><br>
                                    <strong>Rota:</strong> <span id="info_rota">-</span>
                                </div>
                                <div class="col-md-4">
                                    <strong>Peso:</strong> <span id="info_peso">-</span> kg<br>
                                    <strong>Medidas:</strong> <span id="info_medidas">-</span>
                                </div>
                                <div class="col-md-4">
                                    <strong>Cubagem Total:</strong> <span id="info_cubagem">-</span> m³
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dados da Nota Fiscal -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="numero_nota_fiscal" class="form-label">Nº Nota Fiscal *</label>
                                <input type="text" class="form-control" id="numero_nota_fiscal" name="numero_nota_fiscal" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="valor_nota_fiscal" class="form-label">Valor Nota Fiscal (R$) *</label>
                                <input type="text" class="form-control" id="valor_nota_fiscal" name="valor_nota_fiscal" required onchange="calcularFrete()" placeholder="0,00">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="peso_nota_fiscal" class="form-label">Peso Nota Fiscal (kg) *</label>
                                <input type="text" class="form-control" id="peso_nota_fiscal" name="peso_nota_fiscal" required onchange="calcularFrete()" placeholder="0,00">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="valor_frete" class="form-label">Valor do Frete (R$) *</label>
                                <input type="text" class="form-control" id="valor_frete" name="valor_frete" required readonly placeholder="0,00">
                                <small class="text-muted">Calculado automaticamente</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="valor_frete_calculado" class="form-label">Frete Calculado (R$)</label>
                                <input type="text" class="form-control" id="valor_frete_calculado" name="valor_frete_calculado" readonly placeholder="0,00">
                                <small class="text-muted">Baseado na tabela da transportadora</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="prazo_entrega" class="form-label">Prazo de Entrega (dias) *</label>
                                <input type="number" class="form-control" id="prazo_entrega" name="prazo_entrega" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="observacoes" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                        </div>
                        
                        <!-- Campos ocultos para dados calculados -->
                        <input type="hidden" id="cubagem_total" name="cubagem_total">
                        <input type="hidden" id="pedido_dados" name="pedido_dados">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modern-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="modern-btn">Salvar Cotação</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript Personalizado -->
    <script src="/assets/js/main.js"></script>
    
    <script>
    // Variável global para armazenar dados do pedido
    let dadosPedidoAtual = null;
    
    // Função para filtrar pedidos por número ou picking
    function filtrarPedidos() {
        const filtro = document.getElementById('filtro_pedido').value.toLowerCase();
        const select = document.getElementById('pedido_id');
        const options = select.getElementsByTagName('option');
        
        for (let i = 1; i < options.length; i++) { // Pula a primeira opção ("Selecione...")
            const option = options[i];
            const numero = option.getAttribute('data-numero').toLowerCase();
            const picking = option.getAttribute('data-picking').toLowerCase();
            const texto = option.textContent.toLowerCase();
            
            if (numero.includes(filtro) || picking.includes(filtro) || texto.includes(filtro)) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        }
    }
    
    // Função para carregar dados do pedido selecionado
    function carregarDadosPedido() {
        const select = document.getElementById('pedido_id');
        const pedidoId = select.value;
        
        if (!pedidoId) {
            document.getElementById('info_pedido').style.display = 'none';
            dadosPedidoAtual = null;
            return;
        }
        
        // Buscar dados via AJAX
        console.log('Carregando dados do pedido:', pedidoId);
        fetch(`get_pedido_cotacao.php?pedido_id=${pedidoId}`)
            .then(response => {
                console.log('Resposta recebida:', response);
                return response.json();
            })
            .then(data => {
                console.log('Dados do pedido carregados:', data);
                if (data.success) {
                    dadosPedidoAtual = data;
                    exibirInformacoesPedido(data);
                    console.log('Chamando calcularFrete após carregar pedido');
                    calcularFrete();
                } else {
                    console.error('Erro nos dados do pedido:', data.error);
                    alert('Erro ao carregar dados do pedido: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                alert('Erro ao carregar dados do pedido');
            });
    }
    
    // Função para exibir informações do pedido
    function exibirInformacoesPedido(data) {
        document.getElementById('info_cliente').textContent = data.pedido.cliente;
        document.getElementById('info_rota').textContent = data.resumo.rota;
        document.getElementById('info_peso').textContent = data.resumo.peso_total;
        document.getElementById('info_medidas').textContent = data.resumo.total_medidas + ' item(s)';
        document.getElementById('info_cubagem').textContent = data.resumo.cubagem_total;
        
        // Preencher campos ocultos
        document.getElementById('cubagem_total').value = data.pedido.cubagem_total_m3;
        document.getElementById('pedido_dados').value = JSON.stringify(data.pedido);
        
        // Mostrar o painel de informações
        document.getElementById('info_pedido').style.display = 'block';
    }
    
    // Função para calcular frete automaticamente
    function calcularFrete() {
        console.log('Iniciando cálculo de frete...');
        
        const transportadoraSelect = document.getElementById('transportadora_id');
        const valorNotaFiscal = obterValorNumerico(document.getElementById('valor_nota_fiscal'));
        const pesoNotaFiscal = obterValorNumerico(document.getElementById('peso_nota_fiscal'));
        
        console.log('Valores:', {
            transportadora: transportadoraSelect.value,
            valorNota: valorNotaFiscal,
            pesoNota: pesoNotaFiscal,
            dadosPedido: dadosPedidoAtual
        });
        
        if (!transportadoraSelect.value) {
            console.log('Transportadora não selecionada');
            return;
        }
        
        if (!dadosPedidoAtual) {
            console.log('Dados do pedido não carregados');
            return;
        }
        
        if (valorNotaFiscal === 0) {
            console.log('Valor da nota fiscal é zero');
            return;
        }
        
        const option = transportadoraSelect.selectedOptions[0];
        const freteTonelada = parseFloat(option.getAttribute('data-frete-tonelada')) || 0;
        const freteMinimo = parseFloat(option.getAttribute('data-frete-minimo')) || 0;
        const freteValor = parseFloat(option.getAttribute('data-frete-valor')) || 0;
        const pedagioPesoCubico = parseFloat(option.getAttribute('data-pedagio-peso-cubico')) || 0;
        
        console.log('Dados da transportadora:', {
            freteTonelada,
            freteMinimo,
            freteValor,
            pedagioPesoCubico
        });
        
        const cubagem = dadosPedidoAtual.pedido.cubagem_total_m3 || 0;
        const pesoTotal = Math.max(pesoNotaFiscal, dadosPedidoAtual.pedido.peso || 0);
        
        console.log('Dados para cálculo:', {
            cubagem,
            pesoTotal,
            pesoNota: pesoNotaFiscal,
            pesoPedido: dadosPedidoAtual.pedido.peso
        });
        
        // Cálculo do frete baseado na tabela da transportadora
        let freteCalculado = 0;
        
        // Frete por tonelada
        const toneladas = pesoTotal / 1000;
        freteCalculado += freteTonelada * toneladas;
        
        // Pedágio por peso cúbico
        freteCalculado += pedagioPesoCubico * cubagem;
        
        // Percentual sobre valor da nota fiscal
        const percentualValor = (freteValor / 100) * valorNotaFiscal;
        freteCalculado += percentualValor;
        
        // Aplicar frete mínimo
        freteCalculado = Math.max(freteCalculado, freteMinimo);
        
        // Garantir que o valor não seja negativo
        freteCalculado = Math.max(freteCalculado, 0);
        
        console.log('Resultado do cálculo:', {
            freteCalculado,
            toneladas,
            fretePorTonelada: freteTonelada * toneladas,
            pedagioCubico: pedagioPesoCubico * cubagem,
            percentualValor: (freteValor / 100) * valorNotaFiscal,
            freteMinimo
        });
        
        // Atualizar campos com formatação brasileira
        const valorFormatado = freteCalculado.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        document.getElementById('valor_frete_calculado').value = valorFormatado;
        document.getElementById('valor_frete').value = valorFormatado;
        
        console.log('Campos atualizados com valor:', freteCalculado.toFixed(2));
    }
    
    // Função para validar formulário antes do envio
    function validarFormulario() {
        const pedidoId = document.getElementById('pedido_id').value;
        const transportadoraId = document.getElementById('transportadora_id').value;
        const numeroNotaFiscal = document.getElementById('numero_nota_fiscal').value.trim();
        const valorNotaFiscal = obterValorNumerico(document.getElementById('valor_nota_fiscal'));
        const pesoNotaFiscal = obterValorNumerico(document.getElementById('peso_nota_fiscal'));
        const valorFrete = obterValorNumerico(document.getElementById('valor_frete'));
        const prazoEntrega = parseInt(document.getElementById('prazo_entrega').value) || 0;
        
        // Validações
        if (!pedidoId) {
            alert('Por favor, selecione um pedido.');
            document.getElementById('pedido_id').focus();
            return false;
        }
        
        if (!transportadoraId) {
            alert('Por favor, selecione uma transportadora.');
            document.getElementById('transportadora_id').focus();
            return false;
        }
        
        if (!numeroNotaFiscal) {
            alert('Por favor, informe o número da nota fiscal.');
            document.getElementById('numero_nota_fiscal').focus();
            return false;
        }
        
        if (valorNotaFiscal <= 0) {
            alert('Por favor, informe um valor válido para a nota fiscal.');
            document.getElementById('valor_nota_fiscal').focus();
            return false;
        }
        
        if (pesoNotaFiscal <= 0) {
            alert('Por favor, informe um peso válido para a nota fiscal.');
            document.getElementById('peso_nota_fiscal').focus();
            return false;
        }
        
        if (valorFrete <= 0) {
            alert('O valor do frete deve ser maior que zero. Verifique os dados da transportadora.');
            document.getElementById('valor_frete').focus();
            return false;
        }
        
        if (prazoEntrega <= 0) {
            alert('Por favor, informe um prazo de entrega válido.');
            document.getElementById('prazo_entrega').focus();
            return false;
        }
        
        return true;
    }
    
    // Função para formatar campos numéricos decimais
    function formatarCampoDecimal(campo) {
        let valor = campo.value.replace(/[^0-9.,]/g, '');
        
        // Permitir apenas um ponto ou vírgula decimal
        let partes = valor.split(/[.,]/);
        if (partes.length > 2) {
            valor = partes[0] + '.' + partes[1];
        } else if (partes.length === 2) {
            valor = partes[0] + '.' + partes[1];
        }
        
        // Limitar casas decimais a 2
        if (valor.includes('.')) {
            let [inteira, decimal] = valor.split('.');
            if (decimal.length > 2) {
                decimal = decimal.substring(0, 2);
            }
            valor = inteira + '.' + decimal;
        }
        
        campo.value = valor;
    }
    
    // Função para formatar valor monetário com máscara
    function formatarValorMonetario(campo) {
        let valor = campo.value.replace(/[^0-9.,]/g, '');
        
        if (valor === '') {
            campo.value = '';
            return;
        }
        
        // Permitir apenas um separador decimal
        let partes = valor.split(/[.,]/);
        if (partes.length > 2) {
            valor = partes[0] + '.' + partes[1];
        } else if (partes.length === 2) {
            valor = partes[0] + '.' + partes[1];
        }
        
        // Limitar casas decimais a 2
        if (valor.includes('.')) {
            let [inteira, decimal] = valor.split('.');
            if (decimal && decimal.length > 2) {
                decimal = decimal.substring(0, 2);
            }
            valor = inteira + (decimal ? '.' + decimal : '');
        }
        
        campo.value = valor;
    }
    
    // Função para obter valor numérico de campo formatado
    function obterValorNumerico(campo) {
        return parseFloat(campo.value.replace(/[^0-9.,]/g, '').replace(',', '.')) || 0;
    }
    
    // Adicionar validações em tempo real
    document.getElementById('numero_nota_fiscal').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    
    document.getElementById('valor_nota_fiscal').addEventListener('input', function() {
        formatarCampoDecimal(this);
    });
    
    document.getElementById('peso_nota_fiscal').addEventListener('input', function() {
        formatarCampoDecimal(this);
    });
    
    document.getElementById('prazo_entrega').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    
    // Event listeners para calcular frete automaticamente
    document.getElementById('transportadora_id').addEventListener('change', function() {
        console.log('Transportadora alterada:', this.value);
        calcularFrete();
    });
    
    document.getElementById('valor_nota_fiscal').addEventListener('input', function() {
        console.log('Valor nota fiscal alterado:', this.value);
        calcularFrete();
    });
    
    document.getElementById('peso_nota_fiscal').addEventListener('input', function() {
        console.log('Peso nota fiscal alterado:', this.value);
        calcularFrete();
    });
    
    document.getElementById('pedido_id').addEventListener('change', function() {
        console.log('Pedido alterado:', this.value);
        // O cálculo será executado após carregar os dados do pedido
    });
    
    // Event listener para validar formulário no envio
    document.querySelector('#novaCotacaoModal form').addEventListener('submit', function(e) {
        if (!validarFormulario()) {
            e.preventDefault();
            return false;
        }
    });
    
    // Event listener para limpar filtro quando modal é fechado
    document.getElementById('novaCotacaoModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('filtro_pedido').value = '';
        document.getElementById('info_pedido').style.display = 'none';
        dadosPedidoAtual = null;
        
        // Limpar formulário
        this.querySelector('form').reset();
        
        // Mostrar todas as opções novamente
        const select = document.getElementById('pedido_id');
        const options = select.getElementsByTagName('option');
        for (let i = 1; i < options.length; i++) {
            options[i].style.display = '';
        }
    });
    
    // Verificar se há pedido_id na URL para pré-selecionar
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const pedidoId = urlParams.get('pedido_id');
        
        if (pedidoId) {
            // Abrir modal automaticamente
            const modal = new bootstrap.Modal(document.getElementById('novaCotacaoModal'));
            modal.show();
            
            // Aguardar o modal abrir completamente antes de selecionar o pedido
            document.getElementById('novaCotacaoModal').addEventListener('shown.bs.modal', function() {
                const selectPedido = document.getElementById('pedido_id');
                selectPedido.value = pedidoId;
                
                // Disparar evento change para carregar dados do pedido
                const event = new Event('change', { bubbles: true });
                selectPedido.dispatchEvent(event);
            }, { once: true });
        }
    });
    
    // Animações modernas
    document.addEventListener('DOMContentLoaded', function() {
        // Animação fade-in para elementos
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        // Observar elementos com fade-in
        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
        
        // Efeitos de hover para cards
        document.querySelectorAll('.modern-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
            });
        });
        
        // Efeitos de hover para botões
        document.querySelectorAll('.modern-btn').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-1px)';
                this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });
        
        // Efeitos de hover para linhas da tabela
        document.querySelectorAll('.modern-table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9fa';
                this.style.transform = 'scale(1.01)';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
                this.style.transform = 'scale(1)';
            });
        });
        
    });
    
    // Função para aprovar cotação
    function aprovarCotacao(cotacaoId) {
        if (confirm('Tem certeza que deseja aprovar esta cotação?')) {
            atualizarStatusCotacao(cotacaoId, 'aprovada');
            return true;
        }
        return false;
    }
    
    // Função para rejeitar cotação
    function rejeitarCotacao(cotacaoId) {
        if (confirm('Tem certeza que deseja rejeitar esta cotação?')) {
            atualizarStatusCotacao(cotacaoId, 'rejeitada');
            return true;
        }
        return false;
    }
    
    // Função para atualizar status da cotação
    function atualizarStatusCotacao(cotacaoId, novoStatus) {
        // Mostrar loading
        const buttons = document.querySelectorAll(`[data-id="${cotacaoId}"]`);
        buttons.forEach(btn => {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        });
        
        const formData = new FormData();
        formData.append('action', 'atualizar_status');
        formData.append('cotacao_id', cotacaoId);
        formData.append('status', novoStatus);
        
        fetch('cotacoes.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar mensagem de sucesso
                const statusText = novoStatus === 'aprovada' ? 'aprovada' : 'rejeitada';
                
                // Criar alerta de sucesso
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="fas fa-check-circle"></i> Cotação ${statusText} com sucesso!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                // Inserir alerta no topo da página
                const mainContent = document.querySelector('.modern-main');
                mainContent.insertBefore(alertDiv, mainContent.firstChild);
                
                // Recarregar a página após 1.5 segundos
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                alert('Erro ao atualizar status: ' + (data.message || 'Erro desconhecido'));
                // Restaurar botões em caso de erro
                buttons.forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = btn.title.includes('Aprovar') ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>';
                });
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao processar solicitação');
            // Restaurar botões em caso de erro
            buttons.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = btn.title.includes('Aprovar') ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>';
            });
        });
    }
    </script>
</body>
</html>