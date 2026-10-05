-- Create employee_applications table
-- This table stores applications from users wanting to work at piggery farms

CREATE TABLE IF NOT EXISTS `employee_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `applicant_user_id` int(11) NOT NULL COMMENT 'User ID of the applicant',
  `livestock_owner_id` int(11) NOT NULL COMMENT 'Livestock owner ID they are applying to',
  `position` varchar(50) NOT NULL COMMENT 'Position applying for (pig_caretaker, lechonero, etc)',
  `cover_letter` text DEFAULT NULL COMMENT 'Optional cover letter from applicant',
  `status` enum('pending','approved','rejected') DEFAULT 'pending' COMMENT 'Application status',
  `rejection_reason` text DEFAULT NULL COMMENT 'Reason for rejection (if rejected)',
  `reviewed_at` datetime DEFAULT NULL COMMENT 'When the application was reviewed',
  `applied_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'When the application was submitted',
  PRIMARY KEY (`id`),
  KEY `idx_applicant` (`applicant_user_id`),
  KEY `idx_owner` (`livestock_owner_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_employee_app_applicant` FOREIGN KEY (`applicant_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_app_owner` FOREIGN KEY (`livestock_owner_id`) REFERENCES `livestock_owners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Employee applications to piggery farms';

-- Index for finding applications by status and date
CREATE INDEX idx_status_date ON employee_applications(status, applied_at DESC);
