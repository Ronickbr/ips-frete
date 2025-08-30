<?php
session_start();
require_once 'config/database.php';

// Verificar se o usuário está logado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Verificar se o usuário é administrador
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'administrador';
}

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Processar upload de arquivo
$mensagem = '';
$tipo_mensagem = '';
$resultados_comparacao = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo_fatura'])) {
    $arquivo = $_FILES['arquivo_fatura'];
    
    // Validar arquivo
    if ($arquivo['error'] === UPLOAD_ERR_OK) {
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        
        if (in_array($extensao, ['csv', 'xlsx', 'xls'])) {
            try {
                $resultados_comparacao = processarArquivoFatura($pdo, $arquivo);
                $mensagem = 'Arquivo processado com sucesso! ' . count($resultados_comparacao) . ' registros analisados.';
                $tipo_mensagem = 'success';
            } catch (Exception $e) {
                $mensagem = 'Erro ao processar arquivo: ' . $e->getMessage();
                $tipo_mensagem = 'error';
            }
        } else {
            $mensagem = 'Formato de arquivo não suportado. Use CSV, XLS ou XLSX.';
            $tipo_mensagem = 'error';
        }
    } else {
        $mensagem = 'Erro no upload do arquivo.';
        $tipo_mensagem = 'error';
    }
}

