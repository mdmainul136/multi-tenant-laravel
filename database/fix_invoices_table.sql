-- Drop invoices table if exists
DROP TABLE IF EXISTS `invoices`;

-- Create invoices table
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `payment_id` bigint unsigned DEFAULT NULL,
  `module_id` bigint unsigned NOT NULL,
  `invoice_number` varchar(255) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subscription_type` varchar(255) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL,
  `status` enum('draft','paid','pending','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `notes` text,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  KEY `invoices_invoice_number_index` (`invoice_number`),
  KEY `invoices_tenant_id_index` (`tenant_id`),
  KEY `invoices_status_index` (`status`),
  KEY `invoices_invoice_date_index` (`invoice_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
