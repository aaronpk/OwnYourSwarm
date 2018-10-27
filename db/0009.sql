ALTER TABLE users
ADD COLUMN `send_responses_other_users` tinyint(4) DEFAULT 1 AFTER `micropub_style`,
ADD COLUMN `send_responses_swarm` tinyint(4) DEFAULT 1 AFTER `micropub_style`;
