-- Add missing columns to tenants table

ALTER TABLE `tenants` 
ADD COLUMN IF NOT EXISTS `admin_email` VARCHAR(255) AFTER `email`;

-- Verify columns
SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'tenants' AND TABLE_SCHEMA = 'tenant_master'
ORDER BY ORDINAL_POSITION;
