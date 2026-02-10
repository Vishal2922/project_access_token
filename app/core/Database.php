<?php
class Database {
    // Localhost IP address: 127.0.0.1 
    private $host = "127.0.0.1"; 
    // New Port requirement: 3308
    private $port = "3308";
    private $db_name = "sample_api"; // Create this in phpMyAdmin 
    private $username = "root"; 
    private $password = ""; 
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
        
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name;
            
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
   
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
            
        } catch(PDOException $exception) {
          
            http_response_code(500);
            echo json_encode(["message" => "Connection error: " . $exception->getMessage()]);
            exit();
        }
        return $this->conn;
    }
}