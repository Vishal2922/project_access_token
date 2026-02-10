<?php
class User {
    private $conn;
    private $table_name = "users";

    // User properties
    public $id;
    public $name;
    public $email;
    public $password;
    public $refresh_token; 

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * MODIFIED: Email check logic with refresh_token fetch
     */
    public function emailExists() {
        $query = "SELECT id, password, refresh_token FROM " . $this->table_name . " WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->email]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $row['id'];
            $this->password = $row['password']; 
            $this->refresh_token = $row['refresh_token'] ?? null;
            return true;
        }
        return false;
    }

    /**
     * Standard User Creation
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " SET name = :name, email = :email, password = :password";
        $stmt = $this->conn->prepare($query);

        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));

        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password', $this->password);

        return $stmt->execute();
    }

    /**
     * UPDATED: Strict hexadecimal token update
     */
    public function updateRefreshToken($userId, $token) {
        // Ippo intha $token Hex format-la irukkum (from JWT::toHex)
        $query = "UPDATE " . $this->table_name . " SET refresh_token = :token WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':id', $userId);
        
        return $stmt->execute();
    }

    /**
     * UPDATED: Validation based on HEX token string
     */
    public function validateRefreshToken($token) {
        // Middleware cookie-la ulla JWT-ai hex-aa mathi inga anuppum
        $query = "SELECT id, email FROM " . $this->table_name . " WHERE refresh_token = ? LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$token]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * NEW: Clear refresh token during logout
     * Intha method DB-la ulla token-ai NULL panni session-ai permanently end pannum.
     */
    public function clearRefreshToken($userId) {
        $query = "UPDATE " . $this->table_name . " SET refresh_token = NULL WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$userId]);
    }
}