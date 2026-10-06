-- ============================================================
-- Run On Console (ROC) - Migration 004: Email Verification Challenges & Events
-- Target Engine: MySQL 5.7+ / 8.0+ / MariaDB
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Create email_verification_challenges Table
CREATE TABLE IF NOT EXISTS email_verification_challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    link_token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts INT DEFAULT 0,
    consumed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_challenge_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    KEY idx_user_id (user_id),
    KEY idx_link_token (link_token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create email_events Table for Delivery & Audit Audit Logging
CREATE TABLE IF NOT EXISTS email_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    queue_id INT NULL,
    user_id INT NULL,
    event_type VARCHAR(50) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_queue_id (queue_id),
    KEY idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
