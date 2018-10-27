ALTER TABLE users
ADD COLUMN `add_tags` varchar(255) DEFAULT '' AFTER `micropub_style`;

