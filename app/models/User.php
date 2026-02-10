<?php
class User {
    private $conn;
    private $table_name = "users";

    // User properties
    public $id;
    public $name;
    public $email;
    public $password;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Check if email already exists in the database.
     * 
     */
    public function emailExists() {
        $query = "SELECT id, password FROM " . $this->table_name . " WHERE email = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->email]);

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->password = $row['password']; // Hashed password from DB
            return true;
        }
        return false;
    }

    
     //Create a new user account.
     
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET name = :name, email = :email, password = :password";
        
        $stmt = $this->conn->prepare($query);

        // Sanitize input
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));

        // Bind parameters
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password', $this->password);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}