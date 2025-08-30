<?php
/**
 * Página de Login do Sistema de Controle de Fretes
 */

// Iniciar sessão
session_start();

// Se já estiver logado, redirecionar para dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit();
}

// Incluir configurações
require_once 'config/database.php';

$error_message = '';
$success_message = '';

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_message = 'Por favor, preencha todos os campos.';
    } else {
        try {
            $conn = getDBConnection();
            
            // Buscar usuário por email
            $stmt = $conn->prepare("SELECT id, email, password_hash, nome, tipo FROM usuarios WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login bem-sucedido
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['nome'];
                $_SESSION['user_type'] = $user['tipo'];
                
                header('Location: /index.php');
                exit();
            } else {
                $error_message = 'Email ou senha incorretos.';
            }
        } catch (Exception $e) {
            error_log('Erro no login: ' . $e->getMessage());
            $error_message = 'Erro interno do sistema. Tente novamente.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Controle de Fretes</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Modern Theme CSS -->
    <link href="assets/css/modern-theme.css" rel="stylesheet">
    
    <style>
        body {
            background: var(--gradient-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="%23ffffff" opacity="0.05"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }
        
        .login-container {
            position: relative;
            z-index: 1;
        }
        
        .login-card {
            background: var(--gradient-card);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            animation: fadeIn 0.8s ease-out;
        }
        
        .login-header {
            background: var(--gradient-primary);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            animation: shimmer 3s infinite;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .logo-svg {
            width: 60px;
            height: auto;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }
        
        .system-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .system-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .form-floating {
            position: relative;
        }
        
        .form-floating .form-control {
            height: 3.5rem;
            padding: 1rem 0.75rem 0.25rem 0.75rem;
        }
        
        .form-floating label {
            padding: 1rem 0.75rem;
            color: var(--gray-500);
            font-weight: 500;
        }
        
        .btn-login {
            background: var(--gradient-primary);
            border: none;
            border-radius: var(--radius-lg);
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            filter: brightness(1.05);
        }
        
        .info-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-lg);
        }
        
        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }
        
        .floating-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-circle:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-circle:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 70%;
            right: 10%;
            animation-delay: 2s;
        }
        
        .floating-circle:nth-child(3) {
            width: 60px;
            height: 60px;
            top: 30%;
            right: 20%;
            animation-delay: 4s;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(30px) scale(0.95); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
        }
        
        .logo-img {
            max-width: 200px;
            height: auto;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
            transition: transform 0.3s ease;
        }
        
        .logo-img:hover {
            transform: scale(1.05);
        }
        
        @media (max-width: 768px) {
            .logo-img {
                max-width: 150px;
            }
        }
        
        @media (max-width: 480px) {
            .logo-img {
                max-width: 120px;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Background Elements -->
    <div class="floating-elements">
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
    </div>
    
    <div class="container login-container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card border-0">
                    <div class="card-header login-header text-center py-5">
                        <div class="logo-container">
                            <img src="assets/images/Logo_ips.png" alt="Logo IPS" class="logo-img" style="max-width: 200px; height: auto; margin-bottom: 10px;">
                        </div>
                        <h1 class="system-title" style="color: white; margin-top: 0;">IPS Fretes</h1>
                        <p class="system-subtitle">Faça login para continuar</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" class="fade-in">
                            <div class="form-floating mb-4">
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                                       placeholder="Digite seu email" required>
                                <label for="email">
                                    <i class="fas fa-envelope me-2"></i>
                                    Email
                                </label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Digite sua senha" required>
                                <label for="password">
                                    <i class="fas fa-lock me-2"></i>
                                    Senha
                                </label>
                            </div>
                            
                            <div class="d-grid mb-4">
                                <button type="submit" class="btn btn-primary btn-login text-white">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Entrar no Sistema
                                    <span class="loading-spinner d-none ms-2"></span>
                                </button>
                            </div>
                        </form>
                        
                        <!-- Informações de Teste -->
                        <div class="alert alert-info border-0" style="background: rgba(23, 162, 184, 0.1); border-left: 4px solid #17a2b8 !important;">
                            <h6 class="alert-heading mb-2">
                                <i class="fas fa-info-circle me-2"></i>
                                Dados para Teste
                            </h6>
                            <div class="row">
                                <div class="col-12">
                                    <small class="text-muted">
                                        <strong>Email:</strong> admin@sistema.com<br>
                                        <strong>Senha:</strong> admin123
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Informações do Sistema -->
                <div class="text-center mt-4">
                    <div class="card border-0" style="background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px);">
                        <div class="card-body py-3">
                            <small class="text-white">
                                <i class="fas fa-server me-1"></i>
                                Sistema inicializado com Docker
                                <br>
                                <i class="fas fa-database me-1"></i>
                                MySQL 8.0 | PHP 8.2 | Apache 2.4
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Animação de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 200);
            });
            
            // Animação dos elementos flutuantes
            const floatingElements = document.querySelectorAll('.floating-element');
            floatingElements.forEach(el => {
                const randomDelay = Math.random() * 2;
                el.style.animationDelay = randomDelay + 's';
            });
        });
        
        // Animação do botão de login
        document.querySelector('form').addEventListener('submit', function(e) {
            const button = this.querySelector('.btn-login');
            const spinner = button.querySelector('.loading-spinner');
            
            button.disabled = true;
            spinner.classList.remove('d-none');
            
            // Simular carregamento por 1 segundo antes de enviar
            setTimeout(() => {
                // O formulário será enviado normalmente
            }, 1000);
        });
        
        // Efeito de hover nos info-items
        document.querySelectorAll('.info-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>