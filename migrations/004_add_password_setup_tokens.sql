-- Migration: Add password setup token fields to users table
-- This enables secure, time-limited password setup links for new users

ALTER TABLE `users`
ADD COLUMN `password_setup_token` VARCHAR(64) DEFAULT NULL COMMENT 'Secure token for password setup',
ADD COLUMN `password_setup_token_expires` DATETIME DEFAULT NULL COMMENT 'Token expiration timestamp',
ADD INDEX `idx_password_setup_token` (`password_setup_token`);

-- Insert password setup email template
INSERT INTO `email_templates` (`slug`, `subject`, `body`, `variables`) VALUES
('password_setup', 'Set up your Snoozer account password', '
<h2>Welcome to Snoozer, {{NAME}}!</h2>
<p>An account has been created for you. To get started, please set up your password by clicking the button below:</p>
<div style="text-align: center; margin: 30px 0;">
  <a href="{{SETUP_LINK}}" style="display: inline-block; padding: 15px 30px; background-color: #7d3c98; color: white; text-decoration: none; border-radius: 25px; font-weight: bold;">Set Up Password</a>
</div>
<p>Or copy and paste this link into your browser:</p>
<p style="word-break: break-all; color: #7d3c98;">{{SETUP_LINK}}</p>
<p><strong>Important:</strong> This link will expire in {{EXPIRATION_HOURS}} hours for security reasons.</p>
<p>If you did not expect this email, please ignore it or contact support.</p>
', '{{NAME}}, {{SETUP_LINK}}, {{EXPIRATION_HOURS}}');
