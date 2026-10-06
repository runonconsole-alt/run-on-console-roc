-- ============================================================
-- Run On Console (ROC) - Migration 005: Add Email Worker Locking Columns
-- Target Engine: MySQL 5.7+ / 8.0+ / MariaDB
-- Safe, Idempotent Database Schema Update for email_queue
-- ============================================================

-- 1. Modify status column to VARCHAR(50) to support all worker states ('pending', 'processing', 'sent', 'failed', 'retry')
ALTER TABLE `email_queue` 
  MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'pending';

-- 2. Add missing worker columns safely if they do not exist
SET @dbname = DATABASE();

-- Add locked_by column
SET @tablename = "email_queue";
SET @columnname = "locked_by";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `email_queue` ADD COLUMN `locked_by` VARCHAR(100) DEFAULT NULL AFTER `status`;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add locked_at column
SET @columnname = "locked_at";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `email_queue` ADD COLUMN `locked_at` DATETIME DEFAULT NULL AFTER `locked_by`;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add next_attempt_at column
SET @columnname = "next_attempt_at";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `email_queue` ADD COLUMN `next_attempt_at` DATETIME DEFAULT NULL AFTER `locked_at`;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add sent_at column
SET @columnname = "sent_at";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `email_queue` ADD COLUMN `sent_at` DATETIME DEFAULT NULL AFTER `error_message`;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3. Add Index on (status, locked_by)
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND INDEX_NAME = "idx_eq_status_locked"
  ) > 0,
  "SELECT 1",
  "CREATE INDEX `idx_eq_status_locked` ON `email_queue` (`status`, `locked_by`);"
));
PREPARE addIndexIfNotExists FROM @preparedStatement;
EXECUTE addIndexIfNotExists;
DEALLOCATE PREPARE addIndexIfNotExists;
