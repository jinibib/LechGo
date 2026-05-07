-- Migration: Add previous_price and price_updated_at to feed_products
-- Run in phpMyAdmin → lechgo_db → SQL tab

ALTER TABLE `feed_products`
  ADD COLUMN `previous_price` decimal(10,2) DEFAULT NULL AFTER `unit_price`,
  ADD COLUMN `price_updated_at` timestamp NULL DEFAULT NULL AFTER `previous_price`;
