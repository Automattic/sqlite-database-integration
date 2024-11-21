<?php

require_once __DIR__ . '/../wp-includes/sqlite-ast/class-wp-sqlite-driver.php';
require_once __DIR__ . '/../wp-includes/sqlite-ast/class-wp-sqlite-token-factory.php';
require_once __DIR__ . '/../wp-includes/sqlite-ast/class-wp-sqlite-token.php';

use PHPUnit\Framework\TestCase;
use WIP\WP_SQLite_Driver;

class WP_SQLite_Driver_Translation_Tests extends TestCase {
	const GRAMMAR_PATH = __DIR__ . '/../wp-includes/mysql/mysql-grammar.php';

	/**
	 * @var WP_Parser_Grammar
	 */
	private static $grammar;

	public static function setUpBeforeClass(): void {
		self::$grammar = new WP_Parser_Grammar( include self::GRAMMAR_PATH );
	}

	public function testSelect(): void {
		$this->assertQuery(
			'SELECT 1',
			'SELECT 1'
		);

		$this->assertQuery(
			'SELECT * FROM "t"',
			'SELECT * FROM t'
		);

		$this->assertQuery(
			'SELECT "c" FROM "t"',
			'SELECT c FROM t'
		);

		$this->assertQuery(
			'SELECT ALL "c" FROM "t"',
			'SELECT ALL c FROM t'
		);

		$this->assertQuery(
			'SELECT DISTINCT "c" FROM "t"',
			'SELECT DISTINCT c FROM t'
		);

		$this->assertQuery(
			'SELECT "c1" , "c2" FROM "t"',
			'SELECT c1, c2 FROM t'
		);

		$this->assertQuery(
			'SELECT "t"."c" FROM "t"',
			'SELECT t.c FROM t'
		);

		$this->assertQuery(
			'SELECT "c1" FROM "t" WHERE "c2" = \'abc\'',
			"SELECT c1 FROM t WHERE c2 = 'abc'"
		);

		$this->assertQuery(
			'SELECT "c" FROM "t" GROUP BY "c"',
			'SELECT c FROM t GROUP BY c'
		);

		$this->assertQuery(
			'SELECT "c" FROM "t" ORDER BY "c" ASC',
			'SELECT c FROM t ORDER BY c ASC'
		);

		$this->assertQuery(
			'SELECT "c" FROM "t" LIMIT 10',
			'SELECT c FROM t LIMIT 10'
		);

		$this->assertQuery(
			'SELECT "c" FROM "t" GROUP BY "c" HAVING COUNT ( "c" ) > 1',
			'SELECT c FROM t GROUP BY c HAVING COUNT(c) > 1'
		);

		$this->assertQuery(
			'SELECT * FROM "t1" LEFT JOIN "t2" ON "t1"."id" = "t2"."t1_id" WHERE "t1"."name" = \'abc\'',
			"SELECT * FROM t1 LEFT JOIN t2 ON t1.id = t2.t1_id WHERE t1.name = 'abc'"
		);
	}

	public function testInsert(): void {
		$this->assertQuery(
			'INSERT INTO "t" ( "c" ) VALUES ( 1 )',
			'INSERT INTO t (c) VALUES (1)'
		);

		$this->assertQuery(
			'INSERT INTO "s"."t" ( "c" ) VALUES ( 1 )',
			'INSERT INTO s.t (c) VALUES (1)'
		);

		$this->assertQuery(
			'INSERT INTO "t" ( "c1" , "c2" ) VALUES ( 1 , 2 )',
			'INSERT INTO t (c1, c2) VALUES (1, 2)'
		);

		$this->assertQuery(
			'INSERT INTO "t" ( "c" ) VALUES ( 1 ) , ( 2 )',
			'INSERT INTO t (c) VALUES (1), (2)'
		);

		$this->assertQuery(
			'INSERT INTO "t1" SELECT * FROM "t2"',
			'INSERT INTO t1 SELECT * FROM t2'
		);
	}

