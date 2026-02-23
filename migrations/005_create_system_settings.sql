-- Migration 005: System settings table for tracking cron health and other app-level state
CREATE TABLE IF NOT EXISTS `system_settings` (
  `key` VARCHAR(50) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
