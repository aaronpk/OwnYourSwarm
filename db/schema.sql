CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(255) DEFAULT NULL,
  `poll_interval` int(11) NOT NULL DEFAULT '0',
  `foursquare_user_id` varchar(255) DEFAULT NULL,
  `foursquare_url` varchar(255) DEFAULT NULL,
  `foursquare_access_token` varchar(255) DEFAULT NULL,
  `micropub_endpoint` varchar(255) DEFAULT NULL,
  `micropub_syndication_targets` text,
  `micropub_access_token` text,
  `micropub_response` text,
  `micropub_success` tinyint(4) DEFAULT '0',
  `micropub_failures` tinyint(4) DEFAULT '0',
  `micropub_update_success` tinyint(4) DEFAULT '0',
  `micropub_style` varchar(255) DEFAULT 'json',
  `date_created` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_micropub_response` text,
  `last_checkin_url` varchar(255) DEFAULT NULL,
  `last_checkin_date` datetime DEFAULT NULL,
  `last_checkin_payload` text,
  `token_endpoint` varchar(255) DEFAULT NULL,
  `authorization_endpoint` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `checkins` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `foursquare_checkin_id` varchar(255) DEFAULT NULL,
  `published` datetime DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `photos` text,
  `shout` text,
  `foursquare_data` text,
  `mf2_data` text,
  `success` tinyint(4) NOT NULL DEFAULT '0',
  `pending` tinyint(4) NOT NULL DEFAULT '0',
  `num_photos` int(11) NOT NULL DEFAULT '0',
  `num_comments` int(11) NOT NULL DEFAULT '0',
  `num_likes` int(11) NOT NULL DEFAULT '0',
  `num_scores` int(11) NOT NULL DEFAULT '0',
  `date_next_poll` datetime DEFAULT NULL,
  `poll_interval` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `checkin` (`foursquare_checkin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `webmentions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `date_created` datetime DEFAULT NULL,
  `type` VARCHAR(255) NOT NULL DEFAULT '',
  `checkin_id` int(11) DEFAULT NULL,
  `foursquare_checkin` varchar(255) DEFAULT NULL,
  `hash` varchar(255) DEFAULT NULL,
  `author_photo` varchar(255) NOT NULL DEFAULT '',
  `author_url` varchar(255) NOT NULL DEFAULT '',
  `author_name` varchar(255) NOT NULL DEFAULT '',
  `coins` int(11) DEFAULT NULL,
  `content` text,
  `response_date` datetime DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `response_location` varchar(255) DEFAULT NULL,
  `response_body` text,
  PRIMARY KEY (`id`),
  KEY `checkin_url` (`foursquare_checkin`,`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `syndication_rules` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `match` varchar(255) DEFAULT NULL,
  `syndicate_to` text,
  `syndicate_to_name` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
