<?php

/*
 * The SQLite driver uses PDO. Enable PDO function calls:
 * phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
 */

/**
 * SQLite driver for MySQL.
 *
 * This class emulates a MySQL database server on top of an SQLite database.
 * It translates queries written in MySQL SQL dialect to an SQLite SQL dialect,
 * maintains necessary metadata, and executes the translated queries in SQLite.
 *
 * The driver requires PDO with the SQLite driver, and the PCRE engine.
 */
class WP_SQLite_Driver {
	/**
	 * The path to the MySQL SQL grammar file.
	 */
	const MYSQL_GRAMMAR_PATH = __DIR__ . '/../../wp-includes/mysql/mysql-grammar.php';

	/**
	 * The minimum required version of SQLite.
	 *
	 * Currently, we require SQLite >= 3.37.0 due to the STRICT table support:
	 *   https://www.sqlite.org/stricttables.html
	 */
	const MINIMUM_SQLITE_VERSION = '3.37.0';

	/**
	 * The default timeout in seconds for SQLite to wait for a writable lock.
	 */
	const DEFAULT_SQLITE_TIMEOUT = 10;

	/**
	 * An identifier prefix for internal database objects.
	 *
	 * @TODO: Do not allow accessing objects with this prefix.
	 */
	const RESERVED_PREFIX = '_wp_sqlite_';

	/**
	 * A map of MySQL tokens to SQLite data types.
	 *
	 * This is used to translate a MySQL data type to an SQLite data type.
	 */
	const DATA_TYPE_MAP = array(
		// Numeric data types:
		WP_MySQL_Lexer::BIT_SYMBOL                => 'INTEGER',
		WP_MySQL_Lexer::BOOL_SYMBOL               => 'INTEGER',
		WP_MySQL_Lexer::BOOLEAN_SYMBOL            => 'INTEGER',
		WP_MySQL_Lexer::TINYINT_SYMBOL            => 'INTEGER',
		WP_MySQL_Lexer::SMALLINT_SYMBOL           => 'INTEGER',
		WP_MySQL_Lexer::MEDIUMINT_SYMBOL          => 'INTEGER',
		WP_MySQL_Lexer::INT_SYMBOL                => 'INTEGER',
		WP_MySQL_Lexer::INTEGER_SYMBOL            => 'INTEGER',
		WP_MySQL_Lexer::BIGINT_SYMBOL             => 'INTEGER',
		WP_MySQL_Lexer::FLOAT_SYMBOL              => 'REAL',
		WP_MySQL_Lexer::DOUBLE_SYMBOL             => 'REAL',
		WP_MySQL_Lexer::REAL_SYMBOL               => 'REAL',
		WP_MySQL_Lexer::DECIMAL_SYMBOL            => 'REAL',
		WP_MySQL_Lexer::DEC_SYMBOL                => 'REAL',
		WP_MySQL_Lexer::FIXED_SYMBOL              => 'REAL',
		WP_MySQL_Lexer::NUMERIC_SYMBOL            => 'REAL',

		// String data types:
		WP_MySQL_Lexer::CHAR_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::VARCHAR_SYMBOL            => 'TEXT',
		WP_MySQL_Lexer::NCHAR_SYMBOL              => 'TEXT',
		WP_MySQL_Lexer::NVARCHAR_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::TINYTEXT_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::TEXT_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::MEDIUMTEXT_SYMBOL         => 'TEXT',
		WP_MySQL_Lexer::LONGTEXT_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::ENUM_SYMBOL               => 'TEXT',

		// Date and time data types:
		WP_MySQL_Lexer::DATE_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::TIME_SYMBOL               => 'TEXT',
		WP_MySQL_Lexer::DATETIME_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::TIMESTAMP_SYMBOL          => 'TEXT',
		WP_MySQL_Lexer::YEAR_SYMBOL               => 'TEXT',

		// Binary data types:
		WP_MySQL_Lexer::BINARY_SYMBOL             => 'INTEGER',
		WP_MySQL_Lexer::VARBINARY_SYMBOL          => 'BLOB',
		WP_MySQL_Lexer::TINYBLOB_SYMBOL           => 'BLOB',
		WP_MySQL_Lexer::BLOB_SYMBOL               => 'BLOB',
		WP_MySQL_Lexer::MEDIUMBLOB_SYMBOL         => 'BLOB',
		WP_MySQL_Lexer::LONGBLOB_SYMBOL           => 'BLOB',

		// Spatial data types:
		WP_MySQL_Lexer::GEOMETRY_SYMBOL           => 'TEXT',
		WP_MySQL_Lexer::POINT_SYMBOL              => 'TEXT',
		WP_MySQL_Lexer::LINESTRING_SYMBOL         => 'TEXT',
		WP_MySQL_Lexer::POLYGON_SYMBOL            => 'TEXT',
		WP_MySQL_Lexer::MULTIPOINT_SYMBOL         => 'TEXT',
		WP_MySQL_Lexer::MULTILINESTRING_SYMBOL    => 'TEXT',
		WP_MySQL_Lexer::MULTIPOLYGON_SYMBOL       => 'TEXT',
		WP_MySQL_Lexer::GEOMCOLLECTION_SYMBOL     => 'TEXT',
		WP_MySQL_Lexer::GEOMETRYCOLLECTION_SYMBOL => 'TEXT',

		// SERIAL, SET, and JSON types are handled in the translation process.
	);

	/**
	 * A map of normalized MySQL data types to SQLite data types.
	 *
	 * This is used to generate SQLite CREATE TABLE statements from the MySQL
	 * INFORMATION_SCHEMA tables. They keys are MySQL data types normalized
	 * as they appear in the INFORMATION_SCHEMA. Values are SQLite data types.
	 */
	const DATA_TYPE_STRING_MAP = array(
		// Numeric data types:
		'bit'                => 'INTEGER',
		'bool'               => 'INTEGER',
		'boolean'            => 'INTEGER',
		'tinyint'            => 'INTEGER',
		'smallint'           => 'INTEGER',
		'mediumint'          => 'INTEGER',
		'int'                => 'INTEGER',
		'integer'            => 'INTEGER',
		'bigint'             => 'INTEGER',
		'float'              => 'REAL',
		'double'             => 'REAL',
		'real'               => 'REAL',
		'decimal'            => 'REAL',
		'dec'                => 'REAL',
		'fixed'              => 'REAL',
		'numeric'            => 'REAL',

		// String data types:
		'char'               => 'TEXT',
		'varchar'            => 'TEXT',
		'nchar'              => 'TEXT',
		'nvarchar'           => 'TEXT',
		'tinytext'           => 'TEXT',
		'text'               => 'TEXT',
		'mediumtext'         => 'TEXT',
		'longtext'           => 'TEXT',
		'enum'               => 'TEXT',
		'set'                => 'TEXT',
		'json'               => 'TEXT',

		// Date and time data types:
		'date'               => 'TEXT',
		'time'               => 'TEXT',
		'datetime'           => 'TEXT',
		'timestamp'          => 'TEXT',
		'year'               => 'TEXT',

		// Binary data types:
		'binary'             => 'INTEGER',
		'varbinary'          => 'BLOB',
		'tinyblob'           => 'BLOB',
		'blob'               => 'BLOB',
		'mediumblob'         => 'BLOB',
		'longblob'           => 'BLOB',

		// Spatial data types:
		'geometry'           => 'TEXT',
		'point'              => 'TEXT',
		'linestring'         => 'TEXT',
		'polygon'            => 'TEXT',
		'multipoint'         => 'TEXT',
		'multilinestring'    => 'TEXT',
		'multipolygon'       => 'TEXT',
		'geomcollection'     => 'TEXT',
		'geometrycollection' => 'TEXT',
	);

	/**
	 * A map of MySQL to SQLite date format translation.
	 *
	 * It maps MySQL DATE_FORMAT() formats to SQLite STRFTIME() formats.
	 *
	 * For MySQL formats, see:
	 *   https://dev.mysql.com/doc/refman/5.7/en/date-and-time-functions.html#function_date-format
	 *
	 * For SQLite formats, see:
	 *   https://www.sqlite.org/lang_datefunc.html
	 *   https://strftime.org/
	 */
	const MYSQL_DATE_FORMAT_TO_SQLITE_STRFTIME_MAP = array(
		'%a' => '%D',
		'%b' => '%M',
		'%c' => '%n',
		'%D' => '%jS',
		'%d' => '%d',
		'%e' => '%j',
		'%H' => '%H',
		'%h' => '%h',
		'%I' => '%h',
		'%i' => '%M',
		'%j' => '%z',
		'%k' => '%G',
		'%l' => '%g',
		'%M' => '%F',
		'%m' => '%m',
		'%p' => '%A',
		'%r' => '%h:%i:%s %A',
		'%S' => '%s',
		'%s' => '%s',
		'%T' => '%H:%i:%s',
		'%U' => '%W',
		'%u' => '%W',
		'%V' => '%W',
		'%v' => '%W',
		'%W' => '%l',
		'%w' => '%w',
		'%X' => '%Y',
		'%x' => '%o',
		'%Y' => '%Y',
		'%y' => '%y',
	);

	/**
	 * The SQLite engine version.
	 *
	 * This is a mysqli-like property that is needed to avoid a PHP warning in
	 * the WordPress health info. The "WP_Debug_Data::get_wp_database()" method
	 * calls "$wpdb->dbh->client_info" - a mysqli-specific abstraction leak.
	 *
	 * @TODO: This should be fixed in WordPress core.
	 *
	 * See:
	 *   https://github.com/WordPress/wordpress-develop/blob/bcdca3f9925f1d3eca7b78d231837c0caf0c8c24/src/wp-admin/includes/class-wp-debug-data.php#L1579
	 *
	 * @var string
	 */
	public $client_info;

	/**
	 * A MySQL query parser grammar.
	 *
	 * @var WP_Parser_Grammar
	 */
	private static $mysql_grammar;

	/**
	 * The database name.
	 *
	 * @var string
	 */
	private $db_name;

	/**
	 * An instance of the PDO object.
	 *
	 * @var PDO
	 */
	private $pdo;

	/**
	 * A service for managing MySQL INFORMATION_SCHEMA tables in SQLite.
	 *
	 * @var WP_SQLite_Information_Schema_Builder
	 */
	private $information_schema_builder;

	/**
	 * Last executed MySQL query.
	 *
	 * @var string
	 */
	private $last_mysql_query;

	/**
	 * A list of SQLite queries executed for the last MySQL query.
	 *
	 * @var array{ sql: string, params: array }[]
	 */
	private $last_sqlite_queries = array();

	/**
	 * Results of the last emulated query.
	 *
	 * @var array|null
	 */
	private $last_result;

	/**
	 * Return value of the last emulated query.
	 *
	 * @var mixed
	 */
	private $last_return_value;

	/**
	 * Number of rows found by the last SQL_CALC_FOUND_ROW query.
	 *
	 * @var int
	 */
	private $last_sql_calc_found_rows = null;

	/**
	 * Transaction nesting level of the executed SQLite queries.
	 *
	 * @var int
	 */
	private $transaction_level = 0;

	/**
	 * The PDO fetch mode used for the emulated query.
	 *
	 * @var mixed
	 */
	private $pdo_fetch_mode;

