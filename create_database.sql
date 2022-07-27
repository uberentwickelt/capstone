SET sql_notes = 0; -- Disable warnings
-- Create Database, user and grant permissions
CREATE DATABASE IF NOT EXISTS vote DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER 'vote'@'localhost' IDENTIFIED WITH mysql_native_password BY '{Put a sane password here}';
GRANT EXECUTE,INSERT,SELECT,SHOW VIEW,UPDATE,DELETE ON `vote`.* TO 'vote'@'localhost';
FLUSH PRIVILEGES;
-- Create tables
USE `vote`;

DROP TABLE IF EXISTS `election`;
DROP TABLE IF EXISTS `race`;
DROP TABLE IF EXISTS `race_result`;
DROP TABLE IF EXISTS `question`;
DROP TABLE IF EXISTS `question_result`;
DROP TABLE IF EXISTS `login`;
DROP TABLE IF EXISTS `user`;
DROP TABLE IF EXISTS `audit_user`;
DROP TABLE IF EXISTS `machine`;
DROP TABLE IF EXISTS `audit_machine`;
DROP TABLE IF EXISTS `params`;
DROP TABLE IF EXISTS `role`;
DROP TABLE IF EXISTS `audit_role`;
DROP TABLE IF EXISTS `session`;
DROP TABLE IF EXISTS `session_archive`;

