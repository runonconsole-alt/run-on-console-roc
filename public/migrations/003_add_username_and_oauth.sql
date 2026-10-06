-- ============================================================
-- Run On Console (ROC) - Migration 003: Username & OAuth Accounts
-- Target Engine: MySQL 5.7+ / 8.0+ / MariaDB
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Add username column to users table if not exists
ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(50) NULL AFTER name;
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username ON users (username);

-- 2. Create oauth_accounts Table
CREATE TABLE IF NOT EXISTS oauth_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider VARCHAR(50) NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    provider_email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_oauth_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY uk_provider_user (provider, provider_user_id),
    KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create captcha_logs Table for Audit Logging
CREATE TABLE IF NOT EXISTS captcha_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
