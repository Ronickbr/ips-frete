<?php
/**
 * Página Principal do Sistema de Controle de Fretes
 * Arquivo de entrada principal da aplicação
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
if (!isLoggedIn() && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header('Location: /login.php');
    exit();
}

// Testar conexão com banco de dados
try {
    $dbTest = testDBConnection();
    if (!$dbTest) {
        throw new Exception('Falha na conexão com o banco de dados');
    }
} catch (Exception $e) {
    error_log('Erro de conexão: ' . $e->getMessage());
    // Em produção, redirecionar para página de erro
    // header('Location: /error.php');
    // exit();
}

// Funções para buscar dados do dashboard
function getPedidosHoje() {
    try {
        $pdo = getDBConnection();
        $hoje = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pedidos WHERE DATE(created_at) = ?");
        $stmt->execute([$hoje]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    } catch (Exception $e) {
        error_log('Erro ao buscar pedidos de hoje: ' . $e->getMessage());
        return 0;
    }
}

function getCotacoesRealizadas() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cotacoes");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    } catch (Exception $e) {
        error_log('Erro ao buscar cotações realizadas: ' . $e->getMessage());
        return 0;
    }
}

function getFaturasPendentes() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cotacoes WHERE status = 'pendente'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    } catch (Exception $e) {
        error_log('Erro ao buscar faturas pendentes: ' . $e->getMessage());
        return 0;
    }
}

function getTransportadorasAtivas() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM transportadoras WHERE ativo = 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    } catch (Exception $e) {
        error_log('Erro ao buscar transportadoras ativas: ' . $e->getMessage());
        return 0;
    }
}

// Buscar dados para os cards
$pedidosHoje = getPedidosHoje();
$cotacoesRealizadas = getCotacoesRealizadas();
$faturasPendentes = getFaturasPendentes();
$transportadorasAtivas = getTransportadorasAtivas();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Controle de Fretes</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- CSS Personalizado -->
    <link href="/assets/css/style.css" rel="stylesheet">
    <link href="/assets/css/modern-theme.css" rel="stylesheet">
</head>
<body class="modern-body">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Conteúdo Principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 modern-main">
                <div class="modern-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-4 pb-3 mb-4">
                    <div>
                        <h1 class="h2 text-gradient mb-1">Dashboard</h1>
                        <p class="text-muted mb-0">Visão geral do sistema de cotações</p>
                    </div>
                    <div class="user-info">
                        <div class="d-flex align-items-center">
                            <div class="user-avatar me-3">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div class="fw-bold">Bem-vindo!</div>
                                <small class="text-muted"><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Usuário'; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Cards de Métricas -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="metric-card fade-in" style="animation-delay: 0.1s">
                            <div class="metric-icon primary">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Pedidos Hoje</div>
                                <div class="metric-value"><?php echo $pedidosHoje; ?></div>
                                <div class="metric-change positive">
                                    <i class="fas fa-arrow-up"></i> +0%
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="metric-card fade-in" style="animation-delay: 0.2s">
                            <div class="metric-icon success">
                                <i class="fas fa-calculator"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Cotações Realizadas</div>
                                <div class="metric-value"><?php echo $cotacoesRealizadas; ?></div>
                                <div class="metric-change positive">
                                    <i class="fas fa-arrow-up"></i> +0%
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="metric-card fade-in" style="animation-delay: 0.3s">
                            <div class="metric-icon info">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Faturas Pendentes</div>
                                <div class="metric-value"><?php echo $faturasPendentes; ?></div>
                                <div class="metric-change neutral">
                                    <i class="fas fa-minus"></i> 0%
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="metric-card fade-in" style="animation-delay: 0.4s">
                            <div class="metric-icon warning">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Transportadoras Ativas</div>
                                <div class="metric-value"><?php echo $transportadorasAtivas; ?></div>
                                <div class="metric-change positive">
                                    <i class="fas fa-arrow-up"></i> +0%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Status do Sistema -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="modern-card fade-in" style="animation-delay: 0.5s">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="fas fa-server me-2"></i>
                                    Status do Sistema
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="status-item">
                                    <div class="status-label">
                                        <i class="fas fa-database me-2"></i>
                                        Banco de Dados
                                    </div>
                                    <div class="status-value">
                                        <span class="badge badge-success">
                                            <i class="fas fa-check-circle me-1"></i>
                                            Conectado
                                        </span>
                                    </div>
                                </div>
                                <div class="status-item">
                                    <div class="status-label">
                                        <i class="fas fa-clock me-2"></i>
                                        Última Atualização
                                    </div>
                                    <div class="status-value">
                                        <span class="text-muted"><?php echo date('d/m/Y H:i:s'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="modern-card fade-in" style="animation-delay: 0.6s">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Bem-vindo
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-success" role="alert">
                                    <i class="fas fa-check-circle"></i>
                                    Sistema de Controle de Fretes inicializado com sucesso!
                                </div>
                                
                                <p class="mb-3">Use o menu lateral para navegar pelas funcionalidades:</p>
                                 <ul class="feature-list">
                                     <li class="feature-item">
                                         <i class="fas fa-box text-primary me-2"></i>
                                         <strong>Pedidos:</strong> Cadastre e gerencie pedidos com suas medidas
                                     </li>
                                     <li class="feature-item">
                                         <i class="fas fa-calculator text-success me-2"></i>
                                         <strong>Cotações:</strong> Realize cotações de frete baseadas nas transportadoras
                                     </li>
                                     <li class="feature-item">
                                         <i class="fas fa-truck text-warning me-2"></i>
                                         <strong>Transportadoras:</strong> Gerencie as transportadoras e suas configurações
                                     </li>
                                     <li class="feature-item">
                                         <i class="fas fa-chart-bar text-info me-2"></i>
                                         <strong>Relatórios:</strong> Visualize relatórios detalhados do sistema
                                     </li>
                                     <li class="feature-item">
                                         <i class="fas fa-file-invoice text-danger me-2"></i>
                                         <strong>Conferência:</strong> Confira faturas de transportadoras
                                     </li>
                                 </ul>
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
    
    <script>
        // Animações de entrada
        document.addEventListener('DOMContentLoaded', function() {
            // Animar elementos fade-in
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Animar contadores dos cards
            animateCounters();
            
            // Hover effects para cards de métricas
            const metricCards = document.querySelectorAll('.metric-card');
            metricCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Hover effects para itens de funcionalidades
            const featureItems = document.querySelectorAll('.feature-item');
            featureItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = 'var(--bg-light)';
                    this.style.paddingLeft = '20px';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = 'transparent';
                    this.style.paddingLeft = '0';
                });
            });
        });
        
        // Função para animar contadores
        function animateCounters() {
            const counters = document.querySelectorAll('.metric-value');
            counters.forEach(counter => {
                const target = parseInt(counter.textContent);
                let current = 0;
                const increment = target / 20;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target;
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current);
                    }
                }, 50);
            });
        }
        
        // Atualizar horário em tempo real
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('pt-BR');
            const timeElements = document.querySelectorAll('.text-muted');
            timeElements.forEach(element => {
                if (element.textContent.includes('/')) {
                    element.textContent = timeString;
                }
            });
        }
        
        // Atualizar a cada minuto
        setInterval(updateTime, 60000);
    </script>
</body>
</html>