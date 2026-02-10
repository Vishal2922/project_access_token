<?php
// 1. Load Environment Variables from .env file
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; 
        
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

// 2. Autoload classes 
spl_autoload_register(function ($class) {
    $paths = ['app/controllers/', 'app/models/', 'app/middleware/', 'app/helpers/', 'app/core/'];
    foreach ($paths as $path) {
        $file = __DIR__ . '/../' . $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// 3. Global Middleware (Runs before Routing) 
$jsonMiddleware = new JsonMiddleware();
$jsonMiddleware->handle();

// 4. Initialize Router 
$router = new Router();

// --- Authentication Routes ---
$router->add('POST', '/api/register', 'AuthController', 'register'); 
$router->add('POST', '/api/login', 'AuthController', 'login');

/**
 * FIXED: Logout Route added with AuthMiddleware.
 * Session-ai clear panna current user identity kandippa theriyanum.
 */
$router->add('POST', '/api/logout', 'AuthController', 'logout', ['AuthMiddleware']); 

// Protected Patient Module 
$router->add('GET', '/api/patients', 'PatientController', 'index', ['AuthMiddleware']); 
$router->add('GET', '/api/patients/{id}', 'PatientController', 'show', ['AuthMiddleware']);
$router->add('POST', '/api/patients', 'PatientController', 'store', ['AuthMiddleware']); 
$router->add('PUT', '/api/patients/{id}', 'PatientController', 'update', ['AuthMiddleware']);
$router->add('PATCH', '/api/patients/{id}', 'PatientController', 'patch', ['AuthMiddleware']);
$router->add('DELETE', '/api/patients/{id}', 'PatientController', 'destroy', ['AuthMiddleware']); 

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Router dispatching logic
 */
$router->dispatch($uri, $method);