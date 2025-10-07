<?php
/**
 * Configuração de Conexão com Banco de Dados - Naturallis
 * Arquivo: config/database.php
 */

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'naturallis_db');
define('DB_USER', 'root'); // Altere conforme sua configuração
define('DB_PASS', ''); // Altere conforme sua configuração
define('DB_CHARSET', 'utf8mb4');

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $charset = DB_CHARSET;
    public $conn;

    /**
     * Conecta ao banco de dados usando PDO
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            error_log("Erro de conexão PDO: " . $exception->getMessage());
            throw new Exception("Falha na conexão com o banco de dados");
        }

        return $this->conn;
    }

    /**
     * Testa a conexão com o banco de dados
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            if ($conn) {
                return [
                    'success' => true,
                    'message' => 'Conexão realizada com sucesso!'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro na conexão: ' . $e->getMessage()
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Não foi possível estabelecer conexão'
        ];
    }
}

/**
 * Função auxiliar para obter conexão rápida
 */
function getDBConnection() {
    $database = new Database();
    return $database->getConnection();
}

/**
 * Função para hash de senha
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Função para verificar senha
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Função para gerar número de pedido único
 */
function generateOrderNumber() {
    return 'NAT' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Função para sanitizar entrada de dados
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Função para verificar se o usuário é admin
 * Compatível com diferentes convenções de sessão
 */
function isAdmin() {
    // Verificar a convenção principal (usuario_role)
    if (isset($_SESSION['usuario_role']) && $_SESSION['usuario_role'] === 'admin') {
        return true;
    }
    
    // Verificar convenção alternativa (is_admin)
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
        return true;
    }
    
    return false;
}

/**
 * Função para verificar se o usuário está logado
 * Compatível com diferentes convenções de sessão
 */
function isLoggedIn() {
    // Verificar a convenção principal (usuario_id)
    if (isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id'])) {
        return true;
    }
    
    // Verificar convenção alternativa (user_id)
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }
    
    return false;
}
?>