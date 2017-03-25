ALTER TABLE users
ADD COLUMN micropub_update_success TINYINT(4) DEFAULT '0' AFTER micropub_success,
ADD COLUMN micropub_failures TINYINT(4) DEFAULT '0' AFTER micropub_success;
