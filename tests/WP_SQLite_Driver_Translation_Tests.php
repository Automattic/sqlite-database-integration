<?php

require_once __DIR__ . '/../wp-includes/sqlite-ast/class-wp-sqlite-driver.php';
require_once __DIR__ . '/../wp-includes/sqlite-ast/class-wp-sqlite-information-schema-builder.php';

use PHPUnit\Framework\TestCase;

class WP_SQLite_Driver_Translation_Tests extends TestCase {
	const GRAMMAR_PATH = __DIR__ . '/../wp-includes/mysql/mysql-grammar.php';

	/**
	 * @var WP_Parser_Grammar
	 */
	private static $grammar;

	/**
	 * @var WP_SQLite_Driver
	 */
	private $driver;

	public static function setUpBeforeClass(): void {
		self::$grammar = new WP_Parser_Grammar( include self::GRAMMAR_PATH );
	}

	public function setUp(): void {
		$this->driver = new WP_SQLite_Driver( 'wp', new PDO( 'sqlite::memory:' ) );
	}

	public function testSelect(): void {
		$this->assertQuery(
			'SELECT 1',
			'SELECT 1'
		);

		$this->assertQuery(
			'SELECT * FROM `t`',
			'SELECT * FROM t'
		);

		$this->assertQuery(
			'SELECT `c` FROM `t`',
			'SELECT c FROM t'
		);

		$this->assertQuery(
			'SELECT ALL `c` FROM `t`',
			'SELECT ALL c FROM t'
		);

		$this->assertQuery(
			'SELECT DISTINCT `c` FROM `t`',
			'SELECT DISTINCT c FROM t'
		);

		$this->assertQuery(
			'SELECT `c1` , `c2` FROM `t`',
			'SELECT c1, c2 FROM t'
		);

		$this->assertQuery(
			'SELECT `t`.`c` FROM `t`',
			'SELECT t.c FROM t'
		);

		$this->assertQuery(
			'SELECT `c1` FROM `t` WHERE `c2` = \'abc\'',
			"SELECT c1 FROM t WHERE c2 = 'abc'"
		);

		$this->assertQuery(
			'SELECT `c` FROM `t` GROUP BY `c`',
			'SELECT c FROM t GROUP BY c'
		);

		$this->assertQuery(
			'SELECT `c` FROM `t` ORDER BY `c` ASC',
			'SELECT c FROM t ORDER BY c ASC'
		);

		$this->assertQuery(
			'SELECT `c` FROM `t` LIMIT 10',
			'SELECT c FROM t LIMIT 10'
		);

		$this->assertQuery(
			'SELECT `c` FROM `t` GROUP BY `c` HAVING COUNT ( `c` ) > 1',
			'SELECT c FROM t GROUP BY c HAVING COUNT(c) > 1'
		);

		$this->assertQuery(
			'SELECT * FROM `t1` LEFT JOIN `t2` ON `t1`.`id` = `t2`.`t1_id` WHERE `t1`.`name` = \'abc\'',
			"SELECT * FROM t1 LEFT JOIN t2 ON t1.id = t2.t1_id WHERE t1.name = 'abc'"
		);
	}

	public function testInsert(): void {
		$this->assertQuery(
			'INSERT INTO `t` ( `c` ) VALUES ( 1 )',
			'INSERT INTO t (c) VALUES (1)'
		);

		$this->assertQuery(
			'INSERT INTO `s`.`t` ( `c` ) VALUES ( 1 )',
			'INSERT INTO s.t (c) VALUES (1)'
		);

		$this->assertQuery(
			'INSERT INTO `t` ( `c1` , `c2` ) VALUES ( 1 , 2 )',
			'INSERT INTO t (c1, c2) VALUES (1, 2)'
		);

		$this->assertQuery(
			'INSERT INTO `t` ( `c` ) VALUES ( 1 ) , ( 2 )',
			'INSERT INTO t (c) VALUES (1), (2)'
		);

		$this->assertQuery(
			'INSERT INTO `t1` SELECT * FROM `t2`',
			'INSERT INTO t1 SELECT * FROM t2'
		);
	}

	public function testReplace(): void {
		$this->assertQuery(
			'REPLACE INTO `t` ( `c` ) VALUES ( 1 )',
			'REPLACE INTO t (c) VALUES (1)'
		);

		$this->assertQuery(
			'REPLACE INTO `s`.`t` ( `c` ) VALUES ( 1 )',
			'REPLACE INTO s.t (c) VALUES (1)'
		);

		$this->assertQuery(
			'REPLACE INTO `t` ( `c1` , `c2` ) VALUES ( 1 , 2 )',
			'REPLACE INTO t (c1, c2) VALUES (1, 2)'
		);

		$this->assertQuery(
			'REPLACE INTO `t` ( `c` ) VALUES ( 1 ) , ( 2 )',
			'REPLACE INTO t (c) VALUES (1), (2)'
		);

		$this->assertQuery(
			'REPLACE INTO `t1` SELECT * FROM `t2`',
			'REPLACE INTO t1 SELECT * FROM t2'
		);
	}

