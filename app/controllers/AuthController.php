<?php
class AuthController {
    private $db;

    public function __construct() {
        // Database connection initialize
        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * POST /api/register
     * User registration with strict password and email validation
     */
    public function register() {
        // Get data from Middleware or raw input
        $data = $_POST['body'] ?? json_decode(file_get_contents("php://input"), true); 
        
        // 1. Mandatory Field Check
        if(empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            Response::json(400, "Name, Email and Password are required");
        }

        // 2. Gmail Format Validation (Accepts only @gmail.com)
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || !str_ends_with($data['email'], '@gmail.com')) {
            Response::json(400, "Invalid email. Only @gmail.com addresses are accepted.");
        }

        // 3. Password Complexity Check (Aa@1 format, length >= 8)
        // Regex: 1 Upper, 1 Lower, 1 Number, 1 Special Char, Min 8 length
        $passwordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
        if (!preg_match($passwordRegex, $data['password'])) {
            Response::json(400, "Password weak! Must be >= 8 chars with uppercase, lowercase, number, and special char.");
        }

        $user = new User($this->db);
        $user->name = $data['name'];
        $user->email = $data['email'];

        // 4. Duplicate Email Check
        if($user->emailExists()) {
            Response::json(400, "Email already exists");
        }

        // 5. Hash password
        $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // 6. Save to Database
        if($user->create()) {
            Response::json(201, "User registered successfully");
        } else {
            Response::json(500, "Unable to register user due to a system error");
        }
    }

    /**
     * POST /api/login
     * User authentication & JWT generation
     */
    public function login() {
        $data = $_POST['body'] ?? json_decode(file_get_contents("php://input"), true); 
        
        // 1. Accept email & password
        if(empty($data['email'])) {
            Response::json(400, "Email is required");
        }
        if(empty($data['password'])) {
            Response::json(400, "Password is required");
        }

        $user = new User($this->db);
        $user->email = $data['email'];

        // 2. Fetch user by email
        if($user->emailExists()) {
            // 3. Verify password hash
            if(password_verify($data['password'], $user->password)) {
                
                // 4. If valid, generate JWT token
                $tokenData = [
                    "user_id" => $user->id,
                    "email" => $user->email
                ];
                
                $jwt = JWT::generate($tokenData); 

                // Return success response with token
                Response::json(200, [
                    "message" => "Login successful",
                    "token" => $jwt,
                    "expires_in" => 3600
                ]);
            } else {
                Response::json(401, "Invalid password");
            }
        } else {
            Response::json(404, "User not found with the provided email");
        }
    }
}