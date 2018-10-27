ALTER TABLE users
ADD COLUMN `mark_all_private` tinyint(4) DEFAULT 0 AFTER `micropub_style`;

