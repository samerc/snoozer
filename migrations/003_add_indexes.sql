-- Migration: Add missing indexes and foreign keys
-- Date: 2026-01-21
-- Description: Improves query performance and data integrity

-- Add index on message_id for duplicate detection during email ingestion
-- Using prefix index (255) since message_id is TEXT type
ALTER TABLE `emails` ADD INDEX `idx_message_id` (`message_id`(255));

-- Add index on toaddress for snoozer address lookups
ALTER TABLE `emails` ADD INDEX `idx_toaddress` (`toaddress`);

-- Add foreign key for email category relationship
-- First, clean up any orphaned category references
UPDATE `emails` SET `catID` = NULL WHERE `catID` IS NOT NULL AND `catID` NOT IN (SELECT `ID` FROM `emailCategory`);

-- Now add the foreign key constraint
ALTER TABLE `emails`
ADD CONSTRAINT `fk_emails_category`
FOREIGN KEY (`catID`) REFERENCES `emailCategory`(`ID`)
ON DELETE SET NULL ON UPDATE CASCADE;
