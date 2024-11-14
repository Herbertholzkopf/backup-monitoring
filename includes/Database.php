<?php
// includes/Database.php

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
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
        return $this->connection->query($sql);
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
}