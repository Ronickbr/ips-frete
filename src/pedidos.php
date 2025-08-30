<?php
/**
 * Página de Gerenciamento de Pedidos
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

// Processar formulário de edição de pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar_pedido') {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        $pedido_id = $_POST['pedido_id'];
        
        // Atualizar pedido
        $stmt = $pdo->prepare("
            UPDATE pedidos SET 
                numero_pedido = ?, numero_picking = ?, cliente = ?, origem = ?, destino = ?, 
                peso = ?, valor_mercadoria = ?, observacoes = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['numero_pedido'] ?? '',
            $_POST['numero_picking'] ?? '',
            $_POST['cliente'] ?? '',
            $_POST['origem'] ?? '',
            $_POST['destino'] ?? '',
            $_POST['peso'] ?? 0,
            $_POST['valor_mercadoria'] ?? 0,
            $_POST['observacoes'] ?? '',
            $pedido_id
        ]);
        
        // Remover medidas antigas
        $stmt = $pdo->prepare("DELETE FROM medidas WHERE pedido_id = ?");
        $stmt->execute([$pedido_id]);
        
        // Inserir novas medidas
        if (isset($_POST['medidas']) && is_array($_POST['medidas'])) {
            $stmt_medida = $pdo->prepare("
                INSERT INTO medidas (pedido_id, comprimento, altura, largura, quantidade_volumes, cubagem_m3) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['medidas'] as $medida) {
                if (!empty($medida['comprimento'] ?? '') && !empty($medida['altura'] ?? '') && !empty($medida['largura'] ?? '') && !empty($medida['quantidade_volumes'] ?? '')) {
                    $comprimento = floatval($medida['comprimento'] ?? 0);
                    $altura = floatval($medida['altura'] ?? 0);
                    $largura = floatval($medida['largura'] ?? 0);
                    $volumes = intval($medida['quantidade_volumes'] ?? 0);
                    
                    $cubagem_m3 = ($comprimento * $altura * $largura * $volumes) / 1000000;
                    
                    $stmt_medida->execute([
                        $pedido_id,
                        $comprimento,
                        $altura,
                        $largura,
                        $volumes,
                        $cubagem_m3
                    ]);
                }
            }
        }
        
        $pdo->commit();
        $success_message = "Pedido atualizado com sucesso!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Erro ao atualizar pedido: " . $e->getMessage();
    }
}

// Processar formulário de novo pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'novo_pedido') {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Inserir pedido
        $stmt = $pdo->prepare("
            INSERT INTO pedidos (numero_pedido, numero_picking, cliente, origem, destino, peso, valor_mercadoria, observacoes, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendente', NOW())
        ");
        
        $stmt->execute([
            $_POST['numero_pedido'] ?? '',
            $_POST['numero_picking'] ?? '',
            $_POST['cliente'] ?? '',
            $_POST['origem'] ?? '',
            $_POST['destino'] ?? '',
            $_POST['peso'] ?? 0,
            $_POST['valor_mercadoria'] ?? 0,
            $_POST['observacoes'] ?? ''
        ]);
        
        $pedido_id = $pdo->lastInsertId();
        
        // Inserir medidas se existirem
        if (isset($_POST['medidas']) && is_array($_POST['medidas'])) {
            $stmt_medida = $pdo->prepare("
                INSERT INTO medidas (pedido_id, comprimento, altura, largura, quantidade_volumes, cubagem_m3) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['medidas'] as $medida) {
                if (!empty($medida['comprimento'] ?? '') && !empty($medida['altura'] ?? '') && !empty($medida['largura'] ?? '') && !empty($medida['quantidade_volumes'] ?? '')) {
                    $comprimento = floatval($medida['comprimento'] ?? 0);
                    $altura = floatval($medida['altura'] ?? 0);
                    $largura = floatval($medida['largura'] ?? 0);
                    $volumes = intval($medida['quantidade_volumes'] ?? 0);

                    // Calcular cubagem em m³ (convertendo de cm³ para m³)
                    $cubagem_m3 = ($comprimento * $altura * $largura * $volumes) / 1000000;
                    
                    $stmt_medida->execute([
                        $pedido_id,
                        $comprimento,
                        $altura,
                        $largura,
                        $volumes,
                        $cubagem_m3
                    ]);
                }
            }
        }
        
        $pdo->commit();
        $success_message = "Pedido cadastrado com sucesso!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Erro ao cadastrar pedido: " . $e->getMessage();
    }
}

// Buscar pedidos existentes com medidas totais
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT p.*, 
               COUNT(m.id) as total_medidas,
               COALESCE(SUM(m.cubagem_m3), 0) as cubagem_total_m3
        FROM pedidos p 
        LEFT JOIN medidas m ON p.id = m.pedido_id 
        GROUP BY p.id 
        ORDER BY p.created_at DESC 
        LIMIT 20
    ");
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pedidos = [];
    $error_message = "Erro ao carregar pedidos: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - Sistema de Controle de Fretes</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- CSS Personalizado -->
    <link href="/assets/css/modern-theme.css" rel="stylesheet">
</head>
<body class="modern-body">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Conteúdo Principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 modern-main">
                <div class="modern-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-4 pb-3 mb-4 fade-in">
                    <div>
                        <h1 class="h2 text-gradient mb-1">Gerenciamento de Pedidos</h1>
                        <p class="text-muted mb-0">Controle e gerencie todos os pedidos do sistema</p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="modern-btn" data-bs-toggle="modal" data-bs-target="#novoPedidoModal">
                            <i class="fas fa-plus"></i> Novo Pedido
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
                
                <!-- Lista de Pedidos -->
                <div class="modern-card fade-in">
                    <div class="card-header">
                        <h5 class="card-title"><i class="fas fa-list"></i> Pedidos Recentes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pedidos)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-box fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Nenhum pedido encontrado. Clique em "Novo Pedido" para começar.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table modern-table">
                                    <thead>
                                        <tr>
                                            <th>Nº Pedido</th>
                                            <th>Nº PICKING</th>
                                            <th>Cliente</th>
                                            <th>Origem</th>
                                            <th>Destino</th>
                                            <th>Peso (kg)</th>
                                            <th>Medidas</th>
                                            <th>M³ Total</th>
                                            <th>Status</th>
                                            <th>Data</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pedidos as $pedido): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($pedido['numero_pedido']); ?></strong></td>
                                                <td><?php echo htmlspecialchars(isset($pedido['numero_picking']) ? $pedido['numero_picking'] : '-'); ?></td>
                                                <td><?php echo htmlspecialchars($pedido['cliente'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($pedido['origem'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($pedido['destino'] ?? ''); ?></td>
                                                <td><?php echo number_format($pedido['peso'] ?? 0, 2, ',', '.'); ?></td>
                                                <td>
                                                    <?php if ($pedido['total_medidas'] > 0): ?>
                                                        <span class="badge bg-info"><?php echo $pedido['total_medidas']; ?> medida(s)</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo number_format($pedido['cubagem_total_m3'], 3, ',', '.'); ?> m³</td>
                                                <td>
                                                    <?php 
                                                    $status_class = '';
                                                    switch($pedido['status'] ?? 'pendente') {
                                                        case 'pendente': $status_class = 'bg-warning'; break;
                                                        case 'cotado': $status_class = 'bg-info'; break;
                                                        case 'enviado': $status_class = 'bg-success'; break;
                                                        case 'entregue': $status_class = 'bg-primary'; break;
                                                        default: $status_class = 'bg-secondary';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($pedido['status'] ?? 'pendente'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($pedido['created_at'])); ?></td>
                                                <td>
                                                    <button class="modern-btn-sm" title="Editar" 
                                                            onclick="editarPedido(<?php echo $pedido['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="modern-btn-sm info" title="Cotar" 
                                                            onclick="cotarPedido(<?php echo $pedido['id']; ?>)">
                                                        <i class="fas fa-calculator"></i>
                                                    </button>
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
    
    <!-- Modal Novo Pedido -->
    <div class="modal fade" id="novoPedidoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content modern-modal">
                <div class="modal-header modern-modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Novo Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="novo_pedido">
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="numero_pedido" class="form-label">Número do Pedido *</label>
                                <input type="text" class="form-control" id="numero_pedido" name="numero_pedido" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="numero_picking" class="form-label">Nº PICKING</label>
                                <input type="text" class="form-control" id="numero_picking" name="numero_picking">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="cliente" class="form-label">Cliente *</label>
                                <input type="text" class="form-control" id="cliente" name="cliente" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="origem" class="form-label">Origem *</label>
                                <input type="text" class="form-control" id="origem" name="origem" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="destino" class="form-label">Destino *</label>
                                <input type="text" class="form-control" id="destino" name="destino" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="peso" class="form-label">Peso Total (kg) *</label>
                                <input type="number" step="0.01" class="form-control" id="peso" name="peso" required>
                            </div>
                        </div>
                        
                        <!-- Seção de Medidas -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0"><i class="fas fa-ruler-combined"></i> Medidas para Cálculo de M³</h6>
                                <button type="button" class="modern-btn-sm" id="addMedida">
                                    <i class="fas fa-plus"></i> Adicionar Medida
                                </button>
                            </div>
                            
                            <div id="medidasContainer">
                                <!-- Primeira medida (obrigatória) -->
                                <div class="medida-item border rounded p-3 mb-3" data-index="0">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">Medida #1</h6>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">Comprimento (cm) *</label>
                                            <input type="number" step="0.01" class="form-control medida-comprimento" name="medidas[0][comprimento]" required>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">Altura (cm) *</label>
                                            <input type="number" step="0.01" class="form-control medida-altura" name="medidas[0][altura]" required>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">Largura (cm) *</label>
                                            <input type="number" step="0.01" class="form-control medida-largura" name="medidas[0][largura]" required>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">Qtde Volumes *</label>
                                            <input type="number" min="1" class="form-control medida-volumes" name="medidas[0][quantidade_volumes]" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <small class="text-muted">M³ desta medida: <span class="m3-individual">0.000</span></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <strong>M³ Total: <span id="m3Total">0.000</span></strong>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="valor_mercadoria" class="form-label">Valor da Mercadoria (R$)</label>
                            <input type="number" step="0.01" class="form-control" id="valor_mercadoria" name="valor_mercadoria">
                        </div>
                        
                        <div class="mb-3">
                            <label for="observacoes" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer modern-modal-footer">
                        <button type="button" class="modern-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="modern-btn">Salvar Pedido</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Pedido -->
    <div class="modal fade" id="editarPedidoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content modern-modal">
                <div class="modal-header modern-modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Editar Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formEditarPedido">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar_pedido">
                        <input type="hidden" name="pedido_id" id="edit_pedido_id">
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit_numero_pedido" class="form-label">Número do Pedido *</label>
                                <input type="text" class="form-control" id="edit_numero_pedido" name="numero_pedido" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_numero_picking" class="form-label">Nº PICKING</label>
                                <input type="text" class="form-control" id="edit_numero_picking" name="numero_picking">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_cliente" class="form-label">Cliente *</label>
                                <input type="text" class="form-control" id="edit_cliente" name="cliente" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_origem" class="form-label">Origem *</label>
                                <input type="text" class="form-control" id="edit_origem" name="origem" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_destino" class="form-label">Destino *</label>
                                <input type="text" class="form-control" id="edit_destino" name="destino" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="edit_peso" class="form-label">Peso Total (kg) *</label>
                                <input type="number" step="0.01" class="form-control" id="edit_peso" name="peso" required>
                            </div>
                        </div>
                        
                        <!-- Seção de Medidas para Edição -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0"><i class="fas fa-ruler-combined"></i> Medidas para Cálculo de M³</h6>
                                <button type="button" class="modern-btn-sm" id="addMedidaEdit">
                                    <i class="fas fa-plus"></i> Adicionar Medida
                                </button>
                            </div>
                            
                            <div id="editMedidasContainer">
                                <!-- Medidas serão carregadas dinamicamente -->
                            </div>
                            
                            <div class="alert alert-info">
                                <strong>M³ Total: <span id="editM3Total">0.000</span></strong>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_valor_mercadoria" class="form-label">Valor da Mercadoria (R$)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_valor_mercadoria" name="valor_mercadoria">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_observacoes" class="form-label">Observações</label>
                            <textarea class="form-control" id="edit_observacoes" name="observacoes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer modern-modal-footer">
                        <button type="button" class="modern-btn secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="modern-btn">Atualizar Pedido</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript Personalizado -->
    <script src="/assets/js/main.js"></script>
    
    <!-- JavaScript para Animações -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animações de entrada
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
        
        // Efeitos hover para cards
        document.querySelectorAll('.modern-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
            });
        });
        
        // Efeitos hover para botões
        document.querySelectorAll('.modern-btn').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-1px)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Efeitos hover para linhas da tabela
        document.querySelectorAll('.modern-table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = 'rgba(74, 144, 226, 0.05)';
                this.style.transform = 'scale(1.01)';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
                this.style.transform = 'scale(1)';
            });
        });
    });
    </script>
    
    <!-- JavaScript para Medidas -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let medidaIndex = 1;
        
        // Função para calcular M³ individual
        function calcularM3Individual(medidaItem) {
            const comprimento = parseFloat(medidaItem.querySelector('.medida-comprimento').value) || 0;
            const altura = parseFloat(medidaItem.querySelector('.medida-altura').value) || 0;
            const largura = parseFloat(medidaItem.querySelector('.medida-largura').value) || 0;
            const volumes = parseInt(medidaItem.querySelector('.medida-volumes').value) || 0;
            
            const m3Individual = (comprimento * altura * largura * volumes) / 1000000; // Converter cm³ para m³
            medidaItem.querySelector('.m3-individual').textContent = m3Individual.toFixed(3);
            
            calcularM3Total();
        }
        
        // Função para calcular M³ total
        function calcularM3Total() {
            let total = 0;
            document.querySelectorAll('.medida-item').forEach(function(item) {
                const m3Individual = parseFloat(item.querySelector('.m3-individual').textContent) || 0;
                total += m3Individual;
            });
            document.getElementById('m3Total').textContent = total.toFixed(3);
        }
        
        // Adicionar event listeners para cálculo automático
        function adicionarEventListeners(medidaItem) {
            const inputs = medidaItem.querySelectorAll('.medida-comprimento, .medida-altura, .medida-largura, .medida-volumes');
            inputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    calcularM3Individual(medidaItem);
                });
            });
        }
        
        // Adicionar nova medida
        document.getElementById('addMedida').addEventListener('click', function() {
            const container = document.getElementById('medidasContainer');
            const novaMediada = document.createElement('div');
            novaMediada.className = 'medida-item border rounded p-3 mb-3';
            novaMediada.setAttribute('data-index', medidaIndex);
            
            novaMediada.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Medida #${medidaIndex + 1}</h6>
                    <button type="button" class="modern-btn-sm danger remove-medida">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Comprimento (cm) *</label>
                        <input type="number" step="0.01" class="form-control medida-comprimento" name="medidas[${medidaIndex}][comprimento]" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Altura (cm) *</label>
                        <input type="number" step="0.01" class="form-control medida-altura" name="medidas[${medidaIndex}][altura]" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Largura (cm) *</label>
                        <input type="number" step="0.01" class="form-control medida-largura" name="medidas[${medidaIndex}][largura]" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Qtde Volumes *</label>
                        <input type="number" min="1" class="form-control medida-volumes" name="medidas[${medidaIndex}][quantidade_volumes]" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted">M³ desta medida: <span class="m3-individual">0.000</span></small>
                    </div>
                </div>
            `;
            
            container.appendChild(novaMediada);
            adicionarEventListeners(novaMediada);
            
            // Adicionar event listener para remover
            novaMediada.querySelector('.remove-medida').addEventListener('click', function() {
                novaMediada.remove();
                calcularM3Total();
                atualizarNumeracaoMedidas();
            });
            
            medidaIndex++;
        });
        
        // Event listener para adicionar nova medida no modal de edição
        document.getElementById('addMedidaEdit').addEventListener('click', function() {
            adicionarMedidaEdit();
        });
        
        // Função para editar pedido
        window.editarPedido = function(pedidoId) {
            // Fazer requisição AJAX para buscar dados do pedido
            fetch('get_pedido.php?id=' + pedidoId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Preencher campos do formulário
                        document.getElementById('edit_pedido_id').value = data.pedido.id;
                        document.getElementById('edit_numero_pedido').value = data.pedido.numero_pedido || '';
                        document.getElementById('edit_numero_picking').value = data.pedido.numero_picking || '';
                        document.getElementById('edit_cliente').value = data.pedido.cliente || '';
                        document.getElementById('edit_origem').value = data.pedido.origem || '';
                        document.getElementById('edit_destino').value = data.pedido.destino || '';
                        document.getElementById('edit_peso').value = data.pedido.peso || '';
                        document.getElementById('edit_valor_mercadoria').value = data.pedido.valor_mercadoria || '';
                        document.getElementById('edit_observacoes').value = data.pedido.observacoes || '';
                        
                        // Limpar container de medidas
                        document.getElementById('editMedidasContainer').innerHTML = '';
                        
                        // Carregar medidas existentes
                        if (data.medidas && data.medidas.length > 0) {
                            data.medidas.forEach(function(medida) {
                                adicionarMedidaEdit(medida);
                            });
                        } else {
                            // Adicionar uma medida vazia se não houver medidas
                            adicionarMedidaEdit();
                        }
                        
                        // Calcular M³ total
                        calcularM3TotalEdit();
                        
                        // Abrir modal
                        new bootstrap.Modal(document.getElementById('editarPedidoModal')).show();
                    } else {
                        alert('Erro ao carregar dados do pedido: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao carregar dados do pedido.');
                });
        };
        
        // Função para adicionar medida no modal de edição
        function adicionarMedidaEdit(medida = null) {
            const container = document.getElementById('editMedidasContainer');
            const index = container.children.length;
            
            const medidaHtml = `
                <div class="medida-item border rounded p-3 mb-3" data-index="${index}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Medida ${index + 1}</h6>
                        <button type="button" class="modern-btn-sm danger" onclick="removerMedidaEdit(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Comprimento (cm)</label>
                            <input type="number" step="0.01" class="form-control medida-input" 
                                   name="medidas[${index}][comprimento]" 
                                   value="${medida ? medida.comprimento : ''}" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Altura (cm)</label>
                            <input type="number" step="0.01" class="form-control medida-input" 
                                   name="medidas[${index}][altura]" 
                                   value="${medida ? medida.altura : ''}" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Largura (cm)</label>
                            <input type="number" step="0.01" class="form-control medida-input" 
                                   name="medidas[${index}][largura]" 
                                   value="${medida ? medida.largura : ''}" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Qtde Volumes</label>
                            <input type="number" class="form-control medida-input" 
                                   name="medidas[${index}][quantidade_volumes]" 
                                   value="${medida ? medida.quantidade_volumes : ''}" required>
                        </div>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">M³ Individual: <span class="m3-individual">0.000</span></small>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', medidaHtml);
            adicionarEventListenersEdit(container.lastElementChild);
        }
        
        // Função para remover medida no modal de edição
        window.removerMedidaEdit = function(button) {
            const container = document.getElementById('editMedidasContainer');
            if (container.children.length > 1) {
                button.closest('.medida-item').remove();
                atualizarNumeracaoMedidasEdit();
                calcularM3TotalEdit();
            }
        };
        
        // Função para adicionar event listeners nas medidas de edição
        function adicionarEventListenersEdit(medidaElement) {
            const inputs = medidaElement.querySelectorAll('.medida-input');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    calcularM3IndividualEdit(medidaElement);
                    calcularM3TotalEdit();
                });
            });
        }
        
        // Função para calcular M³ individual no modal de edição
        function calcularM3IndividualEdit(medidaElement) {
            const inputs = medidaElement.querySelectorAll('.medida-input');
            const comprimento = parseFloat(inputs[0].value) || 0;
            const altura = parseFloat(inputs[1].value) || 0;
            const largura = parseFloat(inputs[2].value) || 0;
            const quantidade = parseInt(inputs[3].value) || 0;
            
            const m3Individual = (comprimento * altura * largura * quantidade) / 1000000;
            medidaElement.querySelector('.m3-individual').textContent = m3Individual.toFixed(3);
        }
        
        // Função para calcular M³ total no modal de edição
        function calcularM3TotalEdit() {
            const medidas = document.querySelectorAll('#editMedidasContainer .m3-individual');
            let total = 0;
            
            medidas.forEach(span => {
                total += parseFloat(span.textContent) || 0;
            });
            
            document.getElementById('editM3Total').textContent = total.toFixed(3);
        }
        
        // Função para atualizar numeração das medidas no modal de edição
        function atualizarNumeracaoMedidasEdit() {
            const medidas = document.querySelectorAll('#editMedidasContainer .medida-item');
            medidas.forEach((medida, index) => {
                medida.setAttribute('data-index', index);
                medida.querySelector('h6').textContent = `Medida ${index + 1}`;
                
                // Atualizar nomes dos inputs
                const inputs = medida.querySelectorAll('input');
                inputs[0].name = `medidas[${index}][comprimento]`;
                inputs[1].name = `medidas[${index}][altura]`;
                inputs[2].name = `medidas[${index}][largura]`;
                inputs[3].name = `medidas[${index}][quantidade_volumes]`;
            });
        }
        
        // Função para atualizar numeração das medidas
        function atualizarNumeracaoMedidas() {
            document.querySelectorAll('.medida-item').forEach(function(item, index) {
                item.querySelector('h6').textContent = `Medida #${index + 1}`;
            });
        }
        
        // Adicionar event listeners para a primeira medida
        adicionarEventListeners(document.querySelector('.medida-item'));
        
        // Função para cotar pedido
        window.cotarPedido = function(pedidoId) {
            // Redirecionar para a página de cotações com o ID do pedido
            window.location.href = 'cotacoes.php?pedido_id=' + pedidoId;
        };
    });
    </script>
</body>
</html>