<?php
/**
 * Konfiguracja bazy danych
 * Ekipowo.pl - System zarządzania subdomenami
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'X';
    private $username = 'X';
    private $password = 'X';
    private $charset = 'utf8mb4';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw new Exception("Błąd połączenia z bazą danych");
        }
        
        return $this->conn;
    }
    
    public function closeConnection() {
        $this->conn = null;
    }
}

/**
 * Klasa pomocnicza do zarządzania bazą danych
 */
class DatabaseManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function getConnection() {
        return $this->db;
    }
    
    /**
     * Wykonuje zapytanie SELECT i zwraca wyniki
     */
    public function select($query, $params = []) {
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Select error: " . $e->getMessage());
            throw new Exception("Błąd bazy danych podczas pobierania danych: " . $e->getMessage());
        }
    }
    
    /**
     * Wykonuje zapytanie SELECT i zwraca jeden wiersz
     */
    public function selectOne($query, $params = []) {
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("SelectOne error: " . $e->getMessage());
            throw new Exception("Błąd bazy danych podczas pobierania danych: " . $e->getMessage());
        }
    }
    
    /**
     * Wykonuje zapytanie INSERT/UPDATE/DELETE
     */
    public function execute($query, $params = []) {
        try {
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute($params);
            return $result;
        } catch(PDOException $e) {
            error_log("Execute error: " . $e->getMessage());
            throw new Exception("Błąd bazy danych: " . $e->getMessage());
        }
    }
    
    /**
     * Zwraca ID ostatnio wstawionego rekordu
     */
    public function lastInsertId() {
        return $this->db->lastInsertId();
    }
    
    /**
     * Rozpoczyna transakcję
     */
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    /**
     * Zatwierdza transakcję
     */
    public function commit() {
        return $this->db->commit();
    }
    
    /**
     * Wycofuje transakcję
     */
    public function rollback() {
        return $this->db->rollback();
    }
}
?>