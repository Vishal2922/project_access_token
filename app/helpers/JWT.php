<?php
class JWT {
    private static function getSecret() {
        return $_ENV['JWT_SECRET'];
    }

    /**
     * MODIFIED: Strictly 30 seconds for the Refresh Token session.
     */
    private static function getExpiry() {
        return (int)($_ENV['JWT_ACCESS_EXPIRY']);
    }

    /**
     * 1. Generate Refresh Token (JWT Format)
     * This will be stored in HttpOnly Cookie.
     */
    public static function generate($data) {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        
        $payload = base64_encode(json_encode(array_merge($data, [
            'iat' => time(), 
            'exp' => time() + self::getExpiry(), // 30 seconds
            'type' => 'refresh'
        ])));

        $signature = base64_encode(hash_hmac('sha256', "$header.$payload", self::getSecret(), true));

        return "$header.$payload.$signature";
    }

    /**
     * 2. Convert JWT to Hexadecimal
     * This is for Database storage.
     */
    public static function toHex($token) {
        return bin2hex($token);
    }

    /**
     * 3. Full Verification (Signature + Expiry)
     */
    public static function verify($token) {
        $parts = explode('.', $token);
        if (count($parts) != 3) return false;

        list($header, $payload, $signature) = $parts;
        
        $validSig = base64_encode(hash_hmac('sha256', "$header.$payload", self::getSecret(), true));

        // Check Signature
        if ($signature !== $validSig) return false;

        $data = json_decode(base64_decode($payload), true);
        
        // Check if expired
        if ($data['exp'] < time()) return false;

        return $data;
    }

    /**
     * 4. Just Decode Payload (To get data even if expired)
     * This helps in rotation logic to know which user is renewing.
     */
    public static function getPayload($token) {
        $parts = explode('.', $token);
        if (count($parts) != 3) return null;
        return json_decode(base64_decode($parts[1]), true);
    }
}