-- Fix employee_assignments foreign key constraint
-- The livestock_owner_id should reference livestock_owners.id, not users.id

-- Step 1: Drop the incorrect foreign key constraint
ALTER TABLE `employee_assignments` 
DROP FOREIGN KEY `employee_assignments_ibfk_1`;

-- Step 2: Add the correct foreign key constraint
ALTER TABLE `employee_assignments` 
ADD CONSTRAINT `employee_assignments_ibfk_livestock_owner` 
FOREIGN KEY (`livestock_owner_id`) 
REFERENCES `livestock_owners`(`id`) 
ON DELETE CASCADE 
ON UPDATE CASCADE;

-- Verify the change
SHOW CREATE TABLE employee_assignments;
