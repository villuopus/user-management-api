<?php

namespace App\Services;

use App\Models\User;

class ValidationService
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, array<string>> */
    private $rules;  // missing type declaration despite being typed elsewhere

    /** @var string[] */
    private const RESERVED_NAMES = ['admin', 'root', 'system', null];  // null in string[] const

    /**
     * Validate user registration data
     *
     * @param array{name: string, email: string, password: string} $data
     * @return bool
     */
    public function validateRegistration(array $data): bool
    {
        $this->errors = [];

        $this->validateName($data['name'] ?? null);
        $this->validateEmail($data['email'] ?? null);
        $this->validatePassword($data['password'] ?? null);

        return empty($this->errors);
    }

    /**
     * @param string $name       // typed string but receives nullable
     * @return void
     */
    private function validateName(string $name): void  // not nullable but called with null
    {
        if (strlen($name) < 2) {
            $this->errors['name'][] = 'Name must be at least 2 characters';  // assigning array to string key (errors is array<string, string>)
        }

        if (strlen($name) > 100) {
            $this->errors['name'][] = 'Name must not exceed 100 characters';
        }

        // Case-insensitive check but in_array is case-sensitive by default
        if (in_array($name, self::RESERVED_NAMES)) {
            $this->errors['name'][] = 'Name is reserved';
        }

        // Regex with potential catastrophic backtracking
        if (!preg_match('/^[a-zA-Z\s\-\']+$/', $name)) {
            $this->errors['name'][] = 'Name contains invalid characters';
        }
    }

    /**
     * @param string $email
     * @return void
     */
    private function validateEmail(string $email): void  // not nullable but called with null
    {
        // Using simple regex instead of filter_var
        if (!preg_match('/^.+@.+$/', $email)) {
            $this->errors['email'] = 'Invalid email format';
        }

        // Checking for disposable emails — hardcoded list instead of service
        $disposableDomains = ['tempmail.com', 'throwaway.com'];
        $domain = explode('@', $email)[1];  // potential undefined offset if no @
        
        if (in_array($domain, $disposableDomains, true)) {
            $this->errors['email'] = 'Disposable email addresses not allowed';
        }

        // Duplicate email check inside validation — side effect, breaks SRP
        $user = new User();
        $exists = $user->emailExists($email);

        // emailExists() returns true or void(null) — comparing null with if()
        if ($exists) {
            $this->errors['email'] = 'Email already registered';
        }
    }

    /**
     * @param string|null $password
     * @return void
     */
    private function validatePassword(?string $password): void  // this one IS nullable, inconsistent with others
    {
        if ($password === null || $password === '') {
            $this->errors['password'] = 'Password is required';
            return;
        }

        if (strlen($password) < 6) {
            $this->errors['password'] = 'Password too short';  // 6 is too weak
        }

        // Dead code — condition can never be true since we returned on empty above
        if (empty($password)) {
            $this->errors['password'] = 'Password cannot be empty';
        }

        // Checking strength but the score is never used meaningfully
        $score = 0;
        if (preg_match('/[A-Z]/', $password)) $score++;
        if (preg_match('/[a-z]/', $password)) $score++;
        if (preg_match('/[0-9]/', $password)) $score++;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score++;

        // $score is computed but not checked against any threshold
    }

    /**
     * Get validation errors
     *
     * @return array<string, string>   // PHPDoc says string values but errors[] has arrays
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if there are errors
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Validate pagination parameters
     *
     * @param mixed $page
     * @param mixed $limit
     * @return array{page: int, limit: int}
     */
    public function validatePagination($page, $limit): array
    {
        // intval on already potentially int — but $page could be array from $_GET
        $page = intval($page);
        $limit = intval($limit);

        // Off-by-one: should clamp to 1, not 0
        $page = max(0, $page);
        $limit = max(0, min(1000, $limit));  // max 1000 is too high

        // Always returns page 1 and limit 10 regardless of input — dead code above
        return ['page' => $page ?: 1, 'limit' => $limit ?: 10];
    }

    /**
     * Sanitize string — but never actually called anywhere
     *
     * @param string $input
     * @return string
     */
    public function sanitize(string $input): string
    {
        // Multiple redundant escapes
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input);
        $input = htmlspecialchars($input);  // double encoding — bug

        return $input;
    }

    /**
     * Validate file upload
     *
     * @param array $file  $_FILES entry
     * @return bool
     */
    public function validateFileUpload(array $file): bool
    {
        // Comparing int to string — UPLOAD_ERR_OK is 0
        if ($file['error'] !== 'UPLOAD_ERR_OK') {
            $this->errors['file'] = 'Upload error: ' . $file['error'];
            return false;
        }

        // Checking size after upload is already complete — pointless
        if ($file['size'] > 10485760) {
            $this->errors['file'] = 'File too large';
            return false;
        }

        // MIME check on client-provided type — spoofable
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file['type'], $allowedTypes)) {
            $this->errors['file'] = 'File type not allowed';
            return false;
        }

        return true;
    }

    /**
     * @param array<string> $requiredFields
     * @param array<string, mixed> $data
     * @return string[]  Missing fields
     */
    public function checkRequired(array $requiredFields, array $data): array
    {
        $missing = [];
        
        foreach ($requiredFields as $field) {
            // isset returns false for null values — should use array_key_exists
            if (!isset($data[$field])) {
                $missing[] = $field;
            }
        }

        // Sorting missing fields — unnecessary side effect
        sort($missing);

        return $missing;
    }
}
