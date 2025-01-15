<?php

// @TODO: Remove the namespace and use statements when replacing the old driver.
namespace WIP;

use Exception;
use PDO;
use PDOException;
use PDOStatement;
use SQLite3;
use WP_MySQL_Lexer;
use WP_MySQL_Parser;
use WP_MySQL_Token;
use WP_Parser_Grammar;
use WP_Parser_Node;
use WP_SQLite_PDO_User_Defined_Functions;

class WP_SQLite_Driver {
	const GRAMMAR_PATH = __DIR__ . '/../../wp-includes/mysql/mysql-grammar.php';

	const SQLITE_BUSY   = 5;
	const SQLITE_LOCKED = 6;

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
	 * The MySQL to SQLite date formats translation.
	 *
	 * Maps MySQL formats to SQLite strftime() formats.
	 *
	 * For MySQL formats, see:
	 * * https://dev.mysql.com/doc/refman/5.7/en/date-and-time-functions.html#function_date-format
	 *
	 * For SQLite formats, see:
	 * * https://www.sqlite.org/lang_datefunc.html
	 * * https://strftime.org/
	 */
	const DATE_FORMAT_TO_STRFTIME_MAP = array(
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

	const DATA_TYPES_CACHE_TABLE = '_mysql_data_types_cache';

	const CREATE_DATA_TYPES_CACHE_TABLE = 'CREATE TABLE IF NOT EXISTS _mysql_data_types_cache (
		`table` TEXT NOT NULL,
		`column_or_index` TEXT NOT NULL,
		`mysql_type` TEXT NOT NULL,
		PRIMARY KEY(`table`, `column_or_index`)
	);';

	/**
	 * @var WP_Parser_Grammar
	 */
	private static $grammar;

	/**
	 * The database name. In WordPress, the value of DB_NAME.
	 *
	 * @var string
	 */
	private $db_name;

	/**
	 * Class variable to reference to the PDO instance.
	 *
	 * @access private
	 *
	 * @var PDO object
	 */
	private $pdo;

	/**
	 * The database version.
	 *
	 * This is used here to avoid PHP warnings in the health screen.
	 *
	 * @var string
	 */
	public $client_info = '';

	/**
	 * Last executed MySQL query.
	 *
	 * @var string
	 */
	public $mysql_query;

	/**
	 * A list of executed SQLite queries.
	 *
	 * @var array
	 */
	public $executed_sqlite_queries = array();

	/**
	 * The affected table name.
	 *
	 * @var array
	 */
	private $table_name = array();

	/**
	 * The type of the executed query (SELECT, INSERT, etc).
	 *
	 * @var array
	 */
	private $query_type = array();

	/**
	 * The columns to insert.
	 *
	 * @var array
	 */
	private $insert_columns = array();

	/**
	 * Class variable to store the result of the query.
	 *
	 * @access private
	 *
	 * @var array reference to the PHP object
	 */
	private $results = null;

	/**
	 * Class variable to check if there is an error.
	 *
	 * @var boolean
	 */
	public $is_error = false;

	/**
	 * Class variable to store the file name and function to cause error.
	 *
	 * @access private
	 *
	 * @var array
	 */
	private $errors;

	/**
	 * Class variable to store the error messages.
	 *
	 * @access private
	 *
	 * @var array
	 */
	private $error_messages = array();

	/**
	 * Class variable to store the affected row id.
	 *
	 * @var int integer
	 * @access private
	 */
	private $last_insert_id;

	/**
	 * Number of rows found by the last SELECT query.
	 *
	 * @var int
	 */
	private $last_select_found_rows;

	/**
	 * Number of rows found by the last SQL_CALC_FOUND_ROW query.
	 *
	 * @var int integer
	 */
	private $last_sql_calc_found_rows = null;

	/**
	 * Class variable to store the number of rows affected.
	 *
	 * @var int integer
	 */
	private $affected_rows;

	/**
	 * Variable to emulate MySQL affected row.
	 *
	 * @var integer
	 */
	private $num_rows;

	/**
	 * Return value from query().
	 *
	 * Each query has its own return value.
	 *
	 * @var mixed
	 */
	private $return_value;

	/**
	 * Variable to keep track of nested transactions level.
	 *
	 * @var int
	 */
	private $transaction_level = 0;

	/**
	 * Value returned by the last exec().
	 *
	 * @var mixed
	 */
	private $last_exec_returned;

	/**
	 * The PDO fetch mode passed to query().
	 *
	 * @var mixed
	 */
	private $pdo_fetch_mode;

	/**
	 * Associative array with list of system (non-WordPress) tables.
	 *
	 * @var array  [tablename => tablename]
	 */
	private $sqlite_system_tables = array();

	/**
	 * The last error message from SQLite.
	 *
	 * @var string
	 */
	private $last_sqlite_error;

	/**
	 * @var WP_SQLite_Information_Schema_Builder
	 */
	private $information_schema_builder;

