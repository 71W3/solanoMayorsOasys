<?php
/**
 * Database Configuration and Connection Helper
 * Optimized for performance with connection pooling and error handling
 */

class DatabaseConnection {
    private static $instance = null;
    private $connection;
    private $host = "localhost";
    private $username = "root";
    private $password = "";
    private $database = "my_auth_db";
    
    private function __construct() {
        try {
            // Enable mysqli report for better error handling
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            
            // Create connection with optimized settings
            $this->connection = new mysqli(
                $this->host, 
                $this->username, 
                $this->password, 
                $this->database
            );
            
            // Set charset for security and performance
            $this->connection->set_charset("utf8mb4");
            
            // Optimize connection settings
            $this->connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
            $this->connection->options(MYSQLI_INIT_COMMAND, "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        // Check if connection is still alive
        if (!$this->connection->ping()) {
            $this->__construct(); // Reconnect if needed
        }
        return $this->connection;
    }
    
    public function closeConnection() {
        if ($this->connection) {
            $this->connection->close();
            self::$instance = null;
        }
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Helper function to get database connection
 */
function getDB() {
    return DatabaseConnection::getInstance()->getConnection();
}

/**
 * Helper function to safely escape strings
 */
function escapeString($string) {
    return getDB()->real_escape_string(trim($string));
}

/**
 * Helper function for prepared statements
 */
function executeQuery($query, $types = "", $params = []) {
    $conn = getDB();
    $stmt = $conn->prepare($query);
    
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $result = $stmt->execute();
    
    if ($result === false) {
        error_log("Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    return $stmt;
}
?>
