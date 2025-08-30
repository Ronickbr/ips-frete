# Sistema de Controle de Fretes

Sistema web para controle interno de fretes desenvolvido com PHP, Bootstrap, JavaScript e MySQL, executando em ambiente Docker.

## 🚀 Tecnologias Utilizadas

- **Backend**: PHP 8.2 com Apache 2.4
- **Frontend**: Bootstrap 5, JavaScript ES6, Font Awesome
- **Banco de Dados**: MySQL 8.0
- **Containerização**: Docker e Docker Compose
- **Administração DB**: phpMyAdmin

## 📋 Pré-requisitos

- Docker Desktop instalado
- Docker Compose
- Porta 8080 (aplicação), 3306 (MySQL) e 8081 (phpMyAdmin) disponíveis

## 🛠️ Instalação e Configuração

### 1. Clone ou baixe o projeto
```bash
cd "d:\Sites\Sistema Willian"
```

### 2. Construir e iniciar os containers
```bash
docker-compose up --build -d
```

### 3. Verificar se os containers estão rodando
```bash
docker-compose ps
```

### 4. Acessar a aplicação
- **Sistema**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081
  - Servidor: `db`
  - Usuário: `root`
  - Senha: `root123`

## 🗄️ Banco de Dados

O banco de dados `sistema_fretes` é criado automaticamente com as seguintes tabelas:

- **usuarios**: Controle de acesso ao sistema
- **pedidos**: Pedidos de frete
- **medidas**: Dimensões e peso dos pedidos
- **transportadoras**: Empresas de transporte
- **cotacoes**: Cotações de frete
- **faturas**: Controle de faturas

### Dados Iniciais

O sistema já vem com dados de exemplo:
- **Usuário Admin**: admin@sistema.com / senha: admin123
- **Usuário Operador**: operador@sistema.com / senha: operador123
- Transportadoras de exemplo
- Pedidos e cotações de teste

## 📁 Estrutura do Projeto

```
Sistema Willian/
├── docker-compose.yml          # Configuração dos containers
├── Dockerfile                  # Imagem PHP personalizada
├── config/
│   ├── apache.conf            # Configuração Apache
│   └── php.ini               # Configuração PHP
├── database/
│   └── init.sql              # Script de inicialização do banco
├── src/                      # Código fonte da aplicação
│   ├── index.php            # Página principal
│   ├── login.php            # Página de login
│   ├── logout.php           # Script de logout
│   ├── config/
│   │   └── database.php     # Configuração de conexão
│   └── assets/
│       ├── css/
│       │   └── style.css    # Estilos personalizados
│       └── js/
│           └── main.js      # JavaScript principal
└── README.md                # Este arquivo
```

## 🔧 Comandos Úteis

### Parar os containers
```bash
docker-compose down
```

### Reiniciar os containers
```bash
docker-compose restart
```

### Ver logs da aplicação
```bash
docker-compose logs web
```

### Ver logs do MySQL
```bash
docker-compose logs db
```

### Acessar o container da aplicação
```bash
docker-compose exec web bash
```

### Acessar o MySQL via linha de comando
```bash
docker-compose exec db mysql -u root -p
```

### Backup do banco de dados
```bash
docker-compose exec db mysqldump -u root -p sistema_fretes > backup.sql
```

### Restaurar backup do banco de dados
```bash
docker-compose exec -T db mysql -u root -p sistema_fretes < backup.sql
```

## 🌐 Funcionalidades do Sistema

### Dashboard
- Métricas em tempo real
- Visão geral dos pedidos
- Status das cotações
- Controle de faturas

### Gestão de Pedidos
- Cadastro de novos pedidos
- Controle de status
- Histórico de alterações

### Cotações
- Solicitação de cotações
- Comparação de preços
- Aprovação de cotações

### Transportadoras
- Cadastro de transportadoras
- Controle de documentação
- Avaliação de desempenho

### Relatórios
- Relatórios de custos
- Análise de performance
- Exportação de dados

### Conferência de Faturas
- Validação de faturas
- Controle de pagamentos
- Conciliação financeira

## 🔐 Segurança

- Autenticação por sessão
- Senhas criptografadas com password_hash()
- Proteção contra SQL Injection (PDO)
- Validação de dados de entrada
- Controle de acesso por perfil

## 🐛 Troubleshooting

### Erro de conexão com o banco
1. Verificar se o container MySQL está rodando
2. Aguardar alguns segundos para o MySQL inicializar completamente
3. Verificar logs: `docker-compose logs db`

### Porta já em uso
1. Alterar as portas no `docker-compose.yml`
2. Ou parar o serviço que está usando a porta

### Permissões de arquivo
1. No Windows, verificar se o Docker tem acesso à pasta
2. Executar Docker Desktop como administrador se necessário

## 📞 Suporte

Para suporte técnico ou dúvidas sobre o sistema:

1. Verificar os logs dos containers
2. Consultar a documentação do Docker
3. Verificar se todas as portas estão disponíveis

## 📝 Notas de Desenvolvimento

- O sistema utiliza PDO para conexão com MySQL
- Bootstrap 5 para interface responsiva
- JavaScript vanilla para interações
- Estrutura MVC simplificada
- Preparado para expansão de funcionalidades

---

**Desenvolvido para controle interno de fretes**  
*Ambiente Docker - PHP 8.2 + MySQL 8.0 + Apache 2.4*