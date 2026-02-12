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

        // Token header-la illai-naa 401 Unauthorized 
        if (!$accessToken) {
            Response::json(401, "Unauthorized: Access token missing.");
            exit();
        }

        /**
         * 2. SECURITY LOGIC: Identity Anchor Check
         * Token expire aagi irundhaalum, cookie-la ulla refresh token payload-oda match aaganum.
         * Idhu identity mismatch detected aanaal block pannum.
         */
        $tokenPayload = JWT::getPayload($accessToken);
        $refreshToken = $_COOKIE['refresh_token'] ?? null;
        $refreshPayload = JWT::getPayload($refreshToken);

        if (!$tokenPayload || !$refreshPayload) {
            Response::json(401, "Invalid session structure.");
            exit();
        }

        // SECURITY CHECK: Access Token vs Refresh Token User ID match 
        if ((int)$tokenPayload['user_id'] !== (int)$refreshPayload['user_id']) {
            Response::json(403, "Security Alert: Identity mismatch detected.");
            exit();
        }

        /**
         * 3. EXPIRY & SIGNATURE VERIFICATION 
         */
        $userData = JWT::verify($accessToken);

        if ($userData) {
            // PASS: Token valid-aa irundha identity-ai set pannuvom
            self::$currentUserId = $userData['user_id'];
            self::$currentUserEmail = $userData['email'];
            return true;
        } else {
            /**
             * 4. STEP 1 & 2 REQUIREMENT 
             * Backend does NOT auto-refresh. Direct-aa 401 anuppuvom.
             * Frontend catch panni /api/token/refresh-ai manual-aa call pannanum.
             */
            Response::json(401, "Access token expired. Please call /api/token/refresh.");
            exit();
        }
    }
}