	/**
	 * Constructor.
	 *
	 * Set up an SQLite connection and the MySQL-on-SQLite driver.
	 *
	 * @param array $options {
	 *     An array of options.
	 *
	 *     @type string      $database            Database name.
	 *                                            The name of the emulated MySQL database.
	 *     @type string|null $path                Optional. SQLite database path.
	 *                                            For in-memory database, use ':memory:'.
	 *                                            Must be set when PDO instance is not provided.
	 *     @type PDO|null    $connection          Optional. PDO instance with SQLite connection.
	 *                                            If not provided, a new PDO instance will be created.
	 *     @type int|null    $timeout             Optional. SQLite timeout in seconds.
	 *                                            The time to wait for a writable lock.
	 *     @type string|null $sqlite_journal_mode Optional. SQLite journal mode.
	 * }
	 *
	 * @throws WP_SQLite_Driver_Exception When the driver initialization fails.
	 */
	public function __construct( array $options ) {
		// Database name.
		if ( ! isset( $options['database'] ) || ! is_string( $options['database'] ) ) {
			throw $this->new_driver_exception( 'Option "database" is required.' );
		}
		$this->db_name = $options['database'];

		// Database connection.
		if ( isset( $options['connection'] ) && $options['connection'] instanceof PDO ) {
			$this->pdo = $options['connection'];
		}

		// Create a PDO connection if it is not provided.
		if ( ! $this->pdo ) {
			if ( ! isset( $options['path'] ) || ! is_string( $options['path'] ) ) {
				throw $this->new_driver_exception(
					'Option "path" is required when "connection" is not provided.'
				);
			}
			$path = $options['path'];

			try {
				$this->pdo = new PDO( 'sqlite:' . $path );
			} catch ( PDOException $e ) {
				$code = $e->getCode();
				throw $this->new_driver_exception( $e->getMessage(), is_int( $code ) ? $code : 0, $e );
			}
		}

		// Throw exceptions on error.
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		// Configure SQLite timeout.
		if ( isset( $options['timeout'] ) && is_int( $options['timeout'] ) ) {
			$timeout = $options['timeout'];
		} else {
			$timeout = self::DEFAULT_SQLITE_TIMEOUT;
		}
		$this->pdo->setAttribute( PDO::ATTR_TIMEOUT, $timeout );

		// Return all values (except null) as strings.
		$this->pdo->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, true );

		// Check the SQLite version.
		$sqlite_version = $this->get_sqlite_version();
		if ( version_compare( $sqlite_version, self::MINIMUM_SQLITE_VERSION, '<' ) ) {
			throw $this->new_driver_exception(
				sprintf(
					'The SQLite version %s is not supported. Minimum required version is %s.',
					$sqlite_version,
					self::MINIMUM_SQLITE_VERSION
				)
			);
		}

		// Load SQLite version to a property used by WordPress health info.
		$this->client_info = $sqlite_version;

		// Enable foreign keys. By default, they are off.
		$this->pdo->query( 'PRAGMA foreign_keys = ON' );

		// Configure SQLite journal mode.
		if (
			isset( $options['sqlite_journal_mode'] )
			&& in_array(
				$options['sqlite_journal_mode'],
				array( 'DELETE', 'TRUNCATE', 'PERSIST', 'MEMORY', 'WAL', 'OFF' ),
				true
			)
		) {
			$this->pdo->query( 'PRAGMA journal_mode = ' . $options['sqlite_journal_mode'] );
		}

		// Register SQLite functions.
		WP_SQLite_PDO_User_Defined_Functions::register_for( $this->pdo );

		// Load MySQL grammar.
		if ( null === self::$mysql_grammar ) {
			self::$mysql_grammar = new WP_Parser_Grammar( require self::MYSQL_GRAMMAR_PATH );
		}

