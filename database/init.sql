-- Script de inicialização do banco de dados Sistema de Controle de Fretes
-- Criado automaticamente pelo Docker

-- Usar o banco de dados
USE sistema_fretes;

-- Tabela de Usuários
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('operacional', 'administrador') DEFAULT 'operacional',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Criar índices para usuarios
CREATE INDEX idx_usuarios_email ON usuarios(email);

-- Tabela de Pedidos
CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_pedido VARCHAR(50) UNIQUE NOT NULL,
    numero_picking VARCHAR(50) NOT NULL,
    cliente_nome VARCHAR(255) NOT NULL,
    origem VARCHAR(255) NOT NULL,
    destino VARCHAR(255) NOT NULL,
    descricao TEXT,
    status ENUM('pendente', 'em_transito', 'entregue', 'cancelado') DEFAULT 'pendente',
    data_pedido DATE NOT NULL,
    cotacao_id INT,
    status_conferencia ENUM('pendente', 'aprovado', 'rejeitado') DEFAULT 'pendente',
    observacoes_conferencia TEXT,
    data_conferencia TIMESTAMP NULL,
    usuario_conferencia INT,
    usuario_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (usuario_conferencia) REFERENCES usuarios(id),
    FOREIGN KEY (cotacao_id) REFERENCES cotacoes(id)
);

-- Criar índices para pedidos
CREATE INDEX idx_pedidos_numero_pedido ON pedidos(numero_pedido);
CREATE INDEX idx_pedidos_numero_picking ON pedidos(numero_picking);
CREATE INDEX idx_pedidos_usuario_id ON pedidos(usuario_id);

-- Tabela de Medidas
CREATE TABLE medidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT,
    comprimento DECIMAL(10,2) NOT NULL,
    altura DECIMAL(10,2) NOT NULL,
    largura DECIMAL(10,2) NOT NULL,
    quantidade_volumes INT NOT NULL DEFAULT 1,
    cubagem_m3 DECIMAL(10,4) GENERATED ALWAYS AS ((comprimento * altura * largura * quantidade_volumes) / 1000000) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
);

-- Criar índices para medidas
CREATE INDEX idx_medidas_pedido_id ON medidas(pedido_id);

-- Tabela de Transportadoras
CREATE TABLE transportadoras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cnpj VARCHAR(18),
    telefone VARCHAR(20),
    email VARCHAR(255),
    endereco TEXT,
    peso_ate_30kg DECIMAL(10,2),
    peso_ate_100kg DECIMAL(10,2),
    peso_ate_150kg DECIMAL(10,2),
    peso_ate_200kg DECIMAL(10,2),
    frete_por_tonelada DECIMAL(10,2),
    frete_minimo DECIMAL(10,2),
    frete_valor DECIMAL(5,2),
    pedagio_peso_cubico DECIMAL(10,2),
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Cotações
CREATE TABLE cotacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT,
    transportadora_id INT,
    origem VARCHAR(255) NOT NULL,
    destino VARCHAR(255) NOT NULL,
    peso DECIMAL(10,2) NOT NULL,
    valor_frete DECIMAL(12,2) NOT NULL,
    prazo_entrega INT,
    observacoes TEXT,
    status ENUM('pendente', 'aprovada', 'rejeitada') DEFAULT 'pendente',
    data_cotacao DATE NOT NULL,
    numero_nota_fiscal VARCHAR(50),
    valor_nota_fiscal DECIMAL(12,2),
    peso_nota_fiscal DECIMAL(10,2),
    valor_frete_calculado DECIMAL(12,2),
    cubagem_total DECIMAL(10,4),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
    FOREIGN KEY (transportadora_id) REFERENCES transportadoras(id)
);

-- Criar índices para cotacoes
CREATE INDEX idx_cotacoes_pedido_id ON cotacoes(pedido_id);
CREATE INDEX idx_cotacoes_numero_nota ON cotacoes(numero_nota_fiscal);
CREATE INDEX idx_cotacoes_transportadora_id ON cotacoes(transportadora_id);