	public function testReplace(): void {
		$this->assertQuery(
			'REPLACE INTO "t" ( "c" ) VALUES ( 1 )',
			'REPLACE INTO t (c) VALUES (1)'
		);

		$this->assertQuery(
			'REPLACE INTO "s"."t" ( "c" ) VALUES ( 1 )',
			'REPLACE INTO s.t (c) VALUES (1)'
		);

		$this->assertQuery(
			'REPLACE INTO "t" ( "c1" , "c2" ) VALUES ( 1 , 2 )',
			'REPLACE INTO t (c1, c2) VALUES (1, 2)'
		);

		$this->assertQuery(
			'REPLACE INTO "t" ( "c" ) VALUES ( 1 ) , ( 2 )',
			'REPLACE INTO t (c) VALUES (1), (2)'
		);

		$this->assertQuery(
			'REPLACE INTO "t1" SELECT * FROM "t2"',
			'REPLACE INTO t1 SELECT * FROM t2'
		);
	}

	public function testUpdate(): void {
		$this->assertQuery(
			'UPDATE "t" SET "c" = 1',
			'UPDATE t SET c = 1'
		);

		$this->assertQuery(
			'UPDATE "s"."t" SET "c" = 1',
			'UPDATE s.t SET c = 1'
		);

		$this->assertQuery(
			'UPDATE "t" SET "c1" = 1 , "c2" = 2',
			'UPDATE t SET c1 = 1, c2 = 2'
		);

		$this->assertQuery(
			'UPDATE "t" SET "c" = 1 WHERE "c" = 2',
			'UPDATE t SET c = 1 WHERE c = 2'
		);
	}

	public function testDelete(): void {
		$this->assertQuery(
			'DELETE FROM "t"',
			'DELETE FROM t'
		);

		$this->assertQuery(
			'DELETE FROM "s"."t"',
			'DELETE FROM s.t'
		);

		$this->assertQuery(
			'DELETE FROM "t" WHERE "c" = 1',
			'DELETE FROM t WHERE c = 1'
		);
	}

	public function testCreateTable(): void {
		$this->assertQuery(
			'CREATE TABLE "t" ( "id" INTEGER )',
			'CREATE TABLE t (id INT)'
		);

		$this->assertQuery(
			'CREATE TABLE "t" ( "id" INTEGER , "name" TEXT , "score" REAL DEFAULT 0.0 )',
			'CREATE TABLE t (id INT, name TEXT, score FLOAT DEFAULT 0.0)'
		);

		$this->assertQuery(
			'CREATE TABLE "t" ( "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT )',
			'CREATE TABLE t (id INT NOT NULL PRIMARY KEY AUTO_INCREMENT)'
		);

		$this->assertQuery(
			'CREATE TABLE IF NOT EXISTS "t" ( "id" INTEGER )',
			'CREATE TABLE IF NOT EXISTS t (id INT)'
		);
	}

