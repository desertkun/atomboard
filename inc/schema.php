<?php
declare(strict_types=1);

/* ==[ Queries for creatsng new tables ]=================================================================== */

if (ATOM_DBMODE === 'pdo' && ATOM_DBDRIVER === 'pgsql') {
	$postsQuery = 'CREATE TABLE ' . ATOM_DBPOSTS . ' (
		id bigserial PRIMARY KEY,
		parent integer NOT NULL,
		timestamp integer NOT NULL,
		bumped integer NOT NULL,
		ip varchar(39) NOT NULL,
		name varchar(75) NOT NULL,
		tripcode varchar(10) NOT NULL,
		email varchar(75) NOT NULL,
		nameblock text NOT NULL,
		subject varchar(100) NOT NULL,
		message text NOT NULL,
		password varchar(255) NOT NULL,
		file0 text NOT NULL,
		file0_hex varchar(75) NOT NULL,
		file0_original varchar(255) NOT NULL,
		file0_size integer NOT NULL DEFAULT 0,
		file0_size_formatted varchar(75) NOT NULL,
		image0_width smallint NOT NULL DEFAULT 0,
		image0_height smallint NOT NULL DEFAULT 0,
		thumb0 varchar(255) NOT NULL,
		thumb0_width smallint NOT NULL DEFAULT 0,
		thumb0_height smallint NOT NULL DEFAULT 0,
		file1 text NOT NULL,
		file1_hex varchar(75) NOT NULL,
		file1_original varchar(255) NOT NULL,
		file1_size integer NOT NULL DEFAULT 0,
		file1_size_formatted varchar(75) NOT NULL,
		image1_width smallint NOT NULL DEFAULT 0,
		image1_height smallint NOT NULL DEFAULT 0,
		thumb1 varchar(255) NOT NULL,
		thumb1_width smallint NOT NULL DEFAULT 0,
		thumb1_height smallint NOT NULL DEFAULT 0,
		file2 text NOT NULL,
		file2_hex varchar(75) NOT NULL,
		file2_original varchar(255) NOT NULL,
		file2_size integer NOT NULL DEFAULT 0,
		file2_size_formatted varchar(75) NOT NULL,
		image2_width smallint NOT NULL DEFAULT 0,
		image2_height smallint NOT NULL DEFAULT 0,
		thumb2 varchar(255) NOT NULL,
		thumb2_width smallint NOT NULL DEFAULT 0,
		thumb2_height smallint NOT NULL DEFAULT 0,
		file3 text NOT NULL,
		file3_hex varchar(75) NOT NULL,
		file3_original varchar(255) NOT NULL,
		file3_size integer NOT NULL DEFAULT 0,
		file3_size_formatted varchar(75) NOT NULL,
		image3_width smallint NOT NULL DEFAULT 0,
		image3_height smallint NOT NULL DEFAULT 0,
		thumb3 varchar(255) NOT NULL,
		thumb3_width smallint NOT NULL DEFAULT 0,
		thumb3_height smallint NOT NULL DEFAULT 0,
		likes smallint NOT NULL DEFAULT 0,
		moderated smallint NOT NULL DEFAULT 1,
		stickied smallint NOT NULL DEFAULT 0,
		locked smallint NOT NULL DEFAULT 0,
		endless smallint NOT NULL DEFAULT 0,
		pass integer NOT NULL DEFAULT 0
	);
	CREATE INDEX ' . ATOM_DBPOSTS . '_parent_sort_idx ON ' . ATOM_DBPOSTS . ' (parent, stickied DESC, bumped DESC);
	CREATE INDEX ' . ATOM_DBPOSTS . '_parent_id_idx ON ' . ATOM_DBPOSTS . ' (parent, id ASC);
	CREATE INDEX ' . ATOM_DBPOSTS . '_ip_time_idx ON ' . ATOM_DBPOSTS . ' (ip, timestamp DESC);
	CREATE INDEX ' . ATOM_DBPOSTS . '_mod_time_idx ON ' . ATOM_DBPOSTS . ' (moderated, timestamp DESC);
	CREATE INDEX ' . ATOM_DBPOSTS . '_f0_hex_idx ON ' . ATOM_DBPOSTS . ' (file0_hex);
	CREATE INDEX ' . ATOM_DBPOSTS . '_f1_hex_idx ON ' . ATOM_DBPOSTS . ' (file1_hex);
	CREATE INDEX ' . ATOM_DBPOSTS . '_f2_hex_idx ON ' . ATOM_DBPOSTS . ' (file2_hex);
	CREATE INDEX ' . ATOM_DBPOSTS . '_f3_hex_idx ON ' . ATOM_DBPOSTS . ' (file3_hex);';

	$staffQuery = 'CREATE TABLE ' . ATOM_DBSTAFF . ' (
		id bigserial NOT NULL PRIMARY KEY,
		username varchar(50) UNIQUE NOT NULL,
		password_hash varchar(255) NOT NULL,
		role varchar(20) NOT NULL,
		last_login integer NOT NULL DEFAULT 0
	);
	CREATE INDEX ' . ATOM_DBSTAFF . '_role_username_idx ON ' . ATOM_DBSTAFF . '(role, username);';

	$bansQuery = 'CREATE TABLE ' . ATOM_DBBANS . ' (
		id bigserial NOT NULL PRIMARY KEY,
		ip_from bigint NOT NULL,
		ip_to bigint NOT NULL,
		timestamp integer NOT NULL,
		expire integer NOT NULL,
		reason text NOT NULL
	);
	CREATE INDEX ' . ATOM_DBBANS . '_ip_range_idx ON ' . ATOM_DBBANS . '(ip_from, ip_to);
	CREATE INDEX ' . ATOM_DBBANS . '_time_idx ON ' . ATOM_DBBANS . '(timestamp DESC);
	CREATE INDEX ' . ATOM_DBBANS . '_expire_idx ON ' . ATOM_DBBANS . '(expire);';

	$ipLookupsQuery = 'CREATE TABLE ' . ATOM_DBIPLOOKUPS . ' (
		ip varchar(39) NOT NULL PRIMARY KEY,
		abuser smallint NOT NULL DEFAULT 0,
		vps smallint NOT NULL DEFAULT 0,
		proxy smallint NOT NULL DEFAULT 0,
		tor smallint NOT NULL DEFAULT 0,
		vpn smallint NOT NULL DEFAULT 0,
		as_type varchar(20) DEFAULT NULL,
		provider_name varchar(100) DEFAULT NULL,
		last_updated integer NOT NULL DEFAULT 0
	);';

	$reportsQuery = 'CREATE TABLE ' . ATOM_DBREPORTS . ' (
		id bigserial NOT NULL PRIMARY KEY,
		ip varchar(39) NOT NULL,
		board varchar(16) NOT NULL,
		postnum integer NOT NULL,
		timestamp integer NOT NULL,
		reason text NOT NULL
	);
	CREATE INDEX ' . ATOM_DBREPORTS . '_board_pnum_time_idx ON ' . ATOM_DBREPORTS . '(board, postnum DESC, timestamp DESC);
	CREATE INDEX ' . ATOM_DBREPORTS . '_ip_board_pnum_idx ON ' . ATOM_DBREPORTS . '(ip, board, postnum);
	CREATE INDEX ' . ATOM_DBREPORTS . '_pnum_time_idx ON ' . ATOM_DBREPORTS . '(postnum, timestamp DESC);';

	$passQuery = 'CREATE TABLE ' . ATOM_DBPASS . ' (
		number bigserial NOT NULL PRIMARY KEY,
		id varchar(64) UNIQUE NOT NULL,
		issued integer NOT NULL,
		expires integer NOT NULL,
		blocked_till integer NOT NULL DEFAULT 0,
		blocked_reason text,
		meta text NOT NULL,
		meta_admin text NOT NULL,
		name text NOT NULL,
		last_used integer NOT NULL DEFAULT 0,
		last_used_ip varchar(64)
	);
	CREATE INDEX ' . ATOM_DBPASS . '_num_idx ON ' . ATOM_DBPASS . '(number);
	CREATE INDEX ' . ATOM_DBPASS . '_expires_idx ON ' . ATOM_DBPASS . '(expires);';

	$likesQuery = 'CREATE TABLE ' . ATOM_DBLIKES . ' (
		id bigserial NOT NULL PRIMARY KEY,
		ip varchar(39) NOT NULL,
		board varchar(16) NOT NULL,
		postnum integer NOT NULL,
		islike smallint NOT NULL DEFAULT 1
	);
	CREATE INDEX ' . ATOM_DBLIKES . '_ip_board_pnum_idx ON ' . ATOM_DBLIKES . '(ip, board, postnum);
	CREATE INDEX ' . ATOM_DBLIKES . '_board_pnum_idx ON ' . ATOM_DBLIKES . '(board, postnum);';

	$modlogQuery = 'CREATE TABLE ' . ATOM_DBMODLOG . ' (
		id bigserial NOT NULL PRIMARY KEY,
		timestamp integer NOT NULL,
		boardname varchar(255) NOT NULL,
		username varchar(75) NOT NULL,
		action text NOT NULL,
		color varchar(75) NOT NULL,
		private smallint NOT NULL DEFAULT 1
	);
	CREATE INDEX ' . ATOM_DBMODLOG . '_board_public_idx ON ' . ATOM_DBMODLOG . '(boardname, private, timestamp DESC);
	CREATE INDEX ' . ATOM_DBMODLOG . '_board_time_idx ON ' . ATOM_DBMODLOG . '(boardname, timestamp DESC);';

} else {
	$postsQuery = "CREATE TABLE IF NOT EXISTS `" . ATOM_DBPOSTS . "` (
		`id` mediumint(7) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`parent` mediumint(7) unsigned NOT NULL,
		`timestamp` int(20) NOT NULL,
		`bumped` int(20) NOT NULL,
		`ip` varchar(39) NOT NULL,
		`name` varchar(75) NOT NULL,
		`tripcode` varchar(10) NOT NULL,
		`email` varchar(75) NOT NULL,
		`nameblock` text NOT NULL,
		`subject` varchar(100) NOT NULL,
		`message` text NOT NULL,
		`password` varchar(255) NOT NULL,
		`file0` text NOT NULL,
		`file0_hex` varchar(75) NOT NULL,
		`file0_original` varchar(255) NOT NULL,
		`file0_size` int(20) unsigned NOT NULL DEFAULT 0,
		`file0_size_formatted` varchar(75) NOT NULL,
		`image0_width` smallint(5) unsigned NOT NULL DEFAULT 0,
		`image0_height` smallint(5) unsigned NOT NULL DEFAULT 0,
		`thumb0` varchar(255) NOT NULL,
		`thumb0_width` smallint(5) unsigned NOT NULL DEFAULT 0,
		`thumb0_height` smallint(5) unsigned NOT NULL DEFAULT 0,
		`file1` text NOT NULL,
		`file1_hex` varchar(75) NOT NULL,
		`file1_original` varchar(255) NOT NULL,
		`file1_size` int(20) unsigned NOT NULL DEFAULT 0,
		`file1_size_formatted` varchar(75) NOT NULL,
		`image1_width` smallint(5) unsigned NOT NULL DEFAULT 0,
		`image1_height` smallint(5) unsigned NOT NULL DEFAULT 0,
		`thumb1` varchar(255) NOT NULL,
		`thumb1_width` smallint(5) unsigned NOT NULL DEFAULT 0,
		`thumb1_height` smallint(5) unsigned NOT NULL DEFAULT 0,
		`file2` text NOT NULL,
		`file2_hex` varchar(75) NOT NULL,
		`file2_original` varchar(255) NOT NULL,
		`file2_size` int(20) unsigned NOT NULL DEFAULT 0,
		`file2_size_formatted` varchar(75) NOT NULL,
		`image2_width` smallint(5) unsigned NOT NULL DEFAULT 0,
		`image2_height` smallint(5) unsigned NOT NULL DEFAULT 0,
		`thumb2` varchar(255) NOT NULL,
		`thumb2_width` smallint(5) unsigned NOT NULL DEFAULT 0,
		`thumb2_height` smallint(5) unsigned NOT NULL DEFAULT 0,
		`file3` text NOT NULL,
		`file3_hex` varchar(75) NOT NULL,
		`file3_original` varchar(255) NOT NULL,
		`file3_size` int(20) unsigned NOT NULL DEFAULT 0,
		`file3_size_formatted` varchar(75) NOT NULL,
		`image3_width` smallint(5) unsigned NOT NULL DEFAULT 0,
		`image3_height` smallint(5) unsigned NOT NULL DEFAULT 0,
		`thumb3` varchar(255) NOT NULL,
		`thumb3_width` smallint(5) unsigned NOT NULL DEFAULT 0,
		`thumb3_height` smallint(5) unsigned NOT NULL DEFAULT 0,
		`likes` smallint(5) NOT NULL DEFAULT 0,
		`moderated` tinyint(1) NOT NULL DEFAULT 1,
		`stickied` tinyint(1) NOT NULL DEFAULT 0,
		`locked` tinyint(1) NOT NULL DEFAULT 0,
		`endless` tinyint(1) NOT NULL DEFAULT 0,
		`pass` mediumint(7) unsigned NOT NULL DEFAULT 0,
		INDEX `parent_sort_idx` (`parent`, `stickied` DESC, `bumped` DESC),
		INDEX `parent_id_idx` (`parent`, `id` ASC),
		INDEX `ip_time_idx` (`ip`, `timestamp` DESC),
		INDEX `mod_time_idx` (`moderated`, `timestamp` DESC),
		INDEX `f0_hex_idx` (`file0_hex`),
		INDEX `f1_hex_idx` (`file1_hex`),
		INDEX `f2_hex_idx` (`file2_hex`),
		INDEX `f3_hex_idx` (`file3_hex`)
	) ENGINE=InnoDB;";

	$staffQuery = "CREATE TABLE IF NOT EXISTS `" . ATOM_DBSTAFF . "` (
		`id` INT AUTO_INCREMENT PRIMARY KEY,
		`username` VARCHAR(50) UNIQUE NOT NULL,
		`password_hash` VARCHAR(255) NOT NULL,
		`role` ENUM('admin', 'moderator', 'janitor') NOT NULL,
		`last_login` int(20) NOT NULL DEFAULT 0,
		INDEX `role_username_idx` (`role`, `username`)
	) ENGINE=InnoDB;";

	$bansQuery = "CREATE TABLE IF NOT EXISTS `" . ATOM_DBBANS . "` (
		`id` mediumint(7) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`ip_from` bigint(20) NOT NULL,
		`ip_to` bigint(20) NOT NULL,
		`timestamp` int(20) NOT NULL,
		`expire` int(20) NOT NULL,
		`reason` text NOT NULL,
		INDEX `ip_range_idx` (`ip_from`, `ip_to`),
		INDEX `time_idx` (`timestamp` DESC),
		INDEX `expire_idx` (`expire`)
	) ENGINE=InnoDB;";

	$ipLookupsQuery = "CREATE TABLE IF NOT EXISTS `" . ATOM_DBIPLOOKUPS . "` (
		`ip` varchar(39) NOT NULL PRIMARY KEY,
		`abuser` tinyint(1) NOT NULL DEFAULT 0,
		`vps` tinyint(1) NOT NULL DEFAULT 0,
		`proxy` tinyint(1) NOT NULL DEFAULT 0,
		`tor` tinyint(1) NOT NULL DEFAULT 0,
		`vpn` tinyint(1) NOT NULL DEFAULT 0,
		`as_type` varchar(20) DEFAULT NULL,
		`provider_name` varchar(100) DEFAULT NULL,
		`last_updated` int(11) NOT NULL DEFAULT 0
	) ENGINE=InnoDB;";

	$reportsQuery = "CREATE TABLE IF NOT EXISTS `" . ATOM_DBREPORTS . "` (
		`id` mediumint(7) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`ip` varchar(39) NOT NULL,
		`board` varchar(16) NOT NULL,
		`postnum` mediumint(7) unsigned NOT NULL,
		`timestamp` int(20) NOT NULL,
		`reason` text NOT NULL,
		INDEX `board_pnum_time_idx` (`board`, `postnum` DESC, `timestamp` DESC),
		INDEX `ip_board_pnum_idx` (`ip`, `board`, `postnum`),
		INDEX `pnum_time_idx` (`postnum`, `timestamp` DESC)
	) ENGINE=InnoDB;";

	$passQuery = "CREATE TABLE IF NOT EXISTS `" . ATOM_DBPASS . "` (
		`number` mediumint(7) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`id` varchar(64) NOT NULL,
		`issued` int(20) NOT NULL,
		`expires` int(20) NOT NULL,
		`blocked_till` int(20) NOT NULL DEFAULT 0,
		`blocked_reason` text,
		`meta` text NOT NULL,
		`meta_admin` text NOT NULL,
		`name` text NOT NULL,
		`last_used` int(20) NOT NULL DEFAULT 0,
		`last_used_ip` varchar(64),
		INDEX `num_idx` (`number`),
		INDEX `expires_idx` (`expires`)
	) ENGINE=InnoDB;";

	$likesQuery = "CREATE TABLE IF NOT EXISTS `" . ATOM_DBLIKES . "` (
		`id` mediumint(7) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`ip` varchar(39) NOT NULL,
		`board` varchar(16) NOT NULL,
		`postnum` mediumint(7) unsigned NOT NULL,
		`islike` tinyint(1) NOT NULL DEFAULT 1,
		INDEX `ip_board_pnum_idx` (`ip`, `board`, `postnum`),
		INDEX `board_pnum_idx` (`board`, `postnum`)
	) ENGINE=InnoDB;";

	$modlogQuery = "CREATE TABLE IF NOT EXISTS `" . ATOM_DBMODLOG . "` (
		`id` mediumint(7) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`timestamp` int(20) NOT NULL,
		`boardname` varchar(255) NOT NULL,
		`username` varchar(75) NOT NULL,
		`action` text NOT NULL,
		`color` varchar(75) NOT NULL,
		`private` tinyint(1) NOT NULL DEFAULT 1,
		INDEX `board_public_idx` (`boardname`, `private`, `timestamp` DESC),
		INDEX `board_time_idx` (`boardname`, `timestamp` DESC)
	) ENGINE=InnoDB;";
}
