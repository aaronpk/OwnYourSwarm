ALTER TABLE webmentions
ADD COLUMN author_name VARCHAR(255) NOT NULL DEFAULT '' AFTER `hash`,
ADD COLUMN author_url VARCHAR(255) NOT NULL DEFAULT '' AFTER `hash`,
ADD COLUMN author_photo VARCHAR(255) NOT NULL DEFAULT '' AFTER `hash`;

ALTER TABLE webmentions
ADD COLUMN type VARCHAR(255) NOT NULL DEFAULT '' AFTER `date_created`;
UPDATE webmentions SET type = 'coin';
UPDATE webmentions SET author_photo = icon;
ALTER TABLE webmentions DROP COLUMN icon;

ALTER TABLE users
CHANGE COLUMN tier poll_interval INT(11) NOT NULL DEFAULT '0';

ALTER TABLE users
ADD COLUMN date_next_poll DATETIME DEFAULT NULL AFTER poll_interval;

