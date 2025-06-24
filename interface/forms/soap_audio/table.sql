CREATE TABLE IF NOT EXISTS `form_soap_audio` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `date` datetime DEFAULT NULL,
  `pid` bigint(20) DEFAULT NULL,
  `user` varchar(255) DEFAULT NULL,
  `groupname` varchar(255) DEFAULT NULL,
  `authorized` tinyint(4) DEFAULT NULL,
  `activity` tinyint(4) DEFAULT NULL,
  `subjective` text,
  `objective` text,
  `assessment` text,
  `plan` text,
  `transcript` text COMMENT 'Raw transcript from audio dictation.',
  `remarks` text COMMENT 'Additional remarks or notes.',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