		// Initialize information schema builder.
		$this->information_schema_builder = new WP_SQLite_Information_Schema_Builder(
			$this->db_name,
			array( $this, 'execute_sqlite_query' )
		);
		$this->information_schema_builder->ensure_information_schema_tables();
	}

	/**
	 * Get the PDO object.
	 *
	 * @return PDO
	 */
	public function get_pdo(): PDO {
		return $this->pdo;
	}

	/**
	 * Get the version of the SQLite engine.
	 *
	 * @return string SQLite engine version as a string.
	 */
	public function get_sqlite_version(): string {
		return $this->pdo->query( 'SELECT SQLITE_VERSION()' )->fetchColumn();
	}

	/**
	 * Get the last executed MySQL query.
	 *
	 * @return string|null
	 */
	public function get_last_mysql_query(): ?string {
		return $this->last_mysql_query;
	}

	/**
	 * Get SQLite queries executed for the last MySQL query.
	 *
	 * @return array{ sql: string, params: array }[]
	 */
	public function get_last_sqlite_queries(): array {
		return $this->last_sqlite_queries;
	}

	/**
	 * Get the auto-increment value generated for the last query.
	 *
	 * @return int|string
	 */
	public function get_insert_id() {
		$last_insert_id = $this->pdo->lastInsertId();
		if ( is_numeric( $last_insert_id ) ) {
			$last_insert_id = (int) $last_insert_id;
		}
		return $last_insert_id;
	}

	/**
	 * Translate and execute a MySQL query in SQLite.
	 *
	 * A single MySQL query can be translated into zero or more SQLite queries.
	 *
	 * @param string $query              Full SQL statement string.
	 * @param int    $fetch_mode         PDO fetch mode. Default is PDO::FETCH_OBJ.
	 * @param array  ...$fetch_mode_args Additional fetch mode arguments.
	 *
	 * @return mixed Return value, depending on the query type.
	 *
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	public function query( string $query, $fetch_mode = PDO::FETCH_OBJ, ...$fetch_mode_args ) {
		$this->flush();
		$this->pdo_fetch_mode   = $fetch_mode;
		$this->last_mysql_query = $query;

		try {
			// Parse the MySQL query.
			$lexer  = new WP_MySQL_Lexer( $query );
			$tokens = $lexer->remaining_tokens();

			$parser = new WP_MySQL_Parser( self::$mysql_grammar, $tokens );
			$ast    = $parser->parse();

			if ( null === $ast ) {
				throw $this->new_driver_exception( 'Failed to parse the MySQL query.' );
			}

			// Handle transaction commands.

			/*
			 * [GRAMMAR]
			 * beginWork: BEGIN_SYMBOL WORK_SYMBOL?
			 */
			$child = $ast->get_first_child();
			if ( $child instanceof WP_Parser_Node && 'beginWork' === $child->rule_name ) {
				$this->begin_transaction();
				return true;
			}

			if ( $child instanceof WP_Parser_Node && 'simpleStatement' === $child->rule_name ) {
				/*
				 * [GRAMMAR]
				 * transactionOrLockingStatement:
				 *   transactionStatement | savepointStatement | lockStatement | xaStatement
				 */
				$subchild = $child->get_first_child_node( 'transactionOrLockingStatement' );
				if ( null !== $subchild ) {
					$tokens = $subchild->get_descendant_tokens();
					$token1 = $tokens[0];
					$token2 = $tokens[1] ?? null;
					if (
						WP_MySQL_Lexer::START_SYMBOL === $token1->id
						&& WP_MySQL_Lexer::TRANSACTION_SYMBOL === $token2->id
					) {
						$this->begin_transaction();
						return true;
					}

					if (
						WP_MySQL_Lexer::BEGIN_SYMBOL === $token1->id
					) {
						$this->begin_transaction();
						return true;
					}

					if (
						WP_MySQL_Lexer::COMMIT_SYMBOL === $token1->id
					) {
						$this->commit();
						return true;
					}

					if (
						WP_MySQL_Lexer::ROLLBACK_SYMBOL === $token1->id
					) {
						$this->rollback();
						return true;
					}
				}
			}

			// Perform all the queries in a nested transaction.
			$this->begin_transaction();
			$this->execute_mysql_query( $ast );
			$this->commit();
			return $this->last_return_value;
		} catch ( Throwable $e ) {
			try {
				$this->rollback();
			} catch ( Throwable $rollback_exception ) {
				// Ignore rollback errors.
			}
			$code = $e->getCode();
			throw $this->new_driver_exception( $e->getMessage(), is_int( $code ) ? $code : 0, $e );
		}
	}

	/**
	 * Get results of the last query.
	 *
	 * @return mixed
	 */
	public function get_query_results() {
		return $this->last_result;
	}

	/**
	 * Get return value of the last query() function call.
	 *
	 * @return mixed
	 */
	public function get_last_return_value() {
		return $this->last_return_value;
	}

	/**
	 * Execute a query in SQLite.
	 *
	 * @param string $sql   The query to execute.
	 * @param array $params The query parameters.
	 * @throws PDOException When the query execution fails.
	 * @return PDOStatement The PDO statement object.
	 */
	public function execute_sqlite_query( string $sql, array $params = array() ): PDOStatement {
		$this->last_sqlite_queries[] = array(
			'sql'    => $sql,
			'params' => $params,
		);
		$stmt                        = $this->pdo->prepare( $sql );
		$stmt->execute( $params );
		return $stmt;
	}

	/**
	 * Begin a new transaction or nested transaction.
	 */
	public function begin_transaction(): void {
		if ( 0 === $this->transaction_level ) {
			$this->execute_sqlite_query( 'BEGIN' );
		} else {
			$this->execute_sqlite_query( 'SAVEPOINT LEVEL' . $this->transaction_level );
		}
		++$this->transaction_level;
	}

	/**
	 * Commit the current transaction or nested transaction.
	 */
	public function commit(): void {
		if ( 0 === $this->transaction_level ) {
			return;
		}

		--$this->transaction_level;
		if ( 0 === $this->transaction_level ) {
			$this->execute_sqlite_query( 'COMMIT' );
		} else {
			$this->execute_sqlite_query( 'RELEASE SAVEPOINT LEVEL' . $this->transaction_level );
		}
	}

	/**
	 * Rollback the current transaction or nested transaction.
	 */
	public function rollback(): void {
		if ( 0 === $this->transaction_level ) {
			return;
		}

		--$this->transaction_level;
		if ( 0 === $this->transaction_level ) {
			$this->execute_sqlite_query( 'ROLLBACK' );
		} else {
			$this->execute_sqlite_query( 'ROLLBACK TO SAVEPOINT LEVEL' . $this->transaction_level );
		}
	}

	/**
	 * Translate and execute a MySQL query in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "query" AST node with "simpleStatement" child.
	 * @throws WP_SQLite_Driver_Exception When the query is not supported.
	 */
	private function execute_mysql_query( WP_Parser_Node $node ): void {
		if ( 'query' !== $node->rule_name ) {
			throw $this->new_driver_exception(
				sprintf( 'Expected "query" node, got: "%s"', $node->rule_name )
			);
		}

		/*
		 * [GRAMMAR]
		 * query:
		 *   EOF
		 *   | (simpleStatement | beginWork) (SEMICOLON_SYMBOL EOF? | EOF)
		 */
		$children = $node->get_child_nodes();
		if ( count( $children ) !== 1 ) {
			throw $this->new_driver_exception(
				sprintf( 'Expected 1 child node, got: %d', count( $children ) )
			);
		}

		if ( 'simpleStatement' !== $children[0]->rule_name ) {
			throw $this->new_driver_exception(
				sprintf( 'Expected "simpleStatement" node, got: "%s"', $children[0]->rule_name )
			);
		}

		// Process the "simpleStatement" AST node.
		$node = $children[0]->get_first_child_node();
		switch ( $node->rule_name ) {
			case 'selectStatement':
				$this->execute_select_statement( $node );
				break;
			case 'insertStatement':
			case 'replaceStatement':
				$this->execute_insert_or_replace_statement( $node );
				break;
			case 'updateStatement':
				$this->execute_update_statement( $node );
				break;
			case 'deleteStatement':
				$this->execute_delete_statement( $node );
				break;
			case 'createStatement':
				$subtree = $node->get_first_child_node();
				switch ( $subtree->rule_name ) {
					case 'createTable':
						$this->execute_create_table_statement( $node );
						break;
					default:
						throw $this->new_not_supported_exception(
							sprintf(
								'statement type: "%s" > "%s"',
								$node->rule_name,
								$subtree->rule_name
							)
						);
				}
				break;
			case 'alterStatement':
				$subtree = $node->get_first_child_node();
				switch ( $subtree->rule_name ) {
					case 'alterTable':
						$this->execute_alter_table_statement( $node );
						break;
					default:
						throw $this->new_not_supported_exception(
							sprintf(
								'statement type: "%s" > "%s"',
								$node->rule_name,
								$subtree->rule_name
							)
						);
				}
				break;
			case 'dropStatement':
				$subtree = $node->get_first_child_node();
				switch ( $subtree->rule_name ) {
					case 'dropTable':
						$this->execute_drop_table_statement( $node );
						break;
					default:
						$query = $this->translate( $node );
						$this->execute_sqlite_query( $query );
						$this->set_result_from_affected_rows();
				}
				break;
			case 'setStatement':
				/*
				 * It would be lovely to support at least SET autocommit,
				 * but I don't think that is even possible with SQLite.
				 */
				$this->last_result = 0;
				break;
			case 'showStatement':
				$this->execute_show_statement( $node );
				break;
			case 'utilityStatement':
				$subtree = $node->get_first_child_node();
				switch ( $subtree->rule_name ) {
					case 'describeStatement':
						$this->execute_describe_statement( $subtree );
						break;
					default:
						throw $this->new_not_supported_exception(
							sprintf(
								'statement type: "%s" > "%s"',
								$node->rule_name,
								$subtree->rule_name
							)
						);
				}
				break;
			default:
				throw $this->new_not_supported_exception(
					sprintf( 'statement type: "%s"', $node->rule_name )
				);
		}
	}

	/**
	 * Translate and execute a MySQL SELECT statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "selectStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_select_statement( WP_Parser_Node $node ): void {
		/*
		 * [GRAMMAR]
		 * selectStatement:
		 *   queryExpression lockingClauseList?
		 *   | selectStatementWithInto
		 */

		// First, translate the query, before we modify last found rows count.
		$query = $this->translate( $node->get_first_child() );

		$has_sql_calc_found_rows = null !== $node->get_first_descendant_token(
			WP_MySQL_Lexer::SQL_CALC_FOUND_ROWS_SYMBOL
		);

		// Handle SQL_CALC_FOUND_ROWS.
		if ( true === $has_sql_calc_found_rows ) {
			// Recursively find a query expression with the first LIMIT or SELECT.
			$query_expr = $node->get_first_descendant_node( 'queryExpression' );
			while ( true ) {
				if ( $query_expr->has_child_node( 'limitClause' ) ) {
					break;
				}

				$query_expr_parens = $query_expr->get_first_child_node( 'queryExpressionParens' );
				if ( null !== $query_expr_parens ) {
					$query_expr = $query_expr_parens->get_first_child_node( 'queryExpression' );
					continue;
				}

				$query_expr_body = $query_expr->get_first_child_node( 'queryExpressionBody' );
				if ( count( $query_expr_body->get_children() ) > 1 ) {
					break;
				}

				$query_term = $query_expr_body->get_first_child_node( 'queryTerm' );
				if (
					count( $query_term->get_children() ) === 1
					&& $query_term->has_child_node( 'queryExpressionParens' )
				) {
					$query_expr = $query_term->get_first_child_node( 'queryExpressionParens' )->get_first_child_node( 'queryExpression' );
					continue;
				}

				break;
			}

			// Exclude the limit clause from the expression.
			$count_expr = new WP_Parser_Node( $query_expr->rule_id, $query_expr->rule_name );
			foreach ( $query_expr->get_children() as $child ) {
				if ( ! ( $child instanceof WP_Parser_Node && 'limitClause' === $child->rule_name ) ) {
					$count_expr->append_child( $child );
				}
			}

			// Get count of all the rows.
			$result = $this->execute_sqlite_query(
				'SELECT COUNT(*) AS cnt FROM (' . $this->translate( $count_expr ) . ')'
			);

			$this->last_sql_calc_found_rows = $result->fetchColumn();
		} else {
			$this->last_sql_calc_found_rows = null;
		}

		// Execute the query.
		$stmt = $this->execute_sqlite_query( $query );
		$this->set_results_from_fetched_data(
			$stmt->fetchAll( $this->pdo_fetch_mode )
		);
	}

	/**
	 * Translate and execute a MySQL INSERT or REPLACE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "insertStatement" or "replaceStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_insert_or_replace_statement( WP_Parser_Node $node ): void {
		$parts = array();
		foreach ( $node->get_children() as $child ) {
			if ( $child instanceof WP_MySQL_Token && WP_MySQL_Lexer::IGNORE_SYMBOL === $child->id ) {
				// Translate "UPDATE IGNORE" to "UPDATE OR IGNORE".
				$parts[] = 'OR IGNORE';
			} else {
				$parts[] = $this->translate( $child );
			}
		}
		$query = implode( ' ', $parts );
		$this->execute_sqlite_query( $query );
		$this->set_result_from_affected_rows();
	}

	/**
	 * Translate and execute a MySQL UPDATE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "updateStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_update_statement( WP_Parser_Node $node ): void {
		// @TODO: Add support for UPDATE with multiple tables and JOINs.
		//        SQLite supports them in the FROM clause.

		$has_order = $node->has_child_node( 'orderClause' );
		$has_limit = $node->has_child_node( 'simpleLimitClause' );

		/*
		 * SQLite doesn't support UPDATE with ORDER BY/LIMIT.
		 * We need to use a subquery to emulate this behavior.
		 *
		 * For instance, the following query:
		 *   UPDATE t SET c = 1 WHERE c = 2 LIMIT 1;
		 * Will be rewritten to:
		 *   UPDATE t SET c = 1 WHERE rowid IN ( SELECT rowid FROM t WHERE c = 2 LIMIT 1 );
		 */
		$where_subquery = null;
		if ( $has_order || $has_limit ) {
			$where_subquery = 'SELECT rowid FROM ' . $this->translate_sequence(
				array(
					$node->get_first_child_node( 'tableReferenceList' ),
					$node->get_first_child_node( 'whereClause' ),
					$node->get_first_child_node( 'orderClause' ),
					$node->get_first_child_node( 'simpleLimitClause' ),
				)
			);
		}

		// Iterate and translate the update statement children.
		$parts = array();
		foreach ( $node->get_children() as $child ) {
			if ( $child instanceof WP_MySQL_Token && WP_MySQL_Lexer::IGNORE_SYMBOL === $child->id ) {
				// Translate "UPDATE IGNORE" to "UPDATE OR IGNORE".
				$parts[] = 'OR IGNORE';
			} else {
				$parts[] = $this->translate( $child );
			}

			// When using a subquery, skip WHERE, ORDER BY, and LIMIT.
			if (
				null !== $where_subquery
				&& $child instanceof WP_Parser_Node
				&& 'updateList' === $child->rule_name
			) {
				// We can stop here, as the update statement grammar is:
				//   ... updateList whereClause? orderClause? simpleLimitClause?
				break;
			}
		}

		// Compose the update query.
		$query = implode( ' ', $parts );
		if ( null !== $where_subquery ) {
			$query .= ' WHERE rowid IN ( ' . $where_subquery . ' )';
		}

		$this->execute_sqlite_query( $query );
		$this->set_result_from_affected_rows();
	}

	/**
	 * Translate and execute a MySQL DELETE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "deleteStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_delete_statement( WP_Parser_Node $node ): void {
		/*
		 * Multi-table DELETE.
		 *
		 * MySQL supports multi-table DELETE statements that don't work in SQLite.
		 * These statements can have the following two flavours:
		 *  1. "DELETE t1, t2 FROM ... JOIN ... WHERE ..."
		 *  2. "DELETE FROM t1, t2 USING ... JOIN ... WHERE ..."
		 *
		 * We will rewrite such statements into a SELECT to fetch the ROWIDs of
		 * the rows to delete and then execute a DELETE statement for each table.
		 */
		$alias_ref_list = $node->get_first_child_node( 'tableAliasRefList' );
		if ( null !== $alias_ref_list ) {
			// 1. Get table aliases targeted by the DELETE statement.
			$table_aliases = array();
			foreach ( $alias_ref_list->get_child_nodes() as $alias_ref ) {
				$table_aliases[] = $this->unquote_sqlite_identifier(
					$this->translate( $alias_ref )
				);
			}

			// 2. Create an alias to table name map.
			$alias_map      = array();
			$table_ref_list = $node->get_first_child_node( 'tableReferenceList' );
			foreach ( $table_ref_list->get_descendant_nodes( 'singleTable' ) as $single_table ) {
				$alias = $this->unquote_sqlite_identifier(
					$this->translate( $single_table->get_first_child_node( 'tableAlias' ) )
				);
				$ref   = $this->unquote_sqlite_identifier(
					$this->translate( $single_table->get_first_child_node( 'tableRef' ) )
				);

				$alias_map[ $alias ] = $ref;
			}

			// 3. Compose the SELECT query to fetch ROWIDs to delete.
			$where_clause = $node->get_first_child_node( 'whereClause' );
			if ( null !== $where_clause ) {
				$where = $this->translate( $where_clause->get_first_child_node( 'expr' ) );
			}

			$select_list = array();
			foreach ( $table_aliases as $table ) {
				$select_list[] = "\"$table\".rowid AS \"{$table}_rowid\"";
			}

			$ids = $this->execute_sqlite_query(
				sprintf(
					'SELECT %s FROM %s %s',
					implode( ', ', $select_list ),
					$this->translate( $table_ref_list ),
					isset( $where ) ? "WHERE $where" : ''
				)
			)->fetchAll( PDO::FETCH_ASSOC );

			// 4. Execute DELETE statements for each table.
			$rows = 0;
			if ( count( $ids ) > 0 ) {
				foreach ( $table_aliases as $table ) {
					$this->execute_sqlite_query(
						sprintf(
							'DELETE FROM %s AS %s WHERE rowid IN ( %s )',
							$this->quote_sqlite_identifier( $alias_map[ $table ] ),
							$this->quote_sqlite_identifier( $table ),
							implode( ', ', array_column( $ids, "{$table}_rowid" ) )
						)
					);
					$this->set_result_from_affected_rows();
					$rows += $this->last_result;
				}
			}

			$this->set_result_from_affected_rows( $rows );
			return;
		}

		// @TODO: Translate DELETE with JOIN to use a subquery.

		$query = $this->translate( $node );
		$this->execute_sqlite_query( $query );
		$this->set_result_from_affected_rows();
	}

	/**
	 * Translate and execute a MySQL CREATE TABLE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "createStatement" AST node with "createTable" child.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_create_table_statement( WP_Parser_Node $node ): void {
		$subnode = $node->get_first_child_node();

		// Handle TEMPORARY and CREATE TABLE ... SELECT.
		$is_temporary = $subnode->has_child_token( WP_MySQL_Lexer::TEMPORARY_SYMBOL );
		$element_list = $subnode->get_first_child_node( 'tableElementList' );
		if ( true === $is_temporary || null === $element_list ) {
			$query = $this->translate( $node ) . ' STRICT';
			$this->execute_sqlite_query( $query );
			$this->set_result_from_affected_rows();
			return;
		}

		// Get table name.
		$table_name = $this->unquote_sqlite_identifier(
			$this->translate( $subnode->get_first_child_node( 'tableName' ) )
		);

		// Handle IF NOT EXISTS.
		if ( $subnode->has_child_node( 'ifNotExists' ) ) {
			$table_exists = $this->execute_sqlite_query(
				'SELECT 1 FROM _mysql_information_schema_tables WHERE table_schema = ? AND table_name = ?',
				array( $this->db_name, $table_name )
			)->fetchColumn();

			if ( $table_exists ) {
				$this->set_result_from_affected_rows( 0 );
				return;
			}
		}

		// Save information to information schema tables.
		$this->information_schema_builder->record_create_table( $node );

		// Generate CREATE TABLE statement from the information schema tables.
		$queries            = $this->get_sqlite_create_table_statement( $table_name );
		$create_table_query = $queries[0];
		$constraint_queries = array_slice( $queries, 1 );

		$this->execute_sqlite_query( $create_table_query );

		foreach ( $constraint_queries as $query ) {
			$this->execute_sqlite_query( $query );
		}
	}

	/**
	 * Translate and execute a MySQL ALTER TABLE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "alterStatement" AST node with "alterTable" child.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_alter_table_statement( WP_Parser_Node $node ): void {
		$table_name = $this->unquote_sqlite_identifier(
			$this->translate( $node->get_first_descendant_node( 'tableRef' ) )
		);

		// Save all column names from the original table.
		$column_names = $this->execute_sqlite_query(
			'SELECT COLUMN_NAME FROM _mysql_information_schema_columns WHERE table_schema = ? AND table_name = ?',
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_COLUMN );

		// Preserve ROWIDs.
		// This also addresses a special case when all original columns are dropped
		// and there is nothing to copy. We'll always have at least the ROWID column.
		array_unshift( $column_names, 'rowid' );

		// Track column renames and removals.
		$column_map = array_combine( $column_names, $column_names );
		foreach ( $node->get_descendant_nodes( 'alterListItem' ) as $action ) {
			$first_token = $action->get_first_child_token();

			switch ( $first_token->id ) {
				case WP_MySQL_Lexer::DROP_SYMBOL:
					$name = $this->translate( $action->get_first_child_node( 'columnInternalRef' ) );
					if ( null !== $name ) {
						$name = $this->unquote_sqlite_identifier( $name );
						unset( $column_map[ $name ] );
					}
					break;
				case WP_MySQL_Lexer::CHANGE_SYMBOL:
					$old_name = $this->unquote_sqlite_identifier(
						$this->translate( $action->get_first_child_node( 'columnInternalRef' ) )
					);
					$new_name = $this->unquote_sqlite_identifier(
						$this->translate( $action->get_first_child_node( 'identifier' ) )
					);

					$column_map[ $old_name ] = $new_name;
					break;
				case WP_MySQL_Lexer::RENAME_SYMBOL:
					$column_ref = $action->get_first_child_node( 'columnInternalRef' );
					if ( null !== $column_ref ) {
						$old_name = $this->unquote_sqlite_identifier(
							$this->translate( $column_ref )
						);
						$new_name = $this->unquote_sqlite_identifier(
							$this->translate( $action->get_first_child_node( 'identifier' ) )
						);

						$column_map[ $old_name ] = $new_name;
					}
					break;
			}
		}

		$this->information_schema_builder->record_alter_table( $node );

		/*
		 * See:
		 *   https://www.sqlite.org/lang_altertable.html#making_other_kinds_of_table_schema_changes
		 */

		// 1. If foreign key constraints are enabled, disable them.
		$pragma_foreign_keys = $this->execute_sqlite_query( 'PRAGMA foreign_keys' )->fetchColumn();
		$this->execute_sqlite_query( 'PRAGMA foreign_keys = OFF' );

		// 2. Create a new table with the new schema.
		$tmp_table_name        = self::RESERVED_PREFIX . "tmp_{$table_name}_" . uniqid();
		$quoted_table_name     = $this->quote_sqlite_identifier( $table_name );
		$quoted_tmp_table_name = $this->quote_sqlite_identifier( $tmp_table_name );
		$queries               = $this->get_sqlite_create_table_statement( $table_name, $tmp_table_name );
		$create_table_query    = $queries[0];
		$constraint_queries    = array_slice( $queries, 1 );
		$this->execute_sqlite_query( $create_table_query );

		// 3. Copy data from the original table to the new table.
		$this->execute_sqlite_query(
			sprintf(
				'INSERT INTO %s (%s) SELECT %s FROM %s',
				$quoted_tmp_table_name,
				implode(
					', ',
					array_map( array( $this, 'quote_sqlite_identifier' ), $column_map )
				),
				implode(
					', ',
					array_map( array( $this, 'quote_sqlite_identifier' ), array_keys( $column_map ) )
				),
				$quoted_table_name
			)
		);

		// 4. Drop the original table.
		$this->execute_sqlite_query( sprintf( 'DROP TABLE %s', $quoted_table_name ) );

		// 5. Rename the new table to the original table name.
		$this->execute_sqlite_query(
			sprintf(
				'ALTER TABLE %s RENAME TO %s',
				$quoted_tmp_table_name,
				$quoted_table_name
			)
		);

		// 6. Reconstruct indexes, triggers, and views.
		foreach ( $constraint_queries as $query ) {
			$this->execute_sqlite_query( $query );
		}

		// 7. If foreign key constraints were enabled, verify and enable them.
		if ( '1' === $pragma_foreign_keys ) {
			$this->execute_sqlite_query( 'PRAGMA foreign_key_check' );
			$this->execute_sqlite_query( 'PRAGMA foreign_keys = ON' );
		}

		// @TODO: Triggers and views.

		// @TODO: Consider using a "fast path" for ALTER TABLE statements that
		//        consist only of operations that SQLite's ALTER TABLE supports.
	}

	/**
	 * Translate and execute a MySQL DROP TABLE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "dropStatement" AST node with "dropTable" child.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_drop_table_statement( WP_Parser_Node $node ): void {
		$child_node = $node->get_first_child_node();

		// MySQL supports removing multiple tables in a single query DROP query.
		// In SQLite, we need to execute each DROP TABLE statement separately.
		$table_refs   = $child_node->get_first_child_node( 'tableRefList' )->get_child_nodes();
		$is_temporary = $child_node->has_child_token( WP_MySQL_Lexer::TEMPORARY_SYMBOL );
		$queries      = array();
		foreach ( $table_refs as $table_ref ) {
			$parts = array();
			foreach ( $child_node->get_children() as $child ) {
				$is_token = $child instanceof WP_MySQL_Token;

				// Skip the TEMPORARY keyword.
				if ( $is_token && WP_MySQL_Lexer::TEMPORARY_SYMBOL === $child->id ) {
					continue;
				}

				// Replace table list with the current table reference.
				if ( ! $is_token && 'tableRefList' === $child->rule_name ) {
					// Add a "temp." schema prefix for temporary tables.
					$prefix = $is_temporary ? '"temp".' : '';
					$part   = $prefix . $this->translate( $table_ref );
				} else {
					$part = $this->translate( $child );
				}

				if ( null !== $part ) {
					$parts[] = $part;
				}
			}
			$queries[] = 'DROP ' . implode( ' ', $parts );
		}

		foreach ( $queries as $query ) {
			$this->execute_sqlite_query( $query );
		}
		$this->information_schema_builder->record_drop_table( $node );
	}

	/**
	 * Translate and execute a MySQL SHOW statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "showStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_show_statement( WP_Parser_Node $node ): void {
		$tokens   = $node->get_child_tokens();
		$keyword1 = $tokens[1];
		$keyword2 = $tokens[2] ?? null;

		switch ( $keyword1->id ) {
			case WP_MySQL_Lexer::CREATE_SYMBOL:
				if ( WP_MySQL_Lexer::TABLE_SYMBOL === $keyword2->id ) {
					$table_name = $this->unquote_sqlite_identifier(
						$this->translate( $node->get_first_child_node( 'tableRef' ) )
					);

					$sql = $this->get_mysql_create_table_statement( $table_name );
					if ( null === $sql ) {
						$this->set_results_from_fetched_data( array() );
					} else {
						$this->set_results_from_fetched_data(
							array(
								(object) array(
									'Create Table' => $sql,
								),
							)
						);
					}
					return;
				}
				// Fall through to default.
			case WP_MySQL_Lexer::INDEX_SYMBOL:
			case WP_MySQL_Lexer::INDEXES_SYMBOL:
			case WP_MySQL_Lexer::KEYS_SYMBOL:
				$table_name = $this->unquote_sqlite_identifier(
					$this->translate( $node->get_first_child_node( 'tableRef' ) )
				);
				$this->execute_show_index_statement( $table_name );
				break;
			case WP_MySQL_Lexer::GRANTS_SYMBOL:
				$this->set_results_from_fetched_data(
					array(
						(object) array(
							'Grants for root@localhost' => 'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, RELOAD, SHUTDOWN, PROCESS, FILE, REFERENCES, INDEX, ALTER, SHOW DATABASES, SUPER, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, REPLICATION SLAVE, REPLICATION CLIENT, CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, CREATE USER, EVENT, TRIGGER, CREATE TABLESPACE, CREATE ROLE, DROP ROLE ON *.* TO `root`@`localhost` WITH GRANT OPTION',
						),
					)
				);
				return;
			case WP_MySQL_Lexer::TABLE_SYMBOL:
				$this->execute_show_table_status_statement( $node );
				break;
			case WP_MySQL_Lexer::TABLES_SYMBOL:
				$this->execute_show_tables_statement( $node );
				break;
			case WP_MySQL_Lexer::VARIABLES_SYMBOL:
				$this->last_result = true;
				return;
			default:
				throw $this->new_not_supported_exception(
					sprintf(
						'statement type: "%s" > "%s"',
						$node->rule_name,
						$keyword1->value
					)
				);
		}
	}

	/**
	 * Translate and execute a MySQL SHOW INDEX statement in SQLite.
	 *
	 * @param string $table_name The table name to show indexes for.
	 */
	private function execute_show_index_statement( string $table_name ): void {
		$index_info = $this->execute_sqlite_query(
			'
				SELECT
					TABLE_NAME AS `Table`,
					NON_UNIQUE AS `Non_unique`,
					INDEX_NAME AS `Key_name`,
					SEQ_IN_INDEX AS `Seq_in_index`,
					COLUMN_NAME AS `Column_name`,
					COLLATION AS `Collation`,
					CARDINALITY AS `Cardinality`,
					SUB_PART AS `Sub_part`,
					PACKED AS `Packed`,
					NULLABLE AS `Null`,
					INDEX_TYPE AS `Index_type`,
					COMMENT AS `Comment`,
					INDEX_COMMENT AS `Index_comment`,
					IS_VISIBLE AS `Visible`,
					EXPRESSION AS `Expression`
				FROM _mysql_information_schema_statistics
				WHERE table_schema = ?
				AND table_name = ?
				ORDER BY
					INDEX_NAME = "PRIMARY" DESC,
					INDEX_TYPE = "FULLTEXT" ASC,
					SEQ_IN_INDEX
			',
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_OBJ );

		$this->set_results_from_fetched_data( $index_info );
	}

	/**
	 * Translate and execute a MySQL SHOW TABLE STATUS statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "showStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_show_table_status_statement( WP_Parser_Node $node ): void {
		// FROM/IN database.
		$in_db = $node->get_first_child_node( 'inDb' );
		if ( null === $in_db ) {
			$database = $this->db_name;
		} else {
			$database = $this->unquote_sqlite_identifier(
				$this->translate( $in_db->get_first_child_node( 'identifier' ) )
			);
		}

		// LIKE and WHERE clauses.
		$like_or_where = $node->get_first_child_node( 'likeOrWhere' );
		if ( null !== $like_or_where ) {
			$condition = $this->translate_show_like_or_where_condition( $like_or_where );
		}

		// Fetch table information.
		$table_info = $this->execute_sqlite_query(
			sprintf(
				'SELECT * FROM _mysql_information_schema_tables WHERE table_schema = ? %s',
				$condition ?? ''
			),
			array( $database )
		)->fetchAll( PDO::FETCH_ASSOC );

		if ( false === $table_info ) {
			$this->set_results_from_fetched_data( array() );
		}

		// Format the results.
		$tables = array();
		foreach ( $table_info as $value ) {
			$tables[] = (object) array(
				'Name'            => $value['TABLE_NAME'],
				'Engine'          => $value['ENGINE'],
				'Version'         => $value['VERSION'],
				'Row_format'      => $value['ROW_FORMAT'],
				'Rows'            => $value['TABLE_ROWS'],
				'Avg_row_length'  => $value['AVG_ROW_LENGTH'],
				'Data_length'     => $value['DATA_LENGTH'],
				'Max_data_length' => $value['MAX_DATA_LENGTH'],
				'Index_length'    => $value['INDEX_LENGTH'],
				'Data_free'       => $value['DATA_FREE'],
				'Auto_increment'  => $value['AUTO_INCREMENT'],
				'Create_time'     => $value['CREATE_TIME'],
				'Update_time'     => $value['UPDATE_TIME'],
				'Check_time'      => $value['CHECK_TIME'],
				'Collation'       => $value['TABLE_COLLATION'],
				'Checksum'        => $value['CHECKSUM'],
				'Create_options'  => $value['CREATE_OPTIONS'],
				'Comment'         => $value['TABLE_COMMENT'],
			);
		}

		$this->set_results_from_fetched_data( $tables );
	}

	/**
	 * Translate and execute a MySQL SHOW TABLES statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "showStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_show_tables_statement( WP_Parser_Node $node ): void {
		// FROM/IN database.
		$in_db = $node->get_first_child_node( 'inDb' );
		if ( null === $in_db ) {
			$database = $this->db_name;
		} else {
			$database = $this->unquote_sqlite_identifier(
				$this->translate( $in_db->get_first_child_node( 'identifier' ) )
			);
		}

		// LIKE and WHERE clauses.
		$like_or_where = $node->get_first_child_node( 'likeOrWhere' );
		if ( null !== $like_or_where ) {
			$condition = $this->translate_show_like_or_where_condition( $like_or_where );
		}

		// Fetch table information.
		$table_info = $this->execute_sqlite_query(
			sprintf(
				'SELECT * FROM _mysql_information_schema_tables WHERE table_schema = ? %s',
				$condition ?? ''
			),
			array( $database )
		)->fetchAll( PDO::FETCH_ASSOC );

		if ( false === $table_info ) {
			$this->set_results_from_fetched_data( array() );
		}

		// Handle the FULL keyword.
		$command_type = $node->get_first_child_node( 'showCommandType' );
		$is_full      = $command_type && $command_type->has_child_token( WP_MySQL_Lexer::FULL_SYMBOL );

		// Format the results.
		$tables = array();
		foreach ( $table_info as $value ) {
			$table = array(
				"Tables_in_$database" => $value['TABLE_NAME'],
			);
			if ( true === $is_full ) {
				$table['Table_type'] = $value['TABLE_TYPE'];
			}
			$tables[] = (object) $table;
		}

		$this->set_results_from_fetched_data( $tables );
	}

	/**
	 * Translate and execute a MySQL DESCRIBE statement in SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "describeStatement" AST node.
	 * @throws WP_SQLite_Driver_Exception When the query execution fails.
	 */
	private function execute_describe_statement( WP_Parser_Node $node ): void {
		$table_name = $this->unquote_sqlite_identifier(
			$this->translate( $node->get_first_child_node( 'tableRef' ) )
		);

		$column_info = $this->execute_sqlite_query(
			'
				SELECT
					column_name AS `Field`,
					column_type AS `Type`,
					is_nullable AS `Null`,
					column_key AS `Key`,
					column_default AS `Default`,
					extra AS Extra
				FROM _mysql_information_schema_columns
				WHERE table_schema = ?
				AND table_name = ?
			',
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_OBJ );

		$this->set_results_from_fetched_data( $column_info );
	}

	/**
	 * Translate a MySQL AST node or token to an SQLite query fragment.
	 *
	 * @param  WP_Parser_Node|WP_MySQL_Token $node The AST node to translate.
	 * @return string|null                         The translated query fragment.
	 * @throws WP_SQLite_Driver_Exception          When the translation fails.
	 */
	private function translate( $node ): ?string {
		if ( null === $node ) {
			return null;
		}

		if ( $node instanceof WP_MySQL_Token ) {
			return $this->translate_token( $node );
		}

		if ( ! $node instanceof WP_Parser_Node ) {
			throw $this->new_driver_exception(
				sprintf(
					'Expected a WP_Parser_Node or WP_MySQL_Token instance, got: %s',
					gettype( $node )
				)
			);
		}

		$rule_name = $node->rule_name;
		switch ( $rule_name ) {
			case 'querySpecification':
				// Translate "HAVING ..." without "GROUP BY ..." to "GROUP BY 1 HAVING ...".
				if ( $node->has_child_node( 'havingClause' ) && ! $node->has_child_node( 'groupByClause' ) ) {
					$parts = array();
					foreach ( $node->get_children() as $child ) {
						if ( $child instanceof WP_Parser_Node && 'havingClause' === $child->rule_name ) {
							$parts[] = 'GROUP BY 1';
						}
						$part = $this->translate( $child );
						if ( null !== $part ) {
							$parts[] = $part;
						}
					}
					return implode( ' ', $parts );
				}
				return $this->translate_sequence( $node->get_children() );
			case 'qualifiedIdentifier':
			case 'dotIdentifier':
				return $this->translate_sequence( $node->get_children(), '' );
			case 'identifierKeyword':
				return '`' . $this->translate( $node->get_first_child() ) . '`';
			case 'pureIdentifier':
				return $this->translate_pure_identifier( $node );
			case 'textStringLiteral':
				return $this->translate_string_literal( $node );
			case 'dataType':
			case 'nchar':
				$child = $node->get_first_child();
				if ( $child instanceof WP_Parser_Node ) {
					return $this->translate( $child );
				}

				// Handle optional prefixes (data type is the second token):
				//  1. LONG VARCHAR, LONG CHAR(ACTER) VARYING, LONG VARBINARY.
				//  2. NATIONAL CHAR, NATIONAL VARCHAR, NATIONAL CHAR(ACTER) VARYING.
				if ( WP_MySQL_Lexer::LONG_SYMBOL === $child->id ) {
					$child = $node->get_child_tokens()[1] ?? null;
				} elseif ( WP_MySQL_Lexer::NATIONAL_SYMBOL === $child->id ) {
					$child = $node->get_child_tokens()[1] ?? null;
				}

				if ( null === $child ) {
					throw $this->new_invalid_input_exception();
				}

				$type = self::DATA_TYPE_MAP[ $child->id ] ?? null;
				if ( null !== $type ) {
					return $type;
				}

				// SERIAL is an alias for BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE.
				if ( WP_MySQL_Lexer::SERIAL_SYMBOL === $child->id ) {
					return 'INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE';
				}

				// @TODO: Handle SET and JSON.
				throw $this->new_not_supported_exception(
					sprintf( 'data type: %s', $child->value )
				);
			case 'fromClause':
				// FROM DUAL is MySQL-specific syntax that means "FROM no tables"
				// and it is equivalent to omitting the FROM clause entirely.
				if ( $node->has_child_token( WP_MySQL_Lexer::DUAL_SYMBOL ) ) {
					return null;
				}
				return $this->translate_sequence( $node->get_children() );
			case 'insertUpdateList':
				// Translate "ON DUPLICATE KEY UPDATE" to "ON CONFLICT DO UPDATE SET".
				return sprintf(
					'ON CONFLICT DO UPDATE SET %s',
					$this->translate( $node->get_first_child_node( 'updateList' ) )
				);
			case 'simpleExpr':
				return $this->translate_simple_expr( $node );
			case 'predicateOperations':
				$token = $node->get_first_child_token();
				if ( WP_MySQL_Lexer::LIKE_SYMBOL === $token->id ) {
					return $this->translate_like( $node );
				} elseif ( WP_MySQL_Lexer::REGEXP_SYMBOL === $token->id ) {
					return $this->translate_regexp_functions( $node );
				}
				return $this->translate_sequence( $node->get_children() );
			case 'runtimeFunctionCall':
				return $this->translate_runtime_function_call( $node );
			case 'functionCall':
				return $this->translate_function_call( $node );
			case 'systemVariable':
				// @TODO: Emulate some system variables, or use reasonable defaults.
				//        See: https://dev.mysql.com/doc/refman/8.4/en/server-system-variable-reference.html
				//        See: https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html

				// When we have no value, it's reasonable to use NULL.
				return 'NULL';
			case 'castType':
				// Translate "CAST(... AS BINARY)" to "CAST(... AS BLOB)".
				if ( $node->has_child_token( WP_MySQL_Lexer::BINARY_SYMBOL ) ) {
					return 'BLOB';
				}
				return $this->translate_sequence( $node->get_children() );
			case 'defaultCollation':
				// @TODO: Check and save in information schema.
				return null;
			case 'duplicateAsQueryExpression':
				// @TODO: How to handle IGNORE/REPLACE?

				// The "AS" keyword is optional in MySQL, but required in SQLite.
				return 'AS ' . $this->translate( $node->get_first_child_node() );
			case 'indexHint':
			case 'indexHintList':
				return null;
			default:
				return $this->translate_sequence( $node->get_children() );
		}
	}

	/**
	 * Translate a MySQL token to SQLite.
	 *
	 * @param  WP_MySQL_Token $token The MySQL token to translate.
	 * @return string|null           The translated value.
	 */
	private function translate_token( WP_MySQL_Token $token ): ?string {
		switch ( $token->id ) {
			case WP_MySQL_Lexer::EOF:
				return null;
			case WP_MySQL_Lexer::AUTO_INCREMENT_SYMBOL:
				return 'AUTOINCREMENT';
			case WP_MySQL_Lexer::BINARY_SYMBOL:
				/*
				 * There is no "BINARY expr" equivalent in SQLite. We look for the
				 * keyword from a higher level to respect it in particular cases
				 * (REGEXP, LIKE, etc.) and then remove it from the output here.
				 */
				return null;
			case WP_MySQL_Lexer::SQL_CALC_FOUND_ROWS_SYMBOL:
				/*
				 * The "SQL_CALC_FOUND_ROWS" keyword is implemented in the select
				 * statement translation and then removed from the output here.
				 */
				return null;
			default:
				return $token->value;
		}
	}

	/**
	 * Translate a sequence of MySQL AST nodes to SQLite.
	 *
	 * @param  array<WP_Parser_Node|WP_MySQL_Token> $nodes     The MySQL token to translate.
	 * @param  string                               $separator The separator to use between fragments.
	 * @return string|null                                     The translated value.
	 * @throws WP_SQLite_Driver_Exception                      When the translation fails.
	 */
	private function translate_sequence( array $nodes, string $separator = ' ' ): ?string {
		$parts = array();
		foreach ( $nodes as $node ) {
			if ( null === $node ) {
				continue;
			}

			$translated = $this->translate( $node );
			if ( null === $translated ) {
				continue;
			}
			$parts[] = $translated;
		}
		if ( 0 === count( $parts ) ) {
			return null;
		}
		return implode( $separator, $parts );
	}

	/**
	 * Translate a MySQL string literal to SQLite.
	 *
	 * @param  WP_Parser_Node $node The "textStringLiteral" AST node.
	 * @return string               The translated value.
	 */
	private function translate_string_literal( WP_Parser_Node $node ): string {
		$token = $node->get_first_child_token();

		/*
		 * 1. Remove bounding quotes.
		 */
		$quote = $token->value[0];
		$value = substr( $token->value, 1, -1 );

		/*
		 * 2. Normalize escaping of "%" and "_" characters.
		 *
		 * MySQL has unusual handling for "\%" and "\_" in all string literals.
		 * While other sequences follow the C-style escaping ("\?" is "?", etc.),
		 * "\%" resolves to "\%" and "\_" resolves to "\_" (unlike in C strings).
		 *
		 * This means that "\%" behaves like "\\%", and "\_" behaves like "\\_".
		 * To preserve this behavior, we need to add a second backslash in cases
		 * where only one is used. To do so correctly, we need to:
		 *
		 *  1. Skip all double backslash patterns (as "\\" resolves to "\").
		 *  2. Add an extra backslash when "\%" or "\_" follows right after.
		 *
		 * This may be related to: https://bugs.mysql.com/bug.php?id=84118
		 */
		$value = preg_replace( '/(^|[^\\\\](?:\\\\{2}))*(\\\\[%_])/', '$1\\\\$2', $value );

		/*
		 * 3. Unescape quotes within the string.
		 */
		$value = str_replace( $quote . $quote, $quote, $value );

		/*
		 * 4. Unescape C-style escape sequences.
		 *
		 * MySQL string literals are represented using C-style encoded strings,
		 * but SQLite doesn't support such escaping.
		 *
		 * @TODO: Handle NO_BACKSLASH_ESCAPES SQL mode.
		 */
		$value = stripcslashes( $value );

		/*
		 * 5. Translate datetime literals.
		 *
		 * Process only strings that could possibly represent a datetime
		 * literal ("YYYY-MM-DDTHH:MM:SS", "YYYY-MM-DDTHH:MM:SSZ", etc.).
		 */
		if ( strlen( $value ) >= 19 && is_numeric( $value[0] ) ) {
			$value = $this->translate_datetime_literal( $value );
		}

		/*
		 * 6. Handle null characters.
		 *
		 * SQLite doesn't fully support null characters (\u0000) in strings.
		 * However, it can store them and read them, with some limitations.
		 *
		 * In PHP, null bytes are often produced by the serialize() function.
		 * Removing them would damage the serialized data.
		 *
		 * There is no way to store null bytes using a string literal, so we
		 * need to split the string and concatenate null bytes with its parts.
		 * This will convert literals will null bytes to expressions.
		 *
		 * Alternatively, we could replace string literals with parameters and
		 * pass them using prepared statements. However, that's not universally
		 * applicable for all string literals (e.g., in default column values).
		 *
		 * See:
		 *   https://www.sqlite.org/nulinstr.html
		 */
		$parts = array();
		foreach ( explode( "\0", $value ) as $segment ) {
			// Escape and quote each segment.
			$parts[] = "'" . str_replace( "'", "''", $segment ) . "'";
		}
		if ( count( $parts ) > 1 ) {
			return '(' . implode( ' || CHAR(0) || ', $parts ) . ')';
		}
		return $parts[0];
	}

	/**
	 * Translate a MySQL pure identifier to SQLite.
	 *
	 * @param  WP_Parser_Node $node The "pureIdentifier" AST node.
	 * @return string               The translated value.
	 */
	private function translate_pure_identifier( WP_Parser_Node $node ): string {
		$token = $node->get_first_child_token();

		if ( WP_MySQL_Lexer::DOUBLE_QUOTED_TEXT === $token->id ) {
			$value = substr( $token->value, 1, -1 );
			$value = str_replace( '""', '"', $value );
		} elseif ( WP_MySQL_Lexer::BACK_TICK_QUOTED_ID === $token->id ) {
			$value = substr( $token->value, 1, -1 );
			$value = str_replace( '``', '`', $value );
		} else {
			$value = $token->value;
		}

		return '`' . str_replace( '`', '``', $value ) . '`';
	}

	/**
	 * Translate a MySQL simple expression to SQLite.
	 *
	 * @param WP_Parser_Node $node        The "simpleExpr" AST node.
	 * @return string                     The translated value.
	 * @throws WP_SQLite_Driver_Exception When the translation fails.
	 */
	private function translate_simple_expr( WP_Parser_Node $node ): string {
		$token = $node->get_first_child_token();

		// Translate "VALUES(col)" to "excluded.col" in ON DUPLICATE KEY UPDATE.
		if ( null !== $token && WP_MySQL_Lexer::VALUES_SYMBOL === $token->id ) {
			return sprintf(
				'`excluded`.%s',
				$this->translate( $node->get_first_child_node( 'simpleIdentifier' ) )
			);
		}

		return $this->translate_sequence( $node->get_children() );
	}

	/**
	 * Translate a MySQL LIKE expression to SQLite.
	 *
	 * @param WP_Parser_Node $node        The "predicateOperations" AST node.
	 * @return string                     The translated value.
	 * @throws WP_SQLite_Driver_Exception When the translation fails.
	 */
	private function translate_like( WP_Parser_Node $node ): string {
		$tokens    = $node->get_descendant_tokens();
		$is_binary = isset( $tokens[1] ) && WP_MySQL_Lexer::BINARY_SYMBOL === $tokens[1]->id;

		if ( true === $is_binary ) {
			$children = $node->get_children();
			return sprintf(
				'GLOB _helper_like_to_glob_pattern(%s)',
				$this->translate( $children[1] )
			);
		}

		/*
		 * @TODO: Implement the ESCAPE '...' clause.
		 */

		/*
		 * @TODO: Implement more correct LIKE behavior.
		 *
		 * While SQLite supports the LIKE operator, it seems to differ from the
		 * MySQL behavior in some ways:
		 *
		 *  1. In SQLite, LIKE is case-insensitive only for ASCII characters
		 *     ('a' LIKE 'A' is TRUE but 'æ' LIKE 'Æ' is FALSE)
		 *  2. In MySQL, LIKE interprets some escape sequences. See the contents
		 *     of the "_helper_like_to_glob_pattern" function.
		 *
		 * We'll probably need to overload the like() function:
		 *   https://www.sqlite.org/lang_corefunc.html#like
		 */
		return $this->translate_sequence( $node->get_children() ) . " ESCAPE '\\'";
	}

	/**
	 * Translate MySQL REGEXP expression to SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "predicateOperations" AST node.
	 * @return string                     The translated value.
	 * @throws WP_SQLite_Driver_Exception When the translation fails.
	 */
	private function translate_regexp_functions( WP_Parser_Node $node ): string {
		$tokens    = $node->get_descendant_tokens();
		$is_binary = isset( $tokens[1] ) && WP_MySQL_Lexer::BINARY_SYMBOL === $tokens[1]->id;

		/*
		 * If the query says REGEXP BINARY, the comparison is byte-by-byte
		 * and letter casing matters – lowercase and uppercase letters are
		 * represented using different byte codes.
		 *
		 * The REGEXP function can't be easily made to accept two
		 * parameters, so we'll have to use a hack to get around this.
		 *
		 * If the first character of the pattern is a null byte, we'll
		 * remove it and make the comparison case-sensitive. This should
		 * be reasonably safe since PHP does not allow null bytes in
		 * regular expressions anyway.
		 */
		if ( true === $is_binary ) {
			return 'REGEXP CHAR(0) || ' . $this->translate( $node->get_first_child_node() );
		}
		return 'REGEXP ' . $this->translate( $node->get_first_child_node() );
	}

	/**
	 * Translate a MySQL runtime function call to SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "runtimeFunctionCall" AST node.
	 * @return string                     The translated value.
	 * @throws WP_SQLite_Driver_Exception When the translation fails.
	 */
	private function translate_runtime_function_call( WP_Parser_Node $node ): string {
		$child = $node->get_first_child();
		if ( $child instanceof WP_Parser_Node ) {
			return $this->translate( $child );
		}

		switch ( $child->id ) {
			case WP_MySQL_Lexer::CURRENT_TIMESTAMP_SYMBOL:
			case WP_MySQL_Lexer::NOW_SYMBOL:
				/*
				 * 1) SQLite doesn't support CURRENT_TIMESTAMP() with parentheses.
				 * 2) In MySQL, CURRENT_TIMESTAMP and CURRENT_TIMESTAMP() are an
				 *    alias of NOW(). In SQLite, there is no NOW() function.
				 */
				return 'CURRENT_TIMESTAMP';
			case WP_MySQL_Lexer::DATE_ADD_SYMBOL:
			case WP_MySQL_Lexer::DATE_SUB_SYMBOL:
				$nodes = $node->get_child_nodes();
				$value = $this->translate( $nodes[1] );
				$unit  = $this->translate( $nodes[2] );
				if ( 'WEEK' === $unit ) {
					$unit  = 'DAY';
					$value = 7 * $value;
				}
				return sprintf(
					"DATETIME(%s, '%s' || %s || ' %s')",
					$this->translate( $nodes[0] ),
					WP_MySQL_Lexer::DATE_SUB_SYMBOL === $child->id ? '-' : '+',
					$value,
					$unit
				);
			case WP_MySQL_Lexer::LEFT_SYMBOL:
				$nodes = $node->get_child_nodes();
				return sprintf(
					'SUBSTRING(%s, 1, %s)',
					$this->translate( $nodes[0] ),
					$this->translate( $nodes[1] )
				);
			default:
				return $this->translate_sequence( $node->get_children() );
		}
	}

	/**
	 * Translate a MySQL function call to SQLite.
	 *
	 * @param  WP_Parser_Node $node       The "functionCall" AST node.
	 * @return string                     The translated value.
	 * @throws WP_SQLite_Driver_Exception When the translation fails.
	 */
	private function translate_function_call( WP_Parser_Node $node ): string {
		$nodes = $node->get_child_nodes();
		$name  = strtoupper(
			$this->unquote_sqlite_identifier( $this->translate( $nodes[0] ) )
		);

		$args = array();
		if ( isset( $nodes[1] ) ) {
			foreach ( $nodes[1]->get_child_nodes() as $child ) {
				$args[] = $this->translate( $child );
			}
		}

		switch ( $name ) {
			case 'DATE_FORMAT':
				list ( $date, $mysql_format ) = $args;

				$format = strtr( $mysql_format, self::MYSQL_DATE_FORMAT_TO_SQLITE_STRFTIME_MAP );
				if ( ! $format ) {
					throw $this->new_driver_exception(
						sprintf(
							'Could not translate a DATE_FORMAT() format to STRFTIME format (%s)',
							$mysql_format
						)
					);
				}

				/*
				 * MySQL supports comparing strings and floats, e.g.
				 *
				 * > SELECT '00.42' = 0.4200
				 * 1
				 *
				 * SQLite does not support that. At the same time,
				 * WordPress likes to filter dates by comparing numeric
				 * outputs of DATE_FORMAT() to floats, e.g.:
				 *
				 *     -- Filter by hour and minutes
				 *     DATE_FORMAT(
				 *         STR_TO_DATE('2014-10-21 00:42:29', '%Y-%m-%d %H:%i:%s'),
				 *         '%H.%i'
				 *     ) = 0.4200;
				 *
				 * Let's cast the STRFTIME() output to a float if
				 * the date format is typically used for string
				 * to float comparisons.
				 *
				 * In the future, let's update WordPress to avoid comparing
				 * strings and floats.
				 */
				$cast_to_float = "'%H.%i'" === $mysql_format;
				if ( true === $cast_to_float ) {
					return sprintf( 'CAST(STRFTIME(%s, %s) AS FLOAT)', $format, $date );
				}
				return sprintf( 'STRFTIME(%s, %s)', $format, $date );
			case 'CHAR_LENGTH':
				// @TODO LENGTH and CHAR_LENGTH aren't always the same in MySQL for utf8 characters.
				return 'LENGTH(' . $args[0] . ')';
			case 'CONCAT':
				return '(' . implode( ' || ', $args ) . ')';
			case 'FOUND_ROWS':
				// @TODO: The following implementation with an alias assumes
				//        that the function is used in the SELECT field list.
				//        For compatibility with more complex use cases, it may
				//        be better to register it as a custom SQLite function.
				$found_rows = $this->last_sql_calc_found_rows;
				if ( null === $found_rows && is_array( $this->last_result ) ) {
					$found_rows = count( $this->last_result );
				}
				return sprintf( "(SELECT %d) AS 'FOUND_ROWS()'", $found_rows );
			default:
				return $this->translate_sequence( $node->get_children() );
		}
	}

	/**
	 * Translate a MySQL datetime literal to SQLite.
	 *
	 * @param  string $value The MySQL datetime literal.
	 * @return string        The translated value.
	 */
	private function translate_datetime_literal( string $value ): string {
		/*
		 * The code below converts the date format to one preferred by SQLite.
		 *
		 * MySQL accepts ISO 8601 date strings:        'YYYY-MM-DDTHH:MM:SSZ'
		 * SQLite prefers a slightly different format: 'YYYY-MM-DD HH:MM:SS'
		 *
		 * SQLite date and time functions can understand the ISO 8601 notation, but
		 * lookups don't. To keep the lookups working, we need to store all dates
		 * in UTC without the "T" and "Z" characters.
		 *
		 * Caveat: It will adjust every string that matches the pattern, not just dates.
		 *
		 * In theory, we could only adjust semantic dates, e.g. the data inserted
		 * to a date column or compared against a date column.
		 *
		 * In practice, this is hard because dates are just text – SQLite has no separate
		 * datetime field. We'd need to cache the MySQL data type from the original
		 * CREATE TABLE query and then keep refreshing the cache after each ALTER TABLE query.
		 *
		 * That's a lot of complexity that's perhaps not worth it. Let's just convert
		 * everything for now. The regexp assumes "Z" is always at the end of the string,
		 * which is true in the unit test suite, but there could also be a timezone offset
		 * like "+00:00" or "+01:00". We could add support for that later if needed.
		 */
		if ( 1 === preg_match( '/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})Z$/', $value, $matches ) ) {
			$value = $matches[1] . ' ' . $matches[2];
		}

		/*
		 * Mimic MySQL's behavior and truncate invalid dates.
		 *
		 * "2020-12-41 14:15:27" becomes "0000-00-00 00:00:00"
		 *
		 * WARNING: We have no idea whether the truncated value should
		 * be treated as a date in the first place.
		 * In SQLite dates are just strings. This could be a perfectly
		 * valid string that just happens to contain a date-like value.
		 *
		 * At the same time, WordPress seems to rely on MySQL's behavior
		 * and even tests for it in Tests_Post_wpInsertPost::test_insert_empty_post_date.
		 * Let's truncate the dates for now.
		 *
		 * In the future, let's update WordPress to do its own date validation
		 * and stop relying on this MySQL feature,
		 */
		if ( 1 === preg_match( '/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})$/', $value, $matches ) ) {
			/*
			 * Calling strtotime("0000-00-00 00:00:00") in 32-bit environments triggers
			 * an "out of integer range" warning – let's avoid that call for the popular
			 * case of "zero" dates.
			 */
			if ( '0000-00-00 00:00:00' !== $value && false === strtotime( $value ) ) {
				$value = '0000-00-00 00:00:00';
			}
		}
		return $value;
	}

	/**
	 * Translate a MySQL SHOW LIKE ... or SHOW WHERE ... condition to SQLite.
	 *
	 * @param  WP_Parser_Node $like_or_where The "likeOrWhere" AST node.
	 * @return string                        The translated value.
	 * @throws WP_SQLite_Driver_Exception    When the translation fails.
	 */
	private function translate_show_like_or_where_condition( WP_Parser_Node $like_or_where ): string {
		$like_clause = $like_or_where->get_first_child_node( 'likeClause' );
		if ( null !== $like_clause ) {
			$value = $this->translate(
				$like_clause->get_first_child_node( 'textStringLiteral' )
			);
			return sprintf( "AND table_name LIKE %s ESCAPE '\\'", $value );
		}

		$where_clause = $like_or_where->get_first_child_node( 'whereClause' );
		if ( null !== $where_clause ) {
			$value = $this->translate(
				$where_clause->get_first_child_node( 'expr' )
			);
			return sprintf( 'AND %s', $value );
		}

		return '';
	}


	/**
	 * Generate a SQLite CREATE TABLE statement from information schema data.
	 *
	 * @param  string      $table_name     The name of the table to create.
	 * @param  string|null $new_table_name Override the original table name for ALTER TABLE emulation.
	 * @return string[]                    Queries to create the table, indexes, and constraints.
	 * @throws WP_SQLite_Driver_Exception  When the table information is missing.
	 */
	private function get_sqlite_create_table_statement( string $table_name, ?string $new_table_name = null ): array {
		// 1. Get table info.
		$table_info = $this->execute_sqlite_query(
			"
				SELECT *
				FROM _mysql_information_schema_tables
				WHERE table_type = 'BASE TABLE'
				AND table_schema = ?
				AND table_name = ?
			",
			array( $this->db_name, $table_name )
		)->fetch( PDO::FETCH_ASSOC );

		if ( false === $table_info ) {
			throw $this->new_driver_exception(
				sprintf( 'Table "%s" not found in information schema', $table_name )
			);
		}

		// 2. Get column info.
		$column_info = $this->execute_sqlite_query(
			'SELECT * FROM _mysql_information_schema_columns WHERE table_schema = ? AND table_name = ?',
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		// 3. Get index info, grouped by index name.
		$constraint_info = $this->execute_sqlite_query(
			'SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = ? AND table_name = ?',
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		$grouped_constraints = array();
		foreach ( $constraint_info as $constraint ) {
			$name                                 = $constraint['INDEX_NAME'];
			$seq                                  = $constraint['SEQ_IN_INDEX'];
			$grouped_constraints[ $name ][ $seq ] = $constraint;
		}

		// 4. Generate CREATE TABLE statement columns.
		$rows              = array();
		$on_update_queries = array();
		$has_autoincrement = false;
		foreach ( $column_info as $column ) {
			$query  = '  ';
			$query .= $this->quote_sqlite_identifier( $column['COLUMN_NAME'] );

			$type = self::DATA_TYPE_STRING_MAP[ $column['DATA_TYPE'] ];

			/*
			 * In SQLite, there is a PRIMARY KEY quirk for backward compatibility.
			 * This applies to ROWID tables and single-column primary keys only:
			 *  1. "INTEGER PRIMARY KEY" creates an alias of ROWID.
			 *  2. "INT PRIMARY KEY" will not alias of ROWID.
			 *
			 * Therefore, we want to:
			 *  1. Use "INT PRIMARY KEY" when we have a single-column integer
			 *     PRIMARY KEY without AUTOINCREMENT (to avoid the ROWID alias).
			 *  2. Use "INTEGER PRIMARY KEY" otherwise.
			 *
			 * See:
			 *   - https://www.sqlite.org/autoinc.html
			 *   - https://www.sqlite.org/lang_createtable.html
			 */
			if (
				'INTEGER' === $type
				&& 'PRI' === $column['COLUMN_KEY']
				&& 'auto_increment' !== $column['EXTRA']
				&& count( $grouped_constraints['PRIMARY'] ) === 1
			) {
				$type = 'INT';
			}

			$query .= ' ' . $type;

			// In MySQL, text fields are case-insensitive by default.
			// COLLATE NOCASE emulates the same behavior in SQLite.
			// @TODO: Respect the actual column and index collation.
			if ( 'TEXT' === $type ) {
				$query .= ' COLLATE NOCASE';
			}
			if ( 'NO' === $column['IS_NULLABLE'] ) {
				$query .= ' NOT NULL';
			}
			if ( 'auto_increment' === $column['EXTRA'] ) {
				$has_autoincrement = true;
				$query            .= ' PRIMARY KEY AUTOINCREMENT';
			}
			if ( null !== $column['COLUMN_DEFAULT'] ) {
				// @TODO: Handle defaults with expression values (DEFAULT_GENERATED).

				// Handle DEFAULT CURRENT_TIMESTAMP. This works only with timestamp
				// and datetime columns. For other column types, it's just a string.
				if (
					'CURRENT_TIMESTAMP' === $column['COLUMN_DEFAULT']
					&& ( 'timestamp' === $column['DATA_TYPE'] || 'datetime' === $column['DATA_TYPE'] )
				) {
					$query .= ' DEFAULT CURRENT_TIMESTAMP';
				} else {
					$query .= ' DEFAULT ' . $this->pdo->quote( $column['COLUMN_DEFAULT'] );
				}
			}
			$rows[] = $query;

			if ( 'on update CURRENT_TIMESTAMP' === $column['EXTRA'] ) {
				$on_update_queries[] = $this->get_column_on_update_trigger_query(
					$table_name,
					$column['COLUMN_NAME']
				);
			}
		}

		// 5. Generate CREATE TABLE statement constraints, collect indexes.
		$create_index_queries = array();
		foreach ( $grouped_constraints as $constraint ) {
			ksort( $constraint );
			$info = $constraint[1];

			if ( 'PRIMARY' === $info['INDEX_NAME'] ) {
				if ( $has_autoincrement ) {
					continue;
				}
				$query  = '  PRIMARY KEY (';
				$query .= implode(
					', ',
					array_map(
						function ( $column ) {
							return $this->quote_sqlite_identifier( $column['COLUMN_NAME'] );
						},
						$constraint
					)
				);
				$query .= ')';
				$rows[] = $query;
			} else {
				$is_unique = '0' === $info['NON_UNIQUE'];

				// Prefix the original index name with the table name.
				// This is to avoid conflicting index names in SQLite.
				$index_name = $this->quote_sqlite_identifier(
					$table_name . '__' . $info['INDEX_NAME']
				);

				$query  = sprintf(
					'CREATE %sINDEX %s ON %s (',
					$is_unique ? 'UNIQUE ' : '',
					$index_name,
					$this->quote_sqlite_identifier( $table_name )
				);
				$query .= implode(
					', ',
					array_map(
						function ( $column ) {
							return $this->quote_sqlite_identifier( $column['COLUMN_NAME'] );
						},
						$constraint
					)
				);
				$query .= ')';

				$create_index_queries[] = $query;
			}
		}

		// 6. Compose the CREATE TABLE statement.
		$create_table_query  = sprintf(
			"CREATE TABLE %s (\n",
			$this->quote_sqlite_identifier( $new_table_name ?? $table_name )
		);
		$create_table_query .= implode( ",\n", $rows );
		$create_table_query .= "\n) STRICT";
		return array_merge( array( $create_table_query ), $create_index_queries, $on_update_queries );
	}

	/**
	 * Generate a MySQL CREATE TABLE statement from information schema data.
	 *
	 * @param  string $table_name The name of the table to create.
	 * @return string             The CREATE TABLE statement.
	 */
	private function get_mysql_create_table_statement( string $table_name ): ?string {
		// 1. Get table info.
		$table_info = $this->execute_sqlite_query(
			"
				SELECT *
				FROM _mysql_information_schema_tables
				WHERE table_type = 'BASE TABLE'
				AND table_schema = ?
				AND table_name = ?
			",
			array( $this->db_name, $table_name )
		)->fetch( PDO::FETCH_ASSOC );

		if ( false === $table_info ) {
			return null;
		}

		// 2. Get column info.
		$column_info = $this->execute_sqlite_query(
			'SELECT * FROM _mysql_information_schema_columns WHERE table_schema = ? AND table_name = ?',
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		// 3. Get index info, grouped by index name.
		$constraint_info = $this->execute_sqlite_query(
			'SELECT * FROM _mysql_information_schema_statistics WHERE table_schema = ? AND table_name = ?',
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_ASSOC );

		$grouped_constraints = array();
		foreach ( $constraint_info as $constraint ) {
			$name                                 = $constraint['INDEX_NAME'];
			$seq                                  = $constraint['SEQ_IN_INDEX'];
			$grouped_constraints[ $name ][ $seq ] = $constraint;
		}

		// 4. Generate CREATE TABLE statement columns.
		$rows = array();
		foreach ( $column_info as $column ) {
			$sql  = '  ';
			$sql .= $this->quote_mysql_identifier( $column['COLUMN_NAME'] );
			$sql .= ' ' . $column['COLUMN_TYPE'];
			if ( 'NO' === $column['IS_NULLABLE'] ) {
				$sql .= ' NOT NULL';
			} elseif ( 'timestamp' === $column['COLUMN_TYPE'] ) {
				// Nullable "timestamp" columns dump NULL explicitly.
				$sql .= ' NULL';
			}
			if ( 'auto_increment' === $column['EXTRA'] ) {
				$sql .= ' AUTO_INCREMENT';
			}

			// Handle DEFAULT CURRENT_TIMESTAMP. This works only with timestamp
			// and datetime columns. For other column types, it's just a string.
			if (
				'CURRENT_TIMESTAMP' === $column['COLUMN_DEFAULT']
				&& ( 'timestamp' === $column['DATA_TYPE'] || 'datetime' === $column['DATA_TYPE'] )
			) {
				$sql .= ' DEFAULT CURRENT_TIMESTAMP';
			} elseif ( null !== $column['COLUMN_DEFAULT'] ) {
				$sql .= ' DEFAULT ' . $this->pdo->quote( $column['COLUMN_DEFAULT'] );
			} elseif ( 'YES' === $column['IS_NULLABLE'] ) {
				$sql .= ' DEFAULT NULL';
			}

			// Handle ON UPDATE CURRENT_TIMESTAMP.
			if ( str_contains( $column['EXTRA'], 'on update CURRENT_TIMESTAMP' ) ) {
				$sql .= ' ON UPDATE CURRENT_TIMESTAMP';
			}

			$rows[] = $sql;
		}

		// 4. Generate CREATE TABLE statement constraints, collect indexes.
		foreach ( $grouped_constraints as $constraint ) {
			ksort( $constraint );
			$info = $constraint[1];

			if ( 'PRIMARY' === $info['INDEX_NAME'] ) {
				$sql    = '  PRIMARY KEY (';
				$sql   .= implode(
					', ',
					array_map(
						function ( $column ) {
							return $this->quote_mysql_identifier( $column['COLUMN_NAME'] );
						},
						$constraint
					)
				);
				$sql   .= ')';
				$rows[] = $sql;
			} else {
				$is_unique = '0' === $info['NON_UNIQUE'];

				$sql  = sprintf( '  %sKEY ', $is_unique ? 'UNIQUE ' : '' );
				$sql .= $this->quote_mysql_identifier( $info['INDEX_NAME'] );
				$sql .= ' (';
				$sql .= implode(
					', ',
					array_map(
						function ( $column ) {
							return $this->quote_mysql_identifier( $column['COLUMN_NAME'] );
						},
						$constraint
					)
				);
				$sql .= ')';

				$rows[] = $sql;
			}
		}

		// 5. Compose the CREATE TABLE statement.
		$collation = $table_info['TABLE_COLLATION'];
		$charset   = substr( $collation, 0, strpos( $collation, '_' ) );

		$sql  = sprintf( "CREATE TABLE %s (\n", $this->quote_mysql_identifier( $table_name ) );
		$sql .= implode( ",\n", $rows );
		$sql .= "\n)";
		$sql .= sprintf( ' ENGINE=%s', $table_info['ENGINE'] );
		$sql .= sprintf( ' DEFAULT CHARSET=%s', $charset );
		$sql .= sprintf( ' COLLATE=%s', $collation );
		return $sql;
	}


	/**
	 * Get an SQLite query to emulate MySQL "ON UPDATE CURRENT_TIMESTAMP".
	 *
	 * In SQLite, "ON UPDATE CURRENT_TIMESTAMP" is not supported. We need to
	 * create a trigger to emulate this behavior.
	 *
	 * @param string $table  The table name.
	 * @param string $column The column name.
	 */
	private function get_column_on_update_trigger_query( string $table, string $column ): string {
		// The trigger wouldn't work for virtual and "WITHOUT ROWID" tables,
		// but currently that can't happen as we're not creating such tables.
		// See: https://www.sqlite.org/rowidtable.html
		$trigger_name = self::RESERVED_PREFIX . "{$table}_{$column}_on_update";
		return "
			CREATE TRIGGER \"$trigger_name\"
			AFTER UPDATE ON \"$table\"
			FOR EACH ROW
			BEGIN
			  UPDATE \"$table\" SET \"$column\" = CURRENT_TIMESTAMP WHERE rowid = NEW.rowid;
			END
		";
	}

	/**
	 * Unquote a quoted SQLite identifier.
	 *
	 * Remove bounding quotes and replace escaped quotes with their values.
	 *
	 * @param  string $quoted_identifier The quoted identifier value.
	 * @return string                    The unquoted identifier value.
	 */
	private function unquote_sqlite_identifier( string $quoted_identifier ): string {
		$first_byte = $quoted_identifier[0] ?? null;
		if ( '"' === $first_byte || '`' === $first_byte ) {
			$unquoted = substr( $quoted_identifier, 1, -1 );
		} else {
			$unquoted = $quoted_identifier;
		}
		return str_replace( $first_byte . $first_byte, $first_byte, $unquoted );
	}

	/**
	 * Quote an SQLite identifier.
	 *
	 * Wrap the identifier in backticks and escape backtick values within.
	 *
	 * @param  string $unquoted_identifier The unquoted identifier value.
	 * @return string                      The quoted identifier value.
	 */
	private function quote_sqlite_identifier( string $unquoted_identifier ): string {
		return '`' . str_replace( '`', '``', $unquoted_identifier ) . '`';
	}


	/**
	 * Quote a MySQL identifier.
	 *
	 * Wrap the identifier in backticks and escape backtick values within.
	 *
	 * @param  string $unquoted_identifier The unquoted identifier value.
	 * @return string                      The quoted identifier value.
	 */
	private function quote_mysql_identifier( string $unquoted_identifier ): string {
		return '`' . str_replace( '`', '``', $unquoted_identifier ) . '`';
	}

	/**
	 * Clear the state of the driver.
	 */
	private function flush(): void {
		$this->last_mysql_query    = '';
		$this->last_sqlite_queries = array();
		$this->last_result         = null;
		$this->last_return_value   = null;
	}

	/**
	 * Set results of a query() call using fetched data.
	 *
	 * @param array $data The data to set.
	 */
	private function set_results_from_fetched_data( array $data ): void {
		$this->last_result       = $data;
		$this->last_return_value = $this->last_result;
	}

	/**
	 * Set results of a query() call using the number of affected rows.
	 *
	 * @param int|null $override Override the affected rows.
	 */
	private function set_result_from_affected_rows( int $override = null ): void {
		/*
		 * SELECT CHANGES() is a workaround for the fact that $stmt->rowCount()
		 * returns "0" (zero) with the SQLite driver at all times.
		 * See: https://www.php.net/manual/en/pdostatement.rowcount.php
		 */
		if ( null === $override ) {
			$affected_rows = (int) $this->execute_sqlite_query( 'SELECT CHANGES()' )->fetch()[0];
		} else {
			$affected_rows = $override;
		}
		$this->last_result       = $affected_rows;
		$this->last_return_value = $affected_rows;
	}

	/**
	 * Create a new SQLite driver exception.
	 *
	 * @param string         $message  The exception message.
	 * @param int            $code     The exception code.
	 * @param Throwable|null $previous The previous exception.
	 * @return WP_SQLite_Driver_Exception
	 */
	private function new_driver_exception(
		string $message,
		int $code = 0,
		Throwable $previous = null
	): WP_SQLite_Driver_Exception {
		return new WP_SQLite_Driver_Exception( $this, $message, $code, $previous );
	}

	/**
	 * Create a new invalid input exception.
	 *
	 * This exception can be used to mark cases that should never occur according
	 * to the MySQL grammar. It may serve as an assertion that should never fail.
	 *
	 * @return WP_SQLite_Driver_Exception
	 */
	private function new_invalid_input_exception(): WP_SQLite_Driver_Exception {
		return new WP_SQLite_Driver_Exception( $this, 'MySQL query syntax error.' );
	}

	/**
	 * Create a new not supported exception.
	 *
	 * This exception can be used to mark MySQL constructs that are not supported.
	 *
	 * @param string $cause The cause, indicating which construct is not supported.
	 * @return WP_SQLite_Driver_Exception
	 */
	private function new_not_supported_exception( string $cause ): WP_SQLite_Driver_Exception {
		return new WP_SQLite_Driver_Exception(
			$this,
			sprintf( 'MySQL query not supported. Cause: %s', $cause )
		);
	}
}