	public function testDataTypes(): void {
		// Numeric data types.
		$this->assertQuery(
			'CREATE TABLE "t" ( "i1" INTEGER , "i2" INTEGER , "i3" INTEGER )',
			'CREATE TABLE t (i1 BIT, i2 BOOL, i3 BOOLEAN)'
		);

		$this->assertQuery(
			'CREATE TABLE "t" ( "i1" INTEGER , "i2" INTEGER , "i3" INTEGER , "i4" INTEGER , "i5" INTEGER , "i6" INTEGER )',
			'CREATE TABLE t (i1 TINYINT, i2 SMALLINT, i3 MEDIUMINT, i4 INT, i5 INTEGER, i6 BIGINT)'
		);

		$this->assertQuery(
			'CREATE TABLE "t" ( "f1" REAL , "f2" REAL , "f3" REAL , "f4" REAL )',
			'CREATE TABLE t (f1 FLOAT, f2 DOUBLE, f3 DOUBLE PRECISION, f4 REAL)'
		);

		$this->assertQuery(
			'CREATE TABLE "t" ( "f1" REAL , "f2" REAL , "f3" REAL , "f4" REAL )',
			'CREATE TABLE t (f1 DECIMAL, f2 DEC, f3 FIXED, f4 NUMERIC)'
		);

		// String data types.
		$this->assertQuery(
			'CREATE TABLE "t" ( "c1" TEXT , "c2" TEXT , "c3" TEXT , "c4" TEXT )',
			'CREATE TABLE t (c1 CHAR, c2 VARCHAR(255), c3 CHAR VARYING(255), c4 CHARACTER VARYING(255))'
		);

		$this->assertQuery(
			'CREATE TABLE "t" ( "c1" TEXT , "c2" TEXT )',
			'CREATE TABLE t (c1 NATIONAL CHAR, c2 NCHAR)'
		);

		$this->assertQuery(
			'CREATE TABLE "t" ( "c1" TEXT , "c2" TEXT , "c3" TEXT )',
			'CREATE TABLE t (c1 NCHAR VARCHAR(255), c2 NCHAR VARYING(255), c3 NVARCHAR(255))'
		);

		$this->assertQuery(
			'CREATE TABLE "t" ( "c1" TEXT , "c2" TEXT , "c3" TEXT )',
			'CREATE TABLE t (c1 NATIONAL VARCHAR(255), c2 NATIONAL CHAR VARYING(255), c3 NATIONAL CHARACTER VARYING(255))'
		);

		$this->assertQuery(
			'CREATE TABLE "t" ( "t1" TEXT , "t2" TEXT , "t3" TEXT , "t4" TEXT )',
			'CREATE TABLE t (t1 TINYTEXT, t2 TEXT, t3 MEDIUMTEXT, t4 LONGTEXT)'
		);

		$this->assertQuery(
			'CREATE TABLE "t" ( "e" TEXT )',
			'CREATE TABLE t (e ENUM("a", "b", "c"))'
		);

		// Date and time data types.
		$this->assertQuery(
			'CREATE TABLE "t" ( "d" TEXT , "t" TEXT , "dt" TEXT , "ts" TEXT , "y" TEXT )',
			'CREATE TABLE t (d DATE, t TIME, dt DATETIME, ts TIMESTAMP, y YEAR)'
		);

		// Binary data types.
		$this->assertQuery(
			'CREATE TABLE "t" ( "b" INTEGER , "v" BLOB )',
			'CREATE TABLE t (b BINARY, v VARBINARY(255))'
		);

		$this->assertQuery(
			'CREATE TABLE "t" ( "b1" BLOB , "b2" BLOB , "b3" BLOB , "b4" BLOB )',
			'CREATE TABLE t (b1 TINYBLOB, b2 BLOB, b3 MEDIUMBLOB, b4 LONGBLOB)'
		);

		// Spatial data types.
		$this->assertQuery(
			'CREATE TABLE "t" ( "g1" TEXT , "g2" TEXT , "g3" TEXT , "g4" TEXT )',
			'CREATE TABLE t (g1 GEOMETRY, g2 POINT, g3 LINESTRING, g4 POLYGON)'
		);

		$this->assertQuery(
			'CREATE TABLE "t" ( "g1" TEXT , "g2" TEXT , "g3" TEXT )',
			'CREATE TABLE t (g1 MULTIPOINT, g2 MULTILINESTRING, g3 MULTIPOLYGON)'
		);

		$this->assertQuery(
			'CREATE TABLE "t" ( "g1" TEXT , "g2" TEXT )',
			'CREATE TABLE t (g1 GEOMCOLLECTION, g2 GEOMETRYCOLLECTION)'
		);

		// SERIAL
		$this->assertQuery(
			'CREATE TABLE "t" ( "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE )',
			'CREATE TABLE t (id SERIAL)'
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

	private function assertQuery( $expected, string $query ): void {
		$driver = new WP_SQLite_Driver( new PDO( 'sqlite::memory:' ) );
		$driver->query( $query );

		// Check for SQLite syntax errors.
		// This ensures that invalid SQLite syntax will always fail, even if it
		// was the expected result. It prevents us from using wrong assertions.
		$error = $driver->get_error_message();
		if ( $error && preg_match( '/(SQLSTATE\[HY000].+syntax error\.)/i', $error, $matches ) ) {
			$this->fail( 'SQLite syntax error: ' . $matches[1] );
		}

		$executed_queries = array_column( $driver->executed_sqlite_queries, 'sql' );

		// Remove BEGIN and COMMIT/ROLLBACK queries.
		if ( count( $executed_queries ) > 2 ) {
			$executed_queries = array_values( array_slice( $executed_queries, 1, -1, true ) );
		}

		// Remove "select changes()" executed after some queries.
		if (
			count( $executed_queries ) > 1
			&& 'select changes()' === $executed_queries[ count( $executed_queries ) - 1 ] ) {
			array_pop( $executed_queries );
		}

		if ( ! is_array( $expected ) ) {
			$expected = array( $expected );
		}
		$this->assertSame( $expected, $executed_queries );
	}
}
