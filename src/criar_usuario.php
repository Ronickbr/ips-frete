<?php
/**
 * Página de Criação de Usuários
 * Acesso restrito a administradores
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

// Redirecionar se não estiver logado ou não for administrador
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit();
}

if (!isAdmin()) {
    header('Location: /index.php?error=acesso_negado');
    exit();
}

// Variáveis para mensagens
$success_message = '';
$error_message = '';

// Processar formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    $tipo = $_POST['tipo'] ?? 'operacional';
    
    // Validações
    $errors = [];
    
    if (empty($nome)) {
        $errors[] = 'Nome é obrigatório';
    }
    
    if (empty($email)) {
        $errors[] = 'Email é obrigatório';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email inválido';
    }
    
    if (empty($senha)) {
        $errors[] = 'Senha é obrigatória';
    } elseif (strlen($senha) < 6) {
        $errors[] = 'Senha deve ter pelo menos 6 caracteres';
    }
    
    if ($senha !== $confirmar_senha) {
        $errors[] = 'Senhas não coincidem';
    }
    
    if (!in_array($tipo, ['operacional', 'administrador'])) {
        $errors[] = 'Tipo de usuário inválido';
    }
    
    // Verificar se email já existe
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $errors[] = 'Email já está em uso';
            }
        } catch (Exception $e) {
            $errors[] = 'Erro ao verificar email: ' . $e->getMessage();
        }
    }
    
    // Inserir usuário se não houver erros
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            $password_hash = password_hash($senha, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, password_hash, tipo) VALUES (?, ?, ?, ?)");
            $result = $stmt->execute([$nome, $email, $password_hash, $tipo]);
            
            if ($result) {
                $success_message = 'Usuário criado com sucesso!';
                // Limpar campos do formulário
                $nome = $email = $senha = $confirmar_senha = '';
                $tipo = 'operacional';
            } else {
                $error_message = 'Erro ao criar usuário';
            }
        } catch (Exception $e) {
            $error_message = 'Erro ao criar usuário: ' . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Usuário - Sistema de Controle de Fretes</title>
    
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
                        <h1 class="h2 text-gradient mb-1">Criar Usuário</h1>
                        <p class="text-muted mb-0">Cadastrar novo usuário no sistema</p>
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
                
                <!-- Mensagens -->
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Formulário de Criação de Usuário -->
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="modern-card fade-in">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user-plus me-2"></i>
                                    Dados do Novo Usuário
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="formCriarUsuario" novalidate>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="nome" class="form-label">Nome Completo *</label>
                                            <input type="text" class="form-control" id="nome" name="nome" 
                                                   value="<?php echo htmlspecialchars($nome ?? ''); ?>" required>
                                            <div class="invalid-feedback">
                                                Nome é obrigatório
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email *</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                                            <div class="invalid-feedback">
                                                Email válido é obrigatório
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="senha" class="form-label">Senha *</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="senha" name="senha" 
                                                       minlength="6" required>
                                                <button class="btn btn-outline-secondary" type="button" id="toggleSenha">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <div class="invalid-feedback">
                                                Senha deve ter pelo menos 6 caracteres
                                            </div>
                                            <small class="form-text text-muted">Mínimo de 6 caracteres</small>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="confirmar_senha" class="form-label">Confirmar Senha *</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" 
                                                       minlength="6" required>
                                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmarSenha">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <div class="invalid-feedback">
                                                Senhas devem coincidir
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="tipo" class="form-label">Tipo de Usuário *</label>
                                            <select class="form-select" id="tipo" name="tipo" required>
                                                <option value="operacional" <?php echo (isset($tipo) && $tipo === 'operacional') ? 'selected' : ''; ?>>
                                                    Operacional
                                                </option>
                                                <option value="administrador" <?php echo (isset($tipo) && $tipo === 'administrador') ? 'selected' : ''; ?>>
                                                    Administrador
                                                </option>
                                            </select>
                                            <div class="invalid-feedback">
                                                Selecione um tipo de usuário
                                            </div>
                                            <small class="form-text text-muted">
                                                Administradores têm acesso completo ao sistema
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <a href="index.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>
                                            Voltar
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-user-plus me-2"></i>
                                            Criar Usuário
                                        </button>
                                    </div>
                                </form>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Animação fade-in
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Toggle para mostrar/ocultar senha
            const toggleSenha = document.getElementById('toggleSenha');
            const senhaInput = document.getElementById('senha');
            
            toggleSenha.addEventListener('click', function() {
                const type = senhaInput.getAttribute('type') === 'password' ? 'text' : 'password';
                senhaInput.setAttribute('type', type);
                
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
            
            // Toggle para confirmar senha
            const toggleConfirmarSenha = document.getElementById('toggleConfirmarSenha');
            const confirmarSenhaInput = document.getElementById('confirmar_senha');
            
            toggleConfirmarSenha.addEventListener('click', function() {
                const type = confirmarSenhaInput.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmarSenhaInput.setAttribute('type', type);
                
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
            
            // Validação do formulário
            const form = document.getElementById('formCriarUsuario');
            
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                // Verificar se senhas coincidem
                const senha = document.getElementById('senha').value;
                const confirmarSenha = document.getElementById('confirmar_senha').value;
                
                if (senha !== confirmarSenha) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    const confirmarSenhaInput = document.getElementById('confirmar_senha');
                    confirmarSenhaInput.setCustomValidity('Senhas não coincidem');
                    confirmarSenhaInput.classList.add('is-invalid');
                } else {
                    const confirmarSenhaInput = document.getElementById('confirmar_senha');
                    confirmarSenhaInput.setCustomValidity('');
                    confirmarSenhaInput.classList.remove('is-invalid');
                }
                
                form.classList.add('was-validated');
            });
            
            // Validação em tempo real para confirmação de senha
            const confirmarSenhaInput = document.getElementById('confirmar_senha');
            const senhaInput = document.getElementById('senha');
            
            function validatePasswordMatch() {
                const senha = senhaInput.value;
                const confirmarSenha = confirmarSenhaInput.value;
                
                if (confirmarSenha && senha !== confirmarSenha) {
                    confirmarSenhaInput.setCustomValidity('Senhas não coincidem');
                    confirmarSenhaInput.classList.add('is-invalid');
                } else {
                    confirmarSenhaInput.setCustomValidity('');
                    confirmarSenhaInput.classList.remove('is-invalid');
                }
            }
            
            senhaInput.addEventListener('input', validatePasswordMatch);
            confirmarSenhaInput.addEventListener('input', validatePasswordMatch);
        });
    </script>
</body>
</html>