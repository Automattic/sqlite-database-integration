<?php

class WP_SQLite_Information_Schema_Builder {
	/**
	 * SQL definitions for tables that emulate MySQL "information_schema".
	 *
	 * The full MySQL information schema comprises a large number of tables:
	 *   https://dev.mysql.com/doc/refman/8.4/en/information-schema-table-reference.html
	 *
	 * We only implement a limited subset that is necessary for a database schema
	 * introspection and representation, currently covering the following tables:
	 *
	 *  - TABLES
	 *  - VIEWS
	 *  - COLUMNS
	 *  - STATISTICS (indexes)
	 *  - TABLE_CONSTRAINTS (PK, UNIQUE, FK)
	 *  - CHECK_CONSTRAINTS
	 *  - KEY_COLUMN_USAGE (foreign keys)
	 *  - REFERENTIAL_CONSTRAINTS (foreign keys)
	 *  - TRIGGERS
	 */
	const CREATE_INFORMATION_SCHEMA_QUERIES = array(
		// TABLES
		"CREATE TABLE IF NOT EXISTS _mysql_information_schema_tables (
			TABLE_CATALOG TEXT NOT NULL DEFAULT 'def',  -- always 'def'
			TABLE_SCHEMA TEXT NOT NULL,                 -- database name
			TABLE_NAME TEXT NOT NULL,                   -- table name
			TABLE_TYPE TEXT NOT NULL,                   -- 'BASE TABLE' or 'VIEW'
			ENGINE TEXT NOT NULL,                       -- storage engine
			VERSION INTEGER NOT NULL DEFAULT 10,        -- unused, in MySQL 8 hardcoded to 10
			ROW_FORMAT TEXT NOT NULL,                   -- row storage format @TODO - implement
			TABLE_ROWS INTEGER NOT NULL DEFAULT 0,      -- not implemented
			AVG_ROW_LENGTH INTEGER NOT NULL DEFAULT 0,  -- not implemented
			DATA_LENGTH INTEGER NOT NULL DEFAULT 0,     -- not implemented
			MAX_DATA_LENGTH INTEGER NOT NULL DEFAULT 0, -- not implemented
			INDEX_LENGTH INTEGER NOT NULL DEFAULT 0,    -- not implemented
			DATA_FREE INTEGER NOT NULL DEFAULT 0,       -- not implemented
			AUTO_INCREMENT INTEGER,                     -- not implemented
			CREATE_TIME TEXT NOT NULL                   -- table creation timestamp
				DEFAULT CURRENT_TIMESTAMP,
			UPDATE_TIME TEXT,                           -- table update time
			CHECK_TIME TEXT,                            -- not implemented
			TABLE_COLLATION TEXT NOT NULL,              -- table collation
			CHECKSUM INTEGER,                           -- not implemented
			CREATE_OPTIONS TEXT,                        -- extra CREATE TABLE options
			TABLE_COMMENT TEXT NOT NULL DEFAULT ''      -- comment
		) STRICT",

