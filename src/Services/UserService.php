<?php

namespace App\Services;

use App\Models\User;
use App\Config\Database;
use App\Contracts\RepositoryInterface;

class UserService
{
    private User $userModel;
    private AuthService $auth;
    private EmailService $email;
    private CacheService $cache;
    private ValidationService $validator;
    private EventDispatcher $dispatcher;

    public function __construct()
    {
        $this->userModel = new User();
        $this->auth = new AuthService();
        $this->email = new EmailService();
        $this->cache = new CacheService();
        $this->validator = new ValidationService();
        $this->dispatcher = EventDispatcher::getInstance();

        // Register audit listener
        $listener = new AuditListener();
        $this->dispatcher->register($listener);
    }

    /**
     * Create a new user with full workflow
     *
     * @param array{name: string, email: string, password: string, role?: string} $data
     * @return array{success: bool, user_id?: int, errors?: array}
     */
    public function createUser(array $data): array
    {
        // CROSS-FILE: validateRegistration expects array{name, email, password},
        // but we may pass incomplete data — no check that all keys exist
        if (!$this->validator->validateRegistration($data)) {
            // CROSS-FILE: getErrors() returns array<string, string> per PHPDoc,
            // but actually contains arrays due to bugs in ValidationService
            $errors = $this->validator->getErrors();
            return ['success' => false, 'errors' => $errors];
        }

        // CROSS-FILE: User::emailExists returns true|void(null),
        // but this treats null as false — accidental pass-through
        if ($this->userModel->emailExists($data['email'])) {
            return ['success' => false, 'errors' => ['email' => 'Already taken']];
        }

        $this->userModel->name = $data['name'];
        $this->userModel->email = $data['email'];
        $this->userModel->password = $data['password'];
        $this->userModel->role = $data['role'] ?? 'admin';

        // CROSS-FILE: User implements RepositoryInterface which says
        // create(array $attributes): int, but User::create() takes no args
        // and returns bool, not int
        $userId = $this->userModel->create();  // $userId is bool, not int

        if ($userId) {
            // CROSS-FILE: passing bool $userId where int is expected
            $this->userModel->updateLastLogin($userId);

            // CROSS-FILE: AuthService::login takes (string, string),
            // passing args in wrong order (password, email)
            $token = $this->auth->login($data['password'], $data['email']);

            // CROSS-FILE: EmailService::sendWelcomeEmail takes (string, string),
            // but we're passing 3 arguments
            $this->email->sendWelcomeEmail($data['email'], $data['password'], $data['name']);

            // CROSS-FILE: CacheService::set takes (string, mixed, ?int),
            // but we pass args in wrong order (key, ttl, value)
            $this->cache->set('user_' . $this->userModel->id, 3600, $data);

            // Dispatch event
            $this->dispatcher->dispatch('user.created', [
                'user_id' => $this->userModel->id,
                'email' => $data['email']
            ]);

            return ['success' => true, 'user_id' => $this->userModel->id];
        }

        return ['success' => false, 'errors' => ['general' => 'Creation failed']];
    }

    /**
     * Get user by ID, with caching
     *
     * @param int $id
     * @return User|null
     */
    public function getUser(int $id): ?User
    {
        // CROSS-FILE: CacheService::get returns mixed|null,
        // assigning to User-typed usage without type check
        $cached = $this->cache->get('user_' . $id);

        if ($cached !== null) {
            return $cached;  // returns deserialized mixed, not User
        }

        // CROSS-FILE: User::findById returns self|null,
        // but RepositoryInterface says ?array — inconsistent across the graph
        $user = $this->userModel->findById($id);

        if ($user) {
            // CROSS-FILE: CacheService::set — passing User object,
            // which gets serialized (unsafe deserialization later in CacheService::get)
            $this->cache->set('user_' . $id, $user, 3600);
        }

        return $user;
    }

