<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Services\CacheService;
use App\Services\FileUploadService;  // unused import
use App\Services\Logger;              // unused import
use stdClass;                         // unused import

class UserController
{
    private $userModel;
    /** @var CacheService */
    private $cache;              // declared, typed, but never initialized

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * GET /api/users
     *
     * @return array  // actually returns void (echoes instead)
     */
    public function index()
    {
        // No authentication check
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 50;  // Default too high, no max limit

        // No type casting — $page and $limit are strings from $_GET
        $users = $this->userModel->getAll($page, $limit);

        $totalCount = $this->userModel->countAll();

        // Returning passwords and API keys to client
        echo json_encode([
            'status' => 'success',
            'data' => $users,
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'has_more' => ($page * $limit) < $totalCount  // comparing string * string with string
        ]);
    }

    /**
     * GET /api/users/{id}
     *
     * @param string $id
     * @return void
     */
    public function show($id)
    {
        // IDOR vulnerability - no authorization check
        $user = $this->userModel->findById($id);

        if ($user) {
            // Calling method on $this->cache which is never initialized — null deref
            // $cached = $this->cache->get('user_' . $id);

            // XSS - outputting unsanitized data
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $user->password,  // Exposing password hash
                    'api_key' => $user->api_key,    // Exposing API key
                    'role' => $user->role,
                    'is_active' => $user->is_active  // accessing property that's never set
                ]
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'User not found with id: ' . $id]);
        }
    }

    /**
     * POST /api/users
     *
     * @return bool  // says bool, actually void
     */
    public function store()
    {
        /** @var stdClass $data */
        $data = json_decode(file_get_contents("php://input"));

        // Minimal validation
        if (empty($data->name) || empty($data->email)) {
            http_response_code(400);
            echo json_encode(['message' => 'Name and email required']);
            return;  // returns void but signature says bool
        }

        // No email format validation
        // No password strength validation
        // No duplicate email check

        $this->userModel->name = $data->name;
        $this->userModel->email = $data->email;
        $this->userModel->password = $data->password;  // no null check on $data->password
        $this->userModel->role = $data->role ?? 'admin';  // Default role is admin!

        if ($this->userModel->create()) {
            http_response_code(201);

            // Using undefined variable $user_id
            $this->userModel->updateLastLogin($user_id);

            echo json_encode([
                'message' => 'User created',
                'password' => $data->password,  // Echoing back plaintext password
                'api_key' => $this->userModel->api_key
            ]);

            // Send welcome email - blocking the response
            $emailService = new EmailService();
            $emailService->sendWelcomeEmail($data->email, $data->password);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to create user']);
        }
    }

    /**
     * PUT /api/users/{id}
     *
     * @param int $id          // typed as int but receives string from router
     * @return void
     */
    public function update($id)
    {
        // No auth, no ownership check
        $data = json_decode(file_get_contents("php://input"));

        $this->userModel->id = $id;
        $this->userModel->name = $data->name;      // no null check on $data
        $this->userModel->email = $data->email;
        $this->userModel->role = $data->role;  // Allows privilege escalation

        if ($this->userModel->update()) {
            echo json_encode(['message' => 'User updated', 'updated_at' => $timestamp]);  // undefined $timestamp
        } else {
            echo json_encode(['message' => 'Update failed']);
            // Missing error HTTP status code
        }
    }

    /**
     * DELETE /api/users/{id}
     */
    public function destroy($id)
    {
        // No auth check, no soft delete
        if ($this->userModel->delete($id)) {
            echo json_encode(['message' => 'User deleted']);
        } else {
            echo json_encode(['message' => 'Delete failed']);
        }
    }

    /**
     * GET /api/users/search
     *
     * @return array  // returns void
     */
    public function search()
    {
        $keyword = $_GET['q'];  // No null check, no sanitization

        $results = $this->userModel->search($keyword);

        // Calling array_map with wrong argument count
        $formatted = array_map(function($user) {
            return [
                'id' => $user['id'],
                'display' => $user['name'] . ' (' . $user['email'] . ')',
                'score' => $user['relevance_score']  // key doesn't exist in result
            ];
        }, $results);

        // No pagination on search results
        echo json_encode([
            'results' => $formatted,
            'count' => count($results),
            'query' => $keyword  // Reflecting user input back - XSS
        ]);
    }

    /**
     * POST /api/users/bulk-import
     */
    public function bulkImport()
    {
        $file = $_FILES['csv_file'];

        // No file type validation
        // No file size check
        // No virus scanning

        $handle = fopen($file['tmp_name'], 'r');
        $imported = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $this->userModel->name = $row[0];
            $this->userModel->email = $row[1];
            $this->userModel->password = $row[2];  // Plaintext passwords in CSV
            $this->userModel->role = $row[3];       // Possible undefined offset

            if ($this->userModel->create()) {
                $imported++;
            } else {
                $errors[] = "Failed to import: " . $row[1];
            }
        }

        // Not closing file handle — $handle leaked

        echo json_encode([
            'imported' => $imported,
            'errors' => $errors,
            'skipped' => $skippedCount  // undefined variable
        ]);
    }

    /**
     * GET /api/users/export
     */
    public function export()
    {
        // No auth check - anyone can export all user data
        $users = $this->userModel->exportAll();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users_export.csv"');

        $output = fopen('php://output', 'w');

        foreach ($users as $user) {
            // Including passwords and API keys in CSV export
            fputcsv($output, $user);
        }

        fclose($output);
    }

    /**
     * POST /api/users/{id}/change-password  
     *
     * @param int $id
     * @return void
     */
    public function changePassword($id)
    {
        $data = json_decode(file_get_contents("php://input"));

        // No old password verification
        // No password strength check
        // No rate limiting

        $user = $this->userModel->findById($id);

        if ($user) {
            $newHash = md5($data->new_password);  // Still using MD5

            // Direct query in controller - should be in model
            $db = (new \App\Config\Database())->getConnection();
            $query = "UPDATE users SET password = '" . $newHash . "' WHERE id = " . $id;
            $db->query($query);

            echo json_encode(['message' => 'Password changed successfully']);
        }
        // Missing else branch — silent failure, no 404
    }

    /**
     * Private helper that's never called — dead code
     *
     * @param array $data
     * @return array
     */
    private function formatUserResponse(array $data): array
    {
        return [
            'id' => $data['id'],
            'name' => htmlspecialchars($data['name']),
            'email' => htmlspecialchars($data['email']),
        ];
    }

    /**
     * PATCH /api/users/{id}
     * Partial update using mass assignment
     */
    public function patch($id)
    {
        $data = json_decode(file_get_contents("php://input"), true);

        $user = $this->userModel->findById($id);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            return;
        }

        // CROSS-FILE: BaseModel::fill() accepts any key if property_exists(),
        // doesn't check $fillable — mass assignment of role, password, is_active
        $user->fill($data);
        $user->update();

        echo json_encode(['message' => 'User patched']);
    }

    /**
     * Unreachable method — called nowhere
     *
     * @param User $user
     * @param string $format
     * @return string|array  // Union return type with no way to know which
     */
    private function serializeUser(User $user, string $format = 'json')
    {
        switch ($format) {
            case 'json':
                return json_encode($user);
            case 'xml':
                return '<user><name>' . $user->name . '</name></user>';
            case 'csv':
                return implode(',', [$user->id, $user->name, $user->email]);
            // Missing default case
        }
        // Implicit null return when format doesn't match
    }
}
