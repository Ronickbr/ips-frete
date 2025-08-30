<?php
// Sidebar Component - Sistema de Cotações IPS Fretes
// Este arquivo contém o menu lateral reutilizável do sistema

// Função para determinar se o link está ativo
function isActiveLink($page) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return $currentPage === $page ? 'active' : '';
}
?>

<!-- Sidebar -->
<nav class="col-md-3 col-lg-2 d-md-block modern-sidebar">
    <div class="position-sticky pt-3">
        <div class="sidebar-header text-center mb-4">
            <img src="/assets/images/Logo_ips.png" alt="FreteExpress" class="sidebar-logo mb-2" style="width: 150px !important; height: 73px !important; object-fit: contain;">
            <h6 class="text-gradient mb-0">IPS Fretes</h6>
            <small class="text-muted">Sistema de Cotações</small>
        </div>
        
        <ul class="nav flex-column modern-nav">
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveLink('index.php'); ?>" href="index.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveLink('pedidos.php'); ?>" href="pedidos.php">
                    <i class="fas fa-box"></i>
                    <span>Pedidos</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveLink('cotacoes.php'); ?>" href="cotacoes.php">
                    <i class="fas fa-calculator"></i>
                    <span>Cotações</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveLink('transportadoras.php'); ?>" href="transportadoras.php">
                    <i class="fas fa-truck"></i>
                    <span>Transportadoras</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveLink('relatorios.php'); ?>" href="relatorios.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Relatórios</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveLink('conferencia.php'); ?>" href="conferencia.php">
                    <i class="fas fa-check-circle"></i>
                    <span>Conferência</span>
                </a>
            </li>
            <?php if (function_exists('isAdmin') && isAdmin()): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveLink('criar_usuario.php'); ?>" href="criar_usuario.php">
                    <i class="fas fa-user-plus"></i>
                    <span>Criar Usuário</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item mt-4">
                <a class="nav-link logout-link" href="/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sair</span>
                </a>
            </li>
        </ul>
    </div>
</nav>