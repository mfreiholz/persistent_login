/*
 * Roundcube Persistent Login Schema
 *
 * @author Gene Hawkins <texxasrulez@yahoo.com>
 *
 * @licence GNU AGPL
 */

CREATE TABLE IF NOT EXISTS `auth_tokens` (
    `token` varchar(128) NOT NULL,
    `expires` datetime NOT NULL,
    `user_id` int(10) unsigned NOT NULL,
    `user_name` varchar(128) NOT NULL,
    `user_pass` varchar(128) NOT NULL,
	`host` varchar(255) NOT NULL,
    KEY `user_id_fk_auth_tokens` (`user_id`),
	PRIMARY KEY (`token`),
	CONSTRAINT `auth_tokens_ibfk_1` FOREIGN KEY (`user_id`)
	REFERENCES `users`(`user_id`) ON DELETE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8mb4 COLLATE utf8mb4_bin */;

REPLACE INTO `system` (`name`, `value`) VALUES ('tx-persistent-login-version', '2020080200');
