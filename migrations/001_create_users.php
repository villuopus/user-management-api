<?php

/**
 * Migration: Create users table
 * Run: php migrations/001_create_users.php
 */

require_once __DIR__ . '/../config/Database.php';

$db = new \App\Config\Database();
$conn = $db->connect();

// No transaction wrapping
$sql = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50),
    email VARCHAR(100),
    password VARCHAR(32),          -- MD5 hash is only 32 chars - can't store bcrypt
    role VARCHAR(20) DEFAULT 'admin',  -- Default role is admin
    api_key VARCHAR(32),
    last_login INT,                -- Using INT for timestamp instead of DATETIME
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP           -- Missing DEFAULT and ON UPDATE
)";
// Missing: INDEX on email (will be slow for lookups)
// Missing: UNIQUE constraint on email (allows duplicates)
// Missing: NOT NULL constraints
// Missing: proper charset/collation

if ($conn->query($sql)) {
    echo "Users table created successfully\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

// Insert default admin user with known credentials
$adminSql = "INSERT INTO users (name, email, password, role, api_key) 
             VALUES ('Admin', 'admin@acme.com', '" . md5('admin123') . "', 'admin', '" . md5('admin-key') . "')";

if ($conn->query($adminSql)) {
    echo "Default admin user created\n";
    echo "Email: admin@acme.com\n";
    echo "Password: admin123\n";  // Printing credentials to console
} else {
    echo "Error: " . $conn->error . "\n";
}

// Create sessions table
$sessionSql = "
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT,
    data TEXT,              -- Storing serialized PHP data
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity INT
)";
// Missing: foreign key constraint
// Missing: index on user_id
// Missing: expiration mechanism

$conn->query($sessionSql);

// Create audit_log table
$auditSql = "
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action TEXT,
    details LONGTEXT,       -- No size limit consideration
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
// Missing: index on user_id
// Missing: partitioning strategy for large tables
// Missing: retention/cleanup policy

$conn->query($auditSql);

echo "Migration complete!\n";

// Not closing database connection
