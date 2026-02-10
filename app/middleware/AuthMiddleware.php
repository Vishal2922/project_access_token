<?php
class AuthMiddleware {
    
    public static $currentUserEmail;

    public function handle() {
        $headers = getallheaders(); 
        
        if (!isset($headers['Authorization'])) {
            Response::json(401, "Unauthorized: No token provided");
            exit();
        }

        $token = str_replace('Bearer ', '', $headers['Authorization']);
        
        
        $userData = JWT::verify($token);  // triggers the  jwt.php

        if (!$userData) {
            Response::json(401, "Unauthorized: Invalid or expired token");
            exit();
        }

        
        if (is_array($userData)) {
            self::$currentUserEmail = $userData['email'] ?? null;
        } elseif (is_object($userData)) {
            self::$currentUserEmail = $userData->email ?? null;
        }

        if (empty(self::$currentUserEmail)) {
            Response::json(401, "Unauthorized: Token payload missing email context");
            exit();
        }
    }
}