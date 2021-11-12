/*
 * Roundcube Persistent Login Schema
 *
 * @author Gene Hawkins <texxasrulez@yahoo.com>
 *
 * @licence GNU AGPL
 */

-- import to SQLite by running: sqlite3.exe db.sqlite3 -init sqlite.sql

PRAGMA journal_mode = MEMORY;
PRAGMA synchronous = OFF;
PRAGMA foreign_keys = OFF;
PRAGMA ignore_check_constraints = OFF;
PRAGMA auto_vacuum = NONE;
PRAGMA secure_delete = OFF;
BEGIN TRANSACTION;


CREATE TABLE IF NOT EXISTS `auth_tokens` (
`token` TEXT NOT NULL,
`expires` datetime NOT NULL,
`user_id` INTEGER  NOT NULL,
`user_name` TEXT NOT NULL,
`user_pass` TEXT NOT NULL,
`host` TEXT NOT NULL,
PRIMARY KEY (`token`),
FOREIGN KEY (`user_id`)
REFERENCES `users`(`user_id`) ON DELETE CASCADE
);
REPLACE INTO `system` (`name`, `value`) VALUES ('persistent_login-version', '2020080200');



CREATE INDEX `auth_tokens_user_id_fk_auth_tokens` ON `auth_tokens` (`user_id`);

COMMIT;
PRAGMA ignore_check_constraints = ON;
PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
