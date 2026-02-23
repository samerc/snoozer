-- Snoozer Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.2+
-- Includes all migrations 001–007

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
  `password_setup_token` varchar(64) DEFAULT NULL COMMENT 'Secure token for password setup',
  `password_setup_token_expires` datetime DEFAULT NULL COMMENT 'Token expiration timestamp',
  `thread_reminders` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether reminder emails are threaded to the original email (1=yes, 0=no)',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `idx_email` (`email`),
  INDEX `idx_password_setup_token` (`password_setup_token`)
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
  `recurrence` varchar(20) NULL DEFAULT NULL COMMENT 'null=once, daily, weekly, monthly, weekdays',
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
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{TITLE}}</title>
</head>
<body style="margin:0;padding:0;background:#f0f0f4;font-family:Arial,sans-serif;">
  <table width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f0f0f4;">
    <tr>
      <td align="center" style="padding:32px 16px;">
        <table width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
          <tr>
            <td style="background:linear-gradient(135deg,#7d3c98 0%,#a855c8 100%);padding:26px 32px;text-align:center;">
              <div style="font-size:20px;font-weight:800;color:#ffffff;letter-spacing:4px;">SNOOZER</div>
              <div style="font-size:10px;color:rgba(255,255,255,0.65);letter-spacing:1px;margin-top:5px;">reach &amp; maintain a zero inbox</div>
            </td>
          </tr>
          <tr>
            <td style="padding:32px;">
              {{CONTENT}}
            </td>
          </tr>
          <tr>
            <td style="padding:16px 32px;border-top:1px solid #f0f0f0;text-align:center;">
              <span style="font-size:11px;color:#bbb;">You''re receiving this because you set a reminder using Snoozer.</span>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>', '{{TITLE}}, {{CONTENT}}'),
('reminder', 'RE: {{SUBJECT}}', '<p style="font-size:15px;font-weight:700;color:#1a1a1a;margin:0 0 6px 0;">{{SUBJECT}}</p>
<p style="font-size:13px;color:#888;margin:0 0 24px 0;">Here are your snooze options — pick one to reschedule:</p>
<div style="margin-bottom:24px;">
  {{SNOOZE_BUTTONS}}
</div>
<div style="border-top:1px solid #eee;padding-top:16px;">
  <a href="{{CANCEL_URL}}" style="font-size:12px;color:#e74c3c;text-decoration:none;font-weight:600;">&#10005; Cancel this reminder</a>
</div>', '{{SUBJECT}}, {{CANCEL_URL}}, {{SNOOZE_BUTTONS}}'),
('password_setup', 'Set up your Snoozer account password', '<h2 style="font-size:18px;font-weight:700;color:#1a1a1a;margin:0 0 12px 0;">Welcome to Snoozer, {{NAME}}!</h2>
<p style="font-size:14px;color:#555;margin:0 0 24px 0;">An account has been created for you. Click the button below to set your password and get started:</p>
<div style="text-align:center;margin:28px 0;">
  <a href="{{SETUP_LINK}}" style="display:inline-block;padding:14px 32px;background:#7d3c98;color:#fff;text-decoration:none;border-radius:50px;font-weight:700;font-size:14px;letter-spacing:0.5px;">Set Up Password</a>
</div>
<p style="font-size:12px;color:#999;margin:0 0 8px 0;">Or copy and paste this link into your browser:</p>
<p style="font-size:12px;word-break:break-all;color:#7d3c98;margin:0 0 20px 0;">{{SETUP_LINK}}</p>
<p style="font-size:12px;color:#aaa;margin:0;"><strong>Note:</strong> This link expires in {{EXPIRATION_HOURS}} hours. If you did not expect this email, you can safely ignore it.</p>', '{{NAME}}, {{SETUP_LINK}}, {{EXPIRATION_HOURS}}');

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

-- ----------------------------
-- Table: system_settings (Cron health & app state)
-- ----------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `key` varchar(50) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
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
