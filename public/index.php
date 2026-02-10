<?php
// 1. Autoload classes 
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

// 2. Global Middleware (Runs before Routing) 
$jsonMiddleware = new JsonMiddleware();
$jsonMiddleware->handle();

// 3. Initialize Router 
$router = new Router();


// Authentication Routes 
$router->add('POST', '/api/register', 'AuthController', 'register'); 
$router->add('POST', '/api/login', 'AuthController', 'login');


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
 * Matches the URI and Method, then passes the {id} to the controller method.
 */
$router->dispatch($uri, $method);