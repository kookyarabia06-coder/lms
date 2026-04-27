-- Add viewed tracking for training_requests
ALTER TABLE `training_requests` 
ADD COLUMN `viewed_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`;

-- Add index for better query performance
ALTER TABLE `training_requests` 
ADD INDEX `idx_viewed_at` (`viewed_at`);
