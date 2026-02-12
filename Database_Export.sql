-- Snoozer Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.2+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table: users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(320) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `emailVerified` tinyint(1) DEFAULT 0,
  `DefaultReminderTime` int(2) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT 'UTC',
  `theme` varchar(20) DEFAULT 'dark',
  `sslkey` tinyblob,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: emails
-- ----------------------------
DROP TABLE IF EXISTS `emails`;
CREATE TABLE `emails` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` text NOT NULL,
  `fromaddress` varchar(320) NOT NULL,
  `toaddress` varchar(320) NOT NULL,
  `header` text NOT NULL,
  `subject` text NOT NULL,
  `timestamp` int(11) NOT NULL,
  `processed` tinyint(1) DEFAULT NULL,
  `actiontimestamp` int(11) DEFAULT NULL,
  `sslkey` tinyblob NOT NULL,
  `catID` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  INDEX `idx_message_id` (`message_id`(255)),
  INDEX `idx_fromaddress` (`fromaddress`),
  INDEX `idx_toaddress` (`toaddress`),
  INDEX `idx_processed` (`processed`),
  INDEX `idx_actiontimestamp` (`actiontimestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: emailCategory (Kanban columns)
-- ----------------------------
DROP TABLE IF EXISTS `emailCategory`;
CREATE TABLE `emailCategory` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(65) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `emailCategory` (`ID`, `Name`) VALUES
(1, 'Delayed'),
(2, 'Delegated'),
(3, 'Doing'),
(4, 'Dusted');

-- ----------------------------
-- Table: email_templates
-- ----------------------------
DROP TABLE IF EXISTS `email_templates`;
CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `variables` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `email_templates` (`slug`, `subject`, `body`, `variables`) VALUES
('wrapper', NULL, '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
    .header { text-align: center; padding: 20px 0; border-bottom: 2px solid #7d3c98; }
    .content { padding: 20px 0; }
    .footer { text-align: center; padding: 20px 0; font-size: 12px; color: #888; border-top: 1px solid #eee; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1 style="color: #7d3c98; margin: 0;">Snoozer</h1>
    </div>
    <div class="content">
      {{CONTENT}}
    </div>
    <div class="footer">
      <p>Reach &amp; maintain a zero inbox status</p>
    </div>
  </div>
</body>
</html>', '{{TITLE}}, {{CONTENT}}'),
('reminder', 'RE: {{SUBJECT}}', '<h2>Reminder: {{SUBJECT}}</h2>
<p>This is your scheduled reminder.</p>
<div style="margin: 20px 0;">
  {{SNOOZE_BUTTONS}}
</div>
<p style="margin-top: 20px;">
  <a href="{{CANCEL_URL}}" style="color: #e74c3c;">Cancel this reminder</a>
</p>', '{{SUBJECT}}, {{CANCEL_URL}}, {{SNOOZE_BUTTONS}}');

-- ----------------------------
-- Table: login_attempts (Rate limiting)
-- ----------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_key` varchar(255) NOT NULL,
  `attempted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_attempt_key` (`attempt_key`),
  INDEX `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table: audit_logs (Admin action tracking)
-- ----------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(50) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `actor_email` varchar(255) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_actor_id` (`actor_id`),
  INDEX `idx_target` (`target_type`, `target_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------
-- Foreign Key Constraints
-- ----------------------------
ALTER TABLE `emails`
ADD CONSTRAINT `fk_emails_category`
FOREIGN KEY (`catID`) REFERENCES `emailCategory`(`ID`)
ON DELETE SET NULL ON UPDATE CASCADE;

-- ----------------------------
-- Create initial admin user (password: admin123)
-- IMPORTANT: Change this password immediately after first login!
-- ----------------------------
INSERT INTO `users` (`name`, `email`, `password`, `role`, `timezone`, `theme`) VALUES
('Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'UTC', 'dark');
