<?php

namespace App\Services;

class ApiResponse
{
    /**
     * Send a success response
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return never  (terminates execution)
     */
    public static function success($data, string $message = 'OK', int $statusCode = 200): never
    {
        http_response_code($statusCode);
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }

    /**
     * Send an error response
     *
     * @param string $message
     * @param int $statusCode
     * @param array|null $errors  Additional error details
     * @return never
     */
    public static function error(string $message, int $statusCode = 400, ?array $errors = null): never
    {
        http_response_code($statusCode);
        $response = [
            'status' => 'error',
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        echo json_encode($response);
        exit;
    }

    /**
     * Send a paginated response
     *
     * @param array $data
     * @param int $page
     * @param int $limit
     * @param int $total
     * @return never
     */
    public static function paginated(array $data, int $page, int $limit, int $total): never
    {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit),  // division by zero if limit = 0
                'has_next' => ($page * $limit) < $total,
                'has_prev' => $page > 1
            ]
        ]);
        exit;
    }

    /**
     * Transform a collection of models before sending
     * 
     * @param array $items
     * @param callable(array): array $transformer
     * @return array
     */
    public static function transformCollection(array $items, callable $transformer): array
    {
        return array_map($transformer, $items);
    }
}
