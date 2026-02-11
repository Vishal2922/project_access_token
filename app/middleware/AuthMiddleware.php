<?php
class AuthMiddleware {
    public static $currentUserId;
    public static $currentUserEmail;

    public function handle() {
        // 1. Header-la irundhu Access Token-ai edukkurohm
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        $accessToken = null;

        if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $accessToken = $matches[1];
        }

        if (!$accessToken) {
            Response::json(401, "Unauthorized: Access token missing.");
            exit();
        }

        /**
         * 2. IDENTITY VALIDATION (BEFORE Expiry Check)
         * Token expire aagi irundhaalum 'getPayload' moolama details-ai edukkalaam.
         * Vishal-oda (Expired) token-ai Hari anuppunaal, intha step-laivae block aagum.
         */
        $tokenPayload = JWT::getPayload($accessToken);
        $refreshToken = $_COOKIE['refresh_token'] ?? null;
        $refreshPayload = JWT::getPayload($refreshToken);

        if (!$tokenPayload || !$refreshPayload) {
            Response::json(401, "Unauthorized: Invalid session structure.");
            exit();
        }

        // Parallel Identity Match Check
        $accessTokenUserId = (int)$tokenPayload['user_id'];
        $sessionUserId = (int)$refreshPayload['user_id'];

        if ($accessTokenUserId !== $sessionUserId) {
            Response::json(403, "Security Alert: Access token does not match your session identity.");
            exit();
        }

        /**
         * 3. EXPIRY & SIGNATURE VERIFICATION
         * Identity match aanaal mattum, token innum valid-aa-nu check pannuvom.
         */
        $userData = JWT::verify($accessToken);

        if ($userData) {
            // Access token valid-aa irundha, identity-ai set pannuvom
            self::$currentUserId = $userData['user_id'];
            self::$currentUserEmail = $userData['email'];
            return true;
        } else {
            /**
             * 4. REGENERATION FLOW
             * Identity match aayiduchu, aana token expire aayiduchu (40s mudinjathu).
             * Ippo auto-refresh panni pudhu pair tharuvom.
             */
            return $this->refreshAccessTokenFlow($accessToken);
        }
    }

    /**
     * Automatic Token Rotation Logic (Sliding Session)
     */
    private function refreshAccessTokenFlow($expiredToken) {
        $refreshToken = $_COOKIE['refresh_token'] ?? null;

        if (!$refreshToken) {
            Response::json(401, "Session expired. Please login again.");
            exit();
        }

        $refreshData = JWT::verify($refreshToken);

        if (!$refreshData) {
            Response::json(401, "Refresh session expired. Please login again.");
            exit();
        }

        $userId = $refreshData['user_id'];
        $email = $refreshData['email'];

        $database = new Database();
        $db = $database->getConnection();
        $userModel = new User($db);

        $hexRefresh = JWT::toHex($refreshToken);
        $user = $userModel->validateRefreshToken($hexRefresh);

        if ($user) {
            if ($refreshData['ip'] !== $_SERVER['REMOTE_ADDR']) {
                Response::json(403, "Security mismatch: Device not recognized.");
                exit();
            }

            // Regenerate both tokens to reset 1-day expiry
            $newAccessToken = JWT::generateAccessToken(["user_id" => $userId, "email" => $email]);
            $newRefreshToken = JWT::generateRefreshToken(["user_id" => $userId, "email" => $email]);
            
            $userModel->updateRefreshToken($userId, JWT::toHex($newRefreshToken));

            setcookie('refresh_token', $newRefreshToken, [
                'expires' => time() + (int)$_ENV['JWT_REFRESH_EXPIRY'], 
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Strict'
            ]);

            header("New-Access-Token: " . $newAccessToken);
            header("Access-Control-Expose-Headers: New-Access-Token");

            self::$currentUserId = $userId;
            self::$currentUserEmail = $email;
            return true;
        }

        Response::json(401, "Invalid session.");
        exit();
    }
}