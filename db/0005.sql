ALTER TABLE checkins
ADD COLUMN tzoffset INT(11) NOT NULL DEFAULT '0' AFTER `published`;
