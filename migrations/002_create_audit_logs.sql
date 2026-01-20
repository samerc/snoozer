-- Migration: Create audit_logs table for tracking admin actions
-- Run this migration to add audit logging capability

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `action` varchar(50) NOT NULL COMMENT 'Action type (user_created, password_reset, etc.)',
    `actor_id` int(11) DEFAULT NULL COMMENT 'User ID who performed the action',
    `actor_email` varchar(255) DEFAULT NULL COMMENT 'Email of the actor (for display)',
    `target_id` int(11) DEFAULT NULL COMMENT 'ID of affected entity',
    `target_type` varchar(50) DEFAULT NULL COMMENT 'Type of affected entity (user, template, etc.)',
    `details` text DEFAULT NULL COMMENT 'JSON-encoded additional details',
    `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of the actor',
    `created_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_action` (`action`),
    KEY `idx_actor_id` (`actor_id`),
    KEY `idx_target` (`target_type`, `target_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
