<?php
/**
 * Database Connection Class
 * 
 * Provides PDO database connection for CargoTrack system.
 * 
 * Configuration:
 * - Update $host, $db_name, $username, $password below
 * - Or set environment variables: DB_HOST, DB_NAME, DB_USER, DB_PASS
 * 
 * Usage:
 *   $db = new Database();
 *   $conn = $db->getConnection();
 * 
 * For all database queries, use the DatabaseAgent class instead:
 *   $dbAgent = new DatabaseAgent();
 *   $shipments = $dbAgent->getAllShipments();
 */

class Database {
    // Database configuration
    // Option 1: Direct configuration
    private $host = "localhost";
    private $db_name = "cargo_db";
    private $username = "root";
    private $password = "RootPass123!";
    
    // Option 2: Environment variables (uncomment to use)
    // private $host = getenv('DB_HOST') ?: 'localhost';
    // private $db_name = getenv('DB_NAME') ?: 'cargo_db';
    // private $username = getenv('DB_USER') ?: 'root';
    // private $password = getenv('DB_PASS') ?: '';
    
    public $conn;

    /**
     * Get PDO database connection
     * 
     * @return PDO|null Database connection or null on failure
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
            // Set PDO attributes
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            // Set timezone
            $this->conn->exec("SET time_zone = '+03:00'"); // East Africa Time
            
        } catch(PDOException $e) {
            // Log error (in production, don't expose to user)
            error_log("Database connection error: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Database connection failed. Please check configuration."
            ]);
            exit;
        }

        return $this->conn;
    }
    
    /**
     * Get database name
     * @return string
     */
    public function getDbName() {
        return $this->db_name;
    }
    
    /**
     * Test connection
     * @return bool
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            return $conn !== null;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>