<?php
/**
 * Página de Gerenciamento de Transportadoras
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

// Processar formulário de nova transportadora
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nova_transportadora') {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO transportadoras (
                nome, peso_ate_50kg, peso_ate_100kg, peso_ate_150kg, peso_ate_200kg, peso_ate_300kg,
                frete_por_tonelada, frete_minimo, pedagio, frete_valor_percentual, fator_peso_cubico, ativo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([
            $_POST['nome'],
            $_POST['peso_ate_50kg'] ?? 0,
            $_POST['peso_ate_100kg'] ?? 0,
            $_POST['peso_ate_150kg'] ?? 0,
            $_POST['peso_ate_200kg'] ?? 0,
            $_POST['peso_ate_300kg'] ?? 0,
            $_POST['frete_por_tonelada'] ?? 0,
            $_POST['frete_minimo'] ?? 0,
            $_POST['pedagio'] ?? 0,
            $_POST['frete_valor_percentual'] ?? 0,
            $_POST['fator_peso_cubico'] ?? 0
        ]);
        
        $success_message = "Transportadora cadastrada com sucesso!";
    } catch (Exception $e) {
        $error_message = "Erro ao cadastrar transportadora: " . $e->getMessage();
    }
}

// Processar ativação/desativação de transportadora
if (isset($_POST['toggle_status'])) {
    try {
        $pdo = getDBConnection();
        
        $id = $_POST['transportadora_id'];
        $stmt = $pdo->prepare("UPDATE transportadoras SET ativo = NOT ativo WHERE id = ?");
        $stmt->execute([$id]);
        
        $success_message = "Status da transportadora atualizado com sucesso!";
    } catch (Exception $e) {
        $error_message = "Erro ao atualizar status: " . $e->getMessage();
    }
}

// Processar edição de transportadora
if (isset($_POST['action']) && $_POST['action'] === 'editar') {
    $transportadora_id = $_POST['transportadora_id'];
    $nome = $_POST['nome'];
    $peso_ate_50kg = $_POST['peso_ate_50kg'] ?: 0;
    $peso_ate_100kg = $_POST['peso_ate_100kg'] ?: 0;
    $peso_ate_150kg = $_POST['peso_ate_150kg'] ?: 0;
    $peso_ate_200kg = $_POST['peso_ate_200kg'] ?: 0;
    $peso_ate_300kg = $_POST['peso_ate_300kg'] ?: 0;
    $frete_por_tonelada = $_POST['frete_por_tonelada'] ?: 0;
    $frete_minimo = $_POST['frete_minimo'] ?: 0;
    $pedagio = $_POST['pedagio'] ?: 0;
    $frete_valor_percentual = $_POST['frete_valor_percentual'] ?: 0;
    $fator_peso_cubico = $_POST['fator_peso_cubico'] ?: 0;
    
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            UPDATE transportadoras SET 
                nome = ?,
                peso_ate_50kg = ?,
                peso_ate_100kg = ?,
                peso_ate_150kg = ?,
                peso_ate_200kg = ?,
                peso_ate_300kg = ?,
                frete_por_tonelada = ?,
                frete_minimo = ?,
                pedagio = ?,
                frete_valor_percentual = ?,
                fator_peso_cubico = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $nome,
            $peso_ate_50kg,
            $peso_ate_100kg,
            $peso_ate_150kg,
            $peso_ate_200kg,
            $peso_ate_300kg,
            $frete_por_tonelada,
            $frete_minimo,
            $pedagio,
            $frete_valor_percentual,
            $fator_peso_cubico,
            $transportadora_id
        ]);
        
        $success_message = "Transportadora atualizada com sucesso!";
    } catch (Exception $e) {
        $error_message = "Erro ao atualizar transportadora: " . $e->getMessage();
    }
}

// Buscar transportadoras existentes
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT t.*, 
               COUNT(c.id) as total_cotacoes,
               AVG(c.valor_frete_calculado) as valor_medio_frete
        FROM transportadoras t 
        LEFT JOIN cotacoes c ON t.id = c.transportadora_id 
        GROUP BY t.id 
        ORDER BY t.nome
    ");
    $transportadoras = $stmt->fetchAll();
} catch (Exception $e) {
    $transportadoras = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transportadoras - Sistema de Controle de Fretes</title>
    
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
        
                <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2 text-gradient">Gerenciamento de Transportadoras</h1>
                        <p class="text-muted mb-0">Cadastre e gerencie suas transportadoras parceiras</p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novaTransportadoraModal">
                            <i class="fas fa-plus"></i> Nova Transportadora
                        </button>
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
                <div class="row mb-4 fade-in">
                    <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fas fa-shipping-fast"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Total Transportadoras</div>
                                <div class="metric-value"><?php echo count($transportadoras); ?></div>
                                <div class="metric-change">Cadastradas no sistema</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Transportadoras Ativas</div>
                                <div class="metric-value"><?php echo count(array_filter($transportadoras, function($t) { return $t['ativo'] == 1; })); ?></div>
                                <div class="metric-change">Disponíveis para cotação</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fas fa-calculator"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Total Cotações</div>
                                <div class="metric-value"><?php echo array_sum(array_column($transportadoras, 'total_cotacoes')); ?></div>
                                <div class="metric-change">Realizadas no sistema</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-lg-6 col-md-6 mb-3">
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Valor Médio Geral</div>
                                <div class="metric-value">
                                    R$ <?php 
                                    $valores = array_filter(array_column($transportadoras, 'valor_medio_frete'));
                                    echo $valores ? number_format(array_sum($valores) / count($valores), 2, ',', '.') : '0,00';
                                    ?>
                                </div>
                                <div class="metric-change">Preço médio dos fretes</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Lista de Transportadoras -->
                <div class="modern-card fade-in">
                    <div class="card-header">
                        <i class="fas fa-list"></i>
                        <h3 class="card-title">Transportadoras Cadastradas</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($transportadoras)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-shipping-fast fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Nenhuma transportadora encontrada. Clique em "Nova Transportadora" para começar.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table modern-table">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Cotações</th>
                                            <th>Valor Médio</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transportadoras as $transportadora): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($transportadora['nome']); ?></strong>
                                                </td>
                                                <td><span class="badge bg-info"><?php echo $transportadora['total_cotacoes']; ?></span></td>
                                                <td>
                                                    <?php if ($transportadora['valor_medio_frete']): ?>
                                                        <strong>R$ <?php echo number_format($transportadora['valor_medio_frete'], 2, ',', '.'); ?></strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $transportadora['ativo'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $transportadora['ativo'] ? 'Ativa' : 'Inativa'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="modern-btn-sm" title="Editar" onclick="editarTransportadora(<?php echo $transportadora['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="modern-btn-sm" title="Ver Detalhes" onclick="verDetalhes(<?php echo $transportadora['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="transportadora_id" value="<?php echo $transportadora['id']; ?>">
                                                        <button type="submit" name="toggle_status" class="modern-btn-sm <?php echo $transportadora['ativo'] ? 'warning' : 'success'; ?>" title="<?php echo $transportadora['ativo'] ? 'Desativar' : 'Ativar'; ?>">
                                                            <i class="fas <?php echo $transportadora['ativo'] ? 'fa-times' : 'fa-check'; ?>"></i>
                                                        </button>
                                                    </form>
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
    </div>
            </main>
        </div>
    </div>
    
    <!-- Modal de Nova Transportadora -->
    <div class="modal fade" id="novaTransportadoraModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Nova Transportadora</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="nova_transportadora">
                        
                        <!-- Dados Básicos -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3"><i class="fas fa-info-circle"></i> Dados Básicos</h6>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="nome" class="form-label">Nome da Transportadora *</label>
                                    <input type="text" class="form-control" id="nome" name="nome" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tabela de Fretes -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3"><i class="fas fa-calculator"></i> Tabela de Fretes</h6>
                            
                            <!-- Faixas de Peso -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="peso_ate_50kg" class="form-label">Peso até 50 kg (R$)</label>
                                    <input type="number" step="0.01" class="form-control" id="peso_ate_50kg" name="peso_ate_50kg" placeholder="0.00">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="peso_ate_100kg" class="form-label">Peso até 100 kg (R$)</label>
                                    <input type="number" step="0.01" class="form-control" id="peso_ate_100kg" name="peso_ate_100kg" placeholder="0.00">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="peso_ate_150kg" class="form-label">Peso até 150 kg (R$)</label>
                                    <input type="number" step="0.01" class="form-control" id="peso_ate_150kg" name="peso_ate_150kg" placeholder="0.00">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="peso_ate_200kg" class="form-label">Peso até 200 kg (R$)</label>
                                    <input type="number" step="0.01" class="form-control" id="peso_ate_200kg" name="peso_ate_200kg" placeholder="0.00">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="peso_ate_300kg" class="form-label">Peso até 300 kg (R$)</label>
                                    <input type="number" step="0.01" class="form-control" id="peso_ate_300kg" name="peso_ate_300kg" placeholder="0.00">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="frete_por_tonelada" class="form-label">Frete por Tonelada (R$)</label>
                                    <input type="number" step="0.01" class="form-control" id="frete_por_tonelada" name="frete_por_tonelada" placeholder="0.00">
                                </div>
                            </div>
                            
                            <!-- Valores Adicionais -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="frete_minimo" class="form-label">Frete Mínimo (R$)</label>
                                    <input type="number" step="0.01" class="form-control" id="frete_minimo" name="frete_minimo" placeholder="0.00">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="pedagio" class="form-label">Pedágio (R$)</label>
                                    <input type="number" step="0.01" class="form-control" id="pedagio" name="pedagio" placeholder="0.00">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="frete_valor_percentual" class="form-label">Frete Valor % (%)</label>
                                    <input type="number" step="0.01" class="form-control" id="frete_valor_percentual" name="frete_valor_percentual" placeholder="0.00">
                                    <small class="text-muted">Percentual sobre o valor da nota fiscal</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="fator_peso_cubico" class="form-label">Fator Peso Cúbico</label>
                                    <input type="number" step="0.01" class="form-control" id="fator_peso_cubico" name="fator_peso_cubico" placeholder="0.00">
                                    <small class="text-muted">Fator para cálculo do peso cúbico</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modern-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="modern-btn">Salvar Transportadora</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de Edição -->
    <div class="modal fade" id="modalEditarTransportadora" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Transportadora</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEditarTransportadora" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar">
                        <input type="hidden" name="transportadora_id" id="edit_transportadora_id">
                        
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Nome da Transportadora</label>
                                <input type="text" class="form-control" name="nome" id="edit_nome" required>
                            </div>
                        </div>
                        
                        <h6 class="mb-3">Tabela de Fretes</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Peso até 50kg (R$)</label>
                                <input type="number" step="0.01" class="form-control" name="peso_ate_50kg" id="edit_peso_ate_50kg">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Peso até 100kg (R$)</label>
                                <input type="number" step="0.01" class="form-control" name="peso_ate_100kg" id="edit_peso_ate_100kg">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Peso até 150kg (R$)</label>
                                <input type="number" step="0.01" class="form-control" name="peso_ate_150kg" id="edit_peso_ate_150kg">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Peso até 200kg (R$)</label>
                                <input type="number" step="0.01" class="form-control" name="peso_ate_200kg" id="edit_peso_ate_200kg">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Peso até 300kg (R$)</label>
                                <input type="number" step="0.01" class="form-control" name="peso_ate_300kg" id="edit_peso_ate_300kg">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Frete por Tonelada (R$)</label>
                                <input type="number" step="0.01" class="form-control" name="frete_por_tonelada" id="edit_frete_por_tonelada">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Frete Mínimo (R$)</label>
                                <input type="number" step="0.01" class="form-control" name="frete_minimo" id="edit_frete_minimo">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pedágio (R$)</label>
                                <input type="number" step="0.01" class="form-control" name="pedagio" id="edit_pedagio">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Frete Valor % (%)</label>
                                <input type="number" step="0.01" class="form-control" name="frete_valor_percentual" id="edit_frete_valor_percentual">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fator Peso Cúbico</label>
                                <input type="number" step="0.01" class="form-control" name="fator_peso_cubico" id="edit_fator_peso_cubico">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modern-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="modern-btn">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Detalhes -->
    <div class="modal fade" id="modalDetalhesTransportadora" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes da Transportadora</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <h6>Nome da Transportadora</h6>
                            <p id="detail_nome" class="mb-0"></p>
                        </div>
                    </div>
                    
                    <h6 class="mb-3">Tabela de Fretes</h6>
                    <div class="table-responsive">
                        <table class="table modern-table">
                            <tbody>
                                <tr>
                                    <td><strong>Peso até 50kg:</strong></td>
                                    <td id="detail_peso_ate_50kg"></td>
                                </tr>
                                <tr>
                                    <td><strong>Peso até 100kg:</strong></td>
                                    <td id="detail_peso_ate_100kg"></td>
                                </tr>
                                <tr>
                                    <td><strong>Peso até 150kg:</strong></td>
                                    <td id="detail_peso_ate_150kg"></td>
                                </tr>
                                <tr>
                                    <td><strong>Peso até 200kg:</strong></td>
                                    <td id="detail_peso_ate_200kg"></td>
                                </tr>
                                <tr>
                                    <td><strong>Peso até 300kg:</strong></td>
                                    <td id="detail_peso_ate_300kg"></td>
                                </tr>
                                <tr>
                                    <td><strong>Frete por Tonelada:</strong></td>
                                    <td id="detail_frete_por_tonelada"></td>
                                </tr>
                                <tr>
                                    <td><strong>Frete Mínimo:</strong></td>
                                    <td id="detail_frete_minimo"></td>
                                </tr>
                                <tr>
                                    <td><strong>Pedágio:</strong></td>
                                    <td id="detail_pedagio"></td>
                                </tr>
                                <tr>
                                    <td><strong>Frete Valor %:</strong></td>
                                    <td id="detail_frete_valor_percentual"></td>
                                </tr>
                                <tr>
                                    <td><strong>Fator Peso Cúbico:</strong></td>
                                    <td id="detail_fator_peso_cubico"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h6>Estatísticas</h6>
                            <p><strong>Total de Cotações:</strong> <span id="detail_total_cotacoes"></span></p>
                            <p><strong>Valor Médio do Frete:</strong> <span id="detail_valor_medio"></span></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Status</h6>
                            <p><span id="detail_status"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modern-btn secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript Personalizado -->
    <script src="/assets/js/main.js"></script>
    
    <script>
    function editarTransportadora(id) {
        // Buscar dados da transportadora via AJAX
        fetch('get_transportadora.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const t = data.transportadora;
                    document.getElementById('edit_transportadora_id').value = t.id;
                    document.getElementById('edit_nome').value = t.nome;
                    document.getElementById('edit_peso_ate_50kg').value = t.peso_ate_50kg || '';
                    document.getElementById('edit_peso_ate_100kg').value = t.peso_ate_100kg || '';
                    document.getElementById('edit_peso_ate_150kg').value = t.peso_ate_150kg || '';
                    document.getElementById('edit_peso_ate_200kg').value = t.peso_ate_200kg || '';
                    document.getElementById('edit_peso_ate_300kg').value = t.peso_ate_300kg || '';
                    document.getElementById('edit_frete_por_tonelada').value = t.frete_por_tonelada || '';
                    document.getElementById('edit_frete_minimo').value = t.frete_minimo || '';
                    document.getElementById('edit_pedagio').value = t.pedagio || '';
                    document.getElementById('edit_frete_valor_percentual').value = t.frete_valor_percentual || '';
                    document.getElementById('edit_fator_peso_cubico').value = t.fator_peso_cubico || '';
                    
                    new bootstrap.Modal(document.getElementById('modalEditarTransportadora')).show();
                } else {
                    alert('Erro ao carregar dados da transportadora');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao carregar dados da transportadora');
            });
    }

    function verDetalhes(id) {
        // Buscar dados da transportadora via AJAX
        fetch('get_transportadora.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const t = data.transportadora;
                    document.getElementById('detail_nome').textContent = t.nome;
                    document.getElementById('detail_peso_ate_50kg').textContent = t.peso_ate_50kg ? 'R$ ' + parseFloat(t.peso_ate_50kg).toFixed(2).replace('.', ',') : 'N/A';
                    document.getElementById('detail_peso_ate_100kg').textContent = t.peso_ate_100kg ? 'R$ ' + parseFloat(t.peso_ate_100kg).toFixed(2).replace('.', ',') : 'N/A';
                    document.getElementById('detail_peso_ate_150kg').textContent = t.peso_ate_150kg ? 'R$ ' + parseFloat(t.peso_ate_150kg).toFixed(2).replace('.', ',') : 'N/A';
                    document.getElementById('detail_peso_ate_200kg').textContent = t.peso_ate_200kg ? 'R$ ' + parseFloat(t.peso_ate_200kg).toFixed(2).replace('.', ',') : 'N/A';
                    document.getElementById('detail_peso_ate_300kg').textContent = t.peso_ate_300kg ? 'R$ ' + parseFloat(t.peso_ate_300kg).toFixed(2).replace('.', ',') : 'N/A';
                    document.getElementById('detail_frete_por_tonelada').textContent = t.frete_por_tonelada ? 'R$ ' + parseFloat(t.frete_por_tonelada).toFixed(2).replace('.', ',') : 'N/A';
                    document.getElementById('detail_frete_minimo').textContent = t.frete_minimo ? 'R$ ' + parseFloat(t.frete_minimo).toFixed(2).replace('.', ',') : 'N/A';
                    document.getElementById('detail_pedagio').textContent = t.pedagio ? 'R$ ' + parseFloat(t.pedagio).toFixed(2).replace('.', ',') : 'N/A';
                    document.getElementById('detail_frete_valor_percentual').textContent = t.frete_valor_percentual ? parseFloat(t.frete_valor_percentual).toFixed(2).replace('.', ',') + '%' : 'N/A';
                    document.getElementById('detail_fator_peso_cubico').textContent = t.fator_peso_cubico ? parseFloat(t.fator_peso_cubico).toFixed(2).replace('.', ',') : 'N/A';
                    document.getElementById('detail_total_cotacoes').textContent = t.total_cotacoes || '0';
                    document.getElementById('detail_valor_medio').textContent = t.valor_medio_frete ? 'R$ ' + parseFloat(t.valor_medio_frete).toFixed(2).replace('.', ',') : 'N/A';
                    document.getElementById('detail_status').innerHTML = '<span class="badge ' + (t.ativo == '1' ? 'bg-success' : 'bg-secondary') + '">' + (t.ativo == '1' ? 'Ativa' : 'Inativa') + '</span>';
                    
                    new bootstrap.Modal(document.getElementById('modalDetalhesTransportadora')).show();
                } else {
                    alert('Erro ao carregar dados da transportadora');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao carregar dados da transportadora');
            });
    }
    </script>
    
    <script>
    // Animações modernas
    document.addEventListener('DOMContentLoaded', function() {
        // Fade-in animation para elementos
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
        
        // Observar elementos com classe fade-in
        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
        
        // Hover effects para cards
        document.querySelectorAll('.modern-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 10px 30px rgba(0,0,0,0.15)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
            });
        });
        
        // Hover effects para linhas da tabela
        document.querySelectorAll('.modern-table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9ff';
                this.style.transform = 'scale(1.01)';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
                this.style.transform = 'scale(1)';
            });
        });
    });
    </script>
</body>
</html>