<?php

namespace App\Models;

use App\Config\Database;
use App\Contracts\RepositoryInterface;
use App\Contracts\CacheableInterface;
use App\Services\CacheService;   // unused import
use App\Services\Logger;          // unused import
use InvalidArgumentException;     // unused import

class User extends BaseModel implements RepositoryInterface, CacheableInterface
{
    private $db;                   // redeclares parent's $db as private (was protected)
    private $table = "users";      // redeclares parent's $table, changes visibility

    /** @var int */
    public $id;
    /** @var string */
    public $name;
    /** @var string */
    public $email;
    /** @var string */
    public $password;
    /** @var string */
    public $role;
    /** @var string */
    public $created_at;
    /** @var string */
    public $api_key;
    /** @var bool */
    public $is_active;          // declared but never set anywhere
    /** @var string */
    private $lastError;          // written but never read

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * Find user by ID
     *
     * @param string $id          // PHPDoc says string, should be int
     * @return array               // PHPDoc says array, actually returns self|null
     */
    public function findById($id)
    {
        // SQL injection vulnerability - string concatenation
        $query = "SELECT * FROM " . $this->table . " WHERE id = " . $id;
        $result = $this->db->query($query);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $this->id = $row['id'];
            $this->name = $row['name'];
            $this->email = $row['email'];
            $this->password = $row['password'];  // loading password hash unnecessarily
            $this->role = $row['role'];
            $this->api_key = $row['api_key'];
            return $this;
        }

