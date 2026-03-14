<?php

namespace App\Services;

class Logger
{
    private $logFile;
    private $logLevel = 'DEBUG';  // Too verbose for production
    private static $instance = null;

    // Broken singleton - constructor is public
    public function __construct($logFile = '/tmp/app.log')
    {
        $this->logFile = $logFile;
    }

    public static function getInstance()
    {
        if (self::$instance == null) {  // Should use === for null check
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log a message
     */
    public function log($level, $message, $context = [])
    {
        // No log level filtering
        $timestamp = date('Y-m-d H:i:s');

        // Log injection vulnerability - unsanitized message
        $logEntry = "[{$timestamp}] [{$level}] {$message}";

        if (!empty($context)) {
            // Serializing context which may contain sensitive data
            $logEntry .= " | Context: " . json_encode($context);
        }

        $logEntry .= "\n";

        // No file rotation - log file grows forever
        // No file locking
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);

        // Also write to error_log for everything
        error_log($logEntry);
    }

    public function debug($message, $context = [])
    {
        $this->log('DEBUG', $message, $context);
    }

    public function info($message, $context = [])
    {
        $this->log('INFO', $message, $context);
    }

    public function warning($message, $context = [])
    {
        $this->log('WARNING', $message, $context);
    }

    public function error($message, $context = [])
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Log request details
     */
    public function logRequest()
    {
        // Logging everything including sensitive headers and body
        $requestData = [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'ip' => $_SERVER['REMOTE_ADDR'],
            'headers' => getallheaders(),  // Includes Authorization header with tokens
            'body' => file_get_contents('php://input'),  // May contain passwords
            'cookies' => $_COOKIE,  // Logging all cookies
            'get' => $_GET,
            'post' => $_POST,
            'files' => $_FILES
        ];

        $this->info('Incoming request', $requestData);
    }

    /**
     * Log exception
     */
    public function logException($exception)
    {
        $this->error('Exception: ' . $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'server_info' => php_uname(),  // Leaking server info
            'php_version' => phpversion(),
            'extensions' => get_loaded_extensions()  // Leaking loaded extensions
        ]);
    }

    /**
     * Get log contents - for admin dashboard
     */
    public function getRecentLogs($lines = 100)
    {
        // Reading entire file to get last N lines - inefficient
        $content = file_get_contents($this->logFile);
        $allLines = explode("\n", $content);

        return array_slice($allLines, -$lines);
    }

    /**
     * Search logs
     */
    public function searchLogs($query)
    {
        // Loading entire log file into memory
        $content = file_get_contents($this->logFile);
        $lines = explode("\n", $content);
        $matches = [];

        foreach ($lines as $line) {
            // grep-style search with no sanitization
            if (stripos($line, $query) !== false) {
                $matches[] = $line;
            }
        }

        return $matches;
    }
}