-- Tabela de Faturas
CREATE TABLE faturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cotacao_id INT,
    numero_nota_fiscal VARCHAR(50) NOT NULL,
    valor_frete_faturado DECIMAL(12,2) NOT NULL,
    valor_frete_cotado DECIMAL(12,2),
    diferenca DECIMAL(12,2) GENERATED ALWAYS AS (valor_frete_faturado - valor_frete_cotado) STORED,
    status ENUM('pendente', 'conferido', 'divergente') DEFAULT 'pendente',
    arquivo_original VARCHAR(255),
    data_importacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cotacao_id) REFERENCES cotacoes(id)
);

-- Criar índices para faturas
CREATE INDEX idx_faturas_numero_nota ON faturas(numero_nota_fiscal);
CREATE INDEX idx_faturas_status ON faturas(status);

-- DADOS INICIAIS

-- Inserir usuário administrador padrão
INSERT INTO usuarios (email, password_hash, nome, tipo) VALUES 
('admin@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'administrador');

-- Inserir transportadoras exemplo
INSERT INTO transportadoras (nome, cnpj, telefone, email, endereco, peso_ate_30kg, peso_ate_100kg, peso_ate_150kg, peso_ate_200kg, frete_por_tonelada, frete_minimo, frete_valor, pedagio_peso_cubico) VALUES 
('Transportadora Exemplo', '12.345.678/0001-90', '(11) 1234-5678', 'contato@exemplo.com', 'Rua das Flores, 123 - São Paulo/SP', 15.00, 25.00, 35.00, 45.00, 120.00, 50.00, 2.5, 8.00),
('Expresso Rápido', '98.765.432/0001-10', '(21) 9876-5432', 'vendas@expressorapido.com', 'Av. Principal, 456 - Rio de Janeiro/RJ', 18.00, 28.00, 38.00, 48.00, 130.00, 60.00, 3.0, 9.00),
('Logística Total', '11.222.333/0001-44', '(31) 5555-1234', 'comercial@logisticatotal.com', 'Rua do Comércio, 789 - Belo Horizonte/MG', 12.00, 22.00, 32.00, 42.00, 110.00, 45.00, 2.0, 7.50);

-- Inserir pedido exemplo
INSERT INTO pedidos (numero_pedido, numero_picking, cliente_nome, origem, destino, descricao, data_pedido, usuario_id) VALUES 
('PED-2024-001', 'PICK-001', 'Cliente Exemplo Ltda', 'São Paulo - SP', 'Rio de Janeiro - RJ', 'Produtos diversos para entrega', '2024-01-15', 1),
('PED-2024-002', 'PICK-002', 'Empresa ABC', 'Belo Horizonte - MG', 'Salvador - BA', 'Equipamentos eletrônicos', '2024-01-16', 1);

-- Inserir medidas exemplo
INSERT INTO medidas (pedido_id, comprimento, altura, largura, quantidade_volumes) VALUES 
(1, 50.00, 30.00, 40.00, 2),
(1, 60.00, 35.00, 45.00, 1),
(2, 40.00, 25.00, 35.00, 3);

-- Inserir cotação exemplo
INSERT INTO cotacoes (pedido_id, transportadora_id, origem, destino, peso, valor_frete, prazo_entrega, data_cotacao, status) VALUES 
(1, 1, 'São Paulo - SP', 'Rio de Janeiro - RJ', 25.50, 85.00, 3, '2024-01-15', 'aprovada'),
(1, 2, 'São Paulo - SP', 'Rio de Janeiro - RJ', 25.50, 92.00, 2, '2024-01-15', 'pendente'),
(2, 1, 'Belo Horizonte - MG', 'Salvador - BA', 18.30, 120.00, 5, '2024-01-16', 'pendente');

-- Mensagem de sucesso
SELECT 'Banco de dados Sistema de Fretes inicializado com sucesso!' as status;