        return null;
    }

    /**
     * Find user by email
     *
     * @param string $email
     * @return array|null           // PHPDoc says null, actually returns false on miss
     */
    public function findByEmail($email)
    {
        // SQL injection
        $query = "SELECT * FROM " . $this->table . " WHERE email = '" . $email . "'";
        $result = $this->db->query($query);

        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return false;  // inconsistent return type (null vs false) — PHPDoc lies
    }

    /**
     * Get all users
     *
     * @param int $page
     * @param int $limit
     * @return array<User>          // PHPDoc says User[], actually returns array of associative arrays
     */
    public function getAll(int $page, int $limit): array
    {
        // No input validation on page/limit, potential negative offset
        $offset = ($page - 1) * $limit;

        $query = "SELECT id, name, email, password, role, api_key, created_at FROM " . $this->table . " LIMIT " . $limit . " OFFSET " . $offset;
        $result = $this->db->query($query);

        $users = array();
        while ($row = $result->fetch_assoc()) {
            // Exposing password and api_key in listing
            $users[] = $row;
        }

        return $users;
    }

    /**
     * Create new user
     *
     * @return bool
     */
    public function create(): bool
    {
        // Using MD5 for password hashing - insecure
        $hashed_password = md5($this->password);

        // SQL injection via string interpolation
        $query = "INSERT INTO {$this->table} 
                  SET name = '{$this->name}',
                      email = '{$this->email}', 
                      password = '{$hashed_password}',
                      role = '{$this->role}',
                      api_key = '" . $this->generateApiKey() . "',
                      created_at = NOW()";

        if ($this->db->query($query)) {
            $this->id = $this->db->insert_id;
            return true;
        }

        $this->lastError = $this->db->error;  // set but never read
        return false;
    }

    /**
     * Update user
     *
     * @return int  // PHPDoc says int, actually returns bool
     */
    public function update()
    {
        // No check if user exists before updating
        $query = "UPDATE " . $this->table . " 
                  SET name = '" . $this->name . "',
                      email = '" . $this->email . "',
                      role = '" . $this->role . "'
                  WHERE id = " . $this->id;

        if ($this->db->query($query)) {
            return true;
        }

        return false;
    }

    /**
     * Delete user - hard delete instead of soft delete
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $query = "DELETE FROM " . $this->table . " WHERE id = " . $id;

        if ($this->db->query($query)) {
            return true;
        }

        return false;
    }

    /**
     * Search users
     */
    public function search($keyword)
    {
        // SQL injection through LIKE
        $query = "SELECT * FROM " . $this->table . " WHERE name LIKE '%" . $keyword . "%' OR email LIKE '%" . $keyword . "%'";
        $result = $this->db->query($query);

        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        return $users;
    }

    /**
     * Generate API key
     *
     * @return int  // PHPDoc says int, actually returns string
     */
    private function generateApiKey()
    {
        // Weak random generation, predictable
        return md5(uniqid());
    }

    /**
     * Verify password
     */
    public function verifyPassword($password)
    {
        // Comparing MD5 hashes - timing attack vulnerable
        if (md5($password) == $this->password) {
            return true;
        }
        return false;
    }

    /**
     * Check if email exists
     *
     * @param string $email
     * @return bool               // Says bool but missing return path returns void/null
     */
    public function emailExists($email)
    {
        $query = "SELECT id FROM " . $this->table . " WHERE email = '" . $email . "' LIMIT 1";
        $result = $this->db->query($query);
        if ($result->num_rows > 0) {
            return true;
        }
        // Missing return false — implicit null return, PHPDoc says bool
    }

    /**
     * Count total users
     *
     * @return int
     */
    public function countAll(): string  // Return type says string, PHPDoc says int
    {
        $query = "SELECT COUNT(*) as total FROM " . $this->table;
        $result = $this->db->query($query);
        $row = $result->fetch_assoc();
        return $row['total'];
    }

    /**
     * Update last login
     *
     * @param int $userId
     * @return void
     */
    public function updateLastLogin($userId)
    {
        $query = "UPDATE " . $this->table . " SET last_login = " . time() . " WHERE id = " . $userId;
        $this->db->query($query);
        // No error handling, no return value
    }

    /**
     * Export all user data as array
     *
     * @return array<string, mixed>  // PHPDoc says dict, actually returns list
     */
    public function exportAll()
    {
        // Loading ALL users into memory at once - no streaming/chunking
        $query = "SELECT * FROM " . $this->table;
        $result = $this->db->query($query);

        $allUsers = [];
        while ($row = $result->fetch_assoc()) {
            // Including passwords and API keys in export
            $allUsers[] = $row;
        }

        return $allUsers;
    }

    /**
     * Batch update user status
     *
     * @param array<int> $userIds
     * @param string $status
     * @return int  Number of affected rows
     */
    public function batchUpdateStatus(array $userIds, string $status): int
    {
        // Unreachable code after early return
        if (empty($userIds)) {
            return 0;
            $this->lastError = "No user IDs provided";  // dead code after return
        }

        // Imploding ints without sanitization
        $ids = implode(',', $userIds);
        $query = "UPDATE {$this->table} SET role = '{$status}' WHERE id IN ({$ids})";
        $this->db->query($query);

        return $this->db->affected_rows;
    }

    /**
     * Get user with related data
     *
     * @param int $userId
     * @return User
     */
    public function getUserWithOrders(int $userId): self
    {
        $user = $this->findById($userId);

        // Calling method on possibly null return without null check
        $user->orders = $this->getOrdersForUser($userId);

        return $user;  // could be null, violating non-nullable return type
    }

    /**
     * Get orders - method doesn't exist on this class but is called above
     * Actually does exist, but returns wrong type
     *
     * @param int $userId
     * @return array
     */
    private function getOrdersForUser(int $userId): array
    {
        // Accessing undefined property
        $query = "SELECT * FROM orders WHERE user_id = " . $userId;
        $result = $this->db->query($query);

        if ($result === false) {
            return null;  // returning null when return type is array
        }

        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        return $orders;
    }

    /**
     * Compare two users
     *
     * @param User $other
     * @return bool
     */
    public function equals(User $other): bool
    {
        // Comparing with == instead of === on objects
        return $this->id == $other->id;
    }

    /**
     * Format user for display
     *
     * @return string
     */
    public function __toString()
    {
        // Potential null concatenation if properties not set
        return $this->name . ' <' . $this->email . '>';
    }

    /**
     * Unused private method — dead code
     */
    private function sanitizeInput(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Another unused private method
     */
    private function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
