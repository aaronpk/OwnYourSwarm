ALTER TABLE checkins
ADD COLUMN `shout` text DEFAULT NULL AFTER `canonical_url`;
