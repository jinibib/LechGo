-- Migration: Add age_days column to pig_details
-- Run this in phpMyAdmin → lechgo_db → SQL tab

ALTER TABLE `pig_details`
  ADD COLUMN `age_days` int(11) DEFAULT 0
  AFTER `age_months`;

-- Backfill existing rows: convert age_months → age_days
UPDATE `pig_details`
SET `age_days` = `age_months` * 30
WHERE `age_days` = 0 AND `age_months` > 0;
