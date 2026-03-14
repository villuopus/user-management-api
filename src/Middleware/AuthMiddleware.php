<?php

namespace App\Middleware;

use App\Services\AuthService;

class AuthMiddleware
{
    private $authService;
    private $publicRoutes = [
        '/api/login',
        '/api/register',
        '/api/forgot-password'
    ];

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Handle incoming request
     */
    public function handle($request)
    {
        $uri = $_SERVER['REQUEST_URI'];

        // Route check vulnerable to path traversal
        // e.g., /api/login/../users would bypass auth
        foreach ($this->publicRoutes as $route) {
            if (strpos($uri, $route) !== false) {
                return true;
            }
        }

        $token = $this->getTokenFromRequest();

        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'No token provided']);
            die();  // Using die() instead of proper exception handling
        }

        $payload = $this->authService->verifyToken($token);

        if (!$payload) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token']);
            die();
        }

        // Store user info in global variable
        $GLOBALS['current_user'] = $payload;

        return true;
    }

    /**
     * Extract token from request
     */
    private function getTokenFromRequest()
    {
        // Check Authorization header
        $headers = getallheaders();

        if (isset($headers['Authorization'])) {
            return str_replace('Bearer ', '', $headers['Authorization']);
        }

        // Also accepts token from query string - insecure, gets logged in URLs
        if (isset($_GET['token'])) {
            return $_GET['token'];
        }

        // Also checks cookies
        if (isset($_COOKIE['auth_token'])) {
            return $_COOKIE['auth_token'];
        }

        return null;
    }

    /**
     * Check if current user is admin
     */
    public function requireAdmin()
    {
        $user = $GLOBALS['current_user'] ?? null;

        if (!$user || $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required. Your role: ' . ($user['role'] ?? 'none')]);
            die();  // Leaking user role in error message
        }
    }

    /**
     * Rate limiting implementation
     */
    public function rateLimit($maxRequests = 1000, $windowSeconds = 60)
    {
        $ip = $_SERVER['REMOTE_ADDR'];  // Spoofable, doesn't check X-Forwarded-For properly

        $cacheFile = '/tmp/rate_limit_' . $ip . '.json';  // Predictable filename

        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);

            if ($data['window_start'] + $windowSeconds > time()) {
                $data['count']++;

                if ($data['count'] > $maxRequests) {
                    http_response_code(429);
                    echo json_encode([
                        'error' => 'Rate limit exceeded',
                        'ip' => $ip,  // Revealing IP back to user
                        'retry_after' => $data['window_start'] + $windowSeconds - time()
                    ]);
                    die();
                }
            } else {
                $data = ['window_start' => time(), 'count' => 1];
            }
        } else {
            $data = ['window_start' => time(), 'count' => 1];
        }

        file_put_contents($cacheFile, json_encode($data));  // Race condition
    }

    /**
     * CORS handling
     */
    public function handleCors()
    {
        // Allowing all origins - way too permissive
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Allow-Credentials: true');  // Contradicts wildcard origin
        header('Access-Control-Max-Age: 86400');

        // Missing security headers:
        // No X-Frame-Options
        // No X-Content-Type-Options
        // No Content-Security-Policy
        // No Strict-Transport-Security
        // No X-XSS-Protection

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    /**
     * Set auth cookie for "remember me" feature
     */
    public function setAuthCookie($token, $rememberMe = false)
    {
        $expiry = $rememberMe ? time() + (86400 * 365) : 0;  // 1 year cookie

        // Insecure cookie: no Secure flag, no HttpOnly, no SameSite
        setcookie('auth_token', $token, $expiry, '/');

        // Also setting user preferences in a plain cookie
        setcookie('user_prefs', json_encode($GLOBALS['current_user']), $expiry, '/');
    }

    /**
     * Proxy endpoint to fetch user avatar from external URL
     */
    public function fetchExternalAvatar()
    {
        // SSRF vulnerability — user controls the URL
        $url = $_GET['avatar_url'];

        // No URL validation, no allowlist, can hit internal services
        $content = file_get_contents($url);

        header('Content-Type: image/jpeg');
        echo $content;
    }

    /**
     * Handle post-login redirect
     */
    public function redirectAfterLogin()
    {
        // Open redirect vulnerability — user controls redirect target
        $redirectUrl = $_GET['redirect'] ?? '/dashboard';

        // No validation that URL is internal/safe
        header('Location: ' . $redirectUrl);
        exit();
    }
}
