<?php

require_once __DIR__ . '/../../wp-includes/parser/class-wp-parser.php';
require_once __DIR__ . '/../../wp-includes/parser/class-wp-parser-grammar.php';
require_once __DIR__ . '/../../wp-includes/parser/class-wp-parser-node.php';
require_once __DIR__ . '/../../wp-includes/parser/class-wp-parser-token.php';
require_once __DIR__ . '/../../wp-includes/mysql/class-wp-mysql-lexer.php';
require_once __DIR__ . '/../../wp-includes/mysql/class-wp-mysql-token.php';
require_once __DIR__ . '/../../wp-includes/mysql/class-wp-mysql-parser.php';
require_once __DIR__ . '/../../wp-includes/sqlite/class-wp-sqlite-pdo-user-defined-functions.php';
require_once __DIR__ . '/../../wp-includes/sqlite-ast/class-wp-sqlite-information-schema-builder.php';
require_once __DIR__ . '/../../wp-includes/sqlite-ast/class-wp-sqlite-driver.php';

$driver = new WP_SQLite_Driver( 'wp', new PDO( 'sqlite::memory:' ) );

$query = "SELECT * FROM t1 LEFT JOIN t2 ON t1.id = t2.t1_id WHERE t1.name = 'abc'";

$driver->query( $query );

$executed_queries = $driver->get_executed_sqlite_queries();
if ( count( $executed_queries ) > 2 ) {
	// Remove BEGIN and COMMIT/ROLLBACK queries.
	$executed_queries = array_values( array_slice( $executed_queries, 1, -1, true ) );
}

foreach ( $executed_queries as $executed_query ) {
	printf( "Query:  %s\n", $executed_query['sql'] );
	printf( "Params: %s\n", json_encode( $executed_query['params'] ) );
}