		// COLUMNS
		"CREATE TABLE IF NOT EXISTS _mysql_information_schema_columns (
			TABLE_CATALOG TEXT NOT NULL DEFAULT 'def',      -- always 'def'
			TABLE_SCHEMA TEXT NOT NULL,                     -- database name
			TABLE_NAME TEXT NOT NULL,                       -- table name
			COLUMN_NAME TEXT NOT NULL,                      -- column name
			ORDINAL_POSITION INTEGER NOT NULL,              -- column position
			COLUMN_DEFAULT TEXT,                            -- default value, NULL for both NULL and none
			IS_NULLABLE TEXT NOT NULL,                      -- 'YES' or 'NO'
			DATA_TYPE TEXT NOT NULL,                        -- data type (without length, precision, etc.)
			CHARACTER_MAXIMUM_LENGTH INTEGER,               -- max length for string columns in characters
			CHARACTER_OCTET_LENGTH INTEGER,                 -- max length for string columns in bytes
			NUMERIC_PRECISION INTEGER,                      -- number precision for numeric columns
			NUMERIC_SCALE INTEGER,                          -- number scale for numeric columns
			DATETIME_PRECISION INTEGER,                     -- fractional seconds precision for temporal columns
			CHARACTER_SET_NAME TEXT,                        -- charset for string columns
			COLLATION_NAME TEXT,                            -- collation for string columns
			COLUMN_TYPE TEXT NOT NULL,                      -- full data type (with length, precision, etc.)
			COLUMN_KEY TEXT NOT NULL DEFAULT '',            -- if column is indexed ('', 'PRI', 'UNI', 'MUL')
			EXTRA TEXT NOT NULL DEFAULT '',                 -- AUTO_INCREMENT, VIRTUAL, STORED, etc.
			PRIVILEGES TEXT NOT NULL,                       -- not implemented
			COLUMN_COMMENT TEXT NOT NULL DEFAULT '',        -- comment
			GENERATION_EXPRESSION TEXT NOT NULL DEFAULT '', -- expression for generated columns
			SRS_ID INTEGER                                  -- not implemented
		) STRICT",

		// VIEWS
		// @TODO: Implement.
		'CREATE TABLE IF NOT EXISTS _mysql_information_schema_views (
			TABLE_CATALOG TEXT NOT NULL,
			TABLE_SCHEMA TEXT NOT NULL,
			TABLE_NAME TEXT NOT NULL,
			VIEW_DEFINITION TEXT NOT NULL,
			CHECK_OPTION TEXT NOT NULL,
			IS_UPDATABLE TEXT NOT NULL,
			DEFINER TEXT NOT NULL,
			SECURITY_TYPE TEXT NOT NULL,
			CHARACTER_SET_CLIENT TEXT NOT NULL,
			COLLATION_CONNECTION TEXT NOT NULL,
			ALGORITHM TEXT NOT NULL
		) STRICT',

		// STATISTICS (indexes)
		"CREATE TABLE IF NOT EXISTS _mysql_information_schema_statistics (
			TABLE_CATALOG TEXT NOT NULL DEFAULT 'def', -- always 'def'
			TABLE_SCHEMA TEXT NOT NULL,                -- database name
			TABLE_NAME TEXT NOT NULL,                  -- table name
			NON_UNIQUE INTEGER NOT NULL,               -- 0 for unique indexes, 1 otherwise
			INDEX_SCHEMA TEXT NOT NULL,                -- index database name
			INDEX_NAME TEXT NOT NULL,                  -- index name, for PKs always 'PRIMARY'
			SEQ_IN_INDEX INTEGER NOT NULL,             -- column position in index (from 1)
			COLUMN_NAME TEXT,                          -- column name (NULL for functional indexes)
			COLLATION TEXT,                            -- column sort in the index ('A', 'D', or NULL)
			CARDINALITY INTEGER,                       -- not implemented
			SUB_PART INTEGER,                          -- number of indexed chars, NULL for full column
			PACKED TEXT,                               -- not implemented
			NULLABLE TEXT NOT NULL,                    -- 'YES' if column can contain NULL, '' otherwise
			INDEX_TYPE TEXT NOT NULL,                  -- 'BTREE', 'FULLTEXT', 'SPATIAL'
			COMMENT TEXT NOT NULL DEFAULT '',          -- not implemented
			INDEX_COMMENT TEXT NOT NULL DEFAULT '',    -- index comment
			IS_VISIBLE TEXT NOT NULL DEFAULT 'YES',    -- 'NO' if column is hidden, 'YES' otherwise
			EXPRESSION TEXT                            -- expression for functional indexes
		) STRICT",

		// TABLE_CONSTRAINTS
		// @TODO: Implement. Could this be just a view?
		'CREATE TABLE IF NOT EXISTS _mysql_information_schema_table_constraints (
			CONSTRAINT_CATALOG TEXT NOT NULL,
			CONSTRAINT_SCHEMA TEXT NOT NULL,
			CONSTRAINT_NAME TEXT NOT NULL,
			TABLE_SCHEMA TEXT NOT NULL,
			TABLE_NAME TEXT NOT NULL,
			CONSTRAINT_TYPE TEXT NOT NULL
		) STRICT',

		// CHECK_CONSTRAINTS
		// @TODO: Implement.
		'CREATE TABLE IF NOT EXISTS _mysql_information_schema_check_constraints (
			CONSTRAINT_CATALOG TEXT NOT NULL,
			CONSTRAINT_SCHEMA TEXT NOT NULL,
			TABLE_NAME TEXT NOT NULL,
			CONSTRAINT_NAME TEXT NOT NULL,
			CHECK_CLAUSE TEXT NOT NULL
		) STRICT',

		// KEY_COLUMN_USAGE
		// @TODO: Implement.
		'CREATE TABLE IF NOT EXISTS _mysql_information_schema_key_column_usage (
			CONSTRAINT_CATALOG TEXT NOT NULL,
			CONSTRAINT_SCHEMA TEXT NOT NULL,
			CONSTRAINT_NAME TEXT NOT NULL,
			TABLE_CATALOG TEXT NOT NULL,
			TABLE_SCHEMA TEXT NOT NULL,
			TABLE_NAME TEXT NOT NULL,
			COLUMN_NAME TEXT NOT NULL,
			ORDINAL_POSITION INTEGER NOT NULL,
			POSITION_IN_UNIQUE_CONSTRAINT INTEGER,
			REFERENCED_TABLE_SCHEMA TEXT,
			REFERENCED_TABLE_NAME TEXT,
			REFERENCED_COLUMN_NAME TEXT
		) STRICT',

		// REFERENTIAL_CONSTRAINTS
		// @TODO: Implement.
		'CREATE TABLE IF NOT EXISTS _mysql_information_schema_referential_constraints (
			CONSTRAINT_CATALOG TEXT NOT NULL,
			CONSTRAINT_SCHEMA TEXT NOT NULL,
			CONSTRAINT_NAME TEXT NOT NULL,
			UNIQUE_CONSTRAINT_CATALOG TEXT NOT NULL,
			UNIQUE_CONSTRAINT_SCHEMA TEXT NOT NULL,
			UNIQUE_CONSTRAINT_NAME TEXT,
			MATCH_OPTION TEXT NOT NULL,
			UPDATE_RULE TEXT NOT NULL,
			DELETE_RULE TEXT NOT NULL,
			REFERENCED_TABLE_NAME TEXT NOT NULL
		) STRICT',

		// TRIGGERS
		// @TODO: Implement.
		'CREATE TABLE IF NOT EXISTS _mysql_information_schema_triggers (
			TRIGGER_CATALOG TEXT NOT NULL,
			TRIGGER_SCHEMA TEXT NOT NULL,
			TRIGGER_NAME TEXT NOT NULL,
			EVENT_MANIPULATION TEXT NOT NULL,
			EVENT_OBJECT_CATALOG TEXT NOT NULL,
			EVENT_OBJECT_SCHEMA TEXT NOT NULL,
			EVENT_OBJECT_TABLE TEXT NOT NULL,
			ACTION_ORDER INTEGER NOT NULL,
			ACTION_CONDITION TEXT,
			ACTION_STATEMENT TEXT NOT NULL,
			ACTION_ORIENTATION TEXT NOT NULL,
			ACTION_TIMING TEXT NOT NULL,
			ACTION_REFERENCE_OLD_TABLE TEXT,
			ACTION_REFERENCE_NEW_TABLE TEXT,
			ACTION_REFERENCE_OLD_ROW TEXT NOT NULL,
			ACTION_REFERENCE_NEW_ROW TEXT NOT NULL,
			CREATED TEXT,
			SQL_MODE TEXT NOT NULL,
			DEFINER TEXT NOT NULL,
			CHARACTER_SET_CLIENT TEXT NOT NULL,
			COLLATION_CONNECTION TEXT NOT NULL,
			DATABASE_COLLATION TEXT NOT NULL
		) STRICT',
	);

	/**
	 * A mapping of MySQL tokens to normalized MySQL data types.
	 * This is used to store column data types in the information schema.
	 */
	const TOKEN_TO_TYPE_MAP = array(
		WP_MySQL_Lexer::INT_SYMBOL                => 'int',
		WP_MySQL_Lexer::TINYINT_SYMBOL            => 'tinyint',
		WP_MySQL_Lexer::SMALLINT_SYMBOL           => 'smallint',
		WP_MySQL_Lexer::MEDIUMINT_SYMBOL          => 'mediumint',
		WP_MySQL_Lexer::BIGINT_SYMBOL             => 'bigint',
		WP_MySQL_Lexer::REAL_SYMBOL               => 'double',
		WP_MySQL_Lexer::DOUBLE_SYMBOL             => 'double',
		WP_MySQL_Lexer::FLOAT_SYMBOL              => 'float',
		WP_MySQL_Lexer::DECIMAL_SYMBOL            => 'decimal',
		WP_MySQL_Lexer::NUMERIC_SYMBOL            => 'decimal',
		WP_MySQL_Lexer::FIXED_SYMBOL              => 'decimal',
		WP_MySQL_Lexer::BIT_SYMBOL                => 'bit',
		WP_MySQL_Lexer::BOOL_SYMBOL               => 'tinyint',
		WP_MySQL_Lexer::BOOLEAN_SYMBOL            => 'tinyint',
		WP_MySQL_Lexer::BINARY_SYMBOL             => 'binary',
		WP_MySQL_Lexer::VARBINARY_SYMBOL          => 'varbinary',
		WP_MySQL_Lexer::YEAR_SYMBOL               => 'year',
		WP_MySQL_Lexer::DATE_SYMBOL               => 'date',
		WP_MySQL_Lexer::TIME_SYMBOL               => 'time',
		WP_MySQL_Lexer::TIMESTAMP_SYMBOL          => 'timestamp',
		WP_MySQL_Lexer::DATETIME_SYMBOL           => 'datetime',
		WP_MySQL_Lexer::TINYBLOB_SYMBOL           => 'tinyblob',
		WP_MySQL_Lexer::BLOB_SYMBOL               => 'blob',
		WP_MySQL_Lexer::MEDIUMBLOB_SYMBOL         => 'mediumblob',
		WP_MySQL_Lexer::LONGBLOB_SYMBOL           => 'longblob',
		WP_MySQL_Lexer::TINYTEXT_SYMBOL           => 'tinytext',
		WP_MySQL_Lexer::TEXT_SYMBOL               => 'text',
		WP_MySQL_Lexer::MEDIUMTEXT_SYMBOL         => 'mediumtext',
		WP_MySQL_Lexer::LONGTEXT_SYMBOL           => 'longtext',
		WP_MySQL_Lexer::ENUM_SYMBOL               => 'enum',
		WP_MySQL_Lexer::SET_SYMBOL                => 'set',
		WP_MySQL_Lexer::SERIAL_SYMBOL             => 'bigint',
		WP_MySQL_Lexer::GEOMETRY_SYMBOL           => 'geometry',
		WP_MySQL_Lexer::GEOMETRYCOLLECTION_SYMBOL => 'geomcollection',
		WP_MySQL_Lexer::POINT_SYMBOL              => 'point',
		WP_MySQL_Lexer::MULTIPOINT_SYMBOL         => 'multipoint',
		WP_MySQL_Lexer::LINESTRING_SYMBOL         => 'linestring',
		WP_MySQL_Lexer::MULTILINESTRING_SYMBOL    => 'multilinestring',
		WP_MySQL_Lexer::POLYGON_SYMBOL            => 'polygon',
		WP_MySQL_Lexer::MULTIPOLYGON_SYMBOL       => 'multipolygon',
		WP_MySQL_Lexer::JSON_SYMBOL               => 'json',
	);

	/**
	 * The default collation for each MySQL charset.
	 * This is needed as collation is not always specified in a query.
	 */
	const CHARSET_DEFAULT_COLLATION_MAP = array(
		'armscii8' => 'armscii8_general_ci',
		'ascii'    => 'ascii_general_ci',
		'big5'     => 'big5_chinese_ci',
		'binary'   => 'binary',
		'cp1250'   => 'cp1250_general_ci',
		'cp1251'   => 'cp1251_general_ci',
		'cp1256'   => 'cp1256_general_ci',
		'cp1257'   => 'cp1257_general_ci',
		'cp850'    => 'cp850_general_ci',
		'cp852'    => 'cp852_general_ci',
		'cp866'    => 'cp866_general_ci',
		'cp932'    => 'cp932_japanese_ci',
		'dec8'     => 'dec8_swedish_ci',
		'eucjpms'  => 'eucjpms_japanese_ci',
		'euckr'    => 'euckr_korean_ci',
		'gb18030'  => 'gb18030_chinese_ci',
		'gb2312'   => 'gb2312_chinese_ci',
		'gbk'      => 'gbk_chinese_ci',
		'geostd8'  => 'geostd8_general_ci',
		'greek'    => 'greek_general_ci',
		'hebrew'   => 'hebrew_general_ci',
		'hp8'      => 'hp8_english_ci',
		'keybcs2'  => 'keybcs2_general_ci',
		'koi8r'    => 'koi8r_general_ci',
		'koi8u'    => 'koi8u_general_ci',
		'latin1'   => 'latin1_swedish_ci',
		'latin2'   => 'latin2_general_ci',
		'latin5'   => 'latin5_turkish_ci',
		'latin7'   => 'latin7_general_ci',
		'macce'    => 'macce_general_ci',
		'macroman' => 'macroman_general_ci',
		'sjis'     => 'sjis_japanese_ci',
		'swe7'     => 'swe7_swedish_ci',
		'tis620'   => 'tis620_thai_ci',
		'ucs2'     => 'ucs2_general_ci',
		'ujis'     => 'ujis_japanese_ci',
		'utf16'    => 'utf16_general_ci',
		'utf16le'  => 'utf16le_general_ci',
		'utf32'    => 'utf32_general_ci',
		'utf8'     => 'utf8_general_ci',
		'utf8mb4'  => 'utf8mb4_general_ci', // @TODO: From MySQL 8.0.1, this is utf8mb4_0900_ai_ci.
	);

	/**
	 * Maximum number of bytes per character for each charset.
	 * The map contains only multi-byte charsets.
	 * Charsets that are not included are single-byte.
	 */
	const CHARSET_MAX_BYTES_MAP = array(
		'big5'    => 2,
		'cp932'   => 2,
		'eucjpms' => 3,
		'euckr'   => 2,
		'gb18030' => 4,
		'gb2312'  => 2,
		'gbk'     => 2,
		'sjis'    => 2,
		'ucs2'    => 2,
		'ujis'    => 3,
		'utf16'   => 4,
		'utf16le' => 4,
		'utf32'   => 4,
		'utf8'    => 3,
		'utf8mb4' => 4,
	);

	/**
	 * Database name.
	 *
	 * @TODO: Consider passing the database name as a parameter to each method.
	 *        This would expose an API that could support multiple databases
	 *        in the future. Alternatively, it could be a stateful property.
	 *
	 * @var string
	 */
	private $db_name;

	/**
	 * Query callback.
	 *
	 * @TODO: Consider extracting a part of the WP_SQLite_Driver class
	 *        to a class like "WP_SQLite_Connection" and reuse it in both.
	 *
	 * @var callable(string, array): PDOStatement
	 */
	private $query_callback;

	/**
	 * Constructor.
	 *
	 * @param string $db_name
	 * @param callable(string, array): PDOStatement $query_callback
	 */
	public function __construct( string $db_name, callable $query_callback ) {
		$this->db_name        = $db_name;
		$this->query_callback = $query_callback;
	}

	/**
	 * Ensure that the supported information schema tables exist in the SQLite
	 * database. Tables that are missing will be created.
	 */
	public function ensure_information_schema_tables(): void {
		foreach ( self::CREATE_INFORMATION_SCHEMA_QUERIES as $query ) {
			$this->query( $query );
		}
	}

	/**
	 * Analyze CREATE TABLE statement and record data in the information schema.
	 *
	 * @param WP_Parser_Node $node AST node representing a CREATE TABLE statement.
	 */
	public function record_create_table( WP_Parser_Node $node ): void {
		$table_name       = $this->get_value( $node->get_descendant_node( 'tableName' ) );
		$table_engine     = $this->get_table_engine( $node );
		$table_row_format = 'MyISAM' === $table_engine ? 'FIXED' : 'DYNAMIC';
		$table_collation  = $this->get_table_collation( $node );

		// 1. Table.
		$this->insert_values(
			'_mysql_information_schema_tables',
			array(
				'table_schema'    => $this->db_name,
				'table_name'      => $table_name,
				'table_type'      => 'BASE TABLE',
				'engine'          => $table_engine,
				'row_format'      => $table_row_format,
				'table_collation' => $table_collation,
			)
		);

		// 2. Columns.
		$column_position = 1;
		foreach ( $node->get_descendant_nodes( 'columnDefinition' ) as $column_node ) {
			$column_name = $this->get_value( $column_node->get_child_node( 'columnName' ) );

			// Column definition.
			$column_data = $this->extract_column_data(
				$table_name,
				$column_name,
				$column_node,
				$column_position
			);
			$this->insert_values( '_mysql_information_schema_columns', $column_data );

			// Inline column constraint.
			$column_constraint_data = $this->extract_column_constraint_data(
				$table_name,
				$column_name,
				$column_node,
				'YES' === $column_data['is_nullable']
			);
			if ( null !== $column_constraint_data ) {
				$this->insert_values(
					'_mysql_information_schema_statistics',
					$column_constraint_data
				);
			}

			$column_position += 1;
		}

		// 3. Constraints.
		foreach ( $node->get_descendant_nodes( 'tableConstraintDef' ) as $constraint_node ) {
			$this->record_add_constraint( $table_name, $constraint_node );
		}
	}

	public function record_alter_table( WP_Parser_Node $node ): void {
		$table_name = $this->get_value( $node->get_descendant_node( 'tableRef' ) );
		$actions    = $node->get_descendant_nodes( 'alterListItem' );

		foreach ( $actions as $action ) {
			$first_token = $action->get_child_token();

			// ADD
			if ( WP_MySQL_Lexer::ADD_SYMBOL === $first_token->id ) {
				// ADD [COLUMN] (...[, ...])
				$column_definitions = $action->get_descendant_nodes( 'columnDefinition' );
				if ( count( $column_definitions ) > 0 ) {
					foreach ( $column_definitions as $column_definition ) {
						$name = $this->get_value( $column_definition->get_child_node( 'identifier' ) );
						$this->record_add_column( $table_name, $name, $column_definition );
					}
					continue;
				}

				// ADD [COLUMN] ...
				$field_definition = $action->get_descendant_node( 'fieldDefinition' );
				if ( null !== $field_definition ) {
					$name = $this->get_value( $action->get_child_node( 'identifier' ) );
					$this->record_add_column( $table_name, $name, $field_definition );
					// @TODO: Handle FIRST/AFTER.
					continue;
				}

				// ADD CONSTRAINT.
				$constraint = $action->get_descendant_node( 'tableConstraintDef' );
				if ( null !== $constraint ) {
					$this->record_add_constraint( $table_name, $constraint );
					continue;
				}

				throw new \Exception( sprintf( 'Unsupported ALTER TABLE ADD action: %s', $first_token->value ) );
			}

			// CHANGE [COLUMN]
			if ( WP_MySQL_Lexer::CHANGE_SYMBOL === $first_token->id ) {
				$old_name = $this->get_value( $action->get_child_node( 'columnInternalRef' ) );
				$new_name = $this->get_value( $action->get_child_node( 'identifier' ) );
				$this->record_change_column(
					$table_name,
					$old_name,
					$new_name,
					$action->get_descendant_node( 'fieldDefinition' )
				);
				continue;
			}

			// MODIFY [COLUMN]
			if ( WP_MySQL_Lexer::MODIFY_SYMBOL === $first_token->id ) {
				$name = $this->get_value( $action->get_child_node( 'columnInternalRef' ) );
				$this->record_modify_column(
					$table_name,
					$name,
					$action->get_descendant_node( 'fieldDefinition' )
				);
				continue;
			}

			// DROP
			if ( WP_MySQL_Lexer::DROP_SYMBOL === $first_token->id ) {
				// DROP [COLUMN]
				$column_ref = $action->get_child_node( 'columnInternalRef' );
				if ( null !== $column_ref ) {
					$name = $this->get_value( $column_ref );
					$this->record_drop_column( $table_name, $name );
					continue;
				}

				// DROP INDEX
				if ( $action->has_child_node( 'keyOrIndex' ) ) {
					$name = $this->get_value( $action->get_child_node( 'indexRef' ) );
					$this->record_drop_index( $table_name, $name );
					continue;
				}
			}
		}
	}

	public function record_drop_table( WP_Parser_Node $node ): void {
		$child_node = $node->get_child_node();
		if ( $child_node->has_child_token( WP_MySQL_Lexer::TEMPORARY_SYMBOL ) ) {
			return;
		}

		$table_refs = $child_node->get_child_node( 'tableRefList' )->get_child_nodes();
		foreach ( $table_refs as $table_ref ) {
			$table_name = $this->get_value( $table_ref );
			$this->delete_values(
				'_mysql_information_schema_tables',
				array(
					'table_schema' => $this->db_name,
					'table_name'   => $table_name,
				)
			);
			$this->delete_values(
				'_mysql_information_schema_columns',
				array(
					'table_schema' => $this->db_name,
					'table_name'   => $table_name,
				)
			);
			$this->delete_values(
				'_mysql_information_schema_statistics',
				array(
					'table_schema' => $this->db_name,
					'table_name'   => $table_name,
				)
			);
		}

		// @TODO: RESTRICT vs. CASCADE
	}

	private function record_add_column( string $table_name, string $column_name, WP_Parser_Node $node ): void {
		$position = $this->query(
			'
				SELECT MAX(ordinal_position)
				FROM _mysql_information_schema_columns
				WHERE table_schema = ?
				AND table_name = ?
			',
			array( $this->db_name, $table_name )
		)->fetchColumn();

		$column_data = $this->extract_column_data( $table_name, $column_name, $node, (int) $position + 1 );
		$this->insert_values( '_mysql_information_schema_columns', $column_data );

		$column_constraint_data = $this->extract_column_constraint_data( $table_name, $column_name, $node, true );
		if ( null !== $column_constraint_data ) {
			$this->insert_values( '_mysql_information_schema_statistics', $column_constraint_data );
		}
	}

	private function record_change_column(
		string $table_name,
		string $column_name,
		string $new_column_name,
		WP_Parser_Node $node
	): void {
		$column_data = $this->extract_column_data( $table_name, $new_column_name, $node, 0 );
		$this->update_values(
			'_mysql_information_schema_columns',
			$column_data,
			array(
				'table_schema' => $this->db_name,
				'table_name'   => $table_name,
				'column_name'  => $column_name,
			)
		);

		// Update column name in statistics, if it has changed.
		if ( $new_column_name !== $column_name ) {
			$this->update_values(
				'_mysql_information_schema_statistics',
				array(
					'column_name' => $new_column_name,
				),
				array(
					'table_schema' => $this->db_name,
					'table_name'   => $table_name,
					'column_name'  => $column_name,
				)
			);
		}

		// Handle inline constraints. When inline constraint is defined, MySQL
		// always adds a new constraint rather than replacing an existing one.
		$column_constraint_data = $this->extract_column_constraint_data(
			$table_name,
			$new_column_name,
			$node,
			'YES' === $column_data['is_nullable']
		);
		if ( null !== $column_constraint_data ) {
			$this->insert_values( '_mysql_information_schema_statistics', $column_constraint_data );
			$this->sync_column_key_info( $table_name );
		}
	}

	private function record_modify_column(
		string $table_name,
		string $column_name,
		WP_Parser_Node $node
	): void {
		$this->record_change_column( $table_name, $column_name, $column_name, $node );
	}

	private function record_drop_column( $table_name, $column_name ): void {
		$this->delete_values(
			'_mysql_information_schema_columns',
			array(
				'table_schema' => $this->db_name,
				'table_name'   => $table_name,
				'column_name'  => $column_name,
			)
		);

		/**
		 * From MySQL documentation:
		 *
		 *   If columns are dropped from a table, the columns are also removed
		 *   from any index of which they are a part. If all columns that make up
		 *   an index are dropped, the index is dropped as well.
		 *
		 * This means we need to remove the records from the STATISTICS table,
		 * renumber the SEQ_IN_INDEX values, and resync the column key info.
		 *
		 * See:
		 *   - https://dev.mysql.com/doc/refman/8.4/en/alter-table.html
		 */
		$this->delete_values(
			'_mysql_information_schema_statistics',
			array(
				'table_schema' => $this->db_name,
				'table_name'   => $table_name,
				'column_name'  => $column_name,
			)
		);

		// @TODO: Renumber SEQ_IN_INDEX values.

		$this->sync_column_key_info( $table_name );
	}

	private function record_drop_index( string $table_name, string $index_name ): void {
		$this->delete_values(
			'_mysql_information_schema_statistics',
			array(
				'table_schema' => $this->db_name,
				'table_name'   => $table_name,
				'index_name'   => $index_name,
			)
		);
		$this->sync_column_key_info( $table_name );
	}

	private function record_add_constraint( string $table_name, WP_Parser_Node $node ): void {
		// Get first constraint keyword.
		$children = $node->get_children();
		$keyword  = $children[0] instanceof WP_MySQL_Token ? $children[0] : $children[1];
		if ( ! $keyword instanceof WP_MySQL_Token ) {
			$keyword = $keyword->get_child_token();
		}

		if (
			WP_MySQL_Lexer::FOREIGN_SYMBOL === $keyword->id
			|| WP_MySQL_Lexer::CHECK_SYMBOL === $keyword->id
		) {
			throw new \Exception( 'FOREIGN KEY and CHECK constraints are not supported yet.' );
		}

		// Get key parts.
		$key_list = $node->get_child_node( 'keyListVariants' )->get_child();
		if ( 'keyListWithExpression' === $key_list->rule_name ) {
			$key_parts = array();
			foreach ( $key_list->get_descendant_nodes( 'keyPartOrExpression' ) as $key_part ) {
				$key_parts[] = $key_part->get_child();
			}
		} else {
			$key_parts = $key_list->get_descendant_nodes( 'keyPart' );
		}

		// Get index column names.
		$key_part_column_names = array();
		foreach ( $key_parts as $key_part ) {
			$key_part_column_names[] = $this->get_index_column_name( $key_part );
		}

		// Fetch column info.
		$column_names = array_filter( $key_part_column_names );
		if ( count( $column_names ) > 0 ) {
			$column_info = $this->query(
				'
					SELECT column_name, data_type, is_nullable, character_maximum_length
					FROM _mysql_information_schema_columns
					WHERE table_schema = ?
					AND table_name = ?
					AND column_name IN (' . implode( ',', array_fill( 0, count( $column_names ), '?' ) ) . ')
				',
				array_merge( array( $this->db_name, $table_name ), $column_names )
			)->fetchAll(
				PDO::FETCH_ASSOC // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO
			);
		} else {
			$column_info = array();
		}

		$column_info_map = array_combine(
			array_column( $column_info, 'COLUMN_NAME' ),
			$column_info
		);

		// Get first index column data type (needed for index type).
		$first_column_name  = $this->get_index_column_name( $key_parts[0] );
		$first_column_type  = $column_info_map[ $first_column_name ]['DATA_TYPE'] ?? null;
		$has_spatial_column = null !== $first_column_type && $this->is_spatial_data_type( $first_column_type );

		$non_unique = $this->get_index_non_unique( $keyword );
		$index_name = $this->get_index_name( $node );
		$index_type = $this->get_index_type( $node, $keyword, $has_spatial_column );

		$seq_in_index = 1;
		foreach ( $key_parts as $i => $key_part ) {
			$column_name = $key_part_column_names[ $i ];
			$collation   = $this->get_index_column_collation( $key_part, $index_type );
			if (
				'PRIMARY' === $index_name
				|| 'NO' === $column_info_map[ $column_name ]['IS_NULLABLE']
			) {
				$nullable = '';
			} else {
				$nullable = 'YES';
			}

			$sub_part = $this->get_index_column_sub_part(
				$key_part,
				$column_info_map[ $column_name ]['CHARACTER_MAXIMUM_LENGTH'],
				$has_spatial_column
			);

			$this->insert_values(
				'_mysql_information_schema_statistics',
				array(
					'table_schema'  => $this->db_name,
					'table_name'    => $table_name,
					'non_unique'    => $non_unique,
					'index_schema'  => $this->db_name,
					'index_name'    => $index_name,
					'seq_in_index'  => $seq_in_index,
					'column_name'   => $column_name,
					'collation'     => $collation,
					'cardinality'   => 0, // not implemented
					'sub_part'      => $sub_part,
					'packed'        => null, // not implemented
					'nullable'      => $nullable,
					'index_type'    => $index_type,
					'comment'       => '', // not implemented
					'index_comment' => '', // @TODO
					'is_visible'    => 'YES', // @TODO: Save actual visibility value.
					'expression'    => null, // @TODO
				)
			);

			$seq_in_index += 1;
		}

		$this->sync_column_key_info( $table_name );
	}

	private function extract_column_data( string $table_name, string $column_name, WP_Parser_Node $node, int $position ): array {
		$default  = $this->get_column_default( $node );
		$nullable = $this->get_column_nullable( $node );
		$key      = $this->get_column_key( $node );
		$extra    = $this->get_column_extra( $node );
		$comment  = $this->get_column_comment( $node );

		list ( $data_type, $column_type )    = $this->get_column_data_types( $node );
		list ( $charset, $collation )        = $this->get_column_charset_and_collation( $node, $data_type );
		list ( $char_length, $octet_length ) = $this->get_column_lengths( $node, $data_type, $charset );
		list ( $precision, $scale )          = $this->get_column_numeric_attributes( $node, $data_type );
		$datetime_precision                  = $this->get_column_datetime_precision( $node, $data_type );
		$generation_expression               = $this->get_column_generation_expression( $node );

		return array(
			'table_schema'             => $this->db_name,
			'table_name'               => $table_name,
			'column_name'              => $column_name,
			'ordinal_position'         => $position,
			'column_default'           => $default,
			'is_nullable'              => $nullable,
			'data_type'                => $data_type,
			'character_maximum_length' => $char_length,
			'character_octet_length'   => $octet_length,
			'numeric_precision'        => $precision,
			'numeric_scale'            => $scale,
			'datetime_precision'       => $datetime_precision,
			'character_set_name'       => $charset,
			'collation_name'           => $collation,
			'column_type'              => $column_type,
			'column_key'               => $key,
			'extra'                    => $extra,
			'privileges'               => 'select,insert,update,references',
			'column_comment'           => $comment,
			'generation_expression'    => $generation_expression,
			'srs_id'                   => null, // not implemented
		);
	}

	private function extract_column_constraint_data( string $table_name, string $column_name, WP_Parser_Node $node, bool $nullable ): ?array {
		// Handle inline PRIMARY KEY and UNIQUE constraints.
		$has_inline_primary_key = null !== $node->get_descendant_token( WP_MySQL_Lexer::KEY_SYMBOL );
		$has_inline_unique_key  = null !== $node->get_descendant_token( WP_MySQL_Lexer::UNIQUE_SYMBOL );
		if ( $has_inline_primary_key || $has_inline_unique_key ) {
			$index_name = $has_inline_primary_key ? 'PRIMARY' : $column_name;
			return array(
				'table_schema'  => $this->db_name,
				'table_name'    => $table_name,
				'non_unique'    => 0,
				'index_schema'  => $this->db_name,
				'index_name'    => $index_name,
				'seq_in_index'  => 1,
				'column_name'   => $column_name,
				'collation'     => 'A',
				'cardinality'   => 0, // not implemented
				'sub_part'      => null,
				'packed'        => null, // not implemented
				'nullable'      => true === $nullable ? 'YES' : '',
				'index_type'    => 'BTREE',
				'comment'       => '', // not implemented
				'index_comment' => '', // @TODO
				'is_visible'    => 'YES', // @TODO: Save actual visibility value.
				'expression'    => null, // @TODO
			);
		}
		return null;
	}

	/**
	 * Update column info from constraint data in the statistics table.
	 *
	 * When constraints are added or removed, we need to reflect the changes
	 * in the "COLUMN_KEY" and "IS_NULLABLE" columns of the "COLUMNS" table.
	 *
	 *   A) COLUMN_KEY (priority from 1 to 4):
	 *     1. "PRI": Column is any component of a PRIMARY KEY.
	 *     2. "UNI": Column is the first column of a UNIQUE KEY.
	 *     3. "MUL": Column is the first column of a non-unique index.
	 *     4. "":    Column is not indexed.
	 *
	 *   B) IS_NULLABLE: In COLUMNS, "YES"/"NO". In STATISTICS, "YES"/"".
	 */
	private function sync_column_key_info( string $table_name ): void {
		// @TODO: Consider listing only affected columns.
		$this->query(
			"
				WITH s AS (
					SELECT
						column_name,
						CASE
							WHEN MAX(index_name = 'PRIMARY') THEN 'PRI'
							WHEN MAX(non_unique = 0 AND seq_in_index = 1) THEN 'UNI'
							WHEN MAX(seq_in_index = 1) THEN 'MUL'
							ELSE ''
						END AS column_key
					FROM _mysql_information_schema_statistics
					WHERE table_schema = ?
					AND table_name = ?
					GROUP BY column_name
				)
				UPDATE _mysql_information_schema_columns AS c
				SET
					column_key = s.column_key,
					is_nullable = IIF(s.column_key = 'PRI', 'NO', c.is_nullable)
			    FROM s
			    WHERE c.table_schema = ?
			    AND c.table_name = ?
				AND s.column_name = c.column_name
			",
			array( $this->db_name, $table_name, $this->db_name, $table_name )
		);
	}

	private function get_table_engine( WP_Parser_Node $node ): string {
		$engine_node = $node->get_descendant_node( 'engineRef' );
		if ( null === $engine_node ) {
			return 'InnoDB';
		}

		$engine = strtoupper( $this->get_value( $engine_node ) );
		if ( 'INNODB' === $engine ) {
			return 'InnoDB';
		} elseif ( 'MYISAM' === $engine ) {
			return 'MyISAM';
		}
		return $engine;
	}

	private function get_table_collation( WP_Parser_Node $node ): string {
		$collate_node = $node->get_descendant_node( 'collationName' );
		if ( null === $collate_node ) {
			// @TODO: Use default DB collation or DB_CHARSET & DB_COLLATE.
			return 'utf8mb4_general_ci';
		}
		return strtolower( $this->get_value( $collate_node ) );
	}

	private function get_column_default( WP_Parser_Node $node ): ?string {
		foreach ( $node->get_descendant_nodes( 'columnAttribute' ) as $attr ) {
			if ( $attr->has_child_token( WP_MySQL_Lexer::DEFAULT_SYMBOL ) ) {
				// @TODO: MySQL seems to normalize default values for numeric
				//        columns, such as 1.0 to 1, 1e3 to 1000, etc.
				return substr( $this->get_value( $attr ), strlen( 'DEFAULT' ) );
			}
		}
		return null;
	}

	private function get_column_nullable( WP_Parser_Node $node ): string {
		// SERIAL is an alias for BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE.
		$data_type = $node->get_descendant_node( 'dataType' );
		if ( null !== $data_type->get_descendant_token( WP_MySQL_Lexer::SERIAL_SYMBOL ) ) {
			return 'NO';
		}

		foreach ( $node->get_descendant_nodes( 'columnAttribute' ) as $attr ) {
			// PRIMARY KEY columns are always NOT NULL.
			if ( $attr->has_child_token( WP_MySQL_Lexer::KEY_SYMBOL ) ) {
				return 'NO';
			}

			// Check for NOT NULL attribute.
			if (
				$attr->has_child_token( WP_MySQL_Lexer::NOT_SYMBOL )
				&& $attr->has_child_node( 'nullLiteral' )
			) {
				return 'NO';
			}
		}
		return 'YES';
	}

	private function get_column_key( WP_Parser_Node $column_node ): string {
		// 1. PRI: Column is a primary key or its any component.
		if (
			null !== $column_node->get_descendant_token( WP_MySQL_Lexer::KEY_SYMBOL )
		) {
			return 'PRI';
		}

		// SERIAL is an alias for BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE.
		$data_type = $column_node->get_descendant_node( 'dataType' );
		if ( null !== $data_type->get_descendant_token( WP_MySQL_Lexer::SERIAL_SYMBOL ) ) {
			return 'PRI';
		}

		// 2. UNI: Column has UNIQUE constraint.
		if ( null !== $column_node->get_descendant_token( WP_MySQL_Lexer::UNIQUE_SYMBOL ) ) {
			return 'UNI';
		}

		// 3. MUL: Column has INDEX.
		if ( null !== $column_node->get_descendant_token( WP_MySQL_Lexer::INDEX_SYMBOL ) ) {
			return 'MUL';
		}

		return '';
	}

	private function get_column_extra( WP_Parser_Node $node ): string {
		$extra = '';

		// SERIAL
		$data_type = $node->get_descendant_node( 'dataType' );
		if ( null !== $data_type->get_descendant_token( WP_MySQL_Lexer::SERIAL_SYMBOL ) ) {
			return 'auto_increment';
		}

		foreach ( $node->get_descendant_nodes( 'columnAttribute' ) as $attr ) {
			if ( $attr->has_child_token( WP_MySQL_Lexer::AUTO_INCREMENT_SYMBOL ) ) {
				return 'auto_increment';
			}
			if (
				$attr->has_child_token( WP_MySQL_Lexer::ON_SYMBOL )
				&& $attr->has_child_token( WP_MySQL_Lexer::UPDATE_SYMBOL )
			) {
				return 'on update CURRENT_TIMESTAMP';
			}
		}

		if ( $node->get_descendant_token( WP_MySQL_Lexer::VIRTUAL_SYMBOL ) ) {
			$extra = 'VIRTUAL GENERATED';
		} elseif ( $node->get_descendant_token( WP_MySQL_Lexer::STORED_SYMBOL ) ) {
			$extra = 'STORED GENERATED';
		}
		return $extra;
	}

	private function get_column_comment( WP_Parser_Node $node ): string {
		foreach ( $node->get_descendant_nodes( 'columnAttribute' ) as $attr ) {
			if ( $attr->has_child_token( WP_MySQL_Lexer::COMMENT_SYMBOL ) ) {
				return $this->get_value( $attr->get_child_node( 'textLiteral' ) );
			}
		}
		return '';
	}

	private function get_column_data_types( WP_Parser_Node $node ): array {
		$type_node = $node->get_descendant_node( 'dataType' );
		$type      = $type_node->get_descendant_tokens();
		$token     = $type[0];

		// Normalize types.
		if ( isset( self::TOKEN_TO_TYPE_MAP[ $token->id ] ) ) {
			$type = self::TOKEN_TO_TYPE_MAP[ $token->id ];
		} elseif (
			// VARCHAR/NVARCHAR
			// NCHAR/NATIONAL VARCHAR
			// CHAR/CHARACTER/NCHAR VARYING
			// NATIONAL CHAR/CHARACTER VARYING
			WP_MySQL_Lexer::VARCHAR_SYMBOL === $token->id
			|| WP_MySQL_Lexer::NVARCHAR_SYMBOL === $token->id
			|| ( isset( $type[1] ) && WP_MySQL_Lexer::VARCHAR_SYMBOL === $type[1]->id )
			|| ( isset( $type[1] ) && WP_MySQL_Lexer::VARYING_SYMBOL === $type[1]->id )
			|| ( isset( $type[2] ) && WP_MySQL_Lexer::VARYING_SYMBOL === $type[2]->id )
		) {
			$type = 'varchar';
		} elseif (
			// CHAR, NCHAR, NATIONAL CHAR
			WP_MySQL_Lexer::CHAR_SYMBOL === $token->id
			|| WP_MySQL_Lexer::NCHAR_SYMBOL === $token->id
			|| isset( $type[1] ) && WP_MySQL_Lexer::CHAR_SYMBOL === $type[1]->id
		) {
			$type = 'char';
		} elseif (
			// LONG VARBINARY
			WP_MySQL_Lexer::LONG_SYMBOL === $token->id
			&& isset( $type[1] ) && WP_MySQL_Lexer::VARBINARY_SYMBOL === $type[1]->id
		) {
			$type = 'mediumblob';
		} elseif (
			// LONG CHAR/CHARACTER, LONG CHAR/CHARACTER VARYING
			WP_MySQL_Lexer::LONG_SYMBOL === $token->id
			&& isset( $type[1] ) && WP_MySQL_Lexer::CHAR_SYMBOL === $type[1]->id
		) {
			$type = 'mediumtext';
		} elseif (
			// LONG VARCHAR
			WP_MySQL_Lexer::LONG_SYMBOL === $token->id
			&& isset( $type[1] ) && WP_MySQL_Lexer::VARCHAR_SYMBOL === $type[1]->id
		) {
			$type = 'mediumtext';
		} else {
			throw new \RuntimeException( 'Unknown data type: ' . $token->value );
		}

		// Get full type.
		$full_type = $type;
		if ( 'enum' === $type || 'set' === $type ) {
			$string_list = $type_node->get_descendant_node( 'stringList' );
			$values      = $string_list->get_child_nodes( 'textString' );
			foreach ( $values as $i => $value ) {
				$values[ $i ] = "'" . str_replace( "'", "''", $this->get_value( $value ) ) . "'";
			}
			$full_type .= '(' . implode( ',', $values ) . ')';
		}

		$field_length = $type_node->get_descendant_node( 'fieldLength' );
		if ( null !== $field_length ) {
			if ( 'decimal' === $type || 'float' === $type || 'double' === $type ) {
				$full_type .= rtrim( $this->get_value( $field_length ), ')' ) . ',0)';
			} else {
				$full_type .= $this->get_value( $field_length );
			}
			/*
			 * As of MySQL 8.0.17, the display width attribute is deprecated for
			 * integer types (tinyint, smallint, mediumint, int/integer, bigint)
			 * and is not stored anymore. However, it may be important for older
			 * versions and WP's dbDelta, so it is safer to keep it at the moment.
			 * @TODO: Investigate if it is important to keep this.
			 */
		}

		$precision = $type_node->get_descendant_node( 'precision' );
		if ( null !== $precision ) {
			$full_type .= $this->get_value( $precision );
		}

		$datetime_precision = $type_node->get_descendant_node( 'typeDatetimePrecision' );
		if ( null !== $datetime_precision ) {
			$full_type .= $this->get_value( $datetime_precision );
		}

		if (
			WP_MySQL_Lexer::BOOL_SYMBOL === $token->id
			|| WP_MySQL_Lexer::BOOLEAN_SYMBOL === $token->id
		) {
			$full_type .= '(1)'; // Add length for booleans.
		}

		if ( null === $field_length && null === $precision ) {
			if ( 'decimal' === $type ) {
				$full_type .= '(10,0)'; // Add default precision for decimals.
			} elseif ( 'char' === $type || 'bit' === $type || 'binary' === $type ) {
				$full_type .= '(1)';    // Add default length for char, bit, binary.
			}
		}

		// UNSIGNED.
		// SERIAL is an alias for BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE.
		if (
			$type_node->get_descendant_token( WP_MySQL_Lexer::UNSIGNED_SYMBOL )
			|| $type_node->get_descendant_token( WP_MySQL_Lexer::SERIAL_SYMBOL )
		) {
			$full_type .= ' unsigned';
		}

		// ZEROFILL.
		if ( $type_node->get_descendant_token( WP_MySQL_Lexer::ZEROFILL_SYMBOL ) ) {
			$full_type .= ' zerofill';
		}

		return array( $type, $full_type );
	}

	private function get_column_charset_and_collation( WP_Parser_Node $node, string $data_type ): array {
		if ( ! (
			'char' === $data_type
			|| 'varchar' === $data_type
			|| 'tinytext' === $data_type
			|| 'text' === $data_type
			|| 'mediumtext' === $data_type
			|| 'longtext' === $data_type
			|| 'enum' === $data_type
			|| 'set' === $data_type
		) ) {
			return array( null, null );
		}

		$charset   = null;
		$collation = null;
		$is_binary = false;

		// Charset.
		$charset_node = $node->get_descendant_node( 'charsetWithOptBinary' );
		if ( null !== $charset_node ) {
			$charset_name_node = $charset_node->get_child_node( 'charsetName' );
			if ( null !== $charset_name_node ) {
				$charset = strtolower( $this->get_value( $charset_name_node ) );
			} elseif ( $charset_node->has_child_token( WP_MySQL_Lexer::ASCII_SYMBOL ) ) {
				$charset = 'latin1';
			} elseif ( $charset_node->has_child_token( WP_MySQL_Lexer::UNICODE_SYMBOL ) ) {
				$charset = 'ucs2';
			} elseif ( $charset_node->has_child_token( WP_MySQL_Lexer::BYTE_SYMBOL ) ) {
				// @TODO: This changes varchar to varbinary.
			}

			// @TODO: "DEFAULT"

			if ( $charset_node->has_child_token( WP_MySQL_Lexer::BINARY_SYMBOL ) ) {
				$is_binary = true;
			}
		} else {
			// National charsets (in MySQL, it's "utf8").
			$data_type_node = $node->get_descendant_node( 'dataType' );
			if (
				$data_type_node->has_child_node( 'nchar' )
				|| $data_type_node->has_child_token( WP_MySQL_Lexer::NCHAR_SYMBOL )
				|| $data_type_node->has_child_token( WP_MySQL_Lexer::NATIONAL_SYMBOL )
				|| $data_type_node->has_child_token( WP_MySQL_Lexer::NVARCHAR_SYMBOL )
			) {
				$charset = 'utf8';
			}
		}

		// Normalize charset.
		if ( 'utf8mb3' === $charset ) {
			$charset = 'utf8';
		}

		// Collation.
		$collation_node = $node->get_descendant_node( 'collationName' );
		if ( null !== $collation_node ) {
			$collation = strtolower( $this->get_value( $collation_node ) );
		}

		// Defaults.
		// @TODO: These are hardcoded now. We should get them from table/DB.
		if ( null === $charset && null === $collation ) {
			$charset = 'utf8mb4';
			// @TODO: "BINARY" (seems to change varchar to varbinary).
			// @TODO: "DEFAULT"
		}

		// If only one of charset/collation is set, the other one is derived.
		if ( null === $collation ) {
			if ( $is_binary ) {
				$collation = $charset . '_bin';
			} elseif ( isset( self::CHARSET_DEFAULT_COLLATION_MAP[ $charset ] ) ) {
				$collation = self::CHARSET_DEFAULT_COLLATION_MAP[ $charset ];
			} else {
				$collation = $charset . '_general_ci';
			}
		} elseif ( null === $charset ) {
			$charset = substr( $collation, 0, strpos( $collation, '_' ) );
		}

		return array( $charset, $collation );
	}

	private function get_column_lengths( WP_Parser_Node $node, string $data_type, ?string $charset ): array {
		// Text and blob types.
		if ( 'tinytext' === $data_type || 'tinyblob' === $data_type ) {
			return array( 255, 255 );
		} elseif ( 'text' === $data_type || 'blob' === $data_type ) {
			return array( 65535, 65535 );
		} elseif ( 'mediumtext' === $data_type || 'mediumblob' === $data_type ) {
			return array( 16777215, 16777215 );
		} elseif ( 'longtext' === $data_type || 'longblob' === $data_type ) {
			return array( 4294967295, 4294967295 );
		}

		// For CHAR, VARCHAR, BINARY, VARBINARY, we need to check the field length.
		if (
			'char' === $data_type
			|| 'binary' === $data_type
			|| 'varchar' === $data_type
			|| 'varbinary' === $data_type
		) {
			$field_length = $node->get_descendant_node( 'fieldLength' );
			if ( null === $field_length ) {
				$length = 1;
			} else {
				$length = (int) trim( $this->get_value( $field_length ), '()' );
			}

			if ( 'char' === $data_type || 'varchar' === $data_type ) {
				$max_bytes_per_char = self::CHARSET_MAX_BYTES_MAP[ $charset ] ?? 1;
				return array( $length, $max_bytes_per_char * $length );
			} else {
				return array( $length, $length );
			}
		}

		// For ENUM and SET, we need to check the longest value.
		if ( 'enum' === $data_type || 'set' === $data_type ) {
			$string_list = $node->get_descendant_node( 'stringList' );
			$values      = $string_list->get_child_nodes( 'textString' );
			$length      = 0;
			foreach ( $values as $value ) {
				$length = max( $length, strlen( $this->get_value( $value ) ) );
			}
			$max_bytes_per_char = self::CHARSET_MAX_BYTES_MAP[ $charset ] ?? 1;
			return array( $length, $max_bytes_per_char * $length );
		}

		return array( null, null );
	}

	private function get_column_numeric_attributes( WP_Parser_Node $node, string $data_type ): array {
		if ( 'tinyint' === $data_type ) {
			return array( 3, 0 );
		} elseif ( 'smallint' === $data_type ) {
			return array( 5, 0 );
		} elseif ( 'mediumint' === $data_type ) {
			return array( 7, 0 );
		} elseif ( 'int' === $data_type ) {
			return array( 10, 0 );
		} elseif ( 'bigint' === $data_type ) {
			if ( null !== $node->get_descendant_token( WP_MySQL_Lexer::UNSIGNED_SYMBOL ) ) {
				return array( 20, 0 );
			}

			// SERIAL is an alias for BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE.
			$data_type = $node->get_descendant_node( 'dataType' );
			if ( null !== $data_type->get_descendant_token( WP_MySQL_Lexer::SERIAL_SYMBOL ) ) {
				return array( 20, 0 );
			}

			return array( 19, 0 );
		}

		// For bit columns, we need to check the precision.
		if ( 'bit' === $data_type ) {
			$field_length = $node->get_descendant_node( 'fieldLength' );
			if ( null === $field_length ) {
				return array( 1, null );
			}
			return array( (int) trim( $this->get_value( $field_length ), '()' ), null );
		}

		// For floating point numbers, we need to check the precision and scale.
		$precision      = null;
		$scale          = null;
		$precision_node = $node->get_descendant_node( 'precision' );
		if ( null !== $precision_node ) {
			$values    = $precision_node->get_descendant_tokens( WP_MySQL_Lexer::INT_NUMBER );
			$precision = (int) $values[0]->value;
			$scale     = (int) $values[1]->value;
		}

		if ( 'float' === $data_type ) {
			return array( $precision ?? 12, $scale );
		} elseif ( 'double' === $data_type ) {
			return array( $precision ?? 22, $scale );
		} elseif ( 'decimal' === $data_type ) {
			if ( null === $precision ) {
				// Only precision can be specified ("fieldLength" in the grammar).
				$field_length = $node->get_descendant_node( 'fieldLength' );
				if ( null !== $field_length ) {
					$precision = (int) trim( $this->get_value( $field_length ), '()' );
				}
			}
			return array( $precision ?? 10, $scale ?? 0 );
		}

		return array( null, null );
	}

	private function get_column_datetime_precision( WP_Parser_Node $node, string $data_type ): ?int {
		if ( 'time' === $data_type || 'datetime' === $data_type || 'timestamp' === $data_type ) {
			$precision = $node->get_descendant_node( 'typeDatetimePrecision' );
			if ( null === $precision ) {
				return 0;
			} else {
				return (int) $this->get_value( $precision );
			}
		}
		return null;
	}

	private function get_column_generation_expression( WP_Parser_Node $node ): string {
		if ( null !== $node->get_descendant_token( WP_MySQL_Lexer::GENERATED_SYMBOL ) ) {
			$expr = $node->get_descendant_node( 'exprWithParentheses' );
			return $this->get_value( $expr );
		}
		return '';
	}

	private function get_index_name( WP_Parser_Node $node ): string {
		if ( $node->get_descendant_token( WP_MySQL_Lexer::PRIMARY_SYMBOL ) ) {
			return 'PRIMARY';
		}

		$name_node = $node->get_descendant_node( 'indexName' );
		if ( null === $name_node ) {
			/*
			 * In MySQL, the default index name equals the first column name.
			 * For functional indexes, the string "functional_index" is used.
			 * If the name is already used, we need to append a number.
			 */
			$subnode = $node->get_child_node( 'keyListVariants' )->get_child_node();
			if ( 'exprWithParentheses' === $subnode->rule_name ) {
				$name = 'functional_index';
			} else {
				$name = $this->get_value( $subnode->get_descendant_node( 'identifier' ) );
			}

			// @TODO: Check if the name is already used.
			return $name;
		}
		return $this->get_value( $name_node );
	}

	private function get_index_non_unique( WP_MySQL_Token $token ): int {
		if (
			WP_MySQL_Lexer::PRIMARY_SYMBOL === $token->id
			|| WP_MySQL_Lexer::UNIQUE_SYMBOL === $token->id
		) {
			return 0;
		}
		return 1;
	}

	private function get_index_type(
		WP_Parser_Node $node,
		WP_MySQL_Token $token,
		bool $has_spatial_column
	): string {
		// Handle "USING ..." clause.
		$index_type = $node->get_descendant_node( 'indexTypeClause' );
		if ( null !== $index_type ) {
			$index_type = strtoupper(
				$this->get_value( $index_type->get_child_node( 'indexType' ) )
			);
			if ( 'RTREE' === $index_type ) {
				return 'SPATIAL';
			} elseif ( 'HASH' === $index_type ) {
				// InnoDB uses BTREE even when HASH is specified.
				return 'BTREE';
			}
			return $index_type;
		}

		// Derive index type from its definition.
		if ( WP_MySQL_Lexer::FULLTEXT_SYMBOL === $token->id ) {
			return 'FULLTEXT';
		} elseif ( WP_MySQL_Lexer::SPATIAL_SYMBOL === $token->id ) {
			return 'SPATIAL';
		}

		// Spatial indexes are also derived from column data type.
		if ( $has_spatial_column ) {
			return 'SPATIAL';
		}

		return 'BTREE';
	}

	private function get_index_column_name( WP_Parser_Node $node ): ?string {
		if ( 'keyPart' !== $node->rule_name ) {
			return null;
		}
		return $this->get_value( $node->get_descendant_node( 'identifier' ) );
	}

	private function get_index_column_collation( WP_Parser_Node $node, string $index_type ): ?string {
		if ( 'FULLTEXT' === $index_type ) {
			return null;
		}

		$collate_node = $node->get_descendant_node( 'collationName' );
		if ( null === $collate_node ) {
			return 'A';
		}
		$collate = strtoupper( $this->get_value( $collate_node ) );
		return 'DESC' === $collate ? 'D' : 'A';
	}

	private function get_index_column_sub_part(
		WP_Parser_Node $node,
		?int $max_length,
		bool $is_spatial
	): ?int {
		$field_length = $node->get_descendant_node( 'fieldLength' );
		if ( null === $field_length ) {
			if ( $is_spatial ) {
				return 32;
			}
			return null;
		}

		$value = (int) trim( $this->get_value( $field_length ), '()' );
		if ( null !== $max_length && $value >= $max_length ) {
			return $max_length;
		}
		return $value;
	}

	private function is_spatial_data_type( string $data_type ): bool {
		return 'geometry' === $data_type
			|| 'geomcollection' === $data_type
			|| 'point' === $data_type
			|| 'multipoint' === $data_type
			|| 'linestring' === $data_type
			|| 'multilinestring' === $data_type
			|| 'polygon' === $data_type
			|| 'multipolygon' === $data_type;
	}

	/**
	 * This is a helper function to get the full unescaped value of a node.
	 *
	 * @TODO: This should be done in a more correct way, for names maybe allowing
	 *        descending only a single-child hierarchy, such as these:
	 *          identifier -> pureIdentifier -> IDENTIFIER
	 *          identifier -> pureIdentifier -> BACKTICK_QUOTED_ID
	 *          identifier -> pureIdentifier -> DOUBLE_QUOTED_TEXT
	 *          etc.
	 *
	 *        For saving "DEFAULT ..." in column definitions, we actually need to
	 *        serialize the whole node, in the case of expressions. This may mean
	 *        implementing an MySQL AST -> string printer.
	 *
	 * @param WP_Parser_Node $node
	 * @return string
	 */
	private function get_value( WP_Parser_Node $node ): string {
		$full_value = '';
		foreach ( $node->get_children() as $child ) {
			if ( $child instanceof WP_Parser_Node ) {
				$value = $this->get_value( $child );
			} elseif ( WP_MySQL_Lexer::BACK_TICK_QUOTED_ID === $child->id ) {
				$value = substr( $child->value, 1, -1 );
				$value = str_replace( '``', '`', $value );
			} elseif ( WP_MySQL_Lexer::SINGLE_QUOTED_TEXT === $child->id ) {
				$value = $child->value;
				$value = substr( $value, 1, -1 );
				$value = str_replace( '\"', '"', $value );
				$value = str_replace( '""', '"', $value );
			} elseif ( WP_MySQL_Lexer::DOUBLE_QUOTED_TEXT === $child->id ) {
				$value = $child->value;
				$value = substr( $value, 1, -1 );
				$value = str_replace( '\"', '"', $value );
				$value = str_replace( '""', '"', $value );
			} else {
				$value = $child->value;
			}
			$full_value .= $value;
		}
		return $full_value;
	}

	private function insert_values( string $table_name, array $data ): void {
		$this->query(
			'
				INSERT INTO ' . $table_name . ' (' . implode( ', ', array_keys( $data ) ) . ')
				VALUES (' . implode( ', ', array_fill( 0, count( $data ), '?' ) ) . ')
			',
			array_values( $data )
		);
	}

	private function update_values( string $table_name, array $data, array $where ): void {
		$set = array();
		foreach ( $data as $column => $value ) {
			$set[] = $column . ' = ?';
		}

		$where_clause = array();
		foreach ( $where as $column => $value ) {
			$where_clause[] = $column . ' = ?';
		}

		$this->query(
			'
				UPDATE ' . $table_name . '
				SET ' . implode( ', ', $set ) . '
				WHERE ' . implode( ' AND ', $where_clause ) . '
			',
			array_merge( array_values( $data ), array_values( $where ) )
		);
	}

	private function delete_values( string $table_name, array $where ): void {
		$where_clause = array();
		foreach ( $where as $column => $value ) {
			$where_clause[] = $column . ' = ?';
		}

		$this->query(
			'
				DELETE FROM ' . $table_name . '
				WHERE ' . implode( ' AND ', $where_clause ) . '
			',
			array_values( $where )
		);
	}

	/**
	 * @param string $query
	 * @param array $params
	 * @return PDOStatement
	 */
	private function query( string $query, array $params = array() ) {
		return ( $this->query_callback )( $query, $params );
	}
}
