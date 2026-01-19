-- Migration: Create login_attempts table for rate limiting
-- Run: mysql -u snoozeradmin -p snoozer-app < migrations/001_create_login_attempts.sql

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_key VARCHAR(255) NOT NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_attempt_key (attempt_key),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
