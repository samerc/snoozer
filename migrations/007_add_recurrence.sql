-- Migration 007: Add recurrence column to emails table
-- Supports recurring reminders (daily, weekly, monthly, weekdays)
-- NULL = one-time reminder (existing behaviour)

ALTER TABLE emails
  ADD COLUMN recurrence VARCHAR(20) NULL DEFAULT NULL
  COMMENT 'null=once, daily, weekly, monthly, weekdays';
