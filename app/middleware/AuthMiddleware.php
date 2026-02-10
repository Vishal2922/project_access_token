<?php
class AuthMiddleware {
    public static $currentUserId;
    public static $currentUserEmail;

    public function handle() {
        // 1. Direct-ah cookie-la irundhu JWT token-ai edukkurohm
        $token = $_COOKIE['refresh_token'] ?? null;

        if (!$token) {
            Response::json(401, "Unauthorized: No session found. Please login.");
            exit();
        }

        // 2. Token-ai verify pannuvom (Signature + Expiry check)
        $userData = JWT::verify($token);

        if ($userData) {
            // Token valid-aa irundha, athula ulla Hex value DB-oda match aagudha-nu check pannanum
            return $this->validateWithDB($token, $userData);
        } else {
            // 3. AUTO-ROTATION: Token expire aagi irundha (after 30s), pudhu token generate pannuvom
            return $this->rotateToken($token);
        }
    }

    /**
     * Database-la ulla Hex value-oda match panni verify pannum
     */
    private function validateWithDB($token, $data) {
        $hexToken = JWT::toHex($token);
        
        $database = new Database();
        $db = $database->getConnection();
        $userModel = new User($db);

        $user = $userModel->validateRefreshToken($hexToken);

        if ($user) {
            self::$currentUserId = $user['id'];
            self::$currentUserEmail = $user['email'];
            return true;
        }

        Response::json(401, "Unauthorized: Session invalid.");
        exit();
    }

    /**
     * Token Expired aanaal, auto-va pudhu token generate panni update pannum logic
     */
    private function rotateToken($oldToken) {
        // Expired token-la irundhu user data-vai edukkurohm
        $payload = JWT::getPayload($oldToken);
        
        if (!$payload) {
            Response::json(401, "Unauthorized: Invalid token structure.");
            exit();
        }

        $userId = $payload['user_id'];
        $email = $payload['email'];

        // Pudhu token generate pannurhom (Next 30 seconds-ku)
        $newToken = JWT::generate(["user_id" => $userId, "email" => $email]);
        $newHex = JWT::toHex($newToken);

        $database = new Database();
        $db = $database->getConnection();
        $userModel = new User($db);

        // Database-la pudhu Hex-ai update pannurhom
        if ($userModel->updateRefreshToken($userId, $newHex)) {
            
            // Cookie-laiyum pudhu token-ai update pannurhom
            setcookie('refresh_token', $newToken, [
                'expires' => time() + (7 * 24 * 60 * 60),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Strict'
            ]);

            self::$currentUserId = $userId;
            self::$currentUserEmail = $email;
            return true;
        }

        Response::json(401, "Unauthorized: Re-authentication failed.");
        exit();
    }
}