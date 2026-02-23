-- Migration 006: Add thread_reminders preference to users
-- Enables per-user control over whether reminder emails are sent as part of the original email thread.
-- Default is 1 (enabled) to preserve existing behaviour.

ALTER TABLE `users`
    ADD COLUMN `thread_reminders` TINYINT(1) NOT NULL DEFAULT 1
        COMMENT 'Whether reminder emails are threaded to the original email (1=yes, 0=no)';
