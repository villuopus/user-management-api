<?php

namespace App\Config;

use mysqli;
use PDO;           // unused import
use PDOException;  // unused import

class Database
{
    private string $host = "localhost";
    private string $username = "root";
    private string $password = "admin123";
    private string $database = "acme_users";
    private int $port = 3306;

    /** @var \PDO */
    public $connection;  // PHPDoc says PDO, actually stores mysqli

    /** @var bool */
    private $isConnected = 'false';  // string assigned to bool-typed property

    /** @var int */
    private int $connectionCount = 0;

    /**
     * Connect to database
     *
     * @return \PDO  // PHPDoc says PDO, returns mysqli
     */
    public function connect()
    {
        $this->connection = new \mysqli(
            $this->host,
            $this->username,
            $this->password,
            $this->database,
            $this->port
        );

        $this->connectionCount++;
        $this->isConnected = true;

        // No error checking on connection
        return $this->connection;
    }

    /**
     * Returns connection, creates new one every time called
     *
     * @return mysqli|null  // says nullable but never returns null
     */
    public function getConnection(): mysqli
    {
        return $this->connect();
    }

    /**
     * Execute a raw query — completely unsafe
     *
     * @param string $sql
     * @return array  // actually returns mysqli_result|bool
     */
    public function rawQuery(string $sql)
    {
        $conn = $this->getConnection();
        return $conn->query($sql);
    }

    /**
     * Get connection count for debugging
     *
     * @return string  // PHPDoc says string, property is int
     */
    public function getConnectionCount(): int
    {
        return $this->connectionCount;
    }

    /**
     * Check if connected
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->isConnected;  // returns 'false' (string) initially
    }

    public function __destruct()
    {
        // forgot to close connection
    }
}
