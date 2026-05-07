-- ============================================================
-- Migration: Fix pig_pins count + pig_details/feeding_schedule FK
-- Run this in phpMyAdmin → lechgo_db → SQL tab
-- ============================================================

-- STEP 1: Reset ghost pig counts
UPDATE `pig_pins`
SET `current_pig_count` = (
    SELECT COUNT(*) FROM `pig_details`
    WHERE `pig_details`.`cage_id` = `pig_pins`.`id`
    AND `pig_details`.`status` = 'active'
),
`status` = CASE
    WHEN (
        SELECT COUNT(*) FROM `pig_details`
        WHERE `pig_details`.`cage_id` = `pig_pins`.`id`
        AND `pig_details`.`status` = 'active'
    ) > 0 THEN 'active'
    ELSE 'inactive'
END;

-- STEP 2: Remove ALL existing FK constraints on pig_details.cage_id
--         (handles any constraint name)
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `pig_details`
  DROP FOREIGN KEY IF EXISTS `fk_pig_details_cage`,
  DROP FOREIGN KEY IF EXISTS `fk_pig_details_pin`;

-- STEP 3: Re-add FK pointing to pig_pins
ALTER TABLE `pig_details`
  ADD CONSTRAINT `fk_pig_details_pin`
  FOREIGN KEY (`cage_id`) REFERENCES `pig_pins` (`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- STEP 4: Remove ALL existing FK constraints on feeding_schedule.cage_id
ALTER TABLE `feeding_schedule`
  DROP FOREIGN KEY IF EXISTS `fk_feeding_schedule_cage`,
  DROP FOREIGN KEY IF EXISTS `fk_feeding_schedule_pin`;

-- STEP 5: Re-add FK pointing to pig_pins
ALTER TABLE `feeding_schedule`
  ADD CONSTRAINT `fk_feeding_schedule_pin`
  FOREIGN KEY (`cage_id`) REFERENCES `pig_pins` (`id`)
  ON DELETE SET NULL ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
