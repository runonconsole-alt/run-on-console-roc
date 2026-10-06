-- ============================================================
-- Run On Console (ROC) - Migration 002: Upgrade Previous Phase 1 Schema
-- Upgrades existing database without deleting users or production data
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Create schema_migrations tracking table if missing
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `version` VARCHAR(50) NOT NULL PRIMARY KEY,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Modify existing users table to ensure id is standard INT
ALTER TABLE `users` MODIFY `id` INT AUTO_INCREMENT;

-- 3. Create user_profiles table if missing
CREATE TABLE IF NOT EXISTS `user_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `country` VARCHAR(50) DEFAULT 'United States',
  `currency` VARCHAR(10) DEFAULT 'USD',
  `avatar_icon` VARCHAR(50) DEFAULT 'gamepad',
  `avatar_bg` VARCHAR(100) DEFAULT 'from-emerald-600 to-teal-500',
  `bio` TEXT DEFAULT NULL,
  `marketing_opt_in` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create dedicated request_rate_limits table if missing
CREATE TABLE IF NOT EXISTS `request_rate_limits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `email_hash` VARCHAR(64) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_rrl_ip_action` (`ip_address`, `action`, `attempted_at`),
  INDEX `idx_rrl_email_action` (`email_hash`, `action`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create user_preferences table if missing
CREATE TABLE IF NOT EXISTS `user_preferences` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `theme` VARCHAR(20) DEFAULT 'dark',
  `email_notifications` TINYINT(1) NOT NULL DEFAULT 1,
  `price_alert_thresholds` JSON DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_prefs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Record Migration Applied
INSERT INTO `schema_migrations` (`version`) VALUES ('002_upgrade_previous_phase1') ON DUPLICATE KEY UPDATE `applied_at` = NOW();

SET FOREIGN_KEY_CHECKS = 1;
