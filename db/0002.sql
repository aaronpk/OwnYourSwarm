CREATE TABLE `webmentions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `date_created` datetime DEFAULT NULL,
  `checkin_id` int(11) DEFAULT NULL,
  `foursquare_checkin` varchar(255) DEFAULT NULL,
  `hash` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `coins` int(11) DEFAULT NULL,
  `content` text,
  `response_date` datetime DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `response_location` varchar(255) DEFAULT NULL,
  `response_body` text,
  PRIMARY KEY (`id`),
  KEY `checkin_url` (`foursquare_checkin`,`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4;
