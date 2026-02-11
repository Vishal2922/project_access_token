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
     * NEW WORKFLOW: Access Token in Response, Refresh Token in DB & Cookie
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
                
                $payload = [
                    "user_id" => $user->id,
                    "email" => $user->email
                ];

                // 1. Generate Access Token (Strictly for Response - 40s)
                $accessToken = JWT::generateAccessToken($payload);

                // 2. Generate Refresh Token (For DB and Cookie - 1 Day)
                $refreshToken = JWT::generateRefreshToken($payload);

                // 3. Convert Refresh Token to Hex and save in DB
                $hexRefresh = JWT::toHex($refreshToken);
                if(!$user->updateRefreshToken($user->id, $hexRefresh)) {
                    Response::json(500, "Error saving session.");
                }

                // 4. Set Refresh Token in HttpOnly Cookie
                setcookie(
                    'refresh_token', 
                    $refreshToken, 
                    [
                        'expires' => time() + (int)$_ENV['JWT_REFRESH_EXPIRY'], 
                        'path' => '/',
                        'httponly' => true,
                        'secure' => false, // Set to true if using HTTPS
                        'samesite' => 'Strict'
                    ]
                );

                // 5. Final Response: Access Token mattum thaan response body-la pogum
                Response::json(200, [
                    "message" => "Login successful",
                    "access_token" => $accessToken,  
                    "access_token_expiry" => (int)$_ENV['JWT_ACCESS_EXPIRY'] . " seconds",
                    "refresh_token_validity" => "Session active for 1 day"
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
     */
    public function logout() {
        $userId = AuthMiddleware::$currentUserId; 

        if ($userId) {
            $user = new User($this->db);
            $user->clearRefreshToken($userId);
        }

        setcookie('refresh_token', '', [
            'expires' => time() - 3600, 
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        Response::json(200, ["message" => "Logged out successfully"]);
    }
}