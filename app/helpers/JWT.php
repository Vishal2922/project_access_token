<?php
class JWT {
    private static function getSecret() {
        return $_ENV['JWT_SECRET'];
    }

    /**
     * 1. Generate Access Token (Strictly 40s)
     */
    public static function generateAccessToken($data) {
        $expiry = (int)$_ENV['JWT_ACCESS_EXPIRY']; 
        return self::createToken($data, $expiry, 'access');
    }

    /**
     * 2. Generate Refresh Token (Validity: 1 Day)
     */
    public static function generateRefreshToken($data) {
        $expiry = (int)$_ENV['JWT_REFRESH_EXPIRY']; 
        return self::createToken($data, $expiry, 'refresh');
    }

    /**
     * Internal Core Function
     */
    private static function createToken($data, $expiry, $type) {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        
        $payload = base64_encode(json_encode(array_merge($data, [
            'iat' => time(), 
            'exp' => time() + $expiry, 
            'ip' => $_SERVER['REMOTE_ADDR'], // IP Binding for security
            'type' => $type
        ])));

        $signature = base64_encode(hash_hmac('sha256', "$header.$payload", self::getSecret(), true));

        return "$header.$payload.$signature";
    }

    /**
     * 3. Convert JWT to Hexadecimal
     */
    public static function toHex($token) {
        return bin2hex($token);
    }

    /**
     * 4. Full Verification (Signature + Expiry)
     */
    public static function verify($token) {
        if (!$token) return false;
        
        $parts = explode('.', $token);
        if (count($parts) != 3) return false;

        list($header, $payload, $signature) = $parts;
        
        $validSig = base64_encode(hash_hmac('sha256', "$header.$payload", self::getSecret(), true));

        if ($signature !== $validSig) return false;

        $data = json_decode(base64_decode($payload), true);
        
        if (isset($data['exp']) && $data['exp'] < time()) {
            return false; 
        }

        return $data;
    }

    /**
     * 5. FIXED: Just Decode Payload
     * Intha method ippo base64-ai decode panni reliable-aa array-va tharum.
     */
    public static function getPayload($token) {
        if (!$token) return null;

        $parts = explode('.', $token);
        if (count($parts) != 3) return null;

        // Base64 decode panni string-ai JSON array-vaa mathurhom
        $decodedPayload = base64_decode($parts[1]);
        return json_decode($decodedPayload, true);
    }
}