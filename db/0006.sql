ALTER TABLE users 
ADD COLUMN `micropub_style` varchar(255) DEFAULT 'json' AFTER `micropub_update_success`;