// Função para processar arquivo de fatura
function processarArquivoFatura($pdo, $arquivo) {
    $resultados = [];
    $dados_fatura = [];
    
    // Ler arquivo CSV
    if (($handle = fopen($arquivo['tmp_name'], 'r')) !== FALSE) {
        $header = fgetcsv($handle, 1000, ','); // Primeira linha como cabeçalho
        
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            if (count($data) >= 2) {
                // Assumindo que as colunas são: número_nf, valor_frete
                $dados_fatura[] = [
                    'numero_nf' => trim($data[0]),
                    'valor_frete' => floatval(str_replace([',', 'R$', ' '], ['', '', ''], $data[1]))
                ];
            }
        }
        fclose($handle);
    }
    
    // Comparar com dados do sistema
    foreach ($dados_fatura as $item_fatura) {
        if (empty($item_fatura['numero_nf'])) continue;
        
        // Buscar no sistema
        $sql = "SELECT c.id, c.numero_nf, c.valor_frete_calculado as valor_frete, 
                       p.numero as numero_pedido, t.nome as transportadora
                FROM cotacoes c
                LEFT JOIN pedidos p ON c.pedido_id = p.id
                LEFT JOIN transportadoras t ON c.transportadora_id = t.id
                WHERE c.numero_nf = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$item_fatura['numero_nf']]);
        $cotacao_sistema = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cotacao_sistema) {
            $diferenca_valor = $item_fatura['valor_frete'] - $cotacao_sistema['valor_frete'];
            $percentual_diferenca = $cotacao_sistema['valor_frete'] > 0 ? 
                                  ($diferenca_valor / $cotacao_sistema['valor_frete']) * 100 : 0;
            
            $status = 'ok';
            if (abs($percentual_diferenca) > 5) {
                $status = 'divergencia';
            }
            
            $resultados[] = [
                'numero_nf' => $item_fatura['numero_nf'],
                'numero_pedido' => $cotacao_sistema['numero_pedido'],
                'transportadora' => $cotacao_sistema['transportadora'],
                'valor_fatura' => $item_fatura['valor_frete'],
                'valor_sistema' => $cotacao_sistema['valor_frete'],
                'diferenca' => $diferenca_valor,
                'percentual_diferenca' => $percentual_diferenca,
                'status' => $status
            ];
        } else {
            $resultados[] = [
                'numero_nf' => $item_fatura['numero_nf'],
                'numero_pedido' => 'N/A',
                'transportadora' => 'N/A',
                'valor_fatura' => $item_fatura['valor_frete'],
                'valor_sistema' => 0,
                'diferenca' => $item_fatura['valor_frete'],
                'percentual_diferenca' => 0,
                'status' => 'nao_encontrado'
            ];
        }
    }
    
    return $resultados;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conferência de Faturas - Sistema de Cotações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/modern-theme.css" rel="stylesheet">
    <style>
        .status-ok { background-color: #d4edda; }
        .status-divergencia { background-color: #f8d7da; }
        .status-nao-encontrado { background-color: #fff3cd; }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .upload-area.dragover {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
    </style>
</head>
<body class="modern-body">
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 ms-sm-auto px-md-4">
                <div class="modern-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2"><i class="fas fa-check-circle text-primary me-2"></i>Conferência de Faturas</h1>
                        <p class="text-muted mb-0">Compare faturas com dados do sistema</p>
                    </div>
                </div>

                <?php if ($mensagem): ?>
                    <div class="alert alert-<?php echo $tipo_mensagem === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mensagem); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Área de Upload -->
                <div class="modern-card fade-in mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Importar Fatura</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <h5>Arraste o arquivo aqui ou clique para selecionar</h5>
                                <p class="text-muted">Formatos aceitos: CSV, XLS, XLSX</p>
                                <p class="text-muted small">Formato esperado: Número da NF, Valor do Frete</p>
                                <input type="file" name="arquivo_fatura" id="arquivo_fatura" class="d-none" accept=".csv,.xls,.xlsx" required>
                                <button type="button" class="modern-btn" onclick="document.getElementById('arquivo_fatura').click()">
                                    <i class="fas fa-folder-open me-2"></i>Selecionar Arquivo
                                </button>
                            </div>
                            <div class="mt-3 d-none" id="fileInfo">
                                <div class="alert alert-info">
                                    <i class="fas fa-file me-2"></i>
                                    <span id="fileName"></span>
                                    <button type="submit" class="modern-btn success ms-3">
                                        <i class="fas fa-check me-2"></i>Processar Arquivo
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Resultados da Comparação -->
                <?php if (!empty($resultados_comparacao)): ?>
                    <div class="modern-card fade-in">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-balance-scale me-2"></i>Resultados da Comparação</h5>
                        </div>
                        <div class="card-body">
                            <!-- Resumo -->
                            <?php
                            $total = count($resultados_comparacao);
                            $ok = count(array_filter($resultados_comparacao, fn($r) => $r['status'] === 'ok'));
                            $divergencias = count(array_filter($resultados_comparacao, fn($r) => $r['status'] === 'divergencia'));
                            $nao_encontrados = count(array_filter($resultados_comparacao, fn($r) => $r['status'] === 'nao_encontrado'));
                            ?>
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="metric-card fade-in">
                                        <div class="metric-icon">
                                            <i class="fas fa-file-invoice"></i>
                                        </div>
                                        <div class="metric-content">
                                            <div class="metric-label">Total Analisados</div>
                                            <div class="metric-value"><?php echo $total; ?></div>
                                            <div class="metric-change">Faturas processadas</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="metric-card fade-in">
                                        <div class="metric-icon text-success">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="metric-content">
                                            <div class="metric-label">Conferem</div>
                                            <div class="metric-value"><?php echo $ok; ?></div>
                                            <div class="metric-change text-success">Valores corretos</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="metric-card fade-in">
                                        <div class="metric-icon text-danger">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <div class="metric-content">
                                            <div class="metric-label">Divergências</div>
                                            <div class="metric-value"><?php echo $divergencias; ?></div>
                                            <div class="metric-change text-danger">Requer atenção</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="metric-card fade-in">
                                        <div class="metric-icon text-warning">
                                            <i class="fas fa-question-circle"></i>
                                        </div>
                                        <div class="metric-content">
                                            <div class="metric-label">Não Encontrados</div>
                                            <div class="metric-value"><?php echo $nao_encontrados; ?></div>
                                            <div class="metric-change text-warning">Verificar sistema</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabela de Resultados -->
                            <div class="table-responsive">
                                <table class="table modern-table">
                                    <thead>
                                        <tr>
                                            <th>Número NF</th>
                                            <th>Pedido</th>
                                            <th>Transportadora</th>
                                            <th>Valor Fatura</th>
                                            <th>Valor Sistema</th>
                                            <th>Diferença</th>
                                            <th>%</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resultados_comparacao as $resultado): ?>
                                            <tr class="status-<?php echo $resultado['status']; ?>">
                                                <td><?php echo htmlspecialchars($resultado['numero_nf']); ?></td>
                                                <td><?php echo htmlspecialchars($resultado['numero_pedido']); ?></td>
                                                <td><?php echo htmlspecialchars($resultado['transportadora']); ?></td>
                                                <td>R$ <?php echo number_format($resultado['valor_fatura'], 2, ',', '.'); ?></td>
                                                <td>R$ <?php echo number_format($resultado['valor_sistema'], 2, ',', '.'); ?></td>
                                                <td class="<?php echo $resultado['diferenca'] > 0 ? 'text-danger' : ($resultado['diferenca'] < 0 ? 'text-success' : ''); ?>">
                                                    R$ <?php echo number_format($resultado['diferenca'], 2, ',', '.'); ?>
                                                </td>
                                                <td class="<?php echo abs($resultado['percentual_diferenca']) > 5 ? 'text-danger' : ''; ?>">
                                                    <?php echo number_format($resultado['percentual_diferenca'], 1, ',', '.'); ?>%
                                                </td>
                                                <td>
                                                    <?php
                                                    switch ($resultado['status']) {
                                                        case 'ok':
                                                            echo '<span class="badge bg-success"><i class="fas fa-check"></i> OK</span>';
                                                            break;
                                                        case 'divergencia':
                                                            echo '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i> Divergência</span>';
                                                            break;
                                                        case 'nao_encontrado':
                                                            echo '<span class="badge bg-warning"><i class="fas fa-question"></i> Não Encontrado</span>';
                                                            break;
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Drag and drop functionality
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('arquivo_fatura');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                showFileInfo(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                showFileInfo(e.target.files[0]);
            }
        });

        function showFileInfo(file) {
            fileName.textContent = file.name;
            fileInfo.classList.remove('d-none');
        }
        
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
    </script>
</body>
</html>