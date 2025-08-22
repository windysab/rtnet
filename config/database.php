<?php
/**
 * Konfigurasi Database untuk Aplikasi RT/RW Net
 * 
 * @author RT/RW Net System
 * @version 1.0
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'rtnet_db';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    public $conn;
    
    public function __construct() {
        $this->getConnection();
    }
    
    /**
     * Koneksi ke database
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            throw new Exception("Database connection failed: " . $exception->getMessage());
        }
        
        return $this->conn;
    }
    
    /**
     * Tutup koneksi database
     */
    public function closeConnection() {
        $this->conn = null;
    }
    
    /**
     * Eksekusi query dengan prepared statement
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            throw new Exception("Database query error: " . $e->getMessage());
        }
    }
    
    /**
     * Insert data dan return last insert id
     */
    public function insert($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $this->conn->lastInsertId();
        } catch(Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Update data dan return affected rows
     */
    public function update($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->rowCount();
        } catch(Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Delete data dan return affected rows
     */
    public function delete($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->rowCount();
        } catch(Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Select data dan return array
     */
    public function select($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll();
        } catch(Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Select single row
     */
    public function selectOne($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetch();
        } catch(Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->conn->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->conn->rollback();
    }
}

// Konfigurasi tambahan
define('DB_HOST', 'localhost');
define('DB_NAME', 'rtnet_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting untuk development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>