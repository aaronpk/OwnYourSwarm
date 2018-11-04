ALTER TABLE users
ADD COLUMN `exclude_checkins_by_others` tinyint(4) DEFAULT '0' AFTER `send_responses_other_users`;

ALTER TABLE users
ADD COLUMN `exclude_blank_checkins` tinyint(4) DEFAULT '0' AFTER `exclude_checkins_by_others`;

