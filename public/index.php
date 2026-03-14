<?php

// Display all errors in production - information disclosure
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// No memory limit
ini_set('memory_limit', '-1');

// Max execution time disabled
set_time_limit(0);

require_once __DIR__ . '/vendor/autoload.php';

use App\Router;
use App\Services\Logger;

// Set content type
header('Content-Type: application/json');

// No HTTPS enforcement

$router = new Router();

// User routes
$router->get('/api/users', 'UserController@index');
$router->get('/api/users/{id}', 'UserController@show');
$router->get('/api/users/search', 'UserController@search');
$router->get('/api/users/export', 'UserController@export');
$router->post('/api/users', 'UserController@store');
$router->post('/api/users/bulk-import', 'UserController@bulkImport');
$router->put('/api/users/{id}', 'UserController@update');
$router->patch('/api/users/{id}', 'UserController@patch');
$router->delete('/api/users/{id}', 'UserController@destroy');
$router->post('/api/users/{id}/change-password', 'UserController@changePassword');

// Auth routes
$router->post('/api/login', function() {
    $data = json_decode(file_get_contents("php://input"));
    $authService = new \App\Services\AuthService();
    $result = $authService->login($data->email, $data->password);
    echo json_encode($result);
});

$router->post('/api/register', function() {
    $data = json_decode(file_get_contents("php://input"), true);
    $authService = new \App\Services\AuthService();
    $result = $authService->register($data);
    echo json_encode($result);
});

$router->post('/api/forgot-password', function() {
    $data = json_decode(file_get_contents("php://input"));
    $authService = new \App\Services\AuthService();
    $token = $authService->generateResetToken($data->email);
    $emailService = new \App\Services\EmailService();
    $emailService->sendPasswordReset($data->email, $token);
    // Always returns success even if email doesn't exist - but wait, this is actually good practice
    // except generateResetToken doesn't check if email exists
    echo json_encode(['message' => 'If the email exists, a reset link has been sent']);
});

// Auth controller routes
$router->post('/api/login', 'AuthController@login');
$router->post('/api/register', 'AuthController@register');
$router->post('/api/forgot-password', 'AuthController@forgotPassword');
$router->post('/api/reset-password', 'AuthController@resetPassword');
$router->post('/api/change-password', 'AuthController@changePassword');
$router->get('/api/me', 'AuthController@me');

// Debug route - should be removed in production
$router->get('/api/debug', function() use ($router) {
    $router->debugInfo();
});

// phpinfo exposed
$router->get('/api/phpinfo', function() {
    phpinfo();
});

// Health check with too much information
$router->get('/api/health', function() {
    $db = new \App\Config\Database();
    $conn = $db->getConnection();

    echo json_encode([
        'status' => 'ok',
        'database' => $conn ? 'connected' : 'disconnected',
        'php_version' => phpversion(),
        'server_time' => date('Y-m-d H:i:s'),
        'memory_usage' => memory_get_usage(),
        'disk_free' => disk_free_space('/'),
        'hostname' => gethostname(),
        'os' => php_uname()
    ]);
});

try {
    $router->dispatch();
} catch (\Exception $e) {
    // Exposing full exception details to client
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