	/**
	 * Constructor.
	 *
	 * Create PDO object, set user defined functions and initialize other settings.
	 * Don't use parent::__construct() because this class does not only returns
	 * PDO instance but many others jobs.
	 *
	 * @param string $db_name The database name. In WordPress, the value of DB_NAME.
	 * @param PDO|null $pdo The PDO object.
	 */
	public function __construct( string $db_name, ?PDO $pdo = null ) {
		$this->db_name = $db_name;
		if ( ! $pdo ) {
			if ( ! is_file( FQDB ) ) {
				$this->prepare_directory();
			}

			$locked      = false;
			$status      = 0;
			$err_message = '';
			do {
				try {
					$options = array(
						PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
						PDO::ATTR_STRINGIFY_FETCHES => true,
						PDO::ATTR_TIMEOUT           => 5,
					);

					$dsn = 'sqlite:' . FQDB;
					$pdo = new PDO( $dsn, null, null, $options ); // phpcs:ignore WordPress.DB.RestrictedClasses
				} catch ( PDOException $ex ) {
					$status = $ex->getCode();
					if ( self::SQLITE_BUSY === $status || self::SQLITE_LOCKED === $status ) {
						$locked = true;
					} else {
						$err_message = $ex->getMessage();
					}
				}
			} while ( $locked );

			if ( $status > 0 ) {
				$message                = sprintf(
					'<p>%s</p><p>%s</p><p>%s</p>',
					'Database initialization error!',
					"Code: $status",
					"Error Message: $err_message"
				);
				$this->is_error         = true;
				$this->error_messages[] = $message;
				return;
			}
		}

		new WP_SQLite_PDO_User_Defined_Functions( $pdo );

		// MySQL data comes across stringified by default.
		$pdo->setAttribute( PDO::ATTR_STRINGIFY_FETCHES, true ); // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
		$pdo->query( self::CREATE_DATA_TYPES_CACHE_TABLE );

		/*
		 * A list of system tables lets us emulate information_schema
		 * queries without returning extra tables.
		 */
		$this->sqlite_system_tables ['sqlite_sequence']              = 'sqlite_sequence';
		$this->sqlite_system_tables [ self::DATA_TYPES_CACHE_TABLE ] = self::DATA_TYPES_CACHE_TABLE;

		$this->pdo = $pdo;

		$this->information_schema_builder = new WP_SQLite_Information_Schema_Builder(
			$this->db_name,
			array( $this, 'execute_sqlite_query' )
		);
		$this->information_schema_builder->ensure_information_schema_tables();

		// Load MySQL grammar.
		if ( null === self::$grammar ) {
			self::$grammar = new WP_Parser_Grammar( require self::GRAMMAR_PATH );
		}

		// Fixes a warning in the site-health screen.
		$this->client_info = SQLite3::version()['versionString'];

		register_shutdown_function( array( $this, '__destruct' ) );

		// WordPress happens to use no foreign keys.
		$statement = $this->pdo->query( 'PRAGMA foreign_keys' );
		// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
		if ( $statement->fetchColumn( 0 ) == '0' ) {
			$this->pdo->query( 'PRAGMA foreign_keys = ON' );
		}
		$this->pdo->query( 'PRAGMA encoding="UTF-8";' );

		$valid_journal_modes = array( 'DELETE', 'TRUNCATE', 'PERSIST', 'MEMORY', 'WAL', 'OFF' );
		if ( defined( 'SQLITE_JOURNAL_MODE' ) && in_array( SQLITE_JOURNAL_MODE, $valid_journal_modes, true ) ) {
			$this->pdo->query( 'PRAGMA journal_mode = ' . SQLITE_JOURNAL_MODE );
		}
	}

