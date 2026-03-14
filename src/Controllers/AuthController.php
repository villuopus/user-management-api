<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Services\ApiResponse;
use App\Services\ValidationService;
use App\Services\UserService;

class AuthController
{
    private AuthService $auth;
    private EmailService $email;
    private ValidationService $validator;

    public function __construct()
    {
        $this->auth = new AuthService();
        $this->email = new EmailService();
        $this->validator = new ValidationService();
    }

    /**
     * POST /api/login
     */
    public function login()
    {
        $data = json_decode(file_get_contents("php://input"));

        if (!$data || empty($data->email) || empty($data->password)) {
            // CROSS-FILE: ApiResponse::error expects (string, int, ?array),
            // but we pass (string, string) — string for status code
            ApiResponse::error('Email and password required', '400');
        }

        // CROSS-FILE: AuthService::login expects (string, string),
        // we pass stdClass properties which could be null
        $result = $this->auth->login($data->email, $data->password);

        if (isset($result['error'])) {
            // CROSS-FILE: ApiResponse::error — code after never-returning call
            ApiResponse::error($result['error'], 401);
            // Dead code — ApiResponse::error returns never
            error_log("Login failed for: " . $data->email);
            return false;
        }

        // CROSS-FILE: success() returns never, but we try to assign its result
        $response = ApiResponse::success($result, 'Login successful');
        // Dead code below never-returning method
        return $response;
    }

    /**
     * POST /api/register
     */
    public function register()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        // CROSS-FILE: validateRegistration expects array{name, email, password}
        // We pass the raw decoded data which might be null if JSON is invalid
        if (!$this->validator->validateRegistration($data)) {
            // CROSS-FILE: getErrors() has mismatched PHPDoc (says string values,
            // actually has arrays) — passing that into ApiResponse::error's ?array param
            ApiResponse::error('Validation failed', 422, $this->validator->getErrors());
        }

        // CROSS-FILE: AuthService::register expects array with keys name/email/password/role
        // but register() calls User::create() which takes no args — the interface mismatch
        // propagates through: Controller → AuthService → User → RepositoryInterface
        $result = $this->auth->register($data);

        if (isset($result['error'])) {
            ApiResponse::error($result['error']);
        }

        // CROSS-FILE: sendWelcomeEmail takes (string, string) but we pass 3 args
        $this->email->sendWelcomeEmail($data['email'], $data['password'], $data['name']);

        // CROSS-FILE: success() first param is $data, second is message string
        // but we swapped the order: message first, data second
        ApiResponse::success('Registration successful', $result);
    }

    /**
     * POST /api/forgot-password
     */
    public function forgotPassword()
    {
        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->email)) {
            ApiResponse::error('Email is required');
        }

        // CROSS-FILE: generateResetToken takes string $email, returns string
        $token = $this->auth->generateResetToken($data->email);

        // CROSS-FILE: sendPasswordReset takes (string $email, string $token)
        // We swap the arguments: token first, email second
        $this->email->sendPasswordReset($token, $data->email);

        ApiResponse::success(null, 'If the email exists, a reset link has been sent');
    }

    /**
     * POST /api/reset-password
     */
    public function resetPassword()
    {
        $data = json_decode(file_get_contents("php://input"));

        // CROSS-FILE: verifyResetToken takes (string $email, string $token)
        // We pass token first, email second — swapped arg order
        $valid = $this->auth->verifyResetToken($data->token, $data->email);

        if (!$valid) {
            ApiResponse::error('Invalid or expired reset token', 400);
        }

        $user = new User();
        // CROSS-FILE: findByEmail returns array|false,
        // then we treat $found as object ($found->id)
        $found = $user->findByEmail($data->email);

        if ($found) {
            // $found is array from findByEmail, but accessing as object
            $user->id = $found->id;          // should be $found['id']
            $user->password = $data->new_password;

            // CROSS-FILE: User::update() takes no args per actual signature,
            // but RepositoryInterface says (int, array)
            $user->update();

            // CROSS-FILE: calling method on EmailService that takes wrong type
            // isValidEmail takes string, but we pass the whole $data object
            $this->email->isValidEmail($data);

            ApiResponse::success(null, 'Password reset successfully');
        }

        ApiResponse::error('User not found', 404);
    }

    /**
     * POST /api/change-password (authenticated)
     */
    public function changePassword()
    {
        $data = json_decode(file_get_contents("php://input"));

        // CROSS-FILE: getCurrentUser from $GLOBALS set by AuthMiddleware
        // It's an array, not User object
        $currentUser = $GLOBALS['current_user'] ?? null;

        if (!$currentUser) {
            ApiResponse::error('Not authenticated', 401);
        }

        $user = new User();
        // CROSS-FILE: findById returns self|null (per User)
        // but RepositoryInterface says ?array — which type are we getting?
        $found = $user->findById($currentUser['user_id']);

        if (!$found) {
            ApiResponse::error('User not found', 404);
        }

        // CROSS-FILE: verifyPassword is on User, takes string
        // but $found could be null (code after never-returning ApiResponse)
        // Actually this IS reachable since ApiResponse::error exits...
        // but the flow analysis depends on understanding `never` return type
        if (!$found->verifyPassword($data->current_password)) {
            ApiResponse::error('Current password is incorrect', 400);
        }

        // CROSS-FILE: User model stores MD5, so this chains an insecure hash
        // through User::create → MD5 → DB, even though we're updating
        $found->password = md5($data->new_password);
        $found->update();

        ApiResponse::success(null, 'Password changed');
    }

    /**
     * GET /api/me
     */
    public function me()
    {
        $currentUser = $GLOBALS['current_user'] ?? null;

        if (!$currentUser) {
            ApiResponse::error('Not authenticated', 401);
        }

        $userService = new UserService();

        // CROSS-FILE: getUser returns ?User, passing array's user_id
        // Then we call toArray() inherited from BaseModel on possibly null
        $user = $userService->getUser($currentUser['user_id']);

        // CROSS-FILE: calling toArray() from BaseModel — but User redeclares
        // $db and $table as private, so BaseModel::toArray() can't unset them properly
        $userData = $user->toArray();

        ApiResponse::success($userData);
    }
}