	public function testUpdate(): void {
		$this->assertQuery(
			'UPDATE `t` SET `c` = 1',
			'UPDATE t SET c = 1'
		);

		$this->assertQuery(
			'UPDATE `s`.`t` SET `c` = 1',
			'UPDATE s.t SET c = 1'
		);

		$this->assertQuery(
			'UPDATE `t` SET `c1` = 1 , `c2` = 2',
			'UPDATE t SET c1 = 1, c2 = 2'
		);

		$this->assertQuery(
			'UPDATE `t` SET `c` = 1 WHERE `c` = 2',
			'UPDATE t SET c = 1 WHERE c = 2'
		);

		// UPDATE with LIMIT.
		$this->assertQuery(
			'UPDATE `t` SET `c` = 1 WHERE rowid IN ( SELECT rowid FROM `t` LIMIT 1 )',
			'UPDATE t SET c = 1 LIMIT 1'
		);

		// UPDATE with ORDER BY and LIMIT.
		$this->assertQuery(
			'UPDATE `t` SET `c` = 1 WHERE rowid IN ( SELECT rowid FROM `t` ORDER BY `c` ASC LIMIT 1 )',
			'UPDATE t SET c = 1 ORDER BY c ASC LIMIT 1'
		);
	}

	public function testDelete(): void {
		$this->assertQuery(
			'DELETE FROM `t`',
			'DELETE FROM t'
		);

		$this->assertQuery(
			'DELETE FROM `s`.`t`',
			'DELETE FROM s.t'
		);

		$this->assertQuery(
			'DELETE FROM `t` WHERE `c` = 1',
			'DELETE FROM t WHERE c = 1'
		);
	}

	public function testCreateTable(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `id` INTEGER ) STRICT',
			'CREATE TABLE t (id INT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testCreateTableWithMultipleColumns(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `id` INTEGER, `name` TEXT COLLATE NOCASE, `score` REAL DEFAULT \'0.0\' ) STRICT',
			'CREATE TABLE t (id INT, name TEXT, score FLOAT DEFAULT 0.0)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'name', 2, null, 'YES', 'text', 65535, 65535, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'text', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'score', 3, '0.0', 'YES', 'float', null, null, 12, null, null, null, null, 'float', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testCreateTableWithBasicConstraints(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT ) STRICT',
			'CREATE TABLE t (id INT NOT NULL PRIMARY KEY AUTO_INCREMENT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'NO', 'int', null, null, 10, 0, null, null, null, 'int', 'PRI', 'auto_increment', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_statistics (table_schema, table_name, non_unique, index_schema, index_name, seq_in_index, column_name, collation, cardinality, sub_part, packed, nullable, index_type, comment, index_comment, is_visible, expression)'
					. " VALUES ('wp', 't', 0, 'wp', 'PRIMARY', 1, 'id', 'A', 0, null, null, '', 'BTREE', '', '', 'YES', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testCreateTableWithEngine(): void {
		// ENGINE is not supported in SQLite, we save it in information schema.
		$this->assertQuery(
			'CREATE TABLE `t` ( `id` INTEGER ) STRICT',
			'CREATE TABLE t (id INT) ENGINE=MyISAM'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'MyISAM', 'FIXED', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testCreateTableWithCollate(): void {
		// COLLATE is not supported in SQLite, we save it in information schema.
		$this->assertQuery(
			'CREATE TABLE `t` ( `id` INTEGER ) STRICT',
			'CREATE TABLE t (id INT) COLLATE utf8mb4_czech_ci'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_czech_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testCreateTableWithPrimaryKey(): void {
		/*
		 * PRIMARY KEY without AUTOINCREMENT:
		 * In this case, integer must be represented as INT, not INTEGER. SQLite
		 * treats "INTEGER PRIMARY KEY" as an alias for ROWID, causing unintended
		 * auto-increment-like behavior for a non-autoincrement column.
		 *
		 * See:
		 *  https://www.sqlite.org/lang_createtable.html#rowids_and_the_integer_primary_key
		 */
		$this->assertQuery(
			'CREATE TABLE `t` ( `id` INT NOT NULL, PRIMARY KEY (`id`) ) STRICT',
			'CREATE TABLE t (id INT PRIMARY KEY)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'NO', 'int', null, null, 10, 0, null, null, null, 'int', 'PRI', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_statistics (table_schema, table_name, non_unique, index_schema, index_name, seq_in_index, column_name, collation, cardinality, sub_part, packed, nullable, index_type, comment, index_comment, is_visible, expression)'
					. " VALUES ('wp', 't', 0, 'wp', 'PRIMARY', 1, 'id', 'A', 0, null, null, '', 'BTREE', '', '', 'YES', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testCreateTableWithPrimaryKeyAndAutoincrement(): void {
		// With AUTOINCREMENT, we expect "INTEGER".
		$this->assertQuery(
			'CREATE TABLE `t1` ( `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT ) STRICT',
			'CREATE TABLE t1 (id INT PRIMARY KEY AUTO_INCREMENT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't1', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't1', 'id', 1, null, 'NO', 'int', null, null, 10, 0, null, null, null, 'int', 'PRI', 'auto_increment', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_statistics (table_schema, table_name, non_unique, index_schema, index_name, seq_in_index, column_name, collation, cardinality, sub_part, packed, nullable, index_type, comment, index_comment, is_visible, expression)'
					. " VALUES ('wp', 't1', 0, 'wp', 'PRIMARY', 1, 'id', 'A', 0, null, null, '', 'BTREE', '', '', 'YES', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't1'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't1'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't1'",
			)
		);

		// In SQLite, PRIMARY KEY must come before AUTOINCREMENT.
		$this->assertQuery(
			'CREATE TABLE `t2` ( `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT ) STRICT',
			'CREATE TABLE t2 (id INT AUTO_INCREMENT PRIMARY KEY)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't2', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't2', 'id', 1, null, 'NO', 'int', null, null, 10, 0, null, null, null, 'int', 'PRI', 'auto_increment', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_statistics (table_schema, table_name, non_unique, index_schema, index_name, seq_in_index, column_name, collation, cardinality, sub_part, packed, nullable, index_type, comment, index_comment, is_visible, expression)'
					. " VALUES ('wp', 't2', 0, 'wp', 'PRIMARY', 1, 'id', 'A', 0, null, null, '', 'BTREE', '', '', 'YES', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't2'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't2'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't2'",
			)
		);

		// In SQLite, AUTOINCREMENT cannot be specified separately from PRIMARY KEY.
		$this->assertQuery(
			'CREATE TABLE `t3` ( `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT ) STRICT',
			'CREATE TABLE t3 (id INT AUTO_INCREMENT, PRIMARY KEY(id))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't3', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't3', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', 'auto_increment', 'select,insert,update,references', '', '', null)",
				"SELECT column_name, data_type, is_nullable, character_maximum_length FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't3' AND column_name IN ('id')",
				'INSERT INTO _mysql_information_schema_statistics (table_schema, table_name, non_unique, index_schema, index_name, seq_in_index, column_name, collation, cardinality, sub_part, packed, nullable, index_type, comment, index_comment, is_visible, expression)'
					. " VALUES ('wp', 't3', 0, 'wp', 'PRIMARY', 1, 'id', 'A', 0, null, null, '', 'BTREE', '', '', 'YES', null)",
				"WITH s AS ( SELECT column_name, CASE WHEN MAX(index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(non_unique = 0 AND seq_in_index = 1) THEN 'UNI' WHEN MAX(seq_in_index = 1) THEN 'MUL' ELSE '' END AS column_key FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't3' GROUP BY column_name ) UPDATE _mysql_information_schema_columns AS c SET column_key = s.column_key, is_nullable = IIF(s.column_key = 'PRI', 'NO', c.is_nullable) FROM s WHERE c.table_schema = 'wp' AND c.table_name = 't3' AND s.column_name = c.column_name",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't3'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't3'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't3'",
			)
		);
	}

