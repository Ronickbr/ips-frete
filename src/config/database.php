<?php
/**
 * Configuração de Conexão com o Banco de Dados MySQL
 * Sistema de Controle de Fretes
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;
    
    public function __construct() {
        // Configurações do banco de dados via variáveis de ambiente
        $this->host = $_ENV['DB_HOST'] ?? 'db';
        $this->db_name = $_ENV['DB_NAME'] ?? 'sistema_fretes';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? 'root123';
    }
    
    /**
     * Estabelece conexão com o banco de dados
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
            // Configurar PDO para lançar exceções em caso de erro
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Configurar para retornar arrays associativos por padrão
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Configurar charset
            $this->conn->exec("set names utf8mb4");
            
        } catch(PDOException $exception) {
            error_log("Erro de conexão: " . $exception->getMessage());
            throw new Exception("Erro ao conectar com o banco de dados: " . $exception->getMessage());
        }
        
        return $this->conn;
    }
    
    /**
     * Fecha a conexão com o banco de dados
     */
    public function closeConnection() {
        $this->conn = null;
    }
    
    /**
     * Testa a conexão com o banco de dados
     * @return bool
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            if ($conn) {
                $stmt = $conn->query("SELECT 1");
                return $stmt !== false;
            }
            return false;
        } catch (Exception $e) {
            error_log("Teste de conexão falhou: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Função auxiliar para obter uma instância da conexão
 * @return PDO
 */
function getDBConnection() {
    $database = new Database();
    return $database->getConnection();
}

/**
 * Função auxiliar para testar a conexão
 * @return bool
 */
function testDBConnection() {
    $database = new Database();
    return $database->testConnection();
}
?>