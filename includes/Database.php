<?php
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $config = require __DIR__ . '/../config/config.php';
            
            $this->connection = new mysqli(
                $config['db']['host'],
                $config['db']['user'],
                $config['db']['password'],
                $config['db']['database']
            );

            if ($this->connection->connect_error) {
                throw new Exception("Verbindungsfehler: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset('utf8mb4');
            $this->connection->query("SET time_zone = '+01:00'");
            
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            throw $e;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql) {
        try {
            $result = $this->connection->query($sql);
            if ($result === false) {
                throw new Exception($this->connection->error);
            }
            return $result;
        } catch (Exception $e) {
            $this->logError("SQL-Fehler: " . $e->getMessage() . "\nQuery: " . $sql);
            throw $e;
        }
    }
    
    public function prepare($sql) {
        try {
            $stmt = $this->connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception($this->connection->error);
            }
            return $stmt;
        } catch (Exception $e) {
            $this->logError("Prepare-Fehler: " . $e->getMessage() . "\nQuery: " . $sql);
            throw $e;
        }
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    private function logError($message) {
        $logfile = __DIR__ . '/../logs/db_error.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] $message\n";
        
        if (!is_dir(dirname($logfile))) {
            mkdir(dirname($logfile), 0755, true);
        }
        
        file_put_contents($logfile, $log_message, FILE_APPEND);
    }
    
    public function beginTransaction() {
        $this->connection->begin_transaction();
    }
    
    public function commit() {
        $this->connection->commit();
    }
    
    public function rollback() {
        $this->connection->rollback();
    }
    
    public function __destruct() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
    
    private function __clone() {}
    
    private function __wakeup() {}
}