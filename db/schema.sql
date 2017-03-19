CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(255) DEFAULT NULL,
  `tier` tinyint(4) NOT NULL DEFAULT '3',
  `foursquare_user_id` varchar(255) DEFAULT NULL,
  `foursquare_url` varchar(255) DEFAULT NULL,
  `foursquare_access_token` varchar(255) DEFAULT NULL,
  `micropub_endpoint` varchar(255) DEFAULT NULL,
  `micropub_syndication_targets` text,
  `micropub_access_token` text,
  `micropub_response` text,
  `micropub_success` tinyint(4) DEFAULT '0',
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
  `foursquare_data` text,
  `mf2_data` text,
  `success` tinyint(4) NOT NULL DEFAULT '0',
  `pending` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