CREATE TABLE IF NOT EXISTS `election` (
  `id` binary(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `year` year NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
);

CREATE TABLE IF NOT EXISTS `race` (
  `id` binary(16) NOT NULL,
  `display` int NOT NULL,
  `name` varchar(10) NOT NULL,
  `election_id` binary(16) NOT NULL,
  `race_id` binary(16) NOT NULL,
  `candiate_id` binary(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
);

CREATE TABLE IF NOT EXISTS `race_result` (
  `id` binary(16) NOT NULL,
  `race_id` binary(16) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `answer` varchar(255) NOT NULL,
  `create` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
);

CREATE TABLE IF NOT EXISTS `question` (
  `id` binary(16) NOT NULL,
  `display` int NOT NULL,
  `name` varchar(10) NOT NULL,
  `election_id` binary(16) NOT NULL,
  `question_id` binary(16) NOT NULL,
  `answer` varchar(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
);

CREATE TABLE IF NOT EXISTS `question_result` (
  `id` binary(16) NOT NULL,
  `question_id` binary(16) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `answer` varchar(255) NOT NULL,
  `create` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
);

CREATE TABLE IF NOT EXISTS `login` (
	`id` binary(16) NOT NULL,
    `username` varchar(32) NOT NULL,
    `display_name` varchar(32) NOT NULL,
    `password` varchar(255) NOT NULL,
    `create` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `password_changed` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
	UNIQUE KEY `username_UNIQUE` (`username`),
    UNIQUE KEY `display_name_UNIQUE` (`display_name`),
	UNIQUE KEY `id_UNIQUE` (`id`) 
);

CREATE TABLE IF NOT EXISTS `machine` (
  `id` binary(16) NOT NULL,
  `friendly_id` varchar(7) NOT NULL,
  `pubkey` varchar(5000) NOT NULL,
  `active` boolean,
  `challenge` varchar(32) DEFAULT NULL,
  `challenge_expire` datetime DEFAULT NULL,
  `last_ip` varchar(39) NOT NULL,
  `last_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
);

CREATE TABLE IF NOT EXISTS `audit_machine` (
  `id` binary(16) NOT NULL,
  `friendly_id` varchar(7) NOT NULL,
  `pubkey` varchar(5000) NOT NULL,
  `active` boolean,
  `last_ip` varchar(39) NOT NULL,
  `last_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `audit_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `params` (
  `key` varchar(30) NOT NULL,
  `value` varchar(30) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`key`),
  UNIQUE KEY `id_UNIQUE` (`key`)
);

CREATE TABLE IF NOT EXISTS `role` (
  `id` binary(16) NOT NULL,
  `name` varchar(100) NOT NULL,
  `picture` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
);

CREATE TABLE IF NOT EXISTS `audit_role` (
  `id` binary(16) NOT NULL,
  `name` varchar(100) NOT NULL,
  `picture` varchar(100) DEFAULT NULL,
  `audit_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `session` (
  `id` binary(16) NOT NULL,
  `user_id` binary(16) DEFAULT NULL,
  `machine_id` binary(16) DEFAULT NULL,
  `browser_id` binary(16) DEFAULT NULL,
  `create` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expire` datetime NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id_UNIQUE` (`id`),
  KEY `user` (`user_id`)
);

CREATE TABLE IF NOT EXISTS `session_archive` (
  `id` binary(16) NOT NULL,
  `user_id` binary(16) NOT NULL,
  `machine_id` binary(16) DEFAULT NULL,
  `create` datetime NOT NULL,
  `expire` datetime NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id_UNIQUE` (`id`),
  KEY `user` (`user_id`)
);

DELIMITER ;;
CREATE FUNCTION get_machine_challenge(machine_id binary(16))
RETURNS VARCHAR(32)
DETERMINISTIC
BEGIN
	SET @challenge = (
	SELECT `challenge` FROM `machine` WHERE `id` = machine_id AND sysdate() < `challenge_expire` AND `challenge` IS NOT NULL AND `active` = 1
    );
    IF @challenge IS NULL AND (SELECT 1 FROM `machine` WHERE `id` = machine_id AND `active` = 1) THEN
		SET @challenge = UPPER(LEFT(MD5(RAND()), 32));
        UPDATE `machine` SET `challenge` = @challenge, 
			`challenge_expire` = (sysdate() + INTERVAL (
				SELECT `value` FROM `params` WHERE `key` = 'MACHINE_CHALLENGE_LENGTH'
			) MINUTE)
		WHERE `id` = machine_id;
	END IF;
    RETURN @challenge;
END;;

CREATE TRIGGER `election_BEFORE_INSERT` BEFORE INSERT ON `election` FOR EACH ROW BEGIN
	IF new.`id` IS NULL THEN
      SET new.`id` = uuid_to_bin(uuid());
	END If;
END;;

CREATE TRIGGER `race_BEFORE_INSERT` BEFORE INSERT ON `race` FOR EACH ROW BEGIN
	IF new.`id` IS NULL THEN
      SET new.`id` = uuid_to_bin(uuid());
	END If;
    IF new.`display` IS NULL THEN
		SET new.`display` = (SELECT COALESCE(max(`display`),0) FROM `race` WHERE `election_id` = new.`election_id`) + 1;
	ELSE
		SET new.`display` = (SELECT
			CASE WHEN (SELECT count(`display`) FROM `race` WHERE `election_id` = new.`election_id` AND `display` = new.`display`) = 0 THEN
				new.`display`
			ELSE
				(SELECT max(`display`) FROM `race` WHERE `election_id` = new.`election_id`) + 1
			END
		);
	END IF;
END;;

CREATE TRIGGER `question_BEFORE_INSERT` BEFORE INSERT ON `question` FOR EACH ROW BEGIN
	IF new.`id` IS NULL THEN
      SET new.`id` = uuid_to_bin(uuid());
	END If;
    IF new.`display` IS NULL THEN
		SET new.`display` = (SELECT COALESCE(max(`display`),0) FROM `question` WHERE `election_id` = new.`election_id`) + 1;
	ELSE
		SET new.`display` = (SELECT
			CASE WHEN (SELECT count(`display`) FROM `question` WHERE `election_id` = new.`election_id` AND `display` = new.`display`) = 0 THEN
				new.`display`
			ELSE
				(SELECT max(`display`) FROM `question` WHERE `election_id` = new.`election_id`) + 1
			END
		);
	END IF;
END;;

CREATE TRIGGER `vote_BEFORE_INSERT` BEFORE INSERT ON `vote` FOR EACH ROW BEGIN
	IF new.`id` IS NULL THEN
      SET new.`id` = uuid_to_bin(uuid());
	END If;
END;;

CREATE TRIGGER `login_BEFORE_INSERT` BEFORE INSERT ON `login` FOR EACH ROW BEGIN
	IF new.`id` IS NULL THEN
      SET new.`id` = uuid_to_bin(uuid());
	END If;
END;;

CREATE TRIGGER `login_BEFORE_UPDATE` BEFORE UPDATE ON `login` FOR EACH ROW BEGIN
	SET new.`create` = old.`create`;
	SET new.`update` = CURRENT_TIMESTAMP;
    IF new.`password` != old.`password` THEN
		SET new.`password_changed` = CURRENT_TIMESTAMP;
	END IF;
END;;

CREATE TRIGGER `user_BEFORE_INSERT` BEFORE INSERT ON `user` FOR EACH ROW BEGIN
	IF new.`id` IS NULL THEN
      SET new.`id` = uuid_to_bin(uuid());
	END If;
END;;

CREATE TRIGGER `audit_user_BEFORE_INSERT` BEFORE INSERT ON `audit_user` FOR EACH ROW BEGIN
	IF new.`id` IS NULL THEN
      SET new.`id` = uuid_to_bin(uuid());
	END If;
END;;

CREATE TRIGGER `role_BEFORE_INSERT` BEFORE INSERT ON `role` FOR EACH ROW BEGIN
	IF new.`id` IS NULL THEN
      SET new.`id` = uuid_to_bin(uuid());
	END If;
END;;

CREATE TRIGGER `audit_role_BEFORE_INSERT` BEFORE INSERT ON `audit_role` FOR EACH ROW BEGIN
	IF new.`id` IS NULL THEN
      SET new.`id` = uuid_to_bin(uuid());
	END If;
END;;

CREATE TRIGGER `machine_BEFORE_INSERT` BEFORE INSERT ON `machine` FOR EACH ROW BEGIN
	IF new.`id` IS NULL THEN
      SET new.`id` = uuid_to_bin(uuid());
	END IF;

	WHILE (new.`friendly_id` IS NULL) DO
		SET @fid = (SELECT CONCAT(UPPER(LEFT(MD5(RAND()), 3)),'-',UPPER(LEFT(MD5(RAND()), 3))));
        IF (SELECT count(`friendly_id`) FROM `machine` WHERE `friendly_id` = @fid) = 0 THEN
			SET new.`friendly_id` = @fid;
		END IF;
	END WHILE;
END;;

CREATE TRIGGER `machine_BEFORE_DELETE` BEFORE DELETE ON `machine` FOR EACH ROW BEGIN
  INSERT INTO `audit_machine` 
  (`id`,`friendly_id`,`pubkey`,`active`,`last_ip`,`last_date`)
  VALUES
  (old.`id`,old.`friendly_id`,old.`pubkey`,0,old.`last_ip`,old.`last_date`);
END;;

CREATE TRIGGER `audit_machine_BEFORE_INSERT` BEFORE INSERT ON `audit_machine` FOR EACH ROW BEGIN
	IF new.`id` IS NULL THEN
      SET new.`id` = uuid_to_bin(uuid());
	END If;
    
    WHILE (new.`friendly_id` IS NULL) DO
		SET @fid = (SELECT CONCAT(UPPER(LEFT(MD5(RAND()), 3)),'-',UPPER(LEFT(MD5(RAND()), 3))));
        IF (SELECT count(`friendly_id`) FROM `audit_machine` WHERE `friendly_id` = @fid) = 0 THEN
			SET new.`friendly_id` = @fid;
		END IF;
	END WHILE;
END;;

CREATE TRIGGER `session_BEFORE_INSERT` BEFORE INSERT ON `session` FOR EACH ROW BEGIN
  SET new.`expire`=ADDDATE(CURRENT_TIMESTAMP,INTERVAL (SELECT `value` FROM `params` WHERE `key` = 'SESSION_INACTIVITY_LENGTH') MINUTE);
END;;

CREATE TRIGGER `session_BEFORE_DELETE` BEFORE DELETE ON `session` FOR EACH ROW BEGIN
  INSERT INTO `session_archive` 
  (`id`,`user_id`,`machine_id`,`create`,`expire`,`ip_address`,`user_agent`) 
  VALUES 
  (old.`id`,old.`user_id`,old.`machine_id`,old.`create`,old.`expire`,old.`ip_address`,old.`user_agent`);
END;;
DELIMITER ;

INSERT INTO `params` (`key`, `value`,`description`) values
("SESSION_INACTIVITY_LENGTH","30","Time in minutes a user session may be inactive before expiring."),
("MACHINE_CHALLENGE_LENGTH","2","Time in minutes a machine challenge is valid");

SET sql_notes = 1; -- Enable warnings