	/**
	 * Destructor
	 *
	 * If SQLITE_MEM_DEBUG constant is defined, append information about
	 * memory usage into database/mem_debug.txt.
	 *
	 * This definition is changed since version 1.7.
	 */
	public function __destruct() {
		if ( defined( 'SQLITE_MEM_DEBUG' ) && SQLITE_MEM_DEBUG ) {
			$max = ini_get( 'memory_limit' );
			if ( is_null( $max ) ) {
				$message = sprintf(
					'[%s] Memory_limit is not set in php.ini file.',
					gmdate( 'Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] )
				);
				error_log( $message );
				return;
			}
			if ( stripos( $max, 'M' ) !== false ) {
				$max = (int) $max * MB_IN_BYTES;
			}
			$peak = memory_get_peak_usage( true );
			$used = round( (int) $peak / (int) $max * 100, 2 );
			if ( $used > 90 ) {
				$message = sprintf(
					"[%s] Memory peak usage warning: %s %% used. (max: %sM, now: %sM)\n",
					gmdate( 'Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ),
					$used,
					$max,
					$peak
				);
				error_log( $message );
			}
		}
	}

	/**
	 * Get the PDO object.
	 *
	 * @return PDO
	 */
	public function get_pdo() {
		return $this->pdo;
	}

	/**
	 * Method to return inserted row id.
	 */
	public function get_insert_id() {
		return $this->last_insert_id;
	}

	/**
	 * Method to return the number of rows affected.
	 */
	public function get_affected_rows() {
		return $this->affected_rows;
	}

	/**
	 * Method to execute query().
	 *
	 * Divide the query types into seven different ones. That is to say:
	 *
	 * 1. SELECT SQL_CALC_FOUND_ROWS
	 * 2. INSERT
	 * 3. CREATE TABLE(INDEX)
	 * 4. ALTER TABLE
	 * 5. SHOW VARIABLES
	 * 6. DROP INDEX
	 * 7. THE OTHERS
	 *
	 * #1 is just a tricky play. See the private function handle_sql_count() in query.class.php.
	 * From #2 through #5 call different functions respectively.
	 * #6 call the ALTER TABLE query.
	 * #7 is a normal process: sequentially call prepare_query() and execute_query().
	 *
	 * #1 process has been changed since version 1.5.1.
	 *
	 * @param string $statement          Full SQL statement string.
	 * @param int    $mode               Not used.
	 * @param array  ...$fetch_mode_args Not used.
	 *
	 * @see PDO::query()
	 *
	 * @throws Exception    If the query could not run.
	 * @throws PDOException If the translated query could not run.
	 *
	 * @return mixed according to the query type
	 */
	public function query( $statement, $mode = PDO::FETCH_OBJ, ...$fetch_mode_args ) { // phpcs:ignore WordPress.DB.RestrictedClasses
		$this->flush();
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters queries before they are translated and run.
			 *
			 * Return a non-null value to cause query() to return early with that result.
			 * Use this filter to intercept queries that don't work correctly in SQLite.
			 *
			 * From within the filter you can do
			 *  function filter_sql ($result, $translator, $statement, $mode, $fetch_mode_args) {
			 *     if ( intercepting this query  ) {
			 *       return $translator->execute_sqlite_query( $statement );
			 *     }
			 *     return $result;
			 *   }
			 *
			 * @param null|array $result Default null to continue with the query.
			 * @param object     $translator The translator object. You can call $translator->execute_sqlite_query().
			 * @param string     $statement The statement passed.
			 * @param int        $mode Fetch mode: PDO::FETCH_OBJ, PDO::FETCH_CLASS, etc.
			 * @param array      $fetch_mode_args Variable arguments passed to query.
			 *
			 * @returns null|array Null to proceed, or an array containing a resultset.
			 * @since 2.1.0
			 */
			$pre = apply_filters( 'pre_query_sqlite_db', null, $this, $statement, $mode, $fetch_mode_args );
			if ( null !== $pre ) {
				return $pre;
			}
		}
		$this->pdo_fetch_mode = $mode;
		$this->mysql_query    = $statement;
		if (
			preg_match( '/^\s*START TRANSACTION/i', $statement )
			|| preg_match( '/^\s*BEGIN/i', $statement )
		) {
			return $this->begin_transaction();
		}
		if ( preg_match( '/^\s*COMMIT/i', $statement ) ) {
			return $this->commit();
		}
		if ( preg_match( '/^\s*ROLLBACK/i', $statement ) ) {
			return $this->rollback();
		}

		try {
			// Parse the MySQL query.
			$lexer  = new WP_MySQL_Lexer( $statement );
			$tokens = $lexer->remaining_tokens();

			$parser = new WP_MySQL_Parser( self::$grammar, $tokens );
			$ast    = $parser->parse();

			if ( null === $ast ) {
				throw new Exception( 'Failed to parse the MySQL query.' );
			}

			// Perform all the queries in a nested transaction.
			$this->begin_transaction();

			do {
				$error = null;
				try {
					$this->execute_mysql_query( $ast );
				} catch ( PDOException $error ) {
					if ( $error->getCode() !== self::SQLITE_BUSY ) {
						throw $error;
					}
				}
			} while ( $error );

			if ( function_exists( 'do_action' ) ) {
				/**
				 * Notifies that a query has been translated and executed.
				 *
				 * @param string $query The executed SQL query.
				 * @param string $query_type The type of the SQL query (e.g. SELECT, INSERT, UPDATE, DELETE).
				 * @param string $table_name The name of the table affected by the SQL query.
				 * @param array $insert_columns The columns affected by the INSERT query (if applicable).
				 * @param int $last_insert_id The ID of the last inserted row (if applicable).
				 * @param int $affected_rows The number of affected rows (if applicable).
				 *
				 * @since 0.1.0
				 */
				do_action(
					'sqlite_translated_query_executed',
					$this->mysql_query,
					$this->query_type,
					$this->table_name,
					$this->insert_columns,
					$this->last_insert_id,
					$this->affected_rows
				);
			}

			// Commit the nested transaction.
			$this->commit();

			return $this->return_value;
		} catch ( Exception $err ) {
			// Rollback the nested transaction.
			$this->rollback();
			if ( defined( 'PDO_DEBUG' ) && PDO_DEBUG === true ) {
				throw $err;
			}
			return $this->handle_error( $err );
		}
	}

	/**
	 * Method to return the queried result data.
	 *
	 * @return mixed
	 */
	public function get_query_results() {
		return $this->results;
	}

	/**
	 * Method to return the number of rows from the queried result.
	 */
	public function get_num_rows() {
		return $this->num_rows;
	}

	/**
	 * Method to return the queried results according to the query types.
	 *
	 * @return mixed
	 */
	public function get_return_value() {
		return $this->return_value;
	}

	/**
	 * Executes a query in SQLite.
	 *
	 * @param mixed $sql The query to execute.
	 * @param mixed $params The parameters to bind to the query.
	 * @throws PDOException If the query could not be executed.
	 * @return object {
	 *     The result of the query.
	 *
	 *     @type PDOStatement $stmt The executed statement
	 *     @type * $result The value returned by $stmt.
	 * }
	 */
	public function execute_sqlite_query( $sql, $params = array() ) {
		$this->executed_sqlite_queries[] = array(
			'sql'    => $sql,
			'params' => $params,
		);

		$stmt = $this->pdo->prepare( $sql );
		if ( false === $stmt || null === $stmt ) {
			$this->last_exec_returned = null;
			$info                     = $this->pdo->errorInfo();
			$this->last_sqlite_error  = $info[0] . ' ' . $info[2];
			throw new PDOException( implode( ' ', array( 'Error:', $info[0], $info[2], 'SQLite:', $sql ) ), $info[1] );
		}
		$returned                 = $stmt->execute( $params );
		$this->last_exec_returned = $returned;
		if ( ! $returned ) {
			$info                    = $stmt->errorInfo();
			$this->last_sqlite_error = $info[0] . ' ' . $info[2];
			throw new PDOException( implode( ' ', array( 'Error:', $info[0], $info[2], 'SQLite:', $sql ) ), $info[1] );
		}

		return $stmt;
	}

	/**
	 * Method to return error messages.
	 *
	 * @throws Exception If error is found.
	 *
	 * @return string
	 */
	public function get_error_message() {
		if ( count( $this->error_messages ) === 0 ) {
			$this->is_error       = false;
			$this->error_messages = array();
			return '';
		}

		if ( false === $this->is_error ) {
			return '';
		}

		$output  = '<div style="clear:both">&nbsp;</div>' . PHP_EOL;
		$output .= '<div class="queries" style="clear:both;margin-bottom:2px;border:red dotted thin;">' . PHP_EOL;
		$output .= '<p>MySQL query:</p>' . PHP_EOL;
		$output .= '<p>' . $this->mysql_query . '</p>' . PHP_EOL;
		$output .= '<p>Queries made or created this session were:</p>' . PHP_EOL;
		$output .= '<ol>' . PHP_EOL;
		foreach ( $this->executed_sqlite_queries as $q ) {
			$message = "Executing: {$q['sql']} | " . ( $q['params'] ? 'parameters: ' . implode( ', ', $q['params'] ) : '(no parameters)' );

			$output .= '<li>' . htmlspecialchars( $message ) . '</li>' . PHP_EOL;
		}
		$output .= '</ol>' . PHP_EOL;
		$output .= '</div>' . PHP_EOL;
		foreach ( $this->error_messages as $num => $m ) {
			$output .= '<div style="clear:both;margin-bottom:2px;border:red dotted thin;" class="error_message" style="border-bottom:dotted blue thin;">' . PHP_EOL;
			$output .= sprintf(
				'Error occurred at line %1$d in Function %2$s. Error message was: %3$s.',
				(int) $this->errors[ $num ]['line'],
				'<code>' . htmlspecialchars( $this->errors[ $num ]['function'] ) . '</code>',
				$m
			) . PHP_EOL;
			$output .= '</div>' . PHP_EOL;
		}

		try {
			throw new Exception();
		} catch ( Exception $e ) {
			$output .= '<p>Backtrace:</p>' . PHP_EOL;
			$output .= '<pre>' . $e->getTraceAsString() . '</pre>' . PHP_EOL;
		}

		return $output;
	}

	/**
	 * Begin a new transaction or nested transaction.
	 *
	 * @return boolean
	 */
	public function begin_transaction() {
		$success = false;
		try {
			if ( 0 === $this->transaction_level ) {
				$this->execute_sqlite_query( 'BEGIN' );
			} else {
				$this->execute_sqlite_query( 'SAVEPOINT LEVEL' . $this->transaction_level );
			}
			$success = $this->last_exec_returned;
		} finally {
			if ( $success ) {
				++$this->transaction_level;
				if ( function_exists( 'do_action' ) ) {
					/**
					 * Notifies that a transaction-related query has been translated and executed.
					 *
					 * @param string $command The SQL statement (one of "START TRANSACTION", "COMMIT", "ROLLBACK").
					 * @param bool $success Whether the SQL statement was successful or not.
					 * @param int $nesting_level The nesting level of the transaction.
					 *
					 * @since 0.1.0
					 */
					do_action( 'sqlite_transaction_query_executed', 'START TRANSACTION', (bool) $this->last_exec_returned, $this->transaction_level - 1 );
				}
			}
		}
		return $success;
	}

	/**
	 * Commit the current transaction or nested transaction.
	 *
	 * @return boolean True on success, false on failure.
	 */
	public function commit() {
		if ( 0 === $this->transaction_level ) {
			return false;
		}

		--$this->transaction_level;
		if ( 0 === $this->transaction_level ) {
			$this->execute_sqlite_query( 'COMMIT' );
		} else {
			$this->execute_sqlite_query( 'RELEASE SAVEPOINT LEVEL' . $this->transaction_level );
		}

		if ( function_exists( 'do_action' ) ) {
			do_action( 'sqlite_transaction_query_executed', 'COMMIT', (bool) $this->last_exec_returned, $this->transaction_level );
		}
		return $this->last_exec_returned;
	}

	/**
	 * Rollback the current transaction or nested transaction.
	 *
	 * @return boolean True on success, false on failure.
	 */
	public function rollback() {
		if ( 0 === $this->transaction_level ) {
			return false;
		}

		--$this->transaction_level;
		if ( 0 === $this->transaction_level ) {
			$this->execute_sqlite_query( 'ROLLBACK' );
		} else {
			$this->execute_sqlite_query( 'ROLLBACK TO SAVEPOINT LEVEL' . $this->transaction_level );
		}
		if ( function_exists( 'do_action' ) ) {
			do_action( 'sqlite_transaction_query_executed', 'ROLLBACK', (bool) $this->last_exec_returned, $this->transaction_level );
		}
		return $this->last_exec_returned;
	}

	/**
	 * Executes a MySQL query in SQLite.
	 *
	 * @param string $query The query.
	 *
	 * @throws Exception If the query is not supported.
	 */
	private function execute_mysql_query( WP_Parser_Node $ast ) {
		if ( 'query' !== $ast->rule_name ) {
			throw new Exception( sprintf( 'Expected "query" node, got: "%s"', $ast->rule_name ) );
		}

		$children = $ast->get_child_nodes();
		if ( count( $children ) !== 1 ) {
			throw new Exception( sprintf( 'Expected 1 child, got: %d', count( $children ) ) );
		}

		$ast = $children[0]->get_child_node();
		switch ( $ast->rule_name ) {
			case 'selectStatement':
				$this->query_type = 'SELECT';
				$this->execute_select_statement( $ast );
				break;
			case 'insertStatement':
			case 'updateStatement':
				$this->execute_update_statement( $ast );
				break;
			case 'replaceStatement':
			case 'deleteStatement':
				if ( 'insertStatement' === $ast->rule_name ) {
					$this->query_type = 'INSERT';
				} elseif ( 'replaceStatement' === $ast->rule_name ) {
					$this->query_type = 'REPLACE';
				} elseif ( 'deleteStatement' === $ast->rule_name ) {
					$this->query_type = 'DELETE';
				}
				$query = $this->translate( $ast );
				$this->execute_sqlite_query( $query );
				$this->set_result_from_affected_rows();
				break;
			case 'createStatement':
				$this->query_type = 'CREATE';
				$subtree          = $ast->get_child_node();
				switch ( $subtree->rule_name ) {
					case 'createTable':
						$this->execute_create_table_statement( $ast );
						break;
					default:
						throw new Exception(
							sprintf(
								'Unsupported statement type: "%s" > "%s"',
								$ast->rule_name,
								$subtree->rule_name
							)
						);
				}
				break;
			case 'alterStatement':
				$this->query_type = 'ALTER';
				$subtree          = $ast->get_child_node();
				switch ( $subtree->rule_name ) {
					case 'alterTable':
						$this->execute_alter_table_statement( $ast );
						break;
					default:
						throw new Exception(
							sprintf(
								'Unsupported statement type: "%s" > "%s"',
								$ast->rule_name,
								$subtree->rule_name
							)
						);
				}
				break;
			case 'dropStatement':
				$this->query_type = 'DROP';
				$query            = $this->translate( $ast );
				$this->execute_sqlite_query( $query );
				$this->set_result_from_affected_rows();
				break;
			case 'setStatement':
				/*
				 * It would be lovely to support at least SET autocommit,
				 * but I don't think that is even possible with SQLite.
				 */
				$this->results = 0;
				break;
			case 'showStatement':
				$this->query_type = 'SHOW';
				$this->execute_show_statement( $ast );
				break;
			case 'utilityStatement':
				$this->query_type = 'DESCRIBE';
				$subtree          = $ast->get_child_node();
				switch ( $subtree->rule_name ) {
					case 'describeStatement':
						$this->execute_describe_statement( $subtree );
						break;
					default:
						throw new Exception(
							sprintf(
								'Unsupported statement type: "%s" > "%s"',
								$ast->rule_name,
								$subtree->rule_name
							)
						);
				}
				break;
			default:
				throw new Exception( sprintf( 'Unsupported statement type: "%s"', $ast->rule_name ) );
		}
	}

	private function execute_select_statement( WP_Parser_Node $node ): void {
		$has_sql_calc_found_rows = null !== $node->get_descendant_token(
			WP_MySQL_Lexer::SQL_CALC_FOUND_ROWS_SYMBOL
		);

		// First, translate the query, before we modify last found rows count.
		$query = $this->translate( $node->get_child() );

		// Handle SQL_CALC_FOUND_ROWS.
		if ( true === $has_sql_calc_found_rows ) {
			// Recursively find a query expression with the first LIMIT or SELECT.
			$query_expr = $node->get_descendant_node( 'queryExpression' );
			while ( true ) {
				if ( $query_expr->has_child_node( 'limitClause' ) ) {
					break;
				}

				$query_expr_parens = $query_expr->get_child_node( 'queryExpressionParens' );
				if ( null !== $query_expr_parens ) {
					$query_expr = $query_expr_parens->get_child_node( 'queryExpression' );
					continue;
				}

				$query_expr_body = $query_expr->get_child_node( 'queryExpressionBody' );
				if ( count( $query_expr_body->get_children() ) > 1 ) {
					break;
				}

				$query_term = $query_expr_body->get_child_node( 'queryTerm' );
				if (
					count( $query_term->get_children() ) === 1
					&& $query_term->has_child_node( 'queryExpressionParens' )
				) {
					$query_expr = $query_term->get_child_node( 'queryExpressionParens' )->get_child_node( 'queryExpression' );
					continue;
				}

				break;
			}

			$count_expr = new WP_Parser_Node( $query_expr->rule_id, $query_expr->rule_name );
			foreach ( $query_expr->get_children() as $child ) {
				if ( ! ( $child instanceof WP_Parser_Node && 'limitClause' === $child->rule_name ) ) {
					$count_expr->append_child( $child );
				}
			}

			$result                         = $this->execute_sqlite_query( 'SELECT COUNT(*) AS cnt FROM (' . $this->translate( $count_expr ) . ')' );
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
		if ( $has_order || $has_limit ) {
			$subquery = 'SELECT rowid FROM ' . $this->translate_sequence(
				array(
					$node->get_descendant_node( 'tableReference' ),
					$node->get_descendant_node( 'whereClause' ),
					$node->get_descendant_node( 'orderClause' ),
					$node->get_descendant_node( 'simpleLimitClause' ),
				)
			);

			$update_nodes = array();
			foreach ( $node->get_children() as $child ) {
				$update_nodes[] = $child;
				if (
					$child instanceof WP_Parser_Node
					&& 'updateList' === $child->rule_name
				) {
					// Skip WHERE, ORDER BY, and LIMIT.
					break;
				}
			}
			$query = $this->translate_sequence( $update_nodes )
				. ' WHERE rowid IN ( ' . $subquery . ' )';
		} else {
			$query = $this->translate( $node );
		}
		$this->execute_sqlite_query( $query );
		$this->set_result_from_affected_rows();
	}

	private function execute_create_table_statement( WP_Parser_Node $node ): void {
		$element_list = $node->get_descendant_node( 'tableElementList' );
		if ( null === $element_list ) {
			$query = $this->translate( $node );
			$this->execute_sqlite_query( $query );
			$this->set_result_from_affected_rows();
			return;
		}

		$table_name = $this->unquote_sqlite_identifier(
			$this->translate( $node->get_descendant_node( 'tableName' ) )
		);

		// Save information to information schema tables.
		$this->information_schema_builder->record_create_table( $node );

		// Generate CREATE TABLE statement from the information schema tables.
		$queries            = $this->get_sqlite_create_table_statement( $table_name );
		$create_table_query = $queries[0];
		$constraint_queries = array_slice( $queries, 1 );

		$this->execute_sqlite_query( $create_table_query );
		$this->results      = $this->last_exec_returned;
		$this->return_value = $this->results;

		foreach ( $constraint_queries as $query ) {
			$this->execute_sqlite_query( $query );
		}
	}

	private function execute_alter_table_statement( WP_Parser_Node $node ): void {
		$table_name = $this->unquote_sqlite_identifier(
			$this->translate( $node->get_descendant_node( 'tableRef' ) )
		);

		// Save all column names from the original table.
		$column_names = $this->execute_sqlite_query(
			'SELECT COLUMN_NAME FROM _mysql_information_schema_columns WHERE table_schema = ? AND table_name = ?',
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_COLUMN );

		// Track column renames and removals.
		$column_map = array_combine( $column_names, $column_names );
		foreach ( $node->get_descendant_nodes( 'alterListItem' ) as $action ) {
			$first_token = $action->get_child_token();

			if ( WP_MySQL_Lexer::DROP_SYMBOL === $first_token->id ) {
				$name = $this->translate( $action->get_child_node( 'columnInternalRef' ) );
				if ( null !== $name ) {
					$name = $this->unquote_sqlite_identifier( $name );
					unset( $column_map[ $name ] );
				}
			}

			if ( WP_MySQL_Lexer::CHANGE_SYMBOL === $first_token->id ) {
				$old_name = $this->unquote_sqlite_identifier(
					$this->translate( $action->get_child_node( 'columnInternalRef' ) )
				);
				$new_name = $this->unquote_sqlite_identifier(
					$this->translate( $action->get_child_node( 'identifier' ) )
				);

				$column_map[ $old_name ] = $new_name;
			}

			if ( WP_MySQL_Lexer::RENAME_SYMBOL === $first_token->id ) {
				$column_ref = $action->get_child_node( 'columnInternalRef' );
				if ( null !== $column_ref ) {
					$old_name = $this->unquote_sqlite_identifier(
						$this->translate( $column_ref )
					);
					$new_name = $this->unquote_sqlite_identifier(
						$this->translate( $action->get_child_node( 'identifier' ) )
					);

					$column_map[ $old_name ] = $new_name;
				}
			}
		}

		$this->information_schema_builder->record_alter_table( $node );

		/*
		 * See:
		 *   https://www.sqlite.org/lang_altertable.html#making_other_kinds_of_table_schema_changes
		 */

		// 1. If foreign key constraints are enabled, disable them.
		// @TODO

		// 2. Create a new table with the new schema.
		$tmp_table_name     = "_tmp__{$table_name}_" . uniqid();
		$queries            = $this->get_sqlite_create_table_statement( $table_name, $tmp_table_name );
		$create_table_query = $queries[0];
		$constraint_queries = array_slice( $queries, 1 );
		$this->execute_sqlite_query( $create_table_query );

		// 3. Copy data from the original table to the new table.
		$this->execute_sqlite_query(
			sprintf(
				'INSERT INTO "%s" (%s) SELECT %s FROM "%s"',
				$tmp_table_name,
				implode(
					', ',
					array_map(
						function ( $column ) {
							return '"' . $column . '"';
						},
						$column_map
					)
				),
				implode(
					', ',
					array_map(
						function ( $column ) {
							return '"' . $column . '"';
						},
						array_keys( $column_map )
					)
				),
				$table_name
			)
		);

		// 4. Drop the original table.
		$this->execute_sqlite_query( sprintf( 'DROP TABLE "%s"', $table_name ) );

		// 5. Rename the new table to the original table name.
		$this->execute_sqlite_query( sprintf( 'ALTER TABLE "%s" RENAME TO "%s"', $tmp_table_name, $table_name ) );

		// 6. Reconstruct indexes, triggers, and views.
		foreach ( $constraint_queries as $query ) {
			$this->execute_sqlite_query( $query );
		}

		// @TODO: Triggers and views.

		$this->results      = 1;
		$this->return_value = $this->results;
		return;

		/*
		 * SQLite supports only a small subset of MySQL ALTER TABLE statement.
		 * We need to handle some differences and emulate some operations:
		 *
		 * 1. Multiple operations in a single ALTER TABLE statement.
		 *
		 *  SQLite doesn't support multiple operations in a single ALTER TABLE
		 *  statement. We need to execute each operation as a separate query.
		 *
		 * 2. ADD COLUMN in SQLite doesn't support some valid MySQL constructs:
		 *
		 *  - Adding a column with PRIMARY KEY or UNIQUE constraint.
		 *  - Adding a column with AUTOINCREMENT.
		 *  - Adding a column with CURRENT_TIME, CURRENT_DATE, CURRENT_TIMESTAMP,
		 *    or an expression in parentheses as a default value.
		 *  - Adding a NOT NULL column without a default value when the table is
		 *    not empty. In MySQL, this depends on the data type and SQL mode.
		 *
		 *    @TODO: Address these nuances.
		 */
	}

	private function execute_show_statement( WP_Parser_Node $node ): void {
		$tokens   = $node->get_child_tokens();
		$keyword1 = $tokens[1];
		$keyword2 = $tokens[2] ?? null;

		switch ( $keyword1->id ) {
			case WP_MySQL_Lexer::CREATE_SYMBOL:
				if ( WP_MySQL_Lexer::TABLE_SYMBOL === $keyword2->id ) {
					$table_name = $this->unquote_sqlite_identifier(
						$this->translate( $node->get_child_node( 'tableRef' ) )
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
					$this->translate( $node->get_child_node( 'tableRef' ) )
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
			default:
				// @TODO
		}
	}

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
			',
			array( $this->db_name, $table_name )
		)->fetchAll( PDO::FETCH_OBJ );

		$this->set_results_from_fetched_data( $index_info );
	}

	private function execute_describe_statement( WP_Parser_Node $node ): void {
		$table_name = $this->unquote_sqlite_identifier(
			$this->translate( $node->get_child_node( 'tableRef' ) )
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

	private function translate( $ast ) {
		if ( null === $ast ) {
			return null;
		}

		if ( $ast instanceof WP_MySQL_Token ) {
			return $this->translate_token( $ast );
		}

		if ( ! $ast instanceof WP_Parser_Node ) {
			throw new Exception( 'translate_query only accepts WP_MySQL_Token and WP_Parser_Node instances' );
		}

		$rule_name = $ast->rule_name;
		switch ( $rule_name ) {
			case 'querySpecification':
				// Translate "HAVING ..." without "GROUP BY ..." to "GROUP BY 1 HAVING ...".
				if ( $ast->has_child_node( 'havingClause' ) && ! $ast->has_child_node( 'groupByClause' ) ) {
					$parts = array();
					foreach ( $ast->get_children() as $child ) {
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
				return $this->translate_sequence( $ast->get_children() );
			case 'qualifiedIdentifier':
			case 'dotIdentifier':
				return $this->translate_sequence( $ast->get_children(), '' );
			case 'identifierKeyword':
				return '"' . $this->translate( $ast->get_child() ) . '"';
			case 'textStringLiteral':
				$token = $ast->get_child_token();

				// 1. Remove bounding quotes.
				$quote = $token->value[0];
				$value = substr( $token->value, 1, -1 );

				// 2. Unescape quotes within the string.
				$value = str_replace( $quote . $quote, $quote, $value );
				$value = str_replace( '\\' . $quote, $quote, $value );

				// 3. Translate datetime literals.
				//    Process only strings that could possibly represent a datetime
				//    literal ("YYYY-MM-DDTHH:MM:SS", "YYYY-MM-DDTHH:MM:SSZ", etc.).
				if ( strlen( $value ) >= 19 && is_numeric( $value[0] ) ) {
					$value = $this->translate_datetime_literal( $value );
				}

				// 4. Remove null characters.
				$value = str_replace( "\0", '', $value );

				// 5. Escape and add quotes.
				return "'" . str_replace( "'", "''", $value ) . "'";
			case 'dataType':
			case 'nchar':
				$child = $ast->get_child();
				if ( $child instanceof WP_Parser_Node ) {
					return $this->translate( $child );
				}

				// Handle optional prefixes (data type is the second token):
				//  1. LONG VARCHAR, LONG CHAR(ACTER) VARYING, LONG VARBINARY.
				//  2. NATIONAL CHAR, NATIONAL VARCHAR, NATIONAL CHAR(ACTER) VARYING.
				if ( WP_MySQL_Lexer::LONG_SYMBOL === $child->id ) {
					$child = $ast->get_child_tokens()[1] ?? null;
				} elseif ( WP_MySQL_Lexer::NATIONAL_SYMBOL === $child->id ) {
					$child = $ast->get_child_tokens()[1] ?? null;
				}

				if ( null === $child ) {
					throw $this->invalid_input_exception();
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
				throw $this->not_supported_exception(
					sprintf( 'data type: %s', $child->value )
				);
			case 'fromClause':
				// FROM DUAL is MySQL-specific syntax that means "FROM no tables"
				// and it is equivalent to omitting the FROM clause entirely.
				if ( $ast->has_child_token( WP_MySQL_Lexer::DUAL_SYMBOL ) ) {
					return null;
				}
				return $this->translate_sequence( $ast->get_children() );
			case 'predicateOperations':
				$token = $ast->get_child_token();
				if ( WP_MySQL_Lexer::LIKE_SYMBOL === $token->id ) {
					return $this->translate_like( $ast );
				} elseif ( WP_MySQL_Lexer::REGEXP_SYMBOL === $token->id ) {
					return $this->translate_regexp_functions( $ast );
				}
				return $this->translate_sequence( $ast->get_children() );
			case 'runtimeFunctionCall':
				return $this->translate_runtime_function_call( $ast );
			case 'functionCall':
				return $this->translate_function_call( $ast );
			case 'systemVariable':
				// @TODO: Emulate some system variables, or use reasonable defaults.
				//        See: https://dev.mysql.com/doc/refman/8.4/en/server-system-variable-reference.html
				//        See: https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html

				// When we have no value, it's reasonable to use NULL.
				return 'NULL';
			case 'castType':
				// Translate "CAST(... AS BINARY)" to "CAST(... AS BLOB)".
				if ( $ast->has_child_token( WP_MySQL_Lexer::BINARY_SYMBOL ) ) {
					return 'BLOB';
				}
				return $this->translate_sequence( $ast->get_children() );
			case 'defaultCollation':
				// @TODO: Check and save in information schema.
				return null;
			case 'duplicateAsQueryExpression':
				// @TODO: How to handle IGNORE/REPLACE?

				// The "AS" keyword is optional in MySQL, but required in SQLite.
				return 'AS ' . $this->translate( $ast->get_child_node() );
			case 'indexHint':
			case 'indexHintList':
				return null;
			default:
				return $this->translate_sequence( $ast->get_children() );
		}
	}

	private function translate_token( WP_MySQL_Token $token ) {
		switch ( $token->id ) {
			case WP_MySQL_Lexer::EOF:
				return null;
			case WP_MySQL_Lexer::IDENTIFIER:
			case WP_MySQL_Lexer::BACK_TICK_QUOTED_ID:
				// @TODO: Properly unquote (MySQL) and escape (SQLite).
				return '"' . trim( $token->value, '`"' ) . '"';
			case WP_MySQL_Lexer::AUTO_INCREMENT_SYMBOL:
				return 'AUTOINCREMENT';
			case WP_MySQL_Lexer::BINARY_SYMBOL:
				/*
				 * There is no "BINARY expr" equivalent in SQLite. We can look for
				 * the BINARY keyword in particular cases (with REGEXP, LIKE, etc.)
				 * and then remove it from the translated output here.
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
		return $this->translate_sequence( $node->get_children() );
	}

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
			return 'REGEXP CHAR(0) || ' . $this->translate( $node->get_child_node() );
		}
		return 'REGEXP ' . $this->translate( $node->get_child_node() );
	}

	private function translate_runtime_function_call( WP_Parser_Node $node ): string {
		$child = $node->get_child();
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

				$format = strtr( $mysql_format, self::DATE_FORMAT_TO_STRFTIME_MAP );
				if ( ! $format ) {
					throw new Exception( "Could not translate a DATE_FORMAT() format to STRFTIME format ($mysql_format)" );
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
			case 'FOUND_ROWS':
				// @TODO: The following implementation with an alias assumes
				//        that the function is used in the SELECT field list.
				//        For compatibility with more complex use cases, it may
				//        be better to register it as a custom SQLite function.
				return sprintf(
					"(SELECT %d) AS 'FOUND_ROWS()'",
					$this->last_sql_calc_found_rows ?? $this->last_select_found_rows
				);
			default:
				return $this->translate_sequence( $node->get_children() );
		}
	}

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

	private function get_sqlite_create_table_statement( string $table_name, ?string $new_table_name = null ): array {
		// 1. Get table info.
		$table_info = $this->execute_sqlite_query(
			'
				SELECT *
				FROM _mysql_information_schema_tables
				WHERE table_type = "BASE TABLE"
				AND table_schema = ?
				AND table_name = ?
			',
			array( $this->db_name, $table_name )
		)->fetch( PDO::FETCH_ASSOC );

		if ( false === $table_info ) {
			throw new Exception( 'Table not found in information_schema' );
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
		$has_autoincrement = false;
		foreach ( $column_info as $column ) {
			$sql  = '  ';
			$sql .= sprintf( '"%s"', str_replace( '"', '""', $column['COLUMN_NAME'] ) );

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

			$sql .= ' ' . $type;
			if ( 'NO' === $column['IS_NULLABLE'] ) {
				$sql .= ' NOT NULL';
			}
			if ( 'auto_increment' === $column['EXTRA'] ) {
				$has_autoincrement = true;
				$sql              .= ' PRIMARY KEY AUTOINCREMENT';
			}
			if ( null !== $column['COLUMN_DEFAULT'] ) {
				// @TODO: Correctly quote based on the data type.
				$sql .= ' DEFAULT ' . $this->pdo->quote( $column['COLUMN_DEFAULT'] );
			}
			$rows[] = $sql;
		}

		// 4. Generate CREATE TABLE statement constraints, collect indexes.
		$create_index_sqls = array();
		foreach ( $grouped_constraints as $constraint ) {
			ksort( $constraint );
			$info = $constraint[1];

			if ( 'PRIMARY' === $info['INDEX_NAME'] ) {
				if ( $has_autoincrement ) {
					continue;
				}
				$sql    = '  PRIMARY KEY (';
				$sql   .= implode(
					', ',
					array_map(
						function ( $column ) {
							return sprintf( '"%s"', str_replace( '"', '""', $column['COLUMN_NAME'] ) );
						},
						$constraint
					)
				);
				$sql   .= ')';
				$rows[] = $sql;
			} else {
				$is_unique = '0' === $info['NON_UNIQUE'];

				$sql  = sprintf( 'CREATE %sINDEX', $is_unique ? 'UNIQUE ' : '' );
				$sql .= sprintf( ' "%s"', $info['INDEX_NAME'] );
				$sql .= sprintf( ' ON "%s" (', $table_name );
				$sql .= implode(
					', ',
					array_map(
						function ( $column ) {
							return sprintf( '"%s"', str_replace( '"', '""', $column['COLUMN_NAME'] ) );
						},
						$constraint
					)
				);
				$sql .= ')';

				$create_index_sqls[] = $sql;
			}
		}

		// 5. Compose the CREATE TABLE statement.
		$sql  = sprintf( 'CREATE TABLE "%s" (%s', str_replace( '"', '""', $new_table_name ?? $table_name ), "\n" );
		$sql .= implode( ",\n", $rows );
		$sql .= "\n)";
		return array_merge( array( $sql ), $create_index_sqls );
	}

	private function get_mysql_create_table_statement( string $table_name ): ?string {
		// 1. Get table info.
		$table_info = $this->execute_sqlite_query(
			'
				SELECT *
				FROM _mysql_information_schema_tables
				WHERE table_type = "BASE TABLE"
				AND table_schema = ?
				AND table_name = ?
			',
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
			$sql = '  ';
			// @TODO: Proper identifier escaping.
			$sql .= sprintf( '`%s`', str_replace( '`', '``', $column['COLUMN_NAME'] ) );

			$sql .= ' ' . $column['COLUMN_TYPE'];
			if ( 'NO' === $column['IS_NULLABLE'] ) {
				$sql .= ' NOT NULL';
			}
			if ( 'auto_increment' === $column['EXTRA'] ) {
				$sql .= ' AUTO_INCREMENT';
			}
			if ( null !== $column['COLUMN_DEFAULT'] ) {
				// @TODO: Correctly quote based on the data type.
				$sql .= ' DEFAULT ' . $this->pdo->quote( $column['COLUMN_DEFAULT'] );
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
							// @TODO: Proper identifier escaping.
							return sprintf( '`%s`', str_replace( '`', '``', $column['COLUMN_NAME'] ) );
						},
						$constraint
					)
				);
				$sql   .= ')';
				$rows[] = $sql;
			} else {
				$is_unique = '0' === $info['NON_UNIQUE'];

				$sql = sprintf( '  %sKEY', $is_unique ? 'UNIQUE ' : '' );
				// @TODO: Proper identifier escaping.
				$sql .= sprintf( ' `%s`', str_replace( '`', '``', $info['INDEX_NAME'] ) );
				$sql .= ' (';
				$sql .= implode(
					', ',
					array_map(
						function ( $column ) {
							// @TODO: Proper identifier escaping.
							return sprintf( '`%s`', str_replace( '`', '``', $column['COLUMN_NAME'] ) );
						},
						$constraint
					)
				);
				$sql .= ')';

				$rows[] = $sql;
			}
		}

		// 5. Compose the CREATE TABLE statement.
		// @TODO: Proper identifier escaping.
		$sql  = sprintf( 'CREATE TABLE `%s` (%s', str_replace( '`', '``', $table_name ), "\n" );
		$sql .= implode( ",\n", $rows );
		$sql .= "\n)";

		$sql .= sprintf( ' ENGINE=%s', $table_info['ENGINE'] );

		$collation = $table_info['TABLE_COLLATION'];
		$charset   = substr( $collation, 0, strpos( $collation, '_' ) );
		$sql      .= sprintf( ' DEFAULT CHARSET=%s', $charset );
		$sql      .= sprintf( ' COLLATE=%s', $collation );
		return $sql;
	}

	private function unquote_sqlite_identifier( string $quoted_identifier ): string {
		$first_byte = $quoted_identifier[0] ?? null;
		if ( '"' === $first_byte ) {
			$unquoted = substr( $quoted_identifier, 1, -1 );
		} else {
			$unquoted = $quoted_identifier;
		}
		return str_replace( '""', '"', $unquoted );
	}

	/**
	 * This method makes database directory and .htaccess file.
	 *
	 * It is executed only once when the installation begins.
	 */
	private function prepare_directory() {
		global $wpdb;
		$u = umask( 0000 );
		if ( ! is_dir( FQDBDIR ) ) {
			if ( ! @mkdir( FQDBDIR, 0704, true ) ) {
				umask( $u );
				wp_die( 'Unable to create the required directory! Please check your server settings.', 'Error!' );
			}
		}
		if ( ! is_writable( FQDBDIR ) ) {
			umask( $u );
			$message = 'Unable to create a file in the directory! Please check your server settings.';
			wp_die( $message, 'Error!' );
		}
		if ( ! is_file( FQDBDIR . '.htaccess' ) ) {
			$fh = fopen( FQDBDIR . '.htaccess', 'w' );
			if ( ! $fh ) {
				umask( $u );
				echo 'Unable to create a file in the directory! Please check your server settings.';

				return false;
			}
			fwrite( $fh, 'DENY FROM ALL' );
			fclose( $fh );
		}
		if ( ! is_file( FQDBDIR . 'index.php' ) ) {
			$fh = fopen( FQDBDIR . 'index.php', 'w' );
			if ( ! $fh ) {
				umask( $u );
				echo 'Unable to create a file in the directory! Please check your server settings.';

				return false;
			}
			fwrite( $fh, '<?php // Silence is gold. ?>' );
			fclose( $fh );
		}
		umask( $u );

		return true;
	}

	/**
	 * Method to clear previous data.
	 */
	private function flush() {
		$this->mysql_query             = '';
		$this->results                 = null;
		$this->last_exec_returned      = null;
		$this->table_name              = null;
		$this->last_insert_id          = null;
		$this->affected_rows           = null;
		$this->insert_columns          = array();
		$this->num_rows                = null;
		$this->return_value            = null;
		$this->error_messages          = array();
		$this->is_error                = false;
		$this->executed_sqlite_queries = array();
	}

	/**
	 * Method to set the results from the fetched data.
	 *
	 * @param array $data The data to set.
	 */
	private function set_results_from_fetched_data( $data ) {
		if ( null === $this->results ) {
			$this->results = $data;
		}
		if ( is_array( $this->results ) ) {
			$this->num_rows               = count( $this->results );
			$this->last_select_found_rows = count( $this->results );
		}
		$this->return_value = $this->results;
	}

	/**
	 * Method to set the results from the affected rows.
	 *
	 * @param int|null $override Override the affected rows.
	 */
	private function set_result_from_affected_rows( $override = null ) {
		/*
		 * SELECT CHANGES() is a workaround for the fact that
		 * $stmt->rowCount() returns "0" (zero) with the
		 * SQLite driver at all times.
		 * Source: https://www.php.net/manual/en/pdostatement.rowcount.php
		 */
		if ( null === $override ) {
			$this->affected_rows = (int) $this->execute_sqlite_query( 'select changes()' )->fetch()[0];
		} else {
			$this->affected_rows = $override;
		}
		$this->return_value = $this->affected_rows;
		$this->num_rows     = $this->affected_rows;
		$this->results      = $this->affected_rows;
	}

	/**
	 * Error handler.
	 *
	 * @param Exception $err Exception object.
	 *
	 * @return bool Always false.
	 */
	private function handle_error( Exception $err ) {
		$message = $err->getMessage();
		$this->set_error( __LINE__, __FUNCTION__, $message );
		$this->return_value = false;
		return false;
	}

	/**
	 * Method to format the error messages and put out to the file.
	 *
	 * When $wpdb::suppress_errors is set to true or $wpdb::show_errors is set to false,
	 * the error messages are ignored.
	 *
	 * @param string $line          Where the error occurred.
	 * @param string $function_name Indicate the function name where the error occurred.
	 * @param string $message       The message.
	 *
	 * @return boolean|void
	 */
	private function set_error( $line, $function_name, $message ) {
		$this->errors[]         = array(
			'line'     => $line,
			'function' => $function_name,
		);
		$this->error_messages[] = $message;
		$this->is_error         = true;
	}

	private function invalid_input_exception() {
		throw new Exception( 'MySQL query syntax error.' );
	}

	private function not_supported_exception( string $cause ): Exception {
		return new Exception(
			sprintf( 'MySQL query not supported. Cause: %s', $cause )
		);
	}
}
