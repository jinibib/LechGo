-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql211.infinityfree.com
-- Generation Time: Sep 05, 2026 at 10:32 AM
-- Server version: 11.4.13-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_42218125_lechgo`
--

-- --------------------------------------------------------

--
-- Table structure for table `budget_planner`
--

CREATE TABLE `budget_planner` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `holiday_event` varchar(255) NOT NULL,
  `planned_budget` decimal(10,2) NOT NULL,
  `family_members` int(11) NOT NULL,
  `total_planned_cost` decimal(10,2) DEFAULT 0.00,
  `holiday_afc` decimal(10,2) DEFAULT 0.00,
  `overspending_percent` decimal(10,2) DEFAULT 0.00,
  `status` enum('planning','completed') DEFAULT 'planning',
  `planned_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
) ;

--
-- Dumping data for table `budget_planner`
--

INSERT INTO `budget_planner` (`id`, `user_id`, `holiday_event`, `planned_budget`, `family_members`, `total_planned_cost`, `holiday_afc`, `overspending_percent`, `status`, `planned_items`, `actual_items`, `created_at`, `updated_at`) VALUES
(14, 69, 'Birthday', '15000.00', 2, '12800.00', '12800.00', '-14.67', 'completed', '[{\"name\":\"Lechon\",\"category\":\"Lechon\",\"price\":12000,\"qty\":1},{\"name\":\"Humba\",\"category\":\"Meat\",\"price\":500,\"qty\":1},{\"name\":\"Pancit\",\"category\":\"Other\",\"price\":300,\"qty\":1}]', '[{\"name\":\"Lechon\",\"category\":\"Lechon\",\"amount\":12000},{\"name\":\"Humba\",\"category\":\"Meat\",\"amount\":500},{\"name\":\"Pancit\",\"category\":\"Other\",\"amount\":300}]', '2026-08-28 14:04:56', '2026-08-28 14:05:33'),
(15, 69, 'New Year', '10000.00', 5, '12050.00', '12050.00', '20.50', 'completed', '[{\"name\":\"Lechon \",\"category\":\"Lechon\",\"price\":12000,\"qty\":1},{\"name\":\"Bihon\",\"category\":\"Other\",\"price\":50,\"qty\":1}]', '[{\"name\":\"Lechon \",\"category\":\"Lechon\",\"amount\":12000},{\"name\":\"Bihon\",\"category\":\"Other\",\"amount\":50}]', '2026-08-28 14:18:42', '2026-08-28 14:19:17'),
(16, 69, 'Birthday', '20000.00', 5, '17700.00', '17700.00', '-11.50', 'completed', '[{\"name\":\"Lechon\",\"category\":\"Lechon\",\"price\":15000,\"qty\":1},{\"name\":\"Bihon\",\"category\":\"Other\",\"price\":400,\"qty\":1},{\"name\":\"Cake\",\"category\":\"Dessert\",\"price\":300,\"qty\":1},{\"name\":\"Catering\",\"category\":\"Other\",\"price\":2000,\"qty\":1}]', '[{\"name\":\"Lechon\",\"category\":\"Lechon\",\"amount\":15000},{\"name\":\"Bihon\",\"category\":\"Other\",\"amount\":400},{\"name\":\"Cake\",\"category\":\"Dessert\",\"amount\":300},{\"name\":\"Catering\",\"category\":\"Other\",\"amount\":2000}]', '2026-08-28 14:25:45', '2026-08-28 14:27:01'),
(17, 69, 'Christmas', '10000.00', 5, '6000.00', '7515.00', '-24.85', 'completed', '[{\"name\":\"Lechon\",\"category\":\"Lechon\",\"price\":6000,\"qty\":1},{\"name\":\"\",\"category\":\"Other\",\"price\":0,\"qty\":1}]', '[{\"name\":\"Lechon\",\"category\":\"Lechon\",\"amount\":6000},{\"name\":\"pancit\",\"category\":\"Other\",\"amount\":500},{\"name\":\"caldereta\",\"category\":\"Other\",\"amount\":1015}]', '2026-09-02 04:24:59', '2026-09-02 04:27:44'),
(19, 69, 'Reunion', '20000.00', 21, '21135.00', '21135.00', '5.68', 'completed', '[{\"name\":\"Buffet\",\"category\":\"Other\",\"price\":21135,\"qty\":1}]', '[{\"name\":\"Buffet\",\"category\":\"Other\",\"amount\":21135}]', '2026-09-02 14:38:18', '2026-09-02 14:38:42');

-- --------------------------------------------------------

--
-- Table structure for table `calendar_items`
--

CREATE TABLE `calendar_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User who created the calendar item',
  `trend_id` int(11) DEFAULT NULL COMMENT 'Associated trend (optional)',
  `calendar_date` date NOT NULL COMMENT 'Date of the calendar item',
  `note` text DEFAULT NULL COMMENT 'Business note for this date',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `caretaker_reports`
--

CREATE TABLE `caretaker_reports` (
  `id` int(11) NOT NULL,
  `caretaker_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `report_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cooking_schedule`
--

CREATE TABLE `cooking_schedule` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `initial_weight` decimal(8,2) DEFAULT NULL,
  `final_weight` decimal(8,2) DEFAULT NULL,
  `estimated_cook_time` decimal(4,2) DEFAULT NULL,
  `actual_cook_time` decimal(4,2) DEFAULT NULL,
  `cooking_method` enum('charcoal','wood','gas','electric','combination') DEFAULT NULL,
  `initial_notes` text DEFAULT NULL,
  `date_to_butcher` date DEFAULT NULL,
  `time_to_butcher` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_payments`
--

CREATE TABLE `customer_payments` (
  `id` int(11) NOT NULL,
  `payment_reference` varchar(100) NOT NULL,
  `order_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `payment_method` enum('paymongo','gcash','cash_on_delivery','bank_transfer','cash') NOT NULL,
  `payment_provider` varchar(50) DEFAULT NULL COMMENT 'PayMongo, GCash, etc.',
  `payment_gateway_id` varchar(255) DEFAULT NULL COMMENT 'External payment gateway transaction ID',
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'PHP',
  `payment_status` enum('pending','processing','completed','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Store full gateway response for debugging'
) ;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_status`
--

CREATE TABLE `delivery_status` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `driver_user_id` int(11) DEFAULT NULL,
  `delivery_method` enum('delivery','pickup') DEFAULT 'delivery',
  `delivery_address` text DEFAULT NULL,
  `delivery_photo` varchar(255) DEFAULT NULL,
  `payment_photo` varchar(255) DEFAULT NULL,
  `delivered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_verification_tokens`
--

CREATE TABLE `email_verification_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `token` varchar(255) NOT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_verification_tokens`
--

INSERT INTO `email_verification_tokens` (`id`, `user_id`, `email`, `token`, `verified_at`, `expires_at`, `created_at`) VALUES
(2, 65, 'jennyvievemahinay@gmail.com', 'c92f5525d53b49cbfd7000a609b151f8f07e7b68ab1f8210396887da0c526e1d', '2026-07-26 09:22:13', '2026-07-27 12:22:04', '2026-07-26 09:22:03'),
(4, 67, 'davedelacerna09@gmail.com', 'b486d467bee77de21d6cd719ae19f3230096c09f85a939ff3615423371ab558b', '2026-07-27 06:10:26', '2026-07-28 09:09:26', '2026-07-27 06:09:26'),
(5, 68, 'mahinaylydia82@gmail.com', 'd8bd75d5368311b5d77167330529e5cb400b02e80ec813ab2987786589fa276e', '2026-07-27 08:57:27', '2026-07-28 11:57:02', '2026-07-27 08:57:02'),
(6, 69, 'xzytrion09@gmail.com', '6676330641a64c2175d78fdd165c767ebdb9b15b187caa3eb49aea9bcc1a71a1', '2026-07-29 07:26:43', '2026-07-30 10:26:24', '2026-07-29 07:26:25'),
(7, 70, 'jennyah153@gmail.com', '79f9c607e4b486a95dd2a140d882eb17a9a8b9382ac47f6e03889d0f90f79800', '2026-08-06 01:30:12', '2026-08-07 04:29:55', '2026-08-06 01:29:55');

-- --------------------------------------------------------

--
-- Table structure for table `employee_applications`
--

CREATE TABLE `employee_applications` (
  `id` int(11) NOT NULL,
  `applicant_user_id` int(11) NOT NULL COMMENT 'User ID of the applicant',
  `livestock_owner_id` int(11) NOT NULL COMMENT 'Livestock owner ID they are applying to',
  `position` varchar(50) NOT NULL COMMENT 'Position applying for (pig_caretaker, lechonero, etc)',
  `cover_letter` text DEFAULT NULL COMMENT 'Optional cover letter from applicant',
  `status` enum('pending','approved','rejected') DEFAULT 'pending' COMMENT 'Application status',
  `rejection_reason` text DEFAULT NULL COMMENT 'Reason for rejection (if rejected)',
  `reviewed_at` datetime DEFAULT NULL COMMENT 'When the application was reviewed',
  `applied_at` datetime DEFAULT current_timestamp() COMMENT 'When the application was submitted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Employee applications to piggery farms';

--
-- Dumping data for table `employee_applications`
--

INSERT INTO `employee_applications` (`id`, `applicant_user_id`, `livestock_owner_id`, `position`, `cover_letter`, `status`, `rejection_reason`, `reviewed_at`, `applied_at`) VALUES
(1, 67, 8, 'pig_caretaker', '', 'approved', NULL, NULL, '2026-07-27 00:49:32'),
(2, 68, 8, 'lechonero', '', 'approved', NULL, '2026-07-27 01:58:55', '2026-07-27 01:58:00');

-- --------------------------------------------------------

--
-- Table structure for table `employee_assignments`
--

CREATE TABLE `employee_assignments` (
  `id` int(11) NOT NULL,
  `livestock_owner_id` int(11) NOT NULL COMMENT 'Livestock owner user ID',
  `employee_user_id` int(11) NOT NULL COMMENT 'Employee user ID',
  `assigned_role` enum('pig_caretaker','lechonero','pig_slaughter','logistics') NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_assignments`
--

INSERT INTO `employee_assignments` (`id`, `livestock_owner_id`, `employee_user_id`, `assigned_role`, `status`, `assigned_at`, `updated_at`) VALUES
(6, 8, 67, 'pig_caretaker', 'active', '2026-07-27 08:51:40', '2026-07-27 08:51:40'),
(7, 8, 68, 'lechonero', 'active', '2026-07-27 08:58:55', '2026-07-27 08:58:55');

-- --------------------------------------------------------

--
-- Table structure for table `feeding_schedule`
--

CREATE TABLE `feeding_schedule` (
  `id` int(11) NOT NULL,
  `caretaker_id` int(11) NOT NULL,
  `cage_id` int(11) DEFAULT NULL,
  `feed_inventory_id` int(11) DEFAULT NULL,
  `feeding_date` date NOT NULL,
  `feeding_time` time DEFAULT NULL,
  `amount_kg` decimal(6,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feed_distributors`
--

CREATE TABLE `feed_distributors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `business_name` varchar(150) NOT NULL,
  `location_id` int(11) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feed_distributor_orders`
--

CREATE TABLE `feed_distributor_orders` (
  `id` int(11) NOT NULL,
  `distributor_id` int(11) NOT NULL,
  `buyer_user_id` int(11) NOT NULL,
  `buyer_name` varchar(150) DEFAULT NULL,
  `order_number` varchar(50) DEFAULT NULL,
  `order_status` enum('pending','confirmed','processing','ready_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `delivery_address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `imported_to_inventory` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feed_distributor_order_items`
--

CREATE TABLE `feed_distributor_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `feed_type` varchar(100) DEFAULT NULL,
  `quantity_kg` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feed_distributor_products`
--

CREATE TABLE `feed_distributor_products` (
  `id` int(11) NOT NULL,
  `distributor_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `feed_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity_available_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feed_inventory`
--

CREATE TABLE `feed_inventory` (
  `id` int(11) NOT NULL,
  `caretaker_id` int(11) NOT NULL,
  `feed_type` varchar(100) NOT NULL,
  `feed_name` varchar(150) NOT NULL DEFAULT 'Feed',
  `quantity_kg` decimal(8,2) NOT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `supplier_name` varchar(150) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('in_stock','low_stock','expired','used') DEFAULT 'in_stock',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feed_orders`
--

CREATE TABLE `feed_orders` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `caretaker_id` int(11) NOT NULL,
  `order_status` enum('pending','reviewing_payment','accepted','rejected','completed','cancelled') DEFAULT 'pending',
  `payment_status` enum('pending','reviewing','verified','failed') DEFAULT 'pending',
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `caretaker_response` text DEFAULT NULL,
  `caretaker_response_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feed_order_status`
--

CREATE TABLE `feed_order_status` (
  `id` int(11) NOT NULL,
  `feed_order_id` int(11) NOT NULL,
  `status_type` enum('order','payment','delivery') NOT NULL DEFAULT 'order',
  `status_value` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL COMMENT 'User ID who changed the status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feed_products`
--

CREATE TABLE `feed_products` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `feed_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `previous_price` decimal(10,2) DEFAULT NULL,
  `price_updated_at` timestamp NULL DEFAULT NULL,
  `quantity_available_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hogs_market`
--

CREATE TABLE `hogs_market` (
  `id` int(11) NOT NULL,
  `livestock_owner_id` int(11) NOT NULL,
  `pig_detail_id` int(11) NOT NULL,
  `pig_tag_id` varchar(100) NOT NULL,
  `pin_number` varchar(50) NOT NULL,
  `weight_kg` decimal(8,2) NOT NULL,
  `price_per_kg` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS (`weight_kg` * `price_per_kg`) STORED,
  `description` text DEFAULT NULL,
  `status` enum('active','reserved','sold','removed') NOT NULL DEFAULT 'active',
  `reserved_by_user_id` int(11) DEFAULT NULL,
  `reserved_by_name` varchar(255) DEFAULT NULL,
  `inquiry_message` text DEFAULT NULL,
  `seller_feedback` text DEFAULT NULL,
  `reserved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hogs_market`
--

INSERT INTO `hogs_market` (`id`, `livestock_owner_id`, `pig_detail_id`, `pig_tag_id`, `pin_number`, `weight_kg`, `price_per_kg`, `description`, `status`, `reserved_by_user_id`, `reserved_by_name`, `inquiry_message`, `seller_feedback`, `reserved_at`, `created_at`, `updated_at`) VALUES
(50, 8, 84, 'PINPIN1-PIG1', 'PIN1', '31.20', '380.00', '', 'active', NULL, NULL, NULL, NULL, NULL, '2026-09-05 13:04:36', '2026-09-05 13:04:36'),
(51, 8, 85, 'PINPIN1-PIG2', 'PIN1', '20.00', '350.00', '', 'active', NULL, NULL, NULL, NULL, NULL, '2026-09-05 14:29:56', '2026-09-05 14:29:56');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `id` int(11) NOT NULL,
  `livestock_owner_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `batch_name` varchar(100) DEFAULT NULL COMMENT 'Optional batch/group identifier',
  `feed_cost` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total feed expenses',
  `labor_cost` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Labor for cooking lechon',
  `veterinary_vaccines_cost` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Veterinary and vaccines expenses',
  `housing_utilities_labor_cost` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Housing, utilities, and labor costs',
  `other_cost` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Other miscellaneous costs',
  `water_cost` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Water expenses',
  `electricity_cost` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Electricity expenses',
  `heart_girth_cm` decimal(10,2) DEFAULT NULL COMMENT 'Heart girth measurement in cm',
  `pig_length_cm` decimal(10,2) DEFAULT NULL COMMENT 'Pig length in cm',
  `calculated_live_weight_kg` decimal(10,2) DEFAULT NULL COMMENT 'Auto-calculated using formula',
  `manual_weight_kg` decimal(10,2) DEFAULT NULL COMMENT 'Manual weight override if available',
  `final_weight_kg` decimal(10,2) DEFAULT NULL COMMENT 'Final weight used for calculations',
  `cost_per_kg` decimal(10,2) DEFAULT NULL COMMENT 'Total cost / Total live weight',
  `total_variable_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'TVC - Variable costs',
  `total_fixed_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'TFC - Fixed costs',
  `total_opportunity_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'TOC - Opportunity costs',
  `apc` decimal(10,2) DEFAULT NULL COMMENT 'Average Production Cost per kg',
  `current_cost` decimal(10,2) DEFAULT NULL,
  `previous_cost` decimal(10,2) DEFAULT NULL,
  `percent_change` decimal(10,2) DEFAULT NULL COMMENT 'Percentage change from previous',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_cost` decimal(10,2) GENERATED ALWAYS AS (`feed_cost` + `labor_cost` + `veterinary_vaccines_cost` + `housing_utilities_labor_cost` + `other_cost` + `water_cost` + `electricity_cost`) STORED COMMENT 'Total production cost including all expenses',
  `biologics_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'Vaccines, medicines, vitamins',
  `fuel_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'Transportation and fuel expenses',
  `caretaker_labor_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'Caretaker/farm worker wages',
  `building_depreciation` decimal(10,2) DEFAULT 0.00 COMMENT 'Depreciation of buildings/structures',
  `equipment_depreciation` decimal(10,2) DEFAULT 0.00 COMMENT 'Depreciation of equipment/tools',
  `permits_taxes` decimal(10,2) DEFAULT 0.00 COMMENT 'Business permits and taxes',
  `capital_opportunity_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'Foregone interest on capital invested',
  `land_rent_opportunity` decimal(10,2) DEFAULT 0.00 COMMENT 'Foregone land rent value',
  `total_variable_costs_tvc` decimal(10,2) DEFAULT 0.00 COMMENT 'TVC = Feed + Biologics + Fuel + Labor',
  `total_fixed_costs_tfc` decimal(10,2) DEFAULT 0.00 COMMENT 'TFC = Building + Equipment depreciation + Permits/Taxes',
  `total_opportunity_costs_toc` decimal(10,2) DEFAULT 0.00 COMMENT 'TOC = Capital + Land opportunity costs',
  `total_production_cost` decimal(10,2) DEFAULT 0.00 COMMENT 'Total = TVC + TFC + TOC',
  `total_live_weight_tw` decimal(10,2) DEFAULT 0.00 COMMENT 'Total weight of pigs produced (kg)',
  `average_production_cost_apc` decimal(10,2) DEFAULT 0.00 COMMENT 'APC = Total Production Cost / Total Weight (PHP/kg)',
  `current_cost_per_kg` decimal(10,2) DEFAULT 0.00 COMMENT 'Current batch cost per kg',
  `previous_cost_per_kg` decimal(10,2) DEFAULT 0.00 COMMENT 'Historical baseline cost per kg',
  `cost_variance` decimal(10,2) DEFAULT 0.00 COMMENT 'Difference: Current - Previous cost',
  `cost_variance_percent` decimal(5,2) DEFAULT 0.00 COMMENT 'Percentage change in cost',
  `batch_mortality_rate` decimal(5,2) DEFAULT 0.00 COMMENT 'Mortality rate percentage for this batch',
  `finished_pigs_count` int(11) DEFAULT 0 COMMENT 'Number of pigs that reached market weight',
  `average_pig_weight_kg` decimal(10,2) DEFAULT 0.00 COMMENT 'Average weight per pig at finish'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_logs`
--

INSERT INTO `inventory_logs` (`id`, `livestock_owner_id`, `log_date`, `batch_name`, `feed_cost`, `labor_cost`, `veterinary_vaccines_cost`, `housing_utilities_labor_cost`, `other_cost`, `water_cost`, `electricity_cost`, `heart_girth_cm`, `pig_length_cm`, `calculated_live_weight_kg`, `manual_weight_kg`, `final_weight_kg`, `cost_per_kg`, `total_variable_cost`, `total_fixed_cost`, `total_opportunity_cost`, `apc`, `current_cost`, `previous_cost`, `percent_change`, `notes`, `created_at`, `updated_at`, `biologics_cost`, `fuel_cost`, `caretaker_labor_cost`, `building_depreciation`, `equipment_depreciation`, `permits_taxes`, `capital_opportunity_cost`, `land_rent_opportunity`, `total_variable_costs_tvc`, `total_fixed_costs_tfc`, `total_opportunity_costs_toc`, `total_production_cost`, `total_live_weight_tw`, `average_production_cost_apc`, `current_cost_per_kg`, `previous_cost_per_kg`, `cost_variance`, `cost_variance_percent`, `batch_mortality_rate`, `finished_pigs_count`, `average_pig_weight_kg`) VALUES
(36, 8, '2026-09-04', 'September', '8000.00', '4500.00', '0.00', '0.00', '0.00', '200.00', '500.00', NULL, NULL, NULL, NULL, NULL, NULL, '0.00', '0.00', '0.00', NULL, NULL, NULL, NULL, NULL, '2026-09-04 12:51:30', '2026-09-04 12:51:53', '200.00', '300.00', '0.00', '0.00', '500.00', '350.00', '10000.00', '0.00', '13700.00', '850.00', '10000.00', '24550.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 0, '0.00');

-- --------------------------------------------------------

--
-- Table structure for table `lechoneros`
--

CREATE TABLE `lechoneros` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `business_name` varchar(150) DEFAULT NULL,
  `specialty` varchar(150) DEFAULT NULL,
  `rating` float DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lechon_listings`
--

CREATE TABLE `lechon_listings` (
  `id` int(11) NOT NULL,
  `livestock_owner_id` int(11) NOT NULL,
  `listing_type` enum('WHOLE_LECHON','PROMO_PACKAGE') NOT NULL DEFAULT 'WHOLE_LECHON',
  `category` enum('WHOLE_LECHON','BOOPIE','LAMAN_LOOB','PORTION','COMBO','OTHER') NOT NULL DEFAULT 'WHOLE_LECHON',
  `name` varchar(255) NOT NULL,
  `weight_kg` decimal(8,2) DEFAULT NULL COMMENT 'Weight in kg for whole lechon',
  `portion_size` varchar(100) DEFAULT NULL COMMENT 'Portion size for portions/packages (e.g., 1/4 Lechon)',
  `serving_capacity` varchar(100) DEFAULT NULL COMMENT 'Good for how many people (e.g., 50-60 pax)',
  `price` decimal(10,2) NOT NULL,
  `available_date` date NOT NULL,
  `available_quantity` int(11) NOT NULL DEFAULT 1,
  `description` longtext DEFAULT NULL,
  `photo_url` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive','removed') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lechon_listings`
--

INSERT INTO `lechon_listings` (`id`, `livestock_owner_id`, `listing_type`, `category`, `name`, `weight_kg`, `portion_size`, `serving_capacity`, `price`, `available_date`, `available_quantity`, `description`, `photo_url`, `status`, `created_at`, `updated_at`) VALUES
(1, 8, 'WHOLE_LECHON', 'WHOLE_LECHON', 'Ala Lechon', '40.00', NULL, '50-60', '18000.00', '2026-09-05', 1, NULL, '/uploads/lechon/lechon_1788615932_1dffbe00.jpeg', 'active', '2026-09-05 13:45:32', '2026-09-05 13:49:29'),
(2, 8, 'PROMO_PACKAGE', '', 'Sulit Lechon', '0.00', 'Whole lechon, 15kg', '70-80', '21000.00', '2026-09-05', 1, NULL, '/uploads/lechon/lechon_1788618020_7b621747.jpeg', 'active', '2026-09-05 14:20:20', '2026-09-05 14:20:20'),
(3, 8, 'WHOLE_LECHON', 'WHOLE_LECHON', 'Malamion Na Lechon', '15.00', NULL, '50-60', '13000.00', '2026-09-05', 1, NULL, '/uploads/lechon/lechon_1788618083_c38ab385.jpeg', 'active', '2026-09-05 14:21:23', '2026-09-05 14:21:23');

-- --------------------------------------------------------

--
-- Table structure for table `lechon_status`
--

CREATE TABLE `lechon_status` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `lechonero_user_id` int(11) NOT NULL,
  `cooked_image` varchar(255) DEFAULT NULL,
  `internal_temperature` decimal(5,2) DEFAULT NULL COMMENT 'Temperature in Celsius',
  `skin_texture` enum('crispy','soft','burnt','undercooked') DEFAULT NULL,
  `meat_tenderness` enum('very_tender','tender','tough','very_tough') DEFAULT NULL,
  `quality_notes` text DEFAULT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `livestock_feed_orders`
--

CREATE TABLE `livestock_feed_orders` (
  `id` int(11) NOT NULL,
  `livestock_owner_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `order_status` enum('pending','confirmed','processing','ready_for_delivery','delivered','cancelled') DEFAULT 'pending',
  `payment_status` enum('unpaid','paid','failed') DEFAULT 'unpaid',
  `delivery_status` enum('pending','in_transit','delivered','failed') DEFAULT 'pending',
  `total_amount` decimal(12,2) NOT NULL,
  `delivery_address` text DEFAULT NULL,
  `scheduled_delivery_date` date DEFAULT NULL,
  `actual_delivery_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `livestock_feed_order_items`
--

CREATE TABLE `livestock_feed_order_items` (
  `id` int(11) NOT NULL,
  `feed_order_id` int(11) NOT NULL,
  `feed_product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `feed_type` varchar(100) NOT NULL,
  `quantity_kg` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `livestock_owners`
--

CREATE TABLE `livestock_owners` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `farm_name` varchar(255) NOT NULL,
  `location` text DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `livestock_owners`
--

INSERT INTO `livestock_owners` (`id`, `user_id`, `farm_name`, `location`, `contact_number`, `created_at`, `updated_at`) VALUES
(8, 66, 'CORDIO PONDIAS FARM', 'Daliao Junction, Daliao, Toril, Davao City', '09296412812', '2026-07-26 14:37:04', '2026-07-26 14:37:04');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `location_id` int(11) NOT NULL,
  `street` varchar(150) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `municipality` varchar(100) NOT NULL,
  `city` varchar(100) DEFAULT 'Davao City',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`location_id`, `street`, `barangay`, `municipality`, `city`, `created_at`, `updated_at`) VALUES
(1, 'J.P. Laurel Avenue', 'Tugbok Proper', 'Tugbok', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(2, 'Sandawa Street', 'Tugbok Proper', 'Tugbok', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(3, 'San Antonio Street', 'Tugbok Proper', 'Tugbok', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(4, 'Tugbok Main Road', 'Tugbok Proper', 'Tugbok', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(5, 'National Highway', 'Tibungco', 'Tugbok', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(6, 'Tibungco Road', 'Tibungco', 'Tugbok', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(7, 'Junction Street', 'Tibungco', 'Tugbok', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(8, 'Mintal Road', 'Mintal', 'Tugbok', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(9, 'Mambago Road', 'Mintal', 'Tugbok', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(10, 'Agricultural Street', 'Mintal', 'Tugbok', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(11, 'Bago Aplaya Road', 'Bago Aplaya', 'Tugbok', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(12, 'Coastal Avenue', 'Bago Aplaya', 'Tugbok', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(13, 'Beach Road', 'Bago Aplaya', 'Tugbok', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(14, 'Cabantian Road', 'Cabantian Proper', 'Cabantian', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(15, 'NFA Road', 'Cabantian Proper', 'Cabantian', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(16, 'Cabantian Market Street', 'Cabantian Proper', 'Cabantian', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(17, 'Highway Junction', 'Cabantian Proper', 'Cabantian', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(18, 'Lamanan Street', 'Lamanan', 'Cabantian', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(19, 'Lamanan Road', 'Lamanan', 'Cabantian', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(20, 'Agricultural Zone', 'Lamanan', 'Cabantian', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(21, 'Catalunan Grande Road', 'Catalunan Grande', 'Cabantian', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(22, 'National Highway', 'Catalunan Grande', 'Cabantian', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(23, 'Riverside Avenue', 'Catalunan Grande', 'Cabantian', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(24, 'Catalunan Pequeno Road', 'Catalunan Pequeno', 'Cabantian', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(25, 'Small Road', 'Catalunan Pequeno', 'Cabantian', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(26, 'Community Avenue', 'Catalunan Pequeno', 'Cabantian', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(27, 'Toril Road', 'Toril Proper', 'Toril', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(28, 'Maharlika Highway', 'Toril Proper', 'Toril', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(29, 'Main Street', 'Toril Proper', 'Toril', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(30, 'Commercial Avenue', 'Toril Proper', 'Toril', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(31, 'Lizada Road', 'Lizada', 'Toril', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(32, 'Lizada Extension', 'Lizada', 'Toril', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(33, 'Highway Road', 'Lizada', 'Toril', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(34, 'Santo Niño Street', 'Santo Niño', 'Toril', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(35, 'Religious Avenue', 'Santo Niño', 'Toril', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(36, 'Community Road', 'Santo Niño', 'Toril', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(37, 'Daliao Road', 'Daliao', 'Toril', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(38, 'Daliao Junction', 'Daliao', 'Toril', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(39, 'Highway Branch', 'Daliao', 'Toril', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(40, 'Bajada Road', 'Bajada Proper', 'Bajada', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(41, 'A. Sondido Street', 'Bajada Proper', 'Bajada', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(42, 'Bajada Market Avenue', 'Bajada Proper', 'Bajada', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(43, 'Main Commerce Road', 'Bajada Proper', 'Bajada', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(44, 'Ulas Road', 'Ulas', 'Bajada', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(45, 'Ulas Extension', 'Ulas', 'Bajada', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(46, 'Provincial Road', 'Ulas', 'Bajada', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(47, 'Agdao Road', 'Agdao Proper', 'Agdao', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(48, 'Bajada Link Road', 'Agdao Proper', 'Agdao', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(49, 'Agdao Market Avenue', 'Agdao Proper', 'Agdao', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(50, 'Commerce Street', 'Agdao Proper', 'Agdao', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(51, 'Pampanoa Road', 'Pampanoa', 'Agdao', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(52, 'Junction Street', 'Pampanoa', 'Agdao', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(53, 'Trading Post Road', 'Pampanoa', 'Agdao', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(54, 'Kilala Road', 'Kilala', 'Agdao', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(55, 'Kilala Junction', 'Kilala', 'Agdao', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(56, 'Highway Extension', 'Kilala', 'Agdao', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(57, 'Rizal Street', 'Poblacion', 'Poblacion', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(58, 'Aldana Street', 'Poblacion', 'Poblacion', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(59, 'Government Avenue', 'Poblacion', 'Poblacion', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(60, 'Cathedral Square', 'Poblacion', 'Poblacion', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(61, 'Uyanguren Road', 'Uyanguren', 'Poblacion', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(62, 'Uyanguren Avenue', 'Uyanguren', 'Poblacion', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(63, 'Commercial District', 'Uyanguren', 'Poblacion', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(64, 'San Pedro Street', 'San Pedro', 'Poblacion', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(65, 'Church Road', 'San Pedro', 'Poblacion', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(66, 'Government Building Avenue', 'San Pedro', 'Poblacion', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(67, 'Matina Town Square', 'Matina Pangi', 'Matina', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(68, 'Commercial Center Road', 'Matina Pangi', 'Matina', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(69, 'Pangi Avenue', 'Matina Pangi', 'Matina', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(70, 'Matina Crossing', 'Matina Crossing', 'Matina', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(71, 'Highway Junction', 'Matina Crossing', 'Matina', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(72, 'Shopping District', 'Matina Crossing', 'Matina', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(73, 'Matina Aplaya Road', 'Matina Aplaya', 'Matina', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(74, 'Seaside Boulevard', 'Matina Aplaya', 'Matina', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(75, 'Beach Avenue', 'Matina Aplaya', 'Matina', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(76, 'Tigatto Road', 'Tigatto', 'Matina', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(77, 'Industrial Street', 'Tigatto', 'Matina', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(78, 'Manufacturing Zone', 'Tigatto', 'Matina', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(79, 'Lanang Road', 'Lanang', 'Lanang', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(80, 'Ecozone Drive', 'Lanang', 'Lanang', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(81, 'Business Park Avenue', 'Lanang', 'Lanang', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(82, 'Industrial Boulevard', 'Lanang', 'Lanang', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(83, 'Calinan Road', 'Calinan', 'Lanang', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(84, 'Mountain View Street', 'Calinan', 'Lanang', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(85, 'Upland Avenue', 'Calinan', 'Lanang', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(86, 'Bunwan Road', 'Bunwan', 'Lanang', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(87, 'Residential Street', 'Bunwan', 'Lanang', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(88, 'Community Lane', 'Bunwan', 'Lanang', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(89, 'Mintal Road', 'Mintal', 'Mintal', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(90, 'National Highway', 'Mintal', 'Mintal', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(91, 'Market Avenue', 'Mintal', 'Mintal', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(92, 'Trading Street', 'Mintal', 'Mintal', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(93, 'Bago Gallera Road', 'Bago Gallera', 'Mintal', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(94, 'Highway Main', 'Bago Gallera', 'Mintal', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(95, 'Commercial Area', 'Bago Gallera', 'Mintal', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(96, 'Sibulan Road', 'Sibulan', 'Mintal', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(97, 'Residential Avenue', 'Sibulan', 'Mintal', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(98, 'Neighborhood Street', 'Sibulan', 'Mintal', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(99, 'Talomo Road', 'Talomo Proper', 'Talomo', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(100, 'Ramon Magsaysay Street', 'Talomo Proper', 'Talomo', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(101, 'Main Avenue', 'Talomo Proper', 'Talomo', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(102, 'Commercial Street', 'Talomo Proper', 'Talomo', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(103, 'Bago Aplaya Road', 'Bago Aplaya', 'Talomo', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(104, 'Seaside Drive', 'Bago Aplaya', 'Talomo', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(105, 'Beach Avenue', 'Bago Aplaya', 'Talomo', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(106, 'Lagao Road', 'Lagao', 'Talomo', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(107, 'Lake View Avenue', 'Lagao', 'Talomo', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(108, 'Residential Street', 'Lagao', 'Talomo', 'Davao City', '2026-03-27 08:52:24', '2026-03-27 08:52:24'),
(109, 'Libby Road', 'Bago Gallera', 'Talomo', 'Davao City', '2026-04-02 10:44:55', '2026-04-02 10:44:55'),
(110, 'Bago Street', 'Bago Gallera', 'Talomo', 'Davao City', '2026-04-02 10:44:55', '2026-04-02 10:44:55'),
(111, 'Spring Valley', 'Bago Gallera', 'Talomo', 'Davao City', '2026-04-02 10:44:55', '2026-04-02 10:44:55'),
(112, 'Suhai Village', 'Bago Gallera', 'Talomo', 'Davao City', '2026-04-02 10:44:55', '2026-04-02 10:44:55'),
(113, 'Inigo Road', 'Bago Gallera', 'Talomo', 'Davao City', '2026-04-02 10:44:55', '2026-04-02 10:44:55'),
(114, 'Libby Road', 'Bago Gallera', 'Talomo', 'Davao City', '2026-04-02 10:46:21', '2026-04-02 10:46:21'),
(115, 'Libby Road', 'Bago Gallera', 'Talomo', 'Davao City', '2026-04-02 10:54:52', '2026-04-02 10:54:52'),
(116, 'Toril Road', 'Toril Proper', 'Toril', 'Davao City', '2026-04-02 11:01:42', '2026-04-02 11:01:42'),
(117, 'Libby Road', 'Bago Gallera', 'Talomo', 'Davao City', '2026-04-03 09:31:05', '2026-04-03 09:31:05'),
(118, 'A. Sondido Street', 'Bajada Proper', 'Bajada', 'Davao City', '2026-04-18 10:58:33', '2026-04-18 10:58:33'),
(119, 'Toril Road', 'Toril Proper', 'Toril', 'Davao City', '2026-04-18 11:06:41', '2026-04-18 11:06:41'),
(120, 'Bago Street', 'Bago Gallera', 'Talomo', 'Davao City', '2026-04-20 08:24:58', '2026-04-20 08:24:58'),
(121, 'Bunwan Road', 'Bunwan', 'Lanang', 'Davao City', '2026-04-28 16:39:35', '2026-04-28 16:39:35');

-- --------------------------------------------------------

--
-- Table structure for table `market_notes`
--

CREATE TABLE `market_notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User who created the note',
  `title` varchar(255) NOT NULL COMMENT 'Note title',
  `content` text NOT NULL COMMENT 'Note content',
  `note_date` date NOT NULL COMMENT 'Date associated with the note',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `market_trends`
--

CREATE TABLE `market_trends` (
  `id` int(11) NOT NULL,
  `title` varchar(500) NOT NULL COMMENT 'Trend title/headline',
  `description` text DEFAULT NULL COMMENT 'Short description of the trend',
  `summary` text DEFAULT NULL COMMENT 'AI-generated summary (2-3 sentences)',
  `category` enum('Pig Farming','Pork Market','Lechon','Pricing','Agriculture','Food Trends','Industry News') DEFAULT 'Industry News' COMMENT 'Trend category',
  `trend_type` varchar(100) DEFAULT NULL COMMENT 'Price Increase, Price Decrease, Supply Change, etc.',
  `impact_level` enum('High','Moderate','Low') DEFAULT 'Low' COMMENT 'Business impact level',
  `business_insight` text DEFAULT NULL COMMENT 'How this affects lechon/pork business',
  `suggested_action` text DEFAULT NULL COMMENT 'Business-oriented suggestion',
  `source` varchar(255) DEFAULT NULL COMMENT 'News source name',
  `source_url` varchar(500) DEFAULT NULL COMMENT 'URL to original article',
  `image_url` varchar(500) DEFAULT NULL COMMENT 'Thumbnail image URL',
  `published_at` datetime DEFAULT NULL COMMENT 'Publication date of the trend',
  `is_demo` tinyint(1) DEFAULT 0 COMMENT 'TRUE if this is demo data',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `market_trends`
--

INSERT INTO `market_trends` (`id`, `title`, `description`, `summary`, `category`, `trend_type`, `impact_level`, `business_insight`, `suggested_action`, `source`, `source_url`, `image_url`, `published_at`, `is_demo`, `created_at`, `updated_at`) VALUES
(1, 'Pork Prices Rise Amid Supply Concerns', 'Global pork prices show upward movement due to reduced supply in major markets', 'Pork prices are increasing across major markets due to supply chain disruptions. This could impact lechon production costs and pricing strategy.', 'Pork Market', 'Price Increase', 'High', 'Rising pork prices will directly increase production costs for lechon businesses. Consider reviewing supplier contracts and potentially adjusting menu pricing.', 'Monitor supplier prices weekly. Review current lechon pricing to maintain margin.', 'DEMO DATA - Market Intelligence Feed', 'https://lechgo.local', 'https://via.placeholder.com/400x200?text=Pork+Market', '2026-09-04 07:11:10', 1, '2026-09-04 14:11:10', '2026-09-04 14:11:10'),
(2, 'New Lechon Flavor Trend in Filipino Food Culture', 'Filipino consumers show increased preference for flavored lechon variants', 'Market research indicates growing consumer interest in specialty lechon flavors. This presents a business opportunity for differentiation.', 'Lechon', 'Consumer Preference', 'Moderate', 'Offering unique flavored variants could increase customer demand and justify premium pricing. Early movers may capture market share.', 'Survey customers about preferred flavors. Test 2-3 variants and measure sales response.', 'DEMO DATA - Market Intelligence Feed', 'https://lechgo.local', 'https://via.placeholder.com/400x200?text=Lechon+Trends', '2026-09-02 07:11:10', 1, '2026-09-04 14:11:10', '2026-09-04 14:11:10'),
(3, 'Feed Price Volatility Expected This Quarter', 'Agricultural experts predict feed price fluctuations due to commodity market changes', 'Feed costs are expected to experience volatility. Timing feed purchases strategically could optimize costs.', 'Pig Farming', 'Price Change', 'High', 'Feed costs directly impact pig production expenses. Strategic purchasing and supply chain optimization are critical.', 'Establish relationships with multiple feed suppliers. Consider bulk purchasing during price dips.', 'DEMO DATA - Market Intelligence Feed', 'https://lechgo.local', 'https://via.placeholder.com/400x200?text=Feed+Prices', '2026-08-30 07:11:10', 1, '2026-09-04 14:11:10', '2026-09-04 14:11:10'),
(4, 'Growing Demand for Sustainable Farming Practices', 'Consumers increasingly prefer products from farms with eco-friendly practices', 'Sustainable farming is becoming a market differentiator. Implementing green practices could improve brand value.', 'Agriculture', 'Market Opportunity', 'Moderate', 'Marketing sustainable practices could attract premium-price customers and improve brand reputation.', 'Document current sustainable practices. Consider certifications or eco-friendly marketing.', 'DEMO DATA - Market Intelligence Feed', 'https://lechgo.local', 'https://via.placeholder.com/400x200?text=Sustainable+Farming', '2026-08-28 07:11:10', 1, '2026-09-04 14:11:10', '2026-09-04 14:11:10'),
(5, 'Food Safety Regulations Update for Livestock Producers', 'New compliance requirements announced for pig farming and meat processing', 'Regulatory changes require updated practices. Ensure compliance to avoid penalties.', 'Industry News', 'Regulatory Change', 'High', 'Regulatory compliance is mandatory. Non-compliance could result in fines or operational restrictions.', 'Review new regulations in detail. Audit current practices for compliance gaps.', 'DEMO DATA - Market Intelligence Feed', 'https://lechgo.local', 'https://via.placeholder.com/400x200?text=Food+Safety', '2026-08-25 07:11:10', 1, '2026-09-04 14:11:10', '2026-09-04 14:11:10'),
(6, 'Pork Prices Rise Amid Supply Concerns', 'Global pork prices show upward movement due to reduced supply in major markets', 'Pork prices are increasing across major markets due to supply chain disruptions. This could impact lechon production costs and pricing strategy.', 'Pork Market', 'Price Increase', 'High', 'Rising pork prices will directly increase production costs for lechon businesses. Consider reviewing supplier contracts and potentially adjusting menu pricing.', 'Monitor supplier prices weekly. Review current lechon pricing to maintain margin.', 'DEMO DATA - Market Intelligence Feed', 'https://lechgo.local', 'https://via.placeholder.com/400x200?text=Pork+Market', '2026-09-04 07:34:59', 1, '2026-09-04 14:34:59', '2026-09-04 14:34:59'),
(7, 'New Lechon Flavor Trend in Filipino Food Culture', 'Filipino consumers show increased preference for flavored lechon variants', 'Market research indicates growing consumer interest in specialty lechon flavors. This presents a business opportunity for differentiation.', 'Lechon', 'Consumer Preference', 'Moderate', 'Offering unique flavored variants could increase customer demand and justify premium pricing. Early movers may capture market share.', 'Survey customers about preferred flavors. Test 2-3 variants and measure sales response.', 'DEMO DATA - Market Intelligence Feed', 'https://lechgo.local', 'https://via.placeholder.com/400x200?text=Lechon+Trends', '2026-09-02 07:34:59', 1, '2026-09-04 14:34:59', '2026-09-04 14:34:59'),
(8, 'Feed Price Volatility Expected This Quarter', 'Agricultural experts predict feed price fluctuations due to commodity market changes', 'Feed costs are expected to experience volatility. Timing feed purchases strategically could optimize costs.', 'Pig Farming', 'Price Change', 'High', 'Feed costs directly impact pig production expenses. Strategic purchasing and supply chain optimization are critical.', 'Establish relationships with multiple feed suppliers. Consider bulk purchasing during price dips.', 'DEMO DATA - Market Intelligence Feed', 'https://lechgo.local', 'https://via.placeholder.com/400x200?text=Feed+Prices', '2026-08-30 07:34:59', 1, '2026-09-04 14:34:59', '2026-09-04 14:34:59'),
(9, 'Growing Demand for Sustainable Farming Practices', 'Consumers increasingly prefer products from farms with eco-friendly practices', 'Sustainable farming is becoming a market differentiator. Implementing green practices could improve brand value.', 'Agriculture', 'Market Opportunity', 'Moderate', 'Marketing sustainable practices could attract premium-price customers and improve brand reputation.', 'Document current sustainable practices. Consider certifications or eco-friendly marketing.', 'DEMO DATA - Market Intelligence Feed', 'https://lechgo.local', 'https://via.placeholder.com/400x200?text=Sustainable+Farming', '2026-08-28 07:34:59', 1, '2026-09-04 14:34:59', '2026-09-04 14:34:59'),
(10, 'Food Safety Regulations Update for Livestock Producers', 'New compliance requirements announced for pig farming and meat processing', 'Regulatory changes require updated practices. Ensure compliance to avoid penalties.', 'Industry News', 'Regulatory Change', 'High', 'Regulatory compliance is mandatory. Non-compliance could result in fines or operational restrictions.', 'Review new regulations in detail. Audit current practices for compliance gaps.', 'DEMO DATA - Market Intelligence Feed', 'https://lechgo.local', 'https://via.placeholder.com/400x200?text=Food+Safety', '2026-08-25 07:34:59', 1, '2026-09-04 14:34:59', '2026-09-04 14:34:59');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'caretaker_approved, caretaker_request, order_placed, etc',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL COMMENT 'URL to redirect when clicked',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`, `read_at`) VALUES
(266, 66, 'piggery_report', 'New Piggery Status Report', 'Dave Dela cerna submitted a report: \"REport\" — Status: ✅ Good', '/livestock-owner/caretaker-pig-inventory', 0, '2026-08-14 10:07:31', NULL),
(267, 66, 'pig_inquiry', ' Pig Reserved — Action Required', 'Dave Dela Cerna wants to buy PINPIN1-PIG1 (₱9,984.00). Message: \"ambi\". Go to My Pig Market to confirm.', '/livestock-owner/my-pig-market', 0, '2026-08-14 10:20:21', NULL),
(268, 69, 'pig_sold', ' Your Pig Order is Confirmed!', 'Great news! The livestock owner has confirmed your reservation for PINPIN1-PIG1 (₱9,984.00). Please coordinate with the seller for pickup/delivery. Message from seller: \"ge\"', '/customer/my-orders', 0, '2026-08-14 10:20:40', NULL),
(269, 69, 'cost_computed', ' Receipt & Payment Instructions Ready', 'Your order receipt for 0 is ready! Full Payment: ₱12,834.00. Payment deadline: Not set Please check your orders for complete payment instructions.', '/customer/my-orders', 0, '2026-08-14 10:23:58', NULL),
(270, 66, 'pig_inquiry', ' Pig Reserved — Action Required', 'Dave Dela Cerna wants to buy PINPIN1-PIG2 (₱2,856.00). Message: \"ge\". Go to My Pig Market to confirm.', '/livestock-owner/my-pig-market', 0, '2026-08-14 11:05:19', NULL),
(271, 69, 'pig_sold', ' Your Pig Order is Confirmed!', 'Great news! The livestock owner has confirmed your reservation for PINPIN1-PIG2 (₱2,856.00). Please coordinate with the seller for pickup/delivery.', '/customer/my-orders', 0, '2026-08-14 11:05:28', NULL),
(272, 69, 'cost_computed', ' Receipt & Payment Instructions Ready', 'Your order receipt for 0 is ready! Full Payment: ₱5,206.00. Payment deadline: Not set Please check your orders for complete payment instructions.', '/customer/my-orders', 0, '2026-08-14 11:10:02', NULL),
(273, 66, 'piggery_report', 'New Piggery Status Report', 'Dave Dela cerna submitted a report: \"Report\" — Status: ✅ Good', '/livestock-owner/caretaker-pig-inventory', 0, '2026-09-01 04:14:04', NULL),
(274, 66, 'piggery_report', 'New Piggery Status Report', 'Dave Dela cerna submitted a report: \"First Report\" — Status: ✅ Good', '/livestock-owner/caretaker-pig-inventory', 0, '2026-09-02 00:29:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `swine_id` int(11) NOT NULL,
  `lechonero_id` int(11) NOT NULL,
  `order_status` enum('pending','confirmed','preparing','cooking','delivering','completed','cancelled') DEFAULT 'pending',
  `total_price` decimal(10,2) DEFAULT NULL,
  `order_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_total_cost`
--

CREATE TABLE `order_total_cost` (
  `id` int(11) NOT NULL,
  `swine_order_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `livestock_owner_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `delivery_address` text NOT NULL,
  `delivery_method` enum('pickup','delivery') NOT NULL DEFAULT 'pickup',
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `labor_cost` decimal(10,2) DEFAULT 0.00,
  `pig_tag_id` varchar(100) NOT NULL,
  `pin_number` varchar(50) NOT NULL,
  `pig_base_amount` decimal(10,2) NOT NULL,
  `include_laman_loob` tinyint(1) DEFAULT 0,
  `laman_loob_price` decimal(10,2) DEFAULT 0.00,
  `include_boopes` tinyint(1) DEFAULT 0,
  `boopes_price` decimal(10,2) DEFAULT 0.00,
  `include_dinuguan` tinyint(1) DEFAULT 0,
  `dinuguan_price` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `order_status` enum('pending','confirmed','preparing','cooking','delivering','completed','cancelled','cost_computed','ready_for_pickup') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_status` enum('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  `payment_type` enum('full','down') DEFAULT 'full' COMMENT 'Payment type: full or down payment (50%)',
  `down_payment_amount` decimal(10,2) DEFAULT 0.00,
  `amount_paid` decimal(10,2) DEFAULT 0.00 COMMENT 'Amount paid so far',
  `remaining_balance` decimal(10,2) DEFAULT 0.00 COMMENT 'Remaining balance to be paid',
  `payment_deadline` date DEFAULT NULL,
  `payment_instructions` text DEFAULT NULL,
  `final_payment_method` varchar(50) DEFAULT NULL COMMENT 'Payment method for final/remaining payment',
  `final_payment_reference` varchar(100) DEFAULT NULL COMMENT 'Payment reference for final payment',
  `final_payment_date` timestamp NULL DEFAULT NULL COMMENT 'Date when final payment was made',
  `paid_at` timestamp NULL DEFAULT NULL COMMENT 'Date when payment was completed',
  `computed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_total_cost`
--

INSERT INTO `order_total_cost` (`id`, `swine_order_id`, `order_number`, `customer_id`, `livestock_owner_id`, `customer_name`, `delivery_address`, `delivery_method`, `delivery_fee`, `labor_cost`, `pig_tag_id`, `pin_number`, `pig_base_amount`, `include_laman_loob`, `laman_loob_price`, `include_boopes`, `boopes_price`, `include_dinuguan`, `dinuguan_price`, `subtotal`, `total_cost`, `order_status`, `payment_method`, `payment_reference`, `payment_status`, `payment_type`, `down_payment_amount`, `amount_paid`, `remaining_balance`, `payment_deadline`, `payment_instructions`, `final_payment_method`, `final_payment_reference`, `final_payment_date`, `paid_at`, `computed_at`, `created_at`, `updated_at`) VALUES
(27, 22, 'PIG-69-1786702822', 69, 8, 'Dave Dela Cerna', '350', 'delivery', '350.00', '2500.00', '0', 'PIN1', '9984.00', 1, '0.00', 0, '0.00', 0, '0.00', '12834.00', '12834.00', 'pending', 'paymongo', 'link_415bc654b1ce9bd16d76d5c2', 'paid', 'full', '0.00', '12834.00', '0.00', NULL, '', NULL, NULL, NULL, '2026-08-14 10:41:24', '2026-08-14 10:23:58', '2026-08-14 10:23:58', '2026-08-14 10:41:24'),
(28, 23, 'PIG-69-1786705519', 69, 8, 'Dave Dela Cerna', 'diri ra', 'delivery', '350.00', '2000.00', '0', 'PIN1', '2856.00', 0, '0.00', 1, '0.00', 0, '0.00', '5206.00', '5206.00', 'pending', 'paymongo', 'link_25e0b3eeb3124970454e60f7', 'paid', 'full', '0.00', '5206.00', '0.00', NULL, '', NULL, NULL, NULL, '2026-08-14 11:36:01', '2026-08-14 11:10:02', '2026-08-14 11:10:02', '2026-08-14 11:36:01');

-- --------------------------------------------------------

--
-- Table structure for table `otp_verification`
--

CREATE TABLE `otp_verification` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otp_verification`
--

INSERT INTO `otp_verification` (`id`, `email`, `otp_code`, `attempts`, `verified_at`, `expires_at`, `locked_until`, `created_at`) VALUES
(259, 'mahinaylydia1963@gmail.com', '572747', 0, '2026-07-18 12:49:58', '2026-07-18 15:54:27', NULL, '2026-07-18 12:49:28'),
(260, 'zhyrielcarillo@gmail.com', '958614', 0, '2026-07-18 12:53:20', '2026-07-18 15:57:59', NULL, '2026-07-18 12:53:01'),
(281, 'mahinaylydia82@gmail.com', '786160', 0, '2026-07-27 09:07:05', '2026-07-27 12:11:42', NULL, '2026-07-27 09:06:42'),
(287, 'jennyah153@gmail.com', '398384', 0, '2026-08-06 01:32:43', '2026-08-06 04:35:13', NULL, '2026-08-06 01:30:12'),
(311, 'jennyvievemahinay@gmail.com', '722925', 0, '2026-08-26 09:41:39', '2026-08-26 12:46:10', NULL, '2026-08-26 09:41:10'),
(323, 'davedelacerna09@gmail.com', '609875', 0, '2026-09-02 00:08:26', '2026-09-02 03:12:55', NULL, '2026-09-02 00:07:55'),
(333, 'schoolprps2004@gmail.com', '963889', 0, '2026-09-05 12:05:28', '2026-09-05 15:10:05', NULL, '2026-09-05 12:05:05'),
(334, 'xzytrion09@gmail.com', '291693', 0, '2026-09-05 12:37:34', '2026-09-05 15:42:11', NULL, '2026-09-05 12:37:11');

-- --------------------------------------------------------

--
-- Table structure for table `pig_caretakers`
--

CREATE TABLE `pig_caretakers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `livestock_owner_id` int(11) DEFAULT NULL,
  `farm_name` varchar(150) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pig_caretakers`
--

INSERT INTO `pig_caretakers` (`id`, `user_id`, `livestock_owner_id`, `farm_name`, `full_name`, `location`, `contact_number`, `created_at`, `updated_at`) VALUES
(21, 67, 8, 'CORDIO PONDIAS FARM', 'Dave Dela cerna', 'Daliao Junction, Daliao, Toril, Davao City', '09927886336', '2026-07-27 08:51:40', '2026-07-27 08:51:40');

-- --------------------------------------------------------

--
-- Table structure for table `pig_details`
--

CREATE TABLE `pig_details` (
  `id` int(11) NOT NULL,
  `cage_id` int(11) NOT NULL,
  `pig_tag_id` varchar(50) DEFAULT NULL,
  `age_months` int(11) DEFAULT NULL,
  `age_days` int(11) DEFAULT 0,
  `weight_kg` decimal(6,2) DEFAULT NULL,
  `heart_girth_cm` decimal(10,2) DEFAULT NULL COMMENT 'Heart girth measurement in centimeters',
  `body_length_cm` decimal(10,2) DEFAULT NULL COMMENT 'Body length measurement in centimeters',
  `calculated_weight_kg` decimal(10,2) DEFAULT NULL COMMENT 'Auto-calculated weight using formula: (Heart Girth² × Body Length) ÷ 69.3',
  `weight_source` enum('manual','calculated') DEFAULT 'manual' COMMENT 'Source of weight: manual entry or calculated',
  `health_status` enum('healthy','sick','recovering','deceased') DEFAULT 'healthy',
  `date_added` date DEFAULT NULL,
  `status` enum('active','sold','removed') DEFAULT 'active',
  `photo_url` varchar(255) DEFAULT NULL,
  `aic_file` varchar(255) DEFAULT NULL,
  `brgy_cert_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pig_details`
--

INSERT INTO `pig_details` (`id`, `cage_id`, `pig_tag_id`, `age_months`, `age_days`, `weight_kg`, `heart_girth_cm`, `body_length_cm`, `calculated_weight_kg`, `weight_source`, `health_status`, `date_added`, `status`, `photo_url`, `aic_file`, `brgy_cert_file`, `created_at`, `updated_at`) VALUES
(84, 144, 'PINPIN1-PIG1', 3, 82, '31.20', '0.75', '0.55', '21.40', 'calculated', 'healthy', '2026-09-02', 'active', '/uploads/pigs/pig_1788320334_280.jpeg', NULL, NULL, '2026-09-02 03:38:54', '2026-09-02 03:38:54'),
(85, 144, 'PINPIN1-PIG2', 3, 82, '31.00', '0.80', '0.51', '22.60', 'calculated', 'healthy', '2026-09-02', 'active', '/uploads/pigs/pig_1788320366_459.jpg', NULL, NULL, '2026-09-02 03:39:25', '2026-09-02 03:39:25');

-- --------------------------------------------------------

--
-- Table structure for table `pig_pins`
--

CREATE TABLE `pig_pins` (
  `id` int(11) NOT NULL,
  `caretaker_id` int(11) NOT NULL,
  `cage_number` varchar(5) NOT NULL,
  `current_pig_count` int(11) NOT NULL DEFAULT 0
) ;

--
-- Dumping data for table `pig_pins`
--

INSERT INTO `pig_pins` (`id`, `caretaker_id`, `cage_number`, `current_pig_count`, `max_capacity`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(144, 21, 'PIN1', 2, 3, 'active', NULL, '2026-08-14 09:45:23', '2026-09-02 03:39:25'),
(145, 21, 'PIN2', 0, 3, 'inactive', NULL, '2026-09-02 00:35:24', '2026-09-02 00:39:11');

-- --------------------------------------------------------

--
-- Table structure for table `pinned_trends`
--

CREATE TABLE `pinned_trends` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User who pinned the trend',
  `trend_id` int(11) NOT NULL COMMENT 'Trend being pinned',
  `pinned_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `placed_orders`
--

CREATE TABLE `placed_orders` (
  `id` int(11) NOT NULL,
  `swine_order_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `livestock_owner_id` int(11) NOT NULL,
  `delivery_address` text NOT NULL,
  `delivery_notes` text DEFAULT NULL,
  `delivery_method` enum('pickup','delivery') DEFAULT 'pickup',
  `include_laman_loob` tinyint(1) DEFAULT 0,
  `include_boopes` tinyint(1) DEFAULT 0,
  `include_dinuguan` tinyint(1) DEFAULT 0,
  `placed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `placed_orders`
--

INSERT INTO `placed_orders` (`id`, `swine_order_id`, `order_number`, `customer_id`, `livestock_owner_id`, `delivery_address`, `delivery_notes`, `delivery_method`, `include_laman_loob`, `include_boopes`, `include_dinuguan`, `placed_at`, `created_at`, `updated_at`) VALUES
(28, 22, 'PIG-69-1786702822', 69, 8, '350', '', 'delivery', 1, 0, 0, '2026-08-14 10:21:03', '2026-08-14 10:21:03', '2026-08-14 10:21:03'),
(29, 23, 'PIG-69-1786705519', 69, 8, 'diri ra', '', 'delivery', 0, 1, 0, '2026-08-14 11:05:47', '2026-08-14 11:05:47', '2026-08-14 11:05:47');

-- --------------------------------------------------------

--
-- Table structure for table `receipts_record`
--

CREATE TABLE `receipts_record` (
  `id` int(11) NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `feed_order_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `buyer_name` varchar(255) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `order_date` datetime NOT NULL,
  `confirmed_date` datetime NOT NULL,
  `accepted_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requirements`
--

CREATE TABLE `requirements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `farm_name` varchar(255) NOT NULL,
  `farm_location` text NOT NULL,
  `business_registration` varchar(500) DEFAULT NULL COMMENT 'DTI or SEC',
  `barangay_clearance` varchar(500) DEFAULT NULL,
  `cpdo_clearance` varchar(500) DEFAULT NULL COMMENT 'Locational Clearance',
  `denr_ecc` varchar(500) DEFAULT NULL COMMENT 'Environmental Compliance Certificate',
  `cvo_sanitary_permit` varchar(500) DEFAULT NULL COMMENT 'City Veterinarian Office',
  `business_permit` varchar(500) DEFAULT NULL COMMENT 'Davao City Business Permit',
  `status` enum('pending','approved','rejected','incomplete') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `requirements`
--

INSERT INTO `requirements` (`id`, `user_id`, `application_id`, `farm_name`, `farm_location`, `business_registration`, `barangay_clearance`, `cpdo_clearance`, `denr_ecc`, `cvo_sanitary_permit`, `business_permit`, `status`, `remarks`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(3, 66, 11, 'CORDIO PONDIAS FARM', 'Daliao Junction, Daliao, Toril, Davao City', 'uploads/piggery_applications/piggery_66_business_registration_1785076589946.pdf', 'uploads/piggery_applications/piggery_66_barangay_clearance_1785076589545.pdf', 'uploads/piggery_applications/piggery_66_cpdo_clearance_1785076589341.pdf', 'uploads/piggery_applications/piggery_66_denr_ecc_1785076589180.pdf', 'uploads/piggery_applications/piggery_66_cvo_sanitary_permit_1785076589648.pdf', 'uploads/piggery_applications/piggery_66_business_permit_1785076589595.pdf', 'pending', NULL, NULL, NULL, '2026-07-26 07:36:29', '2026-07-26 07:36:29');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `reservation_date` date DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reserved_orders`
--

CREATE TABLE `reserved_orders` (
  `id` int(11) NOT NULL,
  `swine_order_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `livestock_owner_id` int(11) NOT NULL,
  `pig_tag_id` varchar(100) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `reserved_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmed_by_owner_at` timestamp NULL DEFAULT NULL,
  `status` enum('reserved','confirmed','cancelled') NOT NULL DEFAULT 'reserved',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reserved_orders`
--

INSERT INTO `reserved_orders` (`id`, `swine_order_id`, `order_number`, `customer_id`, `livestock_owner_id`, `pig_tag_id`, `total_price`, `reserved_date`, `confirmed_by_owner_at`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(27, 22, 'PIG-69-1786702822', 69, 8, 'PINPIN1-PIG1', '9984.00', '2026-08-14 10:20:40', '2026-08-14 10:20:40', 'confirmed', 'ge', '2026-08-14 10:20:40', '2026-08-14 10:20:40'),
(28, 23, 'PIG-69-1786705519', 69, 8, 'PINPIN1-PIG2', '2856.00', '2026-08-14 11:05:28', '2026-08-14 11:05:28', 'confirmed', '', '2026-08-14 11:05:28', '2026-08-14 11:05:28');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `role_applications`
--

CREATE TABLE `role_applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `application_type` enum('customer','piggery_owner') NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `farm_name` varchar(255) DEFAULT NULL,
  `farm_location` text DEFAULT NULL,
  `proof_documents` text DEFAULT NULL COMMENT 'JSON array of document file paths',
  `remarks` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL COMMENT 'Admin user ID who reviewed',
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_applications`
--

INSERT INTO `role_applications` (`id`, `user_id`, `application_type`, `status`, `farm_name`, `farm_location`, `proof_documents`, `remarks`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(11, 66, 'piggery_owner', 'approved', 'CORDIO PONDIAS FARM', 'Daliao Junction, Daliao, Toril, Davao City', 'null', '', 65, '2026-07-26 14:37:04', '2026-07-26 14:36:29', '2026-07-26 14:37:04');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `farm_name` varchar(150) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `swine`
--

CREATE TABLE `swine` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `weight` decimal(6,2) DEFAULT NULL,
  `health_status` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `availability` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `swine_inventory`
--

CREATE TABLE `swine_inventory` (
  `id` int(11) NOT NULL,
  `caretaker_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `overall_status` enum('good','concern','critical') NOT NULL DEFAULT 'good',
  `report_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `swine_inventory`
--

INSERT INTO `swine_inventory` (`id`, `caretaker_id`, `title`, `content`, `overall_status`, `report_date`, `created_at`) VALUES
(20, 21, 'First Report', NULL, 'good', '2026-09-02', '2026-09-02 00:29:22');

-- --------------------------------------------------------

--
-- Table structure for table `swine_inventory_snapshots`
--

CREATE TABLE `swine_inventory_snapshots` (
  `id` int(11) NOT NULL,
  `swine_inventory_id` int(11) NOT NULL,
  `pig_detail_id` int(11) DEFAULT NULL,
  `pig_tag_id` varchar(100) DEFAULT NULL,
  `pin_number` varchar(50) DEFAULT NULL,
  `age_months` int(11) DEFAULT NULL,
  `weight_kg` decimal(8,2) DEFAULT NULL,
  `health_status` enum('healthy','sick','recovering') DEFAULT 'healthy',
  `date_added` date DEFAULT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `aic_file` varchar(255) DEFAULT NULL,
  `brgy_cert_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `swine_inventory_snapshots`
--

INSERT INTO `swine_inventory_snapshots` (`id`, `swine_inventory_id`, `pig_detail_id`, `pig_tag_id`, `pin_number`, `age_months`, `weight_kg`, `health_status`, `date_added`, `photo_url`, `aic_file`, `brgy_cert_file`, `created_at`) VALUES
(22, 20, NULL, 'PINPIN1-PIG1', 'PIN1', 3, '31.20', 'healthy', '2026-09-02', '/uploads/pigs/pig_1788308827_715.jpeg', NULL, NULL, '2026-09-02 00:29:22'),
(23, 20, NULL, 'PINPIN1-PIG2', 'PIN1', 2, '20.00', 'healthy', '2026-09-02', '/uploads/pigs/pig_1788308880_909.jpeg', NULL, NULL, '2026-09-02 00:29:22'),
(24, 20, NULL, 'PINPIN1-PIG3', 'PIN1', 2, '12.60', 'healthy', '2026-09-02', '/uploads/pigs/pig_1788308906_942.jpg', NULL, NULL, '2026-09-02 00:29:22');

-- --------------------------------------------------------

--
-- Table structure for table `swine_order_status`
--

CREATE TABLE `swine_order_status` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `livestock_owner_id` int(11) NOT NULL,
  `hogs_market_id` int(11) NOT NULL,
  `pig_detail_id` int(11) NOT NULL,
  `pig_tag_id` varchar(100) NOT NULL,
  `pin_number` varchar(50) NOT NULL,
  `weight_kg` decimal(8,2) NOT NULL,
  `price_per_kg` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `inquiry_message` text DEFAULT NULL,
  `seller_feedback` text DEFAULT NULL,
  `order_status` enum('pending','confirmed','preparing','cooking','delivering','completed','cancelled') DEFAULT 'pending',
  `payment_status` enum('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `delivery_notes` text DEFAULT NULL,
  `pickup_date` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `swine_order_status`
--

INSERT INTO `swine_order_status` (`id`, `order_number`, `customer_id`, `livestock_owner_id`, `hogs_market_id`, `pig_detail_id`, `pig_tag_id`, `pin_number`, `weight_kg`, `price_per_kg`, `total_price`, `inquiry_message`, `seller_feedback`, `order_status`, `payment_status`, `payment_method`, `payment_reference`, `delivery_address`, `delivery_notes`, `pickup_date`, `completed_at`, `cancelled_at`, `cancellation_reason`, `created_at`, `updated_at`) VALUES
(22, 'PIG-69-1786702822', 69, 8, 46, 68, '0', 'PIN1', '31.20', '320.00', '9984.00', 'ambi', 'ge', '', 'paid', 'paymongo', 'link_415bc654b1ce9bd16d76d5c2', NULL, NULL, '2026-08-22', NULL, NULL, NULL, '2026-08-14 10:20:21', '2026-08-14 10:41:24'),
(23, 'PIG-69-1786705519', 69, 8, 47, 69, '0', 'PIN1', '8.40', '340.00', '2856.00', 'ge', '', '', 'paid', 'paymongo', 'link_25e0b3eeb3124970454e60f7', NULL, NULL, '2026-08-22', NULL, NULL, NULL, '2026-08-14 11:05:19', '2026-08-14 11:36:01');

-- --------------------------------------------------------

--
-- Table structure for table `transaction_logs`
--

CREATE TABLE `transaction_logs` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `order_number` varchar(100) DEFAULT NULL,
  `livestock_owner_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `buyer_name` varchar(150) NOT NULL,
  `feed_type` varchar(100) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `quantity_kg` decimal(8,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `purchase_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_status` varchar(50) DEFAULT 'pending',
  `order_status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `role` enum('customer','lechonero','livestock_owner','supplier','pig_caretaker','admin','logistics','feed_distributor','pig_slaughter') NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `email_verified`, `email_verified_at`, `last_login`, `role`, `phone`, `created_at`) VALUES
(65, 'JENNYVIEVE NIODA MAHINAY', 'jennyvievemahinay@gmail.com', '$2y$10$Z8nVObs7GU7B0wIt2o4LY.SuHa1ejkgJO2Zj5xT2z7B2UdAG2rvbO', 1, '2026-07-26 09:22:13', NULL, 'admin', '09464111328', '2026-07-26 09:22:03'),
(66, 'CORDIO PONDIAS', 'schoolprps2004@gmail.com', '$2y$10$sf3KnalGkXwQotXKGxRTJuf8qTBuM8cHgluyIoprUZgjCIzSZq4Ma', 1, '2026-07-26 09:47:23', NULL, 'livestock_owner', '09296412812', '2026-07-26 09:46:51'),
(67, 'Dave Dela cerna', 'davedelacerna09@gmail.com', '$2y$10$.KrNrZKyWNIwjn1sg/mK7.N20/V.4FYPSa.N6FjaOG9TSHmchkiju', 1, '2026-07-27 06:10:26', NULL, 'pig_caretaker', '09927886336', '2026-07-27 06:09:26'),
(68, 'Migulito Padidas', 'mahinaylydia82@gmail.com', '$2y$10$OL7.5zjWUzP3EUv5JGjjv.WwE2ChlXT20kObGAAGd4PRId4DnK8o6', 1, '2026-07-27 08:57:27', NULL, 'lechonero', '09092965085', '2026-07-27 08:57:02'),
(69, 'Dave Dela Cerna', 'xzytrion09@gmail.com', '$2y$10$BEQ6dv8vNYrUDu4RfJV5iemf9pnxHnJpnN5FBJEZXqDv0H7yn2rSO', 1, '2026-07-29 07:26:43', NULL, 'customer', '09937886336', '2026-07-29 07:26:25'),
(70, 'Jennyah', 'jennyah153@gmail.com', '$2y$10$qRoonYc30.pGCCSfkuqgyewyabFaiyK1C9SgsoCkXnce/EPQ2SCwy', 1, '2026-08-06 01:30:12', NULL, 'customer', '09872356651', '2026-08-06 01:29:55');

-- --------------------------------------------------------

--
-- Table structure for table `workflow_state_log`
--

CREATE TABLE `workflow_state_log` (
  `id` int(11) NOT NULL,
  `contract_document_id` int(11) NOT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by_user_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit trail for contract workflow state changes';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `calendar_items`
--
ALTER TABLE `calendar_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trend_id` (`trend_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_calendar_date` (`calendar_date`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `caretaker_reports`
--
ALTER TABLE `caretaker_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_caretaker_id` (`caretaker_id`),
  ADD KEY `idx_report_date` (`report_date`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `cooking_schedule`
--
ALTER TABLE `cooking_schedule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_order` (`order_number`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_butcher_time` (`time_to_butcher`),
  ADD KEY `idx_date_to_butcher` (`date_to_butcher`);

--
-- Indexes for table `delivery_status`
--
ALTER TABLE `delivery_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_order` (`order_number`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_driver` (`driver_user_id`);

--
-- Indexes for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `employee_applications`
--
ALTER TABLE `employee_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_applicant` (`applicant_user_id`),
  ADD KEY `idx_owner` (`livestock_owner_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_status_date` (`status`,`DESC`);

--
-- Indexes for table `employee_assignments`
--
ALTER TABLE `employee_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_owner` (`livestock_owner_id`,`employee_user_id`),
  ADD KEY `employee_user_id` (`employee_user_id`);

--
-- Indexes for table `feeding_schedule`
--
ALTER TABLE `feeding_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_feeding_schedule_feed` (`feed_inventory_id`),
  ADD KEY `idx_caretaker_id` (`caretaker_id`),
  ADD KEY `idx_feeding_date` (`feeding_date`),
  ADD KEY `fk_feeding_schedule_pin` (`cage_id`);

--
-- Indexes for table `feed_distributors`
--
ALTER TABLE `feed_distributors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_fd_user` (`user_id`),
  ADD KEY `idx_fd_location` (`location_id`);

--
-- Indexes for table `feed_distributor_orders`
--
ALTER TABLE `feed_distributor_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fdo_distributor` (`distributor_id`),
  ADD KEY `idx_fdo_buyer` (`buyer_user_id`);

--
-- Indexes for table `feed_distributor_order_items`
--
ALTER TABLE `feed_distributor_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fdoi_order` (`order_id`),
  ADD KEY `idx_fdoi_product` (`product_id`);

--
-- Indexes for table `feed_distributor_products`
--
ALTER TABLE `feed_distributor_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fdp_distributor` (`distributor_id`);

--
-- Indexes for table `feed_inventory`
--
ALTER TABLE `feed_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_caretaker_id` (`caretaker_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `feed_orders`
--
ALTER TABLE `feed_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_caretaker_id` (`caretaker_id`),
  ADD KEY `idx_order_status` (`order_status`),
  ADD KEY `idx_payment_status` (`payment_status`);

--
-- Indexes for table `feed_order_status`
--
ALTER TABLE `feed_order_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_feed_order_id` (`feed_order_id`),
  ADD KEY `idx_status_type` (`status_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_feed_order_status_user` (`changed_by`);

--
-- Indexes for table `feed_products`
--
ALTER TABLE `feed_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_feed_type` (`feed_type`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `hogs_market`
--
ALTER TABLE `hogs_market`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_owner_id` (`livestock_owner_id`),
  ADD KEY `pig_detail_id` (`pig_detail_id`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livestock_owner_id` (`livestock_owner_id`),
  ADD KEY `log_date` (`log_date`),
  ADD KEY `idx_owner_date` (`livestock_owner_id`,`log_date`);

--
-- Indexes for table `lechoneros`
--
ALTER TABLE `lechoneros`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `lechon_listings`
--
ALTER TABLE `lechon_listings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_owner` (`livestock_owner_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_listing_type` (`listing_type`),
  ADD KEY `idx_available_date` (`available_date`);

--
-- Indexes for table `lechon_status`
--
ALTER TABLE `lechon_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_order` (`order_number`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_lechonero` (`lechonero_user_id`),
  ADD KEY `idx_completed_date` (`completed_at`),
  ADD KEY `idx_quality_assessment` (`internal_temperature`,`skin_texture`,`meat_tenderness`);

--
-- Indexes for table `livestock_feed_orders`
--
ALTER TABLE `livestock_feed_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_livestock_owner_id` (`livestock_owner_id`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_order_status` (`order_status`),
  ADD KEY `idx_payment_status` (`payment_status`);

--
-- Indexes for table `livestock_feed_order_items`
--
ALTER TABLE `livestock_feed_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_livestock_feed_order_items_product` (`feed_product_id`),
  ADD KEY `idx_feed_order_id` (`feed_order_id`);

--
-- Indexes for table `livestock_owners`
--
ALTER TABLE `livestock_owners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_id` (`user_id`),
  ADD KEY `idx_farm_name` (`farm_name`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`location_id`),
  ADD KEY `idx_municipality` (`municipality`),
  ADD KEY `idx_barangay` (`barangay`),
  ADD KEY `idx_city` (`city`);

--
-- Indexes for table `market_notes`
--
ALTER TABLE `market_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_note_date` (`note_date`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `market_trends`
--
ALTER TABLE `market_trends`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_impact_level` (`impact_level`),
  ADD KEY `idx_published_at` (`published_at`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`,`created_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `swine_id` (`swine_id`),
  ADD KEY `lechonero_id` (`lechonero_id`);

--
-- Indexes for table `order_total_cost`
--
ALTER TABLE `order_total_cost`
  ADD PRIMARY KEY (`id`),
  ADD KEY `swine_order_id` (`swine_order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `livestock_owner_id` (`livestock_owner_id`);

--
-- Indexes for table `otp_verification`
--
ALTER TABLE `otp_verification`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `pig_caretakers`
--
ALTER TABLE `pig_caretakers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_livestock_owner_id` (`livestock_owner_id`);

--
-- Indexes for table `pig_details`
--
ALTER TABLE `pig_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cage_id` (`cage_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `pinned_trends`
--
ALTER TABLE `pinned_trends`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_pin` (`user_id`,`trend_id`),
  ADD KEY `trend_id` (`trend_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_pinned_at` (`pinned_at`);

--
-- Indexes for table `placed_orders`
--
ALTER TABLE `placed_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `swine_order_id` (`swine_order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `livestock_owner_id` (`livestock_owner_id`),
  ADD KEY `idx_delivery_method` (`delivery_method`);

--
-- Indexes for table `receipts_record`
--
ALTER TABLE `receipts_record`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `idx_receipt_number` (`receipt_number`),
  ADD KEY `idx_feed_order` (`feed_order_id`),
  ADD KEY `idx_buyer` (`buyer_id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `fk_receipt_accepted_by` (`accepted_by`);

--
-- Indexes for table `requirements`
--
ALTER TABLE `requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_application_id` (`application_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `reserved_orders`
--
ALTER TABLE `reserved_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `swine_order_id` (`swine_order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `livestock_owner_id` (`livestock_owner_id`);

--
-- Indexes for table `role_applications`
--
ALTER TABLE `role_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `application_type` (`application_type`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_suppliers_location` (`location_id`);

--
-- Indexes for table `swine`
--
ALTER TABLE `swine`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `swine_inventory`
--
ALTER TABLE `swine_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_caretaker_id` (`caretaker_id`);

--
-- Indexes for table `swine_inventory_snapshots`
--
ALTER TABLE `swine_inventory_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_swine_inventory_id` (`swine_inventory_id`),
  ADD KEY `idx_pig_detail_id` (`pig_detail_id`);

--
-- Indexes for table `swine_order_status`
--
ALTER TABLE `swine_order_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `livestock_owner_id` (`livestock_owner_id`),
  ADD KEY `hogs_market_id` (`hogs_market_id`),
  ADD KEY `pig_detail_id` (`pig_detail_id`),
  ADD KEY `order_status` (`order_status`);

--
-- Indexes for table `transaction_logs`
--
ALTER TABLE `transaction_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tl_order` (`order_id`),
  ADD KEY `idx_tl_supplier` (`supplier_id`),
  ADD KEY `idx_tl_owner` (`livestock_owner_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `budget_planner`
--
ALTER TABLE `budget_planner`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calendar_items`
--
ALTER TABLE `calendar_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `caretaker_reports`
--
ALTER TABLE `caretaker_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cooking_schedule`
--
ALTER TABLE `cooking_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `customer_payments`
--
ALTER TABLE `customer_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_status`
--
ALTER TABLE `delivery_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `employee_applications`
--
ALTER TABLE `employee_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `employee_assignments`
--
ALTER TABLE `employee_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `feeding_schedule`
--
ALTER TABLE `feeding_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `feed_distributors`
--
ALTER TABLE `feed_distributors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feed_distributor_orders`
--
ALTER TABLE `feed_distributor_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `feed_distributor_order_items`
--
ALTER TABLE `feed_distributor_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `feed_distributor_products`
--
ALTER TABLE `feed_distributor_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `feed_inventory`
--
ALTER TABLE `feed_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `feed_orders`
--
ALTER TABLE `feed_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feed_order_status`
--
ALTER TABLE `feed_order_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=150;

--
-- AUTO_INCREMENT for table `feed_products`
--
ALTER TABLE `feed_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `hogs_market`
--
ALTER TABLE `hogs_market`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `lechoneros`
--
ALTER TABLE `lechoneros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lechon_listings`
--
ALTER TABLE `lechon_listings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lechon_status`
--
ALTER TABLE `lechon_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `livestock_feed_orders`
--
ALTER TABLE `livestock_feed_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `livestock_feed_order_items`
--
ALTER TABLE `livestock_feed_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `livestock_owners`
--
ALTER TABLE `livestock_owners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=122;

--
-- AUTO_INCREMENT for table `market_notes`
--
ALTER TABLE `market_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `market_trends`
--
ALTER TABLE `market_trends`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=275;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_total_cost`
--
ALTER TABLE `order_total_cost`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `otp_verification`
--
ALTER TABLE `otp_verification`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=335;

--
-- AUTO_INCREMENT for table `pig_caretakers`
--
ALTER TABLE `pig_caretakers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `pig_details`
--
ALTER TABLE `pig_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `pig_pins`
--
ALTER TABLE `pig_pins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pinned_trends`
--
ALTER TABLE `pinned_trends`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `placed_orders`
--
ALTER TABLE `placed_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `receipts_record`
--
ALTER TABLE `receipts_record`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `requirements`
--
ALTER TABLE `requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reserved_orders`
--
ALTER TABLE `reserved_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_applications`
--
ALTER TABLE `role_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `swine`
--
ALTER TABLE `swine`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `swine_inventory`
--
ALTER TABLE `swine_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `swine_inventory_snapshots`
--
ALTER TABLE `swine_inventory_snapshots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `swine_order_status`
--
ALTER TABLE `swine_order_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `transaction_logs`
--
ALTER TABLE `transaction_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `calendar_items`
--
ALTER TABLE `calendar_items`
  ADD CONSTRAINT `calendar_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `calendar_items_ibfk_2` FOREIGN KEY (`trend_id`) REFERENCES `market_trends` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `caretaker_reports`
--
ALTER TABLE `caretaker_reports`
  ADD CONSTRAINT `fk_caretaker_reports_caretaker` FOREIGN KEY (`caretaker_id`) REFERENCES `pig_caretakers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  ADD CONSTRAINT `email_verification_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_applications`
--
ALTER TABLE `employee_applications`
  ADD CONSTRAINT `fk_employee_app_applicant` FOREIGN KEY (`applicant_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_employee_app_owner` FOREIGN KEY (`livestock_owner_id`) REFERENCES `livestock_owners` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_assignments`
--
ALTER TABLE `employee_assignments`
  ADD CONSTRAINT `employee_assignments_ibfk_2` FOREIGN KEY (`employee_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_assignments_ibfk_livestock_owner` FOREIGN KEY (`livestock_owner_id`) REFERENCES `livestock_owners` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `feeding_schedule`
--
ALTER TABLE `feeding_schedule`
  ADD CONSTRAINT `fk_feeding_schedule_caretaker` FOREIGN KEY (`caretaker_id`) REFERENCES `pig_caretakers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feeding_schedule_feed` FOREIGN KEY (`feed_inventory_id`) REFERENCES `feed_inventory` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feeding_schedule_pin` FOREIGN KEY (`cage_id`) REFERENCES `pig_pins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `feed_distributors`
--
ALTER TABLE `feed_distributors`
  ADD CONSTRAINT `fk_fd_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fd_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `feed_distributor_orders`
--
ALTER TABLE `feed_distributor_orders`
  ADD CONSTRAINT `fk_fdo_buyer` FOREIGN KEY (`buyer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fdo_distributor` FOREIGN KEY (`distributor_id`) REFERENCES `feed_distributors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `feed_distributor_order_items`
--
ALTER TABLE `feed_distributor_order_items`
  ADD CONSTRAINT `fk_fdoi_order` FOREIGN KEY (`order_id`) REFERENCES `feed_distributor_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_fdoi_product` FOREIGN KEY (`product_id`) REFERENCES `feed_distributor_products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `feed_distributor_products`
--
ALTER TABLE `feed_distributor_products`
  ADD CONSTRAINT `fk_fdp_distributor` FOREIGN KEY (`distributor_id`) REFERENCES `feed_distributors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `feed_inventory`
--
ALTER TABLE `feed_inventory`
  ADD CONSTRAINT `fk_feed_inventory_caretaker` FOREIGN KEY (`caretaker_id`) REFERENCES `pig_caretakers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `feed_orders`
--
ALTER TABLE `feed_orders`
  ADD CONSTRAINT `fk_feed_orders_caretaker` FOREIGN KEY (`caretaker_id`) REFERENCES `pig_caretakers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feed_orders_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `feed_order_status`
--
ALTER TABLE `feed_order_status`
  ADD CONSTRAINT `fk_feed_order_status_order` FOREIGN KEY (`feed_order_id`) REFERENCES `livestock_feed_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feed_order_status_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `feed_products`
--
ALTER TABLE `feed_products`
  ADD CONSTRAINT `fk_feed_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`livestock_owner_id`) REFERENCES `livestock_owners` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lechoneros`
--
ALTER TABLE `lechoneros`
  ADD CONSTRAINT `lechoneros_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lechon_listings`
--
ALTER TABLE `lechon_listings`
  ADD CONSTRAINT `lechon_listings_ibfk_1` FOREIGN KEY (`livestock_owner_id`) REFERENCES `livestock_owners` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lechon_status`
--
ALTER TABLE `lechon_status`
  ADD CONSTRAINT `lechon_status_ibfk_1` FOREIGN KEY (`lechonero_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `livestock_feed_orders`
--
ALTER TABLE `livestock_feed_orders`
  ADD CONSTRAINT `fk_livestock_feed_orders_owner` FOREIGN KEY (`livestock_owner_id`) REFERENCES `livestock_owners` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_livestock_feed_orders_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `livestock_feed_order_items`
--
ALTER TABLE `livestock_feed_order_items`
  ADD CONSTRAINT `fk_livestock_feed_order_items_order` FOREIGN KEY (`feed_order_id`) REFERENCES `livestock_feed_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_livestock_feed_order_items_product` FOREIGN KEY (`feed_product_id`) REFERENCES `feed_products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `livestock_owners`
--
ALTER TABLE `livestock_owners`
  ADD CONSTRAINT `fk_livestock_owners_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `market_notes`
--
ALTER TABLE `market_notes`
  ADD CONSTRAINT `market_notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`swine_id`) REFERENCES `swine` (`id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`lechonero_id`) REFERENCES `lechoneros` (`id`);

--
-- Constraints for table `order_total_cost`
--
ALTER TABLE `order_total_cost`
  ADD CONSTRAINT `fk_order_total_cost_swine_order` FOREIGN KEY (`swine_order_id`) REFERENCES `swine_order_status` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pig_caretakers`
--
ALTER TABLE `pig_caretakers`
  ADD CONSTRAINT `fk_pig_caretakers_livestock_owner` FOREIGN KEY (`livestock_owner_id`) REFERENCES `livestock_owners` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pig_caretakers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pig_details`
--
ALTER TABLE `pig_details`
  ADD CONSTRAINT `fk_pig_details_pin` FOREIGN KEY (`cage_id`) REFERENCES `pig_pins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pinned_trends`
--
ALTER TABLE `pinned_trends`
  ADD CONSTRAINT `pinned_trends_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pinned_trends_ibfk_2` FOREIGN KEY (`trend_id`) REFERENCES `market_trends` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `placed_orders`
--
ALTER TABLE `placed_orders`
  ADD CONSTRAINT `fk_placed_orders_swine_order` FOREIGN KEY (`swine_order_id`) REFERENCES `swine_order_status` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `receipts_record`
--
ALTER TABLE `receipts_record`
  ADD CONSTRAINT `fk_receipt_accepted_by` FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_receipt_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `livestock_owners` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_receipt_order` FOREIGN KEY (`feed_order_id`) REFERENCES `livestock_feed_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_receipt_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `requirements`
--
ALTER TABLE `requirements`
  ADD CONSTRAINT `requirements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `requirements_ibfk_2` FOREIGN KEY (`application_id`) REFERENCES `role_applications` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `requirements_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reserved_orders`
--
ALTER TABLE `reserved_orders`
  ADD CONSTRAINT `fk_reserved_orders_swine_order` FOREIGN KEY (`swine_order_id`) REFERENCES `swine_order_status` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_applications`
--
ALTER TABLE `role_applications`
  ADD CONSTRAINT `role_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_applications_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `fk_suppliers_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `swine`
--
ALTER TABLE `swine`
  ADD CONSTRAINT `swine_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `swine_inventory`
--
ALTER TABLE `swine_inventory`
  ADD CONSTRAINT `fk_swine_inv_caretaker` FOREIGN KEY (`caretaker_id`) REFERENCES `pig_caretakers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `swine_inventory_snapshots`
--
ALTER TABLE `swine_inventory_snapshots`
  ADD CONSTRAINT `fk_snapshot_pig_detail` FOREIGN KEY (`pig_detail_id`) REFERENCES `pig_details` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_snapshot_swine_inventory` FOREIGN KEY (`swine_inventory_id`) REFERENCES `swine_inventory` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