	// @TODO: IF NOT EXISTS
	/*public function testCreateTableWithIfNotExists(): void {
		$this->assertQuery(
			'CREATE TABLE IF NOT EXISTS "t" ( "id" INTEGER ) STRICT',
			'CREATE TABLE IF NOT EXISTS t (id INT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
			)
		);
	}*/

	public function testCreateTableWithInlineUniqueIndexes(): void {
		$this->assertQuery(
			array(
				'CREATE TABLE `t` ( `id` INTEGER, `name` TEXT COLLATE NOCASE ) STRICT',
				'CREATE UNIQUE INDEX `t__id` ON `t` (`id`)',
				'CREATE UNIQUE INDEX `t__name` ON `t` (`name`)',
			),
			'CREATE TABLE t (id INT UNIQUE, name TEXT UNIQUE)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', 'UNI', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_statistics (table_schema, table_name, non_unique, index_schema, index_name, seq_in_index, column_name, collation, cardinality, sub_part, packed, nullable, index_type, comment, index_comment, is_visible, expression)'
					. " VALUES ('wp', 't', 0, 'wp', 'id', 1, 'id', 'A', 0, null, null, 'YES', 'BTREE', '', '', 'YES', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'name', 2, null, 'YES', 'text', 65535, 65535, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'text', 'UNI', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_statistics (table_schema, table_name, non_unique, index_schema, index_name, seq_in_index, column_name, collation, cardinality, sub_part, packed, nullable, index_type, comment, index_comment, is_visible, expression)'
					. " VALUES ('wp', 't', 0, 'wp', 'name', 1, 'name', 'A', 0, null, null, 'YES', 'BTREE', '', '', 'YES', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testCreateTableWithStandaloneUniqueIndexes(): void {
		$this->assertQuery(
			array(
				'CREATE TABLE `t` ( `id` INTEGER, `name` TEXT COLLATE NOCASE ) STRICT',
				'CREATE UNIQUE INDEX `t__id` ON `t` (`id`)',
				'CREATE UNIQUE INDEX `t__name` ON `t` (`name`)',
			),
			'CREATE TABLE t (id INT, name VARCHAR(100), UNIQUE (id), UNIQUE (name))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'name', 2, null, 'YES', 'varchar', 100, 400, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'varchar(100)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT column_name, data_type, is_nullable, character_maximum_length FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't' AND column_name IN ('id')",
				'INSERT INTO _mysql_information_schema_statistics (table_schema, table_name, non_unique, index_schema, index_name, seq_in_index, column_name, collation, cardinality, sub_part, packed, nullable, index_type, comment, index_comment, is_visible, expression)'
					. " VALUES ('wp', 't', 0, 'wp', 'id', 1, 'id', 'A', 0, null, null, 'YES', 'BTREE', '', '', 'YES', null)",
				"WITH s AS ( SELECT column_name, CASE WHEN MAX(index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(non_unique = 0 AND seq_in_index = 1) THEN 'UNI' WHEN MAX(seq_in_index = 1) THEN 'MUL' ELSE '' END AS column_key FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't' GROUP BY column_name ) UPDATE _mysql_information_schema_columns AS c SET column_key = s.column_key, is_nullable = IIF(s.column_key = 'PRI', 'NO', c.is_nullable) FROM s WHERE c.table_schema = 'wp' AND c.table_name = 't' AND s.column_name = c.column_name",
				"SELECT column_name, data_type, is_nullable, character_maximum_length FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't' AND column_name IN ('name')",
				'INSERT INTO _mysql_information_schema_statistics (table_schema, table_name, non_unique, index_schema, index_name, seq_in_index, column_name, collation, cardinality, sub_part, packed, nullable, index_type, comment, index_comment, is_visible, expression)'
					. " VALUES ('wp', 't', 0, 'wp', 'name', 1, 'name', 'A', 0, null, null, 'YES', 'BTREE', '', '', 'YES', null)",
				"WITH s AS ( SELECT column_name, CASE WHEN MAX(index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(non_unique = 0 AND seq_in_index = 1) THEN 'UNI' WHEN MAX(seq_in_index = 1) THEN 'MUL' ELSE '' END AS column_key FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't' GROUP BY column_name ) UPDATE _mysql_information_schema_columns AS c SET column_key = s.column_key, is_nullable = IIF(s.column_key = 'PRI', 'NO', c.is_nullable) FROM s WHERE c.table_schema = 'wp' AND c.table_name = 't' AND s.column_name = c.column_name",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testCreateTableFromSelectQuery(): void {
		// CREATE TABLE AS SELECT ...
		$this->assertQuery(
			'CREATE TABLE `t1` AS SELECT * FROM `t2` STRICT',
			'CREATE TABLE t1 AS SELECT * FROM t2'
		);

		// CREATE TABLE SELECT ...
		// The "AS" keyword is optional in MySQL, but required in SQLite.
		$this->assertQuery(
			'CREATE TABLE `t1` AS SELECT * FROM `t2` STRICT',
			'CREATE TABLE t1 SELECT * FROM t2'
		);
	}

	public function testCreateTemporaryTable(): void {
		$this->assertQuery(
			'CREATE TEMPORARY TABLE `t` ( `id` INTEGER ) STRICT',
			'CREATE TEMPORARY TABLE t (id INT)'
		);

		// With IF NOT EXISTS.
		$this->assertQuery(
			'CREATE TEMPORARY TABLE IF NOT EXISTS `t` ( `id` INTEGER ) STRICT',
			'CREATE TEMPORARY TABLE IF NOT EXISTS t (id INT)'
		);
	}

	public function testAlterTableAddColumn(): void {
		$this->driver->query( 'CREATE TABLE t (id INT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `id` INTEGER, `a` INTEGER ) STRICT',
				'INSERT INTO `<tmp-table>` (`rowid`, `id`) SELECT `rowid`, `id` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t ADD a INT'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT MAX(ordinal_position) FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'a', 2, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testAlterTableAddColumnWithNotNull(): void {
		$this->driver->query( 'CREATE TABLE t (id INT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `id` INTEGER, `a` INTEGER NOT NULL ) STRICT',
				'INSERT INTO `<tmp-table>` (`rowid`, `id`) SELECT `rowid`, `id` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t ADD a INT NOT NULL'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT MAX(ordinal_position) FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'a', 2, null, 'NO', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testAlterTableAddColumnWithDefault(): void {
		$this->driver->query( 'CREATE TABLE t (id INT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `id` INTEGER, `a` INTEGER DEFAULT \'0\' ) STRICT',
				'INSERT INTO `<tmp-table>` (`rowid`, `id`) SELECT `rowid`, `id` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t ADD a INT DEFAULT 0'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT MAX(ordinal_position) FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'a', 2, '0', 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testAlterTableAddColumnWithNotNullAndDefault(): void {
		$this->driver->query( 'CREATE TABLE t (id INT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `id` INTEGER, `a` INTEGER NOT NULL DEFAULT \'0\' ) STRICT',
				'INSERT INTO `<tmp-table>` (`rowid`, `id`) SELECT `rowid`, `id` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t ADD a INT NOT NULL DEFAULT 0'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT MAX(ordinal_position) FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'a', 2, '0', 'NO', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testAlterTableAddMultipleColumns(): void {
		$this->driver->query( 'CREATE TABLE t (id INT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `id` INTEGER, `a` INTEGER, `b` TEXT COLLATE NOCASE, `c` INTEGER ) STRICT',
				'INSERT INTO `<tmp-table>` (`rowid`, `id`) SELECT `rowid`, `id` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t ADD a INT, ADD b TEXT, ADD c BOOL'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT MAX(ordinal_position) FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'a', 2, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT MAX(ordinal_position) FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'b', 3, null, 'YES', 'text', 65535, 65535, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'text', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT MAX(ordinal_position) FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c', 4, null, 'YES', 'tinyint', null, null, 3, 0, null, null, null, 'tinyint(1)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testAlterTableDropColumn(): void {
		$this->driver->query( 'CREATE TABLE t (id INT, a TEXT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `id` INTEGER ) STRICT',
				'INSERT INTO `<tmp-table>` (`rowid`, `id`) SELECT `rowid`, `id` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t DROP a'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"DELETE FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't' AND column_name = 'a'",
				"DELETE FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't' AND column_name = 'a'",
				"WITH s AS ( SELECT column_name, CASE WHEN MAX(index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(non_unique = 0 AND seq_in_index = 1) THEN 'UNI' WHEN MAX(seq_in_index = 1) THEN 'MUL' ELSE '' END AS column_key FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't' GROUP BY column_name ) UPDATE _mysql_information_schema_columns AS c SET column_key = s.column_key, is_nullable = IIF(s.column_key = 'PRI', 'NO', c.is_nullable) FROM s WHERE c.table_schema = 'wp' AND c.table_name = 't' AND s.column_name = c.column_name",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);  }

	public function testAlterTableDropMultipleColumns(): void {
		$this->driver->query( 'CREATE TABLE t (id INT, a INT, b TEXT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `id` INTEGER ) STRICT',
				'INSERT INTO `<tmp-table>` (`rowid`, `id`) SELECT `rowid`, `id` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t DROP a, DROP b'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"DELETE FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't' AND column_name = 'a'",
				"DELETE FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't' AND column_name = 'a'",
				"WITH s AS ( SELECT column_name, CASE WHEN MAX(index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(non_unique = 0 AND seq_in_index = 1) THEN 'UNI' WHEN MAX(seq_in_index = 1) THEN 'MUL' ELSE '' END AS column_key FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't' GROUP BY column_name ) UPDATE _mysql_information_schema_columns AS c SET column_key = s.column_key, is_nullable = IIF(s.column_key = 'PRI', 'NO', c.is_nullable) FROM s WHERE c.table_schema = 'wp' AND c.table_name = 't' AND s.column_name = c.column_name",
				"DELETE FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't' AND column_name = 'b'",
				"DELETE FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't' AND column_name = 'b'",
				"WITH s AS ( SELECT column_name, CASE WHEN MAX(index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(non_unique = 0 AND seq_in_index = 1) THEN 'UNI' WHEN MAX(seq_in_index = 1) THEN 'MUL' ELSE '' END AS column_key FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't' GROUP BY column_name ) UPDATE _mysql_information_schema_columns AS c SET column_key = s.column_key, is_nullable = IIF(s.column_key = 'PRI', 'NO', c.is_nullable) FROM s WHERE c.table_schema = 'wp' AND c.table_name = 't' AND s.column_name = c.column_name",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testAlterTableAddAndDropColumns(): void {
		$this->driver->query( 'CREATE TABLE t (a INT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `b` INTEGER ) STRICT',
				'INSERT INTO `<tmp-table>` (`rowid`) SELECT `rowid` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t ADD b INT, DROP a'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT MAX(ordinal_position) FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'b', 2, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"DELETE FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't' AND column_name = 'a'",
				"DELETE FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't' AND column_name = 'a'",
				"WITH s AS ( SELECT column_name, CASE WHEN MAX(index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(non_unique = 0 AND seq_in_index = 1) THEN 'UNI' WHEN MAX(seq_in_index = 1) THEN 'MUL' ELSE '' END AS column_key FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't' GROUP BY column_name ) UPDATE _mysql_information_schema_columns AS c SET column_key = s.column_key, is_nullable = IIF(s.column_key = 'PRI', 'NO', c.is_nullable) FROM s WHERE c.table_schema = 'wp' AND c.table_name = 't' AND s.column_name = c.column_name",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testAlterTableDropAndAddSingleColumn(): void {
		$this->driver->query( 'CREATE TABLE t (a INT)' );
		$this->assertQuery(
			array(
				'PRAGMA foreign_keys',
				'PRAGMA foreign_keys = OFF',
				'CREATE TABLE `<tmp-table>` ( `a` INTEGER ) STRICT',
				'INSERT INTO `<tmp-table>` (`rowid`) SELECT `rowid` FROM `t`',
				'DROP TABLE `t`',
				'ALTER TABLE `<tmp-table>` RENAME TO `t`',
				'PRAGMA foreign_key_check',
				'PRAGMA foreign_keys = ON',
			),
			'ALTER TABLE t DROP a, ADD a INT'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				"SELECT COLUMN_NAME FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"DELETE FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't' AND column_name = 'a'",
				"DELETE FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't' AND column_name = 'a'",
				"WITH s AS ( SELECT column_name, CASE WHEN MAX(index_name = 'PRIMARY') THEN 'PRI' WHEN MAX(non_unique = 0 AND seq_in_index = 1) THEN 'UNI' WHEN MAX(seq_in_index = 1) THEN 'MUL' ELSE '' END AS column_key FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't' GROUP BY column_name ) UPDATE _mysql_information_schema_columns AS c SET column_key = s.column_key, is_nullable = IIF(s.column_key = 'PRI', 'NO', c.is_nullable) FROM s WHERE c.table_schema = 'wp' AND c.table_name = 't' AND s.column_name = c.column_name",
				"SELECT MAX(ordinal_position) FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'a', 1, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testBitDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `i1` INTEGER, `i2` INTEGER ) STRICT',
			'CREATE TABLE t (i1 BIT, i2 BIT(10))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i1', 1, null, 'YES', 'bit', null, null, 1, null, null, null, null, 'bit(1)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i2', 2, null, 'YES', 'bit', null, null, 10, null, null, null, null, 'bit(10)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testBooleanDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `i1` INTEGER, `i2` INTEGER ) STRICT',
			'CREATE TABLE t (i1 BOOL, i2 BOOLEAN)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i1', 1, null, 'YES', 'tinyint', null, null, 3, 0, null, null, null, 'tinyint(1)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i2', 2, null, 'YES', 'tinyint', null, null, 3, 0, null, null, null, 'tinyint(1)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testIntegerDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `i1` INTEGER, `i2` INTEGER, `i3` INTEGER, `i4` INTEGER, `i5` INTEGER, `i6` INTEGER ) STRICT',
			'CREATE TABLE t (i1 TINYINT, i2 SMALLINT, i3 MEDIUMINT, i4 INT, i5 INTEGER, i6 BIGINT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i1', 1, null, 'YES', 'tinyint', null, null, 3, 0, null, null, null, 'tinyint', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i2', 2, null, 'YES', 'smallint', null, null, 5, 0, null, null, null, 'smallint', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i3', 3, null, 'YES', 'mediumint', null, null, 7, 0, null, null, null, 'mediumint', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i4', 4, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i5', 5, null, 'YES', 'int', null, null, 10, 0, null, null, null, 'int', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'i6', 6, null, 'YES', 'bigint', null, null, 19, 0, null, null, null, 'bigint', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testFloatDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `f1` REAL, `f2` REAL, `f3` REAL, `f4` REAL ) STRICT',
			'CREATE TABLE t (f1 FLOAT, f2 DOUBLE, f3 DOUBLE PRECISION, f4 REAL)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f1', 1, null, 'YES', 'float', null, null, 12, null, null, null, null, 'float', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f2', 2, null, 'YES', 'double', null, null, 22, null, null, null, null, 'double', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f3', 3, null, 'YES', 'double', null, null, 22, null, null, null, null, 'double', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f4', 4, null, 'YES', 'double', null, null, 22, null, null, null, null, 'double', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testDecimalTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `f1` REAL, `f2` REAL, `f3` REAL, `f4` REAL ) STRICT',
			'CREATE TABLE t (f1 DECIMAL, f2 DEC, f3 FIXED, f4 NUMERIC)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f1', 1, null, 'YES', 'decimal', null, null, 10, 0, null, null, null, 'decimal(10,0)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f2', 2, null, 'YES', 'decimal', null, null, 10, 0, null, null, null, 'decimal(10,0)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f3', 3, null, 'YES', 'decimal', null, null, 10, 0, null, null, null, 'decimal(10,0)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'f4', 4, null, 'YES', 'decimal', null, null, 10, 0, null, null, null, 'decimal(10,0)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testCharDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `c1` TEXT COLLATE NOCASE, `c2` TEXT COLLATE NOCASE ) STRICT',
			'CREATE TABLE t (c1 CHAR, c2 CHAR(10))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c1', 1, null, 'YES', 'char', 1, 4, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'char(1)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c2', 2, null, 'YES', 'char', 10, 40, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'char(10)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testVarcharDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `c1` TEXT COLLATE NOCASE, `c2` TEXT COLLATE NOCASE, `c3` TEXT COLLATE NOCASE ) STRICT',
			'CREATE TABLE t (c1 VARCHAR(255), c2 CHAR VARYING(255), c3 CHARACTER VARYING(255))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c1', 1, null, 'YES', 'varchar', 255, 1020, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c2', 2, null, 'YES', 'varchar', 255, 1020, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c3', 3, null, 'YES', 'varchar', 255, 1020, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testNationalCharDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `c1` TEXT COLLATE NOCASE, `c2` TEXT COLLATE NOCASE, `c3` TEXT COLLATE NOCASE, `c4` TEXT COLLATE NOCASE ) STRICT',
			'CREATE TABLE t (c1 NATIONAL CHAR, c2 NCHAR, c3 NATIONAL CHAR (10), c4 NCHAR(10))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c1', 1, null, 'YES', 'char', 1, 3, null, null, null, 'utf8', 'utf8_general_ci', 'char(1)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c2', 2, null, 'YES', 'char', 1, 3, null, null, null, 'utf8', 'utf8_general_ci', 'char(1)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c3', 3, null, 'YES', 'char', 10, 30, null, null, null, 'utf8', 'utf8_general_ci', 'char(10)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c4', 4, null, 'YES', 'char', 10, 30, null, null, null, 'utf8', 'utf8_general_ci', 'char(10)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testNcharVarcharDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `c1` TEXT COLLATE NOCASE, `c2` TEXT COLLATE NOCASE, `c3` TEXT COLLATE NOCASE ) STRICT',
			'CREATE TABLE t (c1 NCHAR VARCHAR(255), c2 NCHAR VARYING(255), c3 NVARCHAR(255))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c1', 1, null, 'YES', 'varchar', 255, 765, null, null, null, 'utf8', 'utf8_general_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c2', 2, null, 'YES', 'varchar', 255, 765, null, null, null, 'utf8', 'utf8_general_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c3', 3, null, 'YES', 'varchar', 255, 765, null, null, null, 'utf8', 'utf8_general_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testNationalVarcharDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `c1` TEXT COLLATE NOCASE, `c2` TEXT COLLATE NOCASE, `c3` TEXT COLLATE NOCASE ) STRICT',
			'CREATE TABLE t (c1 NATIONAL VARCHAR(255), c2 NATIONAL CHAR VARYING(255), c3 NATIONAL CHARACTER VARYING(255))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c1', 1, null, 'YES', 'varchar', 255, 765, null, null, null, 'utf8', 'utf8_general_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c2', 2, null, 'YES', 'varchar', 255, 765, null, null, null, 'utf8', 'utf8_general_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'c3', 3, null, 'YES', 'varchar', 255, 765, null, null, null, 'utf8', 'utf8_general_ci', 'varchar(255)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testTextDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `t1` TEXT COLLATE NOCASE, `t2` TEXT COLLATE NOCASE, `t3` TEXT COLLATE NOCASE, `t4` TEXT COLLATE NOCASE ) STRICT',
			'CREATE TABLE t (t1 TINYTEXT, t2 TEXT, t3 MEDIUMTEXT, t4 LONGTEXT)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 't1', 1, null, 'YES', 'tinytext', 255, 255, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'tinytext', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 't2', 2, null, 'YES', 'text', 65535, 65535, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'text', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 't3', 3, null, 'YES', 'mediumtext', 16777215, 16777215, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'mediumtext', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 't4', 4, null, 'YES', 'longtext', 4294967295, 4294967295, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'longtext', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testEnumDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `e` TEXT COLLATE NOCASE ) STRICT',
			'CREATE TABLE t (e ENUM("a", "b", "c"))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'e', 1, null, 'YES', 'enum', 1, 4, null, null, null, 'utf8mb4', 'utf8mb4_general_ci', 'enum(''a'',''b'',''c'')', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testDateAndTimeDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `d` TEXT COLLATE NOCASE, `t` TEXT COLLATE NOCASE, `dt` TEXT COLLATE NOCASE, `ts` TEXT COLLATE NOCASE, `y` TEXT COLLATE NOCASE ) STRICT',
			'CREATE TABLE t (d DATE, t TIME, dt DATETIME, ts TIMESTAMP, y YEAR)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'd', 1, null, 'YES', 'date', null, null, null, null, null, null, null, 'date', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 't', 2, null, 'YES', 'time', null, null, null, null, 0, null, null, 'time', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'dt', 3, null, 'YES', 'datetime', null, null, null, null, 0, null, null, 'datetime', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'ts', 4, null, 'YES', 'timestamp', null, null, null, null, 0, null, null, 'timestamp', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'y', 5, null, 'YES', 'year', null, null, null, null, null, null, null, 'year', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testBinaryDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `b` INTEGER, `v` BLOB ) STRICT',
			'CREATE TABLE t (b BINARY, v VARBINARY(255))'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'b', 1, null, 'YES', 'binary', 1, 1, null, null, null, null, null, 'binary(1)', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'v', 2, null, 'YES', 'varbinary', 255, 255, null, null, null, null, null, 'varbinary(255)', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testBlobDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `b1` BLOB, `b2` BLOB, `b3` BLOB, `b4` BLOB ) STRICT',
			'CREATE TABLE t (b1 TINYBLOB, b2 BLOB, b3 MEDIUMBLOB, b4 LONGBLOB)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'b1', 1, null, 'YES', 'tinyblob', 255, 255, null, null, null, null, null, 'tinyblob', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'b2', 2, null, 'YES', 'blob', 65535, 65535, null, null, null, null, null, 'blob', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'b3', 3, null, 'YES', 'mediumblob', 16777215, 16777215, null, null, null, null, null, 'mediumblob', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'b4', 4, null, 'YES', 'longblob', 4294967295, 4294967295, null, null, null, null, null, 'longblob', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testBasicSpatialDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `g1` TEXT COLLATE NOCASE, `g2` TEXT COLLATE NOCASE, `g3` TEXT COLLATE NOCASE, `g4` TEXT COLLATE NOCASE ) STRICT',
			'CREATE TABLE t (g1 GEOMETRY, g2 POINT, g3 LINESTRING, g4 POLYGON)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'g1', 1, null, 'YES', 'geometry', null, null, null, null, null, null, null, 'geometry', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'g2', 2, null, 'YES', 'point', null, null, null, null, null, null, null, 'point', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'g3', 3, null, 'YES', 'linestring', null, null, null, null, null, null, null, 'linestring', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'g4', 4, null, 'YES', 'polygon', null, null, null, null, null, null, null, 'polygon', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testMultiObjectSpatialDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `g1` TEXT COLLATE NOCASE, `g2` TEXT COLLATE NOCASE, `g3` TEXT COLLATE NOCASE ) STRICT',
			'CREATE TABLE t (g1 MULTIPOINT, g2 MULTILINESTRING, g3 MULTIPOLYGON)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'g1', 1, null, 'YES', 'multipoint', null, null, null, null, null, null, null, 'multipoint', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'g2', 2, null, 'YES', 'multilinestring', null, null, null, null, null, null, null, 'multilinestring', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'g3', 3, null, 'YES', 'multipolygon', null, null, null, null, null, null, null, 'multipolygon', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testGeometryCollectionDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `g1` TEXT COLLATE NOCASE, `g2` TEXT COLLATE NOCASE ) STRICT',
			'CREATE TABLE t (g1 GEOMCOLLECTION, g2 GEOMETRYCOLLECTION)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'g1', 1, null, 'YES', 'geomcollection', null, null, null, null, null, null, null, 'geomcollection', '', '', 'select,insert,update,references', '', '', null)",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'g2', 2, null, 'YES', 'geomcollection', null, null, null, null, null, null, null, 'geomcollection', '', '', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testSerialDataTypes(): void {
		$this->assertQuery(
			'CREATE TABLE `t` ( `id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT ) STRICT',
			'CREATE TABLE t (id SERIAL)'
		);

		$this->assertExecutedInformationSchemaQueries(
			array(
				'INSERT INTO _mysql_information_schema_tables (table_schema, table_name, table_type, engine, row_format, table_collation)'
					. " VALUES ('wp', 't', 'BASE TABLE', 'InnoDB', 'DYNAMIC', 'utf8mb4_general_ci')",
				'INSERT INTO _mysql_information_schema_columns (table_schema, table_name, column_name, ordinal_position, column_default, is_nullable, data_type, character_maximum_length, character_octet_length, numeric_precision, numeric_scale, datetime_precision, character_set_name, collation_name, column_type, column_key, extra, privileges, column_comment, generation_expression, srs_id)'
					. " VALUES ('wp', 't', 'id', 1, null, 'NO', 'bigint', null, null, 20, 0, null, null, null, 'bigint unsigned', 'PRI', 'auto_increment', 'select,insert,update,references', '', '', null)",
				"SELECT * FROM _mysql_information_schema_tables WHERE table_type = 'BASE TABLE' AND table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_columns WHERE table_schema = 'wp' AND table_name = 't'",
				"SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = 'wp' AND table_name = 't'",
			)
		);
	}

	public function testSystemVariables(): void {
		$this->assertQuery(
			'SELECT NULL',
			'SELECT @@sql_mode'
		);

		$this->assertQuery(
			'SELECT NULL',
			'SELECT @@SESSION.sql_mode'
		);

		$this->assertQuery(
			'SELECT NULL',
			'SELECT @@GLOBAL.sql_mode'
		);
	}

	public function testConcatFunction(): void {
		$this->assertQuery(
			"SELECT ('a' || 'b' || 'c')",
			'SELECT CONCAT("a", "b", "c")'
		);
	}

	private function assertQuery( $expected, string $query ): void {
		$this->driver->query( $query );

		// Check for SQLite syntax errors.
		// This ensures that invalid SQLite syntax will always fail, even if it
		// was the expected result. It prevents us from using wrong assertions.
		$error = $this->driver->get_error_message();
		if ( $error && preg_match( '/(SQLSTATE\[HY000].+syntax error\.)/i', $error, $matches ) ) {
			$this->fail(
				sprintf( "SQLite syntax error: %s\nMySQL query: %s", $matches[1], $query )
			);
		}

		$executed_queries = array_column( $this->driver->get_sqlite_queries(), 'sql' );

		// Remove BEGIN and COMMIT/ROLLBACK queries.
		if ( count( $executed_queries ) > 2 ) {
			$executed_queries = array_values( array_slice( $executed_queries, 1, -1, true ) );
		}

		// Remove "information_schema" queries.
		$executed_queries = array_values(
			array_filter(
				$executed_queries,
				function ( $query ) {
					return ! str_contains( $query, '_mysql_information_schema_' );
				}
			)
		);

		// Remove "select changes()" executed after some queries.
		if (
			count( $executed_queries ) > 1
			&& 'select changes()' === $executed_queries[ count( $executed_queries ) - 1 ] ) {
			array_pop( $executed_queries );
		}

		if ( ! is_array( $expected ) ) {
			$expected = array( $expected );
		}

		// Normalize whitespace.
		foreach ( $executed_queries as $key => $executed_query ) {
			$executed_queries[ $key ] = trim( preg_replace( '/\s+/', ' ', $executed_query ) );
		}

		// Normalize temporary table names.
		foreach ( $executed_queries as $key => $executed_query ) {
			$executed_queries[ $key ] = preg_replace( '/`_wp_sqlite_tmp_[^`]+`/', '`<tmp-table>`', $executed_query );
		}

		$this->assertSame( $expected, $executed_queries );
	}

	private function assertExecutedInformationSchemaQueries( array $expected ): void {
		// Collect and normalize "information_schema" queries.
		$queries = array();
		foreach ( $this->driver->get_sqlite_queries() as $query ) {
			if ( ! str_contains( $query['sql'], '_mysql_information_schema_' ) ) {
				continue;
			}

			// Normalize whitespace.
			$sql = trim( preg_replace( '/\s+/', ' ', $query['sql'] ) );

			// Inline parameters.
			$sql       = str_replace( '?', '%s', $sql );
			$queries[] = sprintf(
				$sql,
				...array_map(
					function ( $param ) {
						if ( null === $param ) {
							return 'null';
						}
						if ( is_string( $param ) ) {
							return $this->driver->get_pdo()->quote( $param );
						}
						return $param;
					},
					$query['params']
				)
			);
		}
		$this->assertSame( $expected, $queries );
	}
}
