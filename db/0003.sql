ALTER TABLE users
ADD COLUMN micropub_update_success TINYINT(4) DEFAULT '0' AFTER micropub_success;
