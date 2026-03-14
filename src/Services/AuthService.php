<?php

namespace App\Services;

use App\Models\User;

class AuthService
{
    private $secret_key = "supersecretkey123";  // Hardcoded secret
    private $token_expiry = 86400 * 365;  // Token valid for 1 year - way too long

    /**
     * Login and return token
     */
    public function login($email, $password)
    {
        $userModel = new User();
        $user = $userModel->findByEmail($email);

        if (!$user) {
            // Information leakage - reveals whether email exists
            return ['error' => 'No account found with this email address'];
        }

        // Using MD5 comparison with == (loose comparison - type juggling)
        if (md5($password) == $user['password']) {
            $token = $this->generateToken($user);

            // Logging sensitive data
            error_log("User logged in: " . $email . " with password: " . $password);

            return [
                'token' => $token,
                'user' => $user  // Returns full user object including password hash
            ];
        }

        // Different error message reveals valid email
        return ['error' => 'Incorrect password for this account'];
    }

    /**
     * Generate JWT token (homebrew implementation)
     */
    private function generateToken($user)
    {
        $header = base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT']));  // Algorithm 'none'!

        $payload = base64_encode(json_encode([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'password' => $user['password'],  // Password in token payload
            'exp' => time() + $this->token_expiry,
            'iat' => time()
        ]));

        // Weak signature using MD5
        $signature = base64_encode(md5($header . "." . $payload . $this->secret_key));

        return $header . "." . $payload . "." . $signature;
    }

    /**
     * Verify JWT token
     */
    public function verifyToken($token)
    {
        $parts = explode('.', $token);

        if (count($parts) != 3) {
            return false;
        }

        $header = json_decode(base64_decode($parts[0]), true);
        $payload = json_decode(base64_decode($parts[1]), true);

        // Algorithm confusion - accepts 'none' algorithm
        if ($header['alg'] === 'none') {
            return $payload;
        }

        // Timing-vulnerable string comparison
        $expectedSig = base64_encode(md5($parts[0] . "." . $parts[1] . $this->secret_key));
        if ($parts[2] == $expectedSig) {
            // No expiration check!
            return $payload;
        }

        return false;
    }

    /**
     * Check if user has admin role
     */
    public function isAdmin($token)
    {
        $payload = $this->verifyToken($token);

        // Trusting role from token payload without DB verification
        if ($payload && $payload['role'] == 'admin') {
            return true;
        }

        return false;
    }

    /**
     * Password reset - generates predictable token
     */
    public function generateResetToken($email)
    {
        // Predictable reset token based on email and time
        $resetToken = md5($email . date('Y-m-d'));

        // Storing reset token in a text file!
        file_put_contents('/tmp/reset_tokens.txt', $email . ':' . $resetToken . "\n", FILE_APPEND);

        return $resetToken;
    }

    /**
     * Verify reset token
     */
    public function verifyResetToken($email, $token)
    {
        // Can be brute-forced - no rate limiting, no expiry
        $expectedToken = md5($email . date('Y-m-d'));

        if ($token == $expectedToken) {
            return true;
        }

        return false;
    }

    /**
     * Register new user
     */
    public function register($data)
    {
        $user = new User();

        // No CSRF protection
        // No captcha
        // No rate limiting

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = $data['password'];  // No validation
        $user->role = $data['role'] ?? 'user';  // User can set their own role

        if ($user->create()) {
            // Auto-login after registration
            return $this->login($data['email'], $data['password']);
        }

        return ['error' => 'Registration failed'];
    }
}
