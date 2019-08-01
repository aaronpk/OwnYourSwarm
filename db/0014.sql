ALTER TABLE users
ADD COLUMN `backfeed_poll_interval` int(11) NOT NULL DEFAULT '0' AFTER `date_next_poll`,
ADD COLUMN `date_next_backfeed_poll` datetime DEFAULT NULL AFTER `backfeed_poll_interval`
;

UPDATE users SET backfeed_poll_interval = poll_interval;
UPDATE users SET date_next_backfeed_poll = date_next_poll;

ALTER TABLE users ALTER COLUMN send_responses_swarm SET DEFAULT '0';
ALTER TABLE users ALTER COLUMN send_responses_other_users SET DEFAULT '0';

ALTER TABLE users
ADD COLUMN `failed_webmentions` int(11) NOT NULL DEFAULT '0' AFTER `send_responses_other_users`;

