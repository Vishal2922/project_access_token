<?php
class AuthController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function register() {
        $data = $_POST['body'] ?? json_decode(file_get_contents("php://input"), true); 
        
        if(empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            Response::json(400, "Name, Email and Password are required");
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || !str_ends_with($data['email'], '@gmail.com')) {
            Response::json(400, "Invalid email. Only @gmail.com addresses are accepted.");
        }

        $passwordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
        if (!preg_match($passwordRegex, $data['password'])) {
            Response::json(400, "Password weak! Must be >= 8 chars with uppercase, lowercase, number, and special char.");
        }

        $user = new User($this->db);
        $user->name = $data['name'];
        $user->email = $data['email'];

        if($user->emailExists()) {
            Response::json(400, "Email already exists");
        }

        $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        if($user->create()) {
            Response::json(201, "User registered successfully");
        } else {
            Response::json(500, "Unable to register user due to a system error");
        }
    }

   /**
     * POST /api/login
     * Strictly uses .env values for expiry
     */
    public function login() {
        $data = $_POST['body'] ?? json_decode(file_get_contents("php://input"), true); 
        
        if(empty($data['email']) || empty($data['password'])) {
            Response::json(400, "Email and Password are required");
        }

        $user = new User($this->db);
        $user->email = $data['email'];

        if($user->emailExists()) {
            if(password_verify($data['password'], $user->password)) {
                
                // 1. Generate JWT Refresh Token
                $token = JWT::generate([
                    "user_id" => $user->id,
                    "email" => $user->email
                ]); 

                // 2. Convert Token to Hexadecimal
                $hexToken = JWT::toHex($token);

                // 3. Save Hex Token in DB
                if(!$user->updateRefreshToken($user->id, $hexToken)) {
                    Response::json(500, "Error saving session.");
                }

                // 4. Set HttpOnly Cookie - Strictly from .env
                setcookie(
                    'refresh_token', 
                    $token, 
                    [
                        'expires' => time() + (int)$_ENV['JWT_EXPIRY'], 
                        'path' => '/',
                        'httponly' => true,
                        'secure' => false, 
                        'samesite' => 'Strict'
                    ]
                );

                // 5. Response - Strictly from .env
                Response::json(200, [
                    "message" => "Login successful",
                    "token" => $token,  
                    "rotation_every" => (int)$_ENV['JWT_ACCESS_EXPIRY'],
                    "session_validity" => (int)$_ENV['JWT_EXPIRY']
                ]);
            } else {
                Response::json(401, "Invalid password");
            }
        } else {
            Response::json(404, "User not found");
        }
    }
    /**
     * POST /api/logout
     * NEW: Clears HttpOnly Cookie and DB Hex Token
     */
    public function logout() {
        // AuthMiddleware set panna ID-ai use pannuvom
        $userId = AuthMiddleware::$currentUserId; 

        if ($userId) {
            $user = new User($this->db);
            // 1. DB-la refresh_token-ai NULL pannuvom
            $user->clearRefreshToken($userId);
        }

        // 2. Browser cookie-ai expire pannuvom
        setcookie('refresh_token', '', [
            'expires' => time() - 3600, // Past time sets expiry immediately
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        Response::json(200, ["message" => "Logged out successfully"]);
    }
}