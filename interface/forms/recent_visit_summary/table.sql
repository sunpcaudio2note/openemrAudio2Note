CREATE TABLE IF NOT EXISTS `form_recent_visit_summary` (
  `id` bigint(20) NOT NULL,
  `date` datetime DEFAULT NULL,
  `pid` bigint(20) DEFAULT NULL,
  `encounter` bigint(20) DEFAULT NULL,
  `user` varchar(255) DEFAULT NULL,
  `groupname` varchar(255) DEFAULT NULL,
  `authorized` tinyint(4) DEFAULT NULL,
  `activity` tinyint(4) DEFAULT NULL,
  `summary_text` mediumtext,
  PRIMARY KEY (`id`),
  KEY `pid` (`pid`)
) ENGINE=InnoDB;