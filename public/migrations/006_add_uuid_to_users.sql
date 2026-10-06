-- ============================================================
-- Run On Console (ROC) - Migration 006: Add UUID Column to Users
-- Target Engine: MySQL 5.7+ / 8.0+ / MariaDB
-- Safe, Idempotent Database Schema Update for users table
-- ============================================================

SET @dbname = DATABASE();
SET @tablename = "users";
SET @columnname = "uuid";

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `users` ADD COLUMN `uuid` VARCHAR(64) DEFAULT NULL AFTER `id`;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Backfill UUIDs for any existing users with NULL uuid
UPDATE `users` SET `uuid` = CONCAT('usr-', LOWER(HEX(RANDOM_BYTES(8)))) WHERE `uuid` IS NULL OR `uuid` = '';
