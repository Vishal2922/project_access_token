<?php
class User {
    private $conn;
    private $table_name = "users";
    private $token_table = "refresh_tokens"; // Trainer's separate table requirement

    // User profile properties
    public $id;
    public $name;
    public $email;
    public $password;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * 1. Check if Email Exists (Login Logic)
     */
    public function emailExists() {
        $query = "SELECT id, password FROM " . $this->table_name . " WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$this->email]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->id = $row['id'];
            $this->password = $row['password']; 
            return true;
        }
        return false;
    }

    /**
     * 2. Create New User
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
     * 3. HASH AND STORE: Refresh Token-ai hash panni store pannuvom.
     * Trainer structure: id, user_id, token_hash, expires_at.
     */
    public function updateRefreshToken($userId, $plainToken) {
        // Step A: Security-kaaga pazhaiya session tokens-ai delete pannuvom.
        $deleteQuery = "DELETE FROM " . $this->token_table . " WHERE user_id = ?";
        $this->conn->prepare($deleteQuery)->execute([$userId]);

        // Step B: Refresh token-ai password maadhiri hash pannuvom.
        $tokenHash = password_hash($plainToken, PASSWORD_BCRYPT);
        
        // Step C: Expiry time-ai DATETIME format-la calculate pannuvom.
        $expiryDate = date('Y-m-d H:i:s', time() + (int)$_ENV['JWT_REFRESH_EXPIRY']);
        
        $query = "INSERT INTO " . $this->token_table . " (user_id, token_hash, expires_at) 
                  VALUES (:user_id, :token_hash, :expires_at)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':token_hash', $tokenHash);
        $stmt->bindParam(':expires_at', $expiryDate);
        
        return $stmt->execute();
    }

    /**
     * 4. VALIDATE WITH HASH: Plain token-ai hash-oda verify pannuvom.
     */
    public function validateRefreshToken($userId, $plainToken) {
        // User ID matum expiry check panni hash-ai edukkurohm.
        $query = "SELECT token_hash FROM " . $this->token_table . " 
                  WHERE user_id = :user_id AND expires_at > NOW() LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // password_verify moolama identity-ai confirm pannuvom.
        if ($row && password_verify($plainToken, $row['token_hash'])) {
            // Success aanaal user context profile-ai return pannuvom.
            $userQuery = "SELECT id, email FROM " . $this->table_name . " WHERE id = ?";
            $userStmt = $this->conn->prepare($userQuery);
            $userStmt->execute([$userId]);
            return $userStmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }

    /**
     * 5. Clear Token (Logout)
     */
    public function clearRefreshToken($userId) {
        $query = "DELETE FROM " . $this->token_table . " WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$userId]);
    }
}