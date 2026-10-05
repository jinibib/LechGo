-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 18, 2026 at 07:59 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lechgo_db`
--

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
(11, 11, 'jennyvievemahinay@gmail.com', '293137e71b22b794cf45a5df26df063948d7ac2cbccfa6bebdc3a0c6e62f14f6', '2026-04-03 09:30:08', '2026-04-04 03:29:43', '2026-04-03 09:29:43'),
(15, 15, 'davedelacerna09@gmail.com', 'bea286897a9c8df5d6449406dd179bef9d8747f1c9dda6f07b0f565e64ff3a71', '2026-04-08 02:23:09', '2026-04-08 20:22:58', '2026-04-08 02:22:58'),
(17, 17, 'mahinaylydia82@gmail.com', 'a65b114e33557b2b8f44b25d96ba49194b33f1a4115a6477d80ebbe3e1de5715', '2026-04-08 02:38:22', '2026-04-08 20:38:12', '2026-04-08 02:38:12');

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

--
-- Dumping data for table `feeding_schedule`
--

INSERT INTO `feeding_schedule` (`id`, `caretaker_id`, `cage_id`, `feed_inventory_id`, `feeding_date`, `feeding_time`, `amount_kg`, `notes`, `created_at`) VALUES
(5, 5, 21, 7, '0000-00-00', '10:59:00', 25.00, '', '2026-04-08 02:59:37'),
(6, 5, 22, 7, '0000-00-00', '10:59:00', 25.00, '', '2026-04-08 02:59:54'),
(7, 5, 23, 7, '0000-00-00', '11:00:00', 48.00, '', '2026-04-08 03:00:14');

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

--
-- Dumping data for table `feed_inventory`
--

INSERT INTO `feed_inventory` (`id`, `caretaker_id`, `feed_type`, `feed_name`, `quantity_kg`, `unit_price`, `supplier_name`, `purchase_date`, `expiry_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(7, 5, 'Grain', 'Feed', 2.00, 35.00, 'JENNYVIEVE NIODA MAHINAY', '2026-04-08', NULL, 'low_stock', NULL, '2026-04-08 02:57:25', '2026-04-08 03:00:14');

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
-- Table structure for table `feed_products`
--

CREATE TABLE `feed_products` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `feed_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `quantity_available_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feed_products`
--

INSERT INTO `feed_products` (`id`, `supplier_id`, `product_name`, `feed_type`, `description`, `unit_price`, `quantity_available_kg`, `image_url`, `is_active`, `created_at`, `updated_at`) VALUES
(9, 4, 'Premium Corn Feed', 'Corn', 'High-quality corn feed - perfect for pig fattening', 45.50, 200.00, NULL, 1, '2026-04-03 09:59:02', '2026-04-03 09:59:02'),
(10, 4, 'Complete Booster Mix', 'Booster', 'Nutritional booster for fast growth and health', 65.00, 150.00, NULL, 1, '2026-04-03 09:59:02', '2026-04-03 09:59:02'),
(11, 4, 'Mineral & Vitamin Mix', 'Supplement', 'Essential minerals and vitamins for healthy pig development', 85.00, 100.00, NULL, 1, '2026-04-03 09:59:02', '2026-04-03 09:59:02'),
(12, 4, 'Soya Bean Meal', 'Protein Feed', 'High protein supplement for optimal pig nutrition', 55.00, 120.00, NULL, 1, '2026-04-03 09:59:02', '2026-04-03 09:59:02'),
(13, 4, 'Rice Bran Feed', 'Grain', 'Cost-effective grain supplement', 35.00, 250.00, NULL, 1, '2026-04-03 09:59:02', '2026-04-03 09:59:02');

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
  `delivery_notes` text DEFAULT NULL,
  `scheduled_delivery_date` date DEFAULT NULL,
  `actual_delivery_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `livestock_feed_orders`
--

INSERT INTO `livestock_feed_orders` (`id`, `livestock_owner_id`, `supplier_id`, `order_number`, `order_status`, `payment_status`, `delivery_status`, `total_amount`, `delivery_address`, `delivery_notes`, `scheduled_delivery_date`, `actual_delivery_date`, `payment_method`, `payment_reference`, `created_at`, `updated_at`) VALUES
(12, 3, 4, 'LO-3-1775615479', 'confirmed', 'unpaid', 'pending', 8730.00, 'Davao Gallerea', 'dasda', NULL, NULL, 'online_payment', NULL, '2026-04-08 02:31:19', '2026-04-08 02:39:43'),
(13, 3, 4, 'LO-3-1775616570', 'confirmed', 'unpaid', 'pending', 45.50, 'asdas', 'asdasd', NULL, NULL, 'cash_on_delivery', NULL, '2026-04-08 02:49:30', '2026-04-08 02:49:40'),
(14, 3, 4, 'LO-3-1775616660', 'confirmed', 'unpaid', 'pending', 3500.00, 'Bago', 'AdSA', NULL, NULL, 'online_payment', NULL, '2026-04-08 02:51:00', '2026-04-08 02:51:30');

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

--
-- Dumping data for table `livestock_feed_order_items`
--

INSERT INTO `livestock_feed_order_items` (`id`, `feed_order_id`, `feed_product_id`, `product_name`, `feed_type`, `quantity_kg`, `unit_price`, `subtotal`, `created_at`) VALUES
(20, 12, 10, 'Complete Booster Mix', '0', 50.00, 65.00, 3250.00, '2026-04-08 02:31:19'),
(21, 12, 9, 'Premium Corn Feed', '0', 60.00, 45.50, 2730.00, '2026-04-08 02:31:19'),
(22, 12, 12, 'Soya Bean Meal', '0', 50.00, 55.00, 2750.00, '2026-04-08 02:31:19'),
(23, 13, 9, 'Premium Corn Feed', 'Corn', 23.00, 45.50, 45.50, '2026-04-08 02:49:30'),
(24, 14, 13, 'Rice Bran Feed', 'Grain', 100.00, 35.00, 3500.00, '2026-04-08 02:51:00');

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
(3, 15, 'Cordio Pondias', 'Bago Street, Bago Gallera, Talomo, Davao City', '09092965085', '2026-04-08 02:28:12', '2026-04-08 02:28:12');

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
(117, 'Libby Road', 'Bago Gallera', 'Talomo', 'Davao City', '2026-04-03 09:31:05', '2026-04-03 09:31:05');

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
(33, 'schoolprps2004@gmail.com', '092357', 0, '2026-04-03 09:50:03', '2026-04-03 03:54:31', NULL, '2026-04-03 09:49:31'),
(50, 'jennyvievemahinay@gmail.com', '831440', 0, '2026-04-13 09:48:27', '2026-04-13 03:52:25', NULL, '2026-04-13 09:47:25'),
(52, 'davedelacerna09@gmail.com', '326114', 0, '2026-04-13 10:06:52', '2026-04-13 04:11:12', NULL, '2026-04-13 10:06:12'),
(53, 'mahinaylydia82@gmail.com', '925033', 0, '2026-04-13 10:08:31', '2026-04-13 04:12:56', NULL, '2026-04-13 10:07:56');

-- --------------------------------------------------------

--
-- Table structure for table `pig_cages`
--

CREATE TABLE `pig_cages` (
  `id` int(11) NOT NULL,
  `caretaker_id` int(11) NOT NULL,
  `cage_number` char(1) NOT NULL CHECK (`cage_number` in ('A','B','C','D','E')),
  `current_pig_count` int(11) NOT NULL DEFAULT 0 CHECK (`current_pig_count` <= 3),
  `max_capacity` int(11) NOT NULL DEFAULT 3,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pig_cages`
--

INSERT INTO `pig_cages` (`id`, `caretaker_id`, `cage_number`, `current_pig_count`, `max_capacity`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(21, 5, 'A', 1, 3, 'active', NULL, '2026-04-08 02:39:03', '2026-04-08 02:58:17'),
(22, 5, 'B', 1, 3, 'active', NULL, '2026-04-08 02:39:03', '2026-04-08 02:58:33'),
(23, 5, 'C', 1, 3, 'active', NULL, '2026-04-08 02:39:03', '2026-04-08 02:58:51'),
(24, 5, 'D', 0, 3, 'active', NULL, '2026-04-08 02:39:03', '2026-04-08 02:39:03'),
(25, 5, 'E', 0, 3, 'active', NULL, '2026-04-08 02:39:03', '2026-04-08 02:39:03');

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
(5, 17, 3, 'Cordio Pondias', NULL, 'Libby Road, Bago Gallera, Talomo, Davao City', '09263209117', '2026-04-08 02:39:03', '2026-04-08 02:57:02');

-- --------------------------------------------------------

--
-- Table structure for table `pig_details`
--

CREATE TABLE `pig_details` (
  `id` int(11) NOT NULL,
  `cage_id` int(11) NOT NULL,
  `pig_tag_id` varchar(50) DEFAULT NULL,
  `breed` varchar(100) DEFAULT NULL,
  `age_months` int(11) DEFAULT NULL,
  `weight_kg` decimal(6,2) DEFAULT NULL,
  `health_status` enum('healthy','sick','recovering','deceased') DEFAULT 'healthy',
  `date_added` date DEFAULT NULL,
  `status` enum('active','sold','removed') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pig_details`
--

INSERT INTO `pig_details` (`id`, `cage_id`, `pig_tag_id`, `breed`, `age_months`, `weight_kg`, `health_status`, `date_added`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(7, 21, 'PIG 1', 'Hybrid', 3, 17.00, 'healthy', '2026-04-08', 'active', '', '2026-04-08 02:58:17', '2026-04-08 02:58:17'),
(8, 22, 'pig 2', 'Hybrid', 4, 18.00, 'healthy', '2026-04-08', 'active', '', '2026-04-08 02:58:33', '2026-04-08 02:58:33'),
(9, 23, 'pig 3', 'Hybrid', 5, 20.00, 'healthy', '2026-04-08', 'active', '', '2026-04-08 02:58:51', '2026-04-08 02:58:51');

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
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `user_id`, `farm_name`, `location_id`, `contact_number`) VALUES
(4, 11, 'Agrivet Supply', 117, '9103449930');

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
  `role` enum('customer','lechonero','livestock_owner','supplier','pig_caretaker','admin','logistics') NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `email_verified`, `email_verified_at`, `last_login`, `role`, `phone`, `created_at`) VALUES
(11, 'JENNYVIEVE NIODA MAHINAY', 'jennyvievemahinay@gmail.com', '$2y$10$85iU3tphkVXiHUoT/1qBtew6IDhYNsVddmVmCl8efdX.LFcG43oga', 1, '2026-04-03 09:30:08', NULL, 'supplier', '09272944675', '2026-04-03 09:29:43'),
(15, 'Dave Dela cerna', 'davedelacerna09@gmail.com', '$2y$10$CcAi3YuY1rmS479pBEBrB.nahvf8ERJQ.tHHMErvyHUEdO6WPbAai', 1, '2026-04-08 02:23:09', NULL, 'livestock_owner', '0912078886', '2026-04-08 02:22:58'),
(17, 'lydia mahinay', 'mahinaylydia82@gmail.com', '$2y$10$H1HO22E5EhmTJZA6iFxqK.yQHwuN7xp.eMvUnww2mdwfvXS9IIqdG', 1, '2026-04-08 02:38:22', NULL, 'pig_caretaker', '9120738886', '2026-04-08 02:38:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `caretaker_reports`
--
ALTER TABLE `caretaker_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_caretaker_id` (`caretaker_id`),
  ADD KEY `idx_report_date` (`report_date`),
  ADD KEY `idx_created_at` (`created_at`);

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
-- Indexes for table `feeding_schedule`
--
ALTER TABLE `feeding_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_feeding_schedule_cage` (`cage_id`),
  ADD KEY `fk_feeding_schedule_feed` (`feed_inventory_id`),
  ADD KEY `idx_caretaker_id` (`caretaker_id`),
  ADD KEY `idx_feeding_date` (`feeding_date`);

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
-- Indexes for table `feed_products`
--
ALTER TABLE `feed_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_feed_type` (`feed_type`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `lechoneros`
--
ALTER TABLE `lechoneros`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

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
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `swine_id` (`swine_id`),
  ADD KEY `lechonero_id` (`lechonero_id`);

--
-- Indexes for table `otp_verification`
--
ALTER TABLE `otp_verification`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `pig_cages`
--
ALTER TABLE `pig_cages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_caretaker_cage` (`caretaker_id`,`cage_number`),
  ADD KEY `idx_caretaker_id` (`caretaker_id`);

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
  ADD UNIQUE KEY `pig_tag_id` (`pig_tag_id`),
  ADD KEY `idx_cage_id` (`cage_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `caretaker_reports`
--
ALTER TABLE `caretaker_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `feeding_schedule`
--
ALTER TABLE `feeding_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `feed_inventory`
--
ALTER TABLE `feed_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `feed_orders`
--
ALTER TABLE `feed_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feed_products`
--
ALTER TABLE `feed_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `lechoneros`
--
ALTER TABLE `lechoneros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `livestock_feed_orders`
--
ALTER TABLE `livestock_feed_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `livestock_feed_order_items`
--
ALTER TABLE `livestock_feed_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `livestock_owners`
--
ALTER TABLE `livestock_owners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=118;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otp_verification`
--
ALTER TABLE `otp_verification`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `pig_cages`
--
ALTER TABLE `pig_cages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `pig_caretakers`
--
ALTER TABLE `pig_caretakers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `pig_details`
--
ALTER TABLE `pig_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `swine`
--
ALTER TABLE `swine`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

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
-- Constraints for table `feeding_schedule`
--
ALTER TABLE `feeding_schedule`
  ADD CONSTRAINT `fk_feeding_schedule_cage` FOREIGN KEY (`cage_id`) REFERENCES `pig_cages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feeding_schedule_caretaker` FOREIGN KEY (`caretaker_id`) REFERENCES `pig_caretakers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feeding_schedule_feed` FOREIGN KEY (`feed_inventory_id`) REFERENCES `feed_inventory` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Constraints for table `feed_products`
--
ALTER TABLE `feed_products`
  ADD CONSTRAINT `fk_feed_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `lechoneros`
--
ALTER TABLE `lechoneros`
  ADD CONSTRAINT `lechoneros_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`swine_id`) REFERENCES `swine` (`id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`lechonero_id`) REFERENCES `lechoneros` (`id`);

--
-- Constraints for table `pig_cages`
--
ALTER TABLE `pig_cages`
  ADD CONSTRAINT `fk_pig_cages_caretaker` FOREIGN KEY (`caretaker_id`) REFERENCES `pig_caretakers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
  ADD CONSTRAINT `fk_pig_details_cage` FOREIGN KEY (`cage_id`) REFERENCES `pig_cages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
