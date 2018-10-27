ALTER TABLE users
ADD COLUMN `include_private_checkins` tinyint(4) DEFAULT 1 AFTER `micropub_style`;