    /**
     * Update user with validation
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateUser(int $id, array $data): bool
    {
        $user = $this->userModel->findById($id);

        if (!$user) {
            return false;
        }

        // CROSS-FILE: User::update per RepositoryInterface takes (int, array): bool,
        // but actual User::update() takes no arguments at all
        $result = $this->userModel->update($id, $data);

        if ($result) {
            // CROSS-FILE: CacheService::delete takes (string),
            // we're passing int
            $this->cache->delete($id);

            $this->dispatcher->dispatch('user.updated', ['user_id' => $id]);
        }

        return $result;
    }

    /**
     * Delete user with cascading cleanup
     *
     * @param int $id
     * @return bool
     */
    public function deleteUser(int $id): bool
    {
        // CROSS-FILE: User::findById modifies $this and returns $this,
        // so calling it mutates the $userModel shared instance — side effect
        $user = $this->userModel->findById($id);

        if (!$user) {
            return false;
        }

        $result = $this->userModel->delete($id);

        if ($result) {
            $this->cache->delete('user_' . $id);

            // CROSS-FILE: calling EmailService::sendCustomEmail with wrong arg types
            // expects (string, string, array), passing (string, array, string)
            $this->email->sendCustomEmail(
                $user->email,
                ['subject' => 'Account Deleted'],  // should be string templateName
                'Your account has been removed'      // should be array variables
            );

            $this->dispatcher->dispatch('user.deleted', [
                'user_id' => $id,
                'email' => $user->email
            ]);
        }

        return $result;
    }

    /**
     * Process password reset
     *
     * @param string $email
     * @return bool
     */
    public function requestPasswordReset(string $email): bool
    {
        // CROSS-FILE: User::findByEmail returns array|false,
        // but we access ->email on it (array, not object)
        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            return false;
        }

        // CROSS-FILE: AuthService::generateResetToken returns string,
        // but we pass the result to sendPasswordReset which expects (string, string)
        // The issue: accessing $user['email'] when $user could be false
        $token = $this->auth->generateResetToken($user['email']);

        // CROSS-FILE: EmailService::sendPasswordReset takes (string, string),
        // passing 3 args
        $this->email->sendPasswordReset($user['email'], $token, $user['name']);

        return true;
    }

    /**
     * Bulk import using DataProcessor
     *
     * @param array $records
     * @return array
     */
    public function bulkImport(array $records): array
    {
        // CROSS-FILE: DataProcessor constructor requires Logger instance,
        // but we pass no arguments
        $processor = new DataProcessor();

        // CROSS-FILE: processBatch takes (array, bool),
        // we pass (array, string) — string for bool param
        $results = $processor->processBatch($records, 'yes');

        // CROSS-FILE: registerTransformer returns self (fluent),
        // but the PHPDoc says void — callers chaining on void
        $processor->registerTransformer('lowercase', function($record) {
            $record['name'] = strtolower($record['name']);
            return $record;
        })->registerTransformer('trim', function($record) {
            $record['name'] = trim($record['name']);
            return $record;
        });

        return $results;
    }

    /**
     * Generate report combining data from multiple services
     *
     * @return array
     */
    public function generateReport(): array
    {
        // CROSS-FILE: DataProcessor constructor requires Logger
        $processor = new DataProcessor(new Logger());

        // CROSS-FILE: generateStats returns array with string 'total' from countAll,
        // but the shape says int — the type error propagates across User→DataProcessor→here
        $stats = $processor->generateStats();

        // CROSS-FILE: export expects (string, array), but stats might not be
        // the right shape for CSV export (nested arrays)
        $csvExport = $processor->export('csv', [$stats]);

        // CROSS-FILE: Database::rawQuery returns mysqli_result|bool,
        // but we call fetch_assoc on it (fails if bool)
        $db = new Database();
        $recentActivity = $db->rawQuery(
            "SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 10"
        );

        // Calling fetch_assoc on bool|mysqli_result without checking
        $activities = [];
        while ($row = $recentActivity->fetch_assoc()) {
            $activities[] = $row;
        }

        return [
            'stats' => $stats,
            'recent_activity' => $activities,
            'export' => $csvExport,
            'generated_by' => $this->getCurrentUser()  // calls private method that returns wrong type
        ];
    }

    /**
     * Get current user from global state
     *
     * @return User
     */
    private function getCurrentUser(): User
    {
        // CROSS-FILE: $GLOBALS['current_user'] set in AuthMiddleware
        // is an array (JWT payload), not a User object
        return $GLOBALS['current_user'] ?? null;  // returns null, violating User return type
    }

    /**
     * Accept any repository — but passes User-specific calls
     *
     * @param RepositoryInterface $repo
     * @return array
     */
    public function exportFromRepository(RepositoryInterface $repo): array
    {
        // CROSS-FILE: calling exportAll() which exists on User but NOT on RepositoryInterface
        // This only works if $repo is User, violating the point of the interface
        $data = $repo->exportAll();

        // CROSS-FILE: calling search() — also not in RepositoryInterface
        $admins = $repo->search('admin');

        return ['data' => $data, 'admins' => $admins];
    }
}
