<?php

// require autoload

use PhpMyAdmin\SqlParser\Context;
use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Components\DataType;
use PhpMyAdmin\SqlParser\Token;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;

// Assumes the PhpMyAdmin Sql Parser is installed via Composer
require_once __DIR__.'/../vendor/autoload.php';

function errHandle($errNo, $errStr, $errFile, $errLine) {
    $msg = "$errStr in $errFile on line $errLine";
    if ($errNo == E_NOTICE || $errNo == E_WARNING) {
        throw new ErrorException($msg, $errNo);
    } else {
        echo $msg;
    }
}

set_error_handler('errHandle');

function queries() {
    $fp = fopen(__DIR__.'/wp-phpunit.sql', 'r');
    // Read $fp line by line. Extract an array of multiline Queries. Queries are delimited by /* \d+ */
    $buf = '';
    while ($line = fgets($fp)) {
        if (1 === preg_match('/^\/\* [0-9]+ \*\//', $line)) {
            if(trim($buf)) {
                yield trim($buf);
            }
            $buf = substr($line, strpos($line, '*/') + 2);
        }
        else {
            $buf .= $line;
        }
    }
    fclose($fp);
}

// Create fulltext index -> select 1=1;
// Remove: collate / default character set
// 

$field_types_translation = [
    'bit' => 'integer',
    'bool' => 'integer',
    'boolean' => 'integer',
    'tinyint' => 'integer',
    'smallint' => 'integer',
    'mediumint' => 'integer',
    'int' => 'integer',
    'integer' => 'integer',
    'bigint' => 'integer',
    'float' => 'real',
    'double' => 'real',
    'decimal' => 'real',
    'dec' => 'real',
    'numeric' => 'real',
    'fixed' => 'real',
    'date' => 'text',
    'datetime' => 'text',
    'timestamp' => 'text',
    'time' => 'text',
    'year' => 'text',
    'char' => 'text',
    'varchar' => 'text',
    'binary' => 'integer',
    'varbinary' => 'blob',
    'tinyblob' => 'blob',
    'tinytext' => 'text',
    'blob' => 'blob',
    'text' => 'text',
    'mediumblob' => 'blob',
    'mediumtext' => 'text',
    'longblob' => 'blob',
    'longtext' => 'text',
];

$mysql_php_date_formats = [
    '%a' => '%D',
    '%b' => '%M',
    '%c' => '%n',
    '%D' => '%jS',
    '%d' => '%d',
    '%e' => '%j',
    '%H' => '%H',
    '%h' => '%h',
    '%I' => '%h',
    '%i' => '%i',
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
];

// $sqlite = new PDO('sqlite::memory:');
$sqlite = new PDO('sqlite:./testdb');
$sqlite->query('PRAGMA encoding="UTF-8";');

foreach(queries() as $k=>$query) {
    $tokens = \PhpMyAdmin\SqlParser\Lexer::getTokens($query);

    $token = $tokens->getNext();
    if($k > 1000) break;
    if($token->value !== 'CREATE') {
        continue;
    }

    echo '**MySQL query:**'.PHP_EOL;
    echo $query.PHP_EOL.PHP_EOL;
    $p = new \PhpMyAdmin\SqlParser\Parser($query);
    $stmt = $p->statements[0];
    $stmt->entityOptions->options = array();

    $inline_primary_key = false;
    $extra_queries = array();;
    foreach($stmt->fields as $k=>$field) {
        if($field->type && $field->type->name) {
            $typelc = strtolower($field->type->name);
            if(isset($field_types_translation[$typelc])) {
                $field->type->name = $field_types_translation[$typelc];
            }
            $field->type->parameters = array();
            unset($field->type->options->options[DataType::$DATA_TYPE_OPTIONS['UNSIGNED']]);
        }
        if($field->options && $field->options->options) {
            if(isset($field->options->options[CreateDefinition::$FIELD_OPTIONS['AUTO_INCREMENT']])) {
                $field->options->options[CreateDefinition::$FIELD_OPTIONS['AUTO_INCREMENT']] = 'PRIMARY KEY AUTOINCREMENT';
                $inline_primary_key = true;
                unset($field->options->options[CreateDefinition::$FIELD_OPTIONS['PRIMARY KEY']]);
            }
        }
        if($field->key) {
            if($field->key->type === 'PRIMARY KEY'){
                if($inline_primary_key) {
                    unset($stmt->fields[$k]);
                }
            } else if($field->key->type === 'FULLTEXT KEY'){
                unset($stmt->fields[$k]);
            } else if(
                $field->key->type === 'KEY' || 
                $field->key->type === 'INDEX' || 
                $field->key->type === 'UNIQUE KEY'
            ){
                $columns = array();
                foreach($field->key->columns as $column) {
                    $columns[] = $column['name'];
                }
                $unique = "";
                if($field->key->type === 'UNIQUE KEY') {
                    $unique = "UNIQUE ";
                }
                $extra_queries[] = 'CREATE '.$unique.' INDEX "'.$stmt->name.'__'.$field->key->name.'" ON "'.$stmt->name.'" ("'.implode('", "', $columns).'")';
                unset($stmt->fields[$k]);
            }
        }
    }
    Context::setMode(Context::SQL_MODE_ANSI_QUOTES);
    $updated_query = $stmt->build();

    echo '**SQLite queries:**'.PHP_EOL;
    echo $updated_query . PHP_EOL;
    $sqlite->exec($updated_query);
    foreach($extra_queries as $query) {
        echo $query . PHP_EOL . PHP_EOL;
        $sqlite->exec($query);
    }

    echo '--------------------'.PHP_EOL.PHP_EOL;
}

$min = (int)file_get_contents('./last-select.txt') ?: 18350;
foreach(queries() as $k=>$query) {
    if($k <= $min) continue;
    $tokens = \PhpMyAdmin\SqlParser\Lexer::getTokens($query);

    $token = $tokens->getNext();
    $query_type = $token->value;
    if(
        $token->value !== 'SELECT' &&
        $token->value !== 'INSERT' &&
        $token->value !== 'UPDATE' &&
        $token->value !== 'DELETE'
    ) {
        continue;
    }

    if(
        // @TODO: Add handling for the following cases:
        strpos($query, 'information_schema.TABLES') !== false
        // MySQL Supports deleting from multiple tables in one query.
        // In SQLite, we need to SELECT first and then DELETE with 
        // the primary keys found by the SELECT.
        || strpos($query, 'DELETE a, b') !== false
        || strpos($query, '@example') !== false
        || strpos($query, 'FOUND_ROWS') !== false
        || strpos($query, 'ORDER BY FIELD') !== false 
        || strpos($query, '@@SESSION.sql_mode') !== false 
        // `CONVERT( field USING charset )` is not supported
        || strpos($query, 'CONVERT( ') !== false 
        // @TODO rewrite `a REGEXP b` to `regexp(a, b)` and
        //       `a NOT REGEXP b` to `not regexp(a, b)`
        || strpos($query, ' REGEXP ') !== false 
    ) {
        continue;
    }

    echo '**MySQL query:**'.PHP_EOL;
    echo $query.PHP_EOL.PHP_EOL;

    $lexer = new \PhpMyAdmin\SqlParser\Lexer($query);
    $list = $lexer->list;
    $newlist = new PhpMyAdmin\SqlParser\TokensList();
    $call_stack = [];
    $paren_nesting = 0;
    $params = [];
    $is_in_duplicate_section = false;
    $table_name = null;
    for($i=0;$i<$list->count;$i++) {
        $token = $list[$i];
        $current_call_stack_elem = count($call_stack) ? $call_stack[count($call_stack) - 1] : null;

        // Capture table name
        if($query_type === 'INSERT') {
            if(!$table_name && $token->type === Token::TYPE_KEYWORD && $token->value === 'INTO') {
                // Get the next non-whitespace token and assume it's the table name
                $j = $i + 1;
                while($list[$j]->type === Token::TYPE_WHITESPACE) {
                    $j++;
                }
                $table_name = $list[$j]->value;
            } else if($token->type === Token::TYPE_KEYWORD && $token->value === 'IGNORE') {                
                $newlist->add(new Token('OR', Token::TYPE_KEYWORD, Token::FLAG_KEYWORD_RESERVED));
                $newlist->add(new Token(' ', Token::TYPE_WHITESPACE));         
                $newlist->add(new Token('IGNORE', Token::TYPE_KEYWORD, Token::FLAG_KEYWORD_RESERVED));
                goto process_nesting;
            }
        }

        if($token->type === Token::TYPE_STRING && $token->flags & Token::FLAG_STRING_SINGLE_QUOTES) {
            $param_name = ':param'.count($params);
            $params[$param_name] = $token->value;
            // Rewrite backslash-escaped single quotes to 
            // doubly-escaped single quotes. The stripslashes()
            // part is fairly naive and needs to be improved.
            // $sqlite_value = SQLite3::escapeString(stripslashes($token->value));
            // $newlist->add(new Token("'$sqlite_value'", Token::TYPE_STRING, Token::FLAG_STRING_SINGLE_QUOTES));
            $newlist->add(new Token($param_name, Token::TYPE_STRING, Token::FLAG_STRING_SINGLE_QUOTES));
            goto process_nesting;
        } else if($token->type === Token::TYPE_KEYWORD) {
            foreach([
                ['YEAR', '%Y'],
                ['MONTH', '%M'],
                ['DAY', '%D'],
                ['DAYOFMONTH', '%d'],
                ['DAYOFWEEK', '%w'],
                ['WEEK', '%W'],
                // @TODO fix
                //       %w returns 0 for Sunday and 6 for Saturday
                //       but weekday returns 1 for Monday and 7 for Sunday
                ['WEEKDAY', '%w'], 
                ['HOUR', '%H'],
                ['MINUTE', '%M'],
                ['SECOND', '%S']
            ] as [$unit, $format]) {
                if($token->value === $unit && $token->flags & Token::FLAG_KEYWORD_FUNCTION) {
                    $newlist->add(new Token('STRFTIME', Token::TYPE_KEYWORD, Token::FLAG_KEYWORD_FUNCTION));
                    $newlist->add(new Token('(', Token::TYPE_OPERATOR));

                    if($unit === 'WEEK') {
                        // Peek to check the "mode" argument
                        // For now naively assume the mode is either
                        // specified after the first "," or defaults to
                        // 0 if ")" is found first
                        $j = $i;
                        do {
                            $peek = $list[++$j];
                        } while (
                            !(
                                $peek->type === Token::TYPE_OPERATOR && 
                                (
                                    $peek->value === ')' ||
                                    $peek->value === ',' 
                                )
                            )
                        );
                        if($peek->value === ',') {
                            $comma_idx = $j;
                            do {
                                $peek = $list[++$j];
                            } while ($peek->type === Token::TYPE_WHITESPACE);
                            // Assume $peek is now a number
                            if($peek->value === 0) {
                                $format = '%U';
                            } else if($peek->value === 1){
                                $format = '%W';
                            } else {
                                throw new Exception('Could not parse the WEEK() mode');
                            }

                            $mode_idx = $j;
                            // Drop the comma and the mode from tokens list
                            unset($list[$mode_idx]);
                            unset($list[$comma_idx]);
                        } else {
                            $format = '%W';
                        }
                    }

                    $newlist->add(new Token("'$format'", Token::TYPE_STRING));
                    $newlist->add(new Token(",", Token::TYPE_OPERATOR));
                    // Skip over the next "(" token
                    do {
                        $peek = $list[++$i];
                    } while (
                        $peek->type !== Token::TYPE_OPERATOR && 
                        $peek->value !== '('
                    );
                    goto process_nesting;
                }
            }
            if($token->keyword === 'RAND' && $token->flags & Token::FLAG_KEYWORD_FUNCTION) {
                $newlist->add(new Token('RANDOM', Token::TYPE_KEYWORD, Token::FLAG_KEYWORD_FUNCTION));
                goto process_nesting;
            } else if(
                $token->flags & Token::FLAG_KEYWORD_FUNCTION && (
                    $token->keyword === 'DATE_ADD' ||
                    $token->keyword === 'DATE_SUB'
                )
            ) {
                $newlist->add(new Token('DATE', Token::TYPE_KEYWORD, Token::FLAG_KEYWORD_FUNCTION));
                goto process_nesting;
            } else if(
                $token->flags & Token::FLAG_KEYWORD_FUNCTION && 
                $token->keyword === 'VALUES' && 
                $is_in_duplicate_section
            ) {
                /*
                Rewrite:
                    VALUES(`option_name`)
                to:
                    excluded.option_name
                Need to know the primary key
                */
                $newlist->add(new Token('excluded', Token::TYPE_KEYWORD, Token::FLAG_KEYWORD_KEY));
                $newlist->add(new Token('.', Token::TYPE_OPERATOR));
                // Naively remove the next ( and )
                $j = $i;
                while(true) {
                    $peek = $list[++$j];
                    if($peek->type === Token::TYPE_OPERATOR && $peek->value === '(') {
                        unset($list[$j]);
                        break;
                    }
                }
                while(true) {
                    $peek = $list[++$j];
                    if($peek->type === Token::TYPE_OPERATOR && $peek->value === ')') {
                        unset($list[$j]);
                        break;
                    }
                }

                goto process_nesting;                
            } else if(
                $token->flags & Token::FLAG_KEYWORD_FUNCTION && 
                $token->keyword === 'DATE_FORMAT'
            ) {
                $newlist->add(new Token('STRFTIME', Token::TYPE_KEYWORD, Token::FLAG_KEYWORD_FUNCTION));
                $newlist->add(new Token('(', Token::TYPE_OPERATOR));
                $j = $i;
                while(true) {
                    $peek = $list[++$j];
                    if($peek->type === Token::TYPE_OPERATOR && $peek->value === '(') {
                        unset($list[$j]);
                        break;
                    }
                }
        
                // Peek to check the "format" argument
                // For now naively assume the format is 
                // the first string value inside the DATE_FORMAT call
                while(true) {
                    $peek = $list[++$j];
                    if($peek->type === Token::TYPE_OPERATOR && $peek->value === ',') {
                        unset($list[$j]);
                        break;
                    }
                }

                // Rewrite the format argument:
                while(true) {
                    $peek = $list[++$j];
                    if($peek->type === Token::TYPE_STRING) {
                        unset($list[$j]);
                        break;
                    }
                }

                $string_at = $j;
                $new_format = strtr($peek->value, $mysql_php_date_formats);
                $newlist->add(new Token("'$new_format'", Token::TYPE_STRING));
                $newlist->add(new Token(",", Token::TYPE_OPERATOR));

                goto process_nesting;
            } else if($token->keyword === 'INTERVAL') {
                $interval_string = '';
                $list->idx = $i + 1;
                $num = $list->getNext()->value;
                $unit = $list->getNext()->value;
                $i = $list->idx - 1;
                
                // Add or subtract the interval value depending on the
                // date_* function closest in the stack
                $interval_op = '+'; // Default to adding
                for($j=count($call_stack) - 1;$i>=0;$i--) {
                    $call = $call_stack[$j];
                    if($call[0] === 'DATE_ADD') {
                        $interval_op = "+";
                        break;
                    } else if($call[0] === 'DATE_SUB') {
                        $interval_op = "-";
                        break;
                    }
                }

                $newlist->add(new Token("'{$interval_op}$num $unit'", Token::TYPE_STRING));
                goto process_nesting;
            } else if($query_type === 'INSERT' && $token->keyword === 'DUPLICATE') {
                /*
                Rewrite:
                    ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`)
                to:
                    ON CONFLICT(ip) DO UPDATE SET option_name = excluded.option_name
                Need to know the primary key
                */
                $newlist->add(new Token("CONFLICT", Token::TYPE_KEYWORD));
                $newlist->add(new Token("(", Token::TYPE_OPERATOR));
                // @TODO don't make assumptions about the names, only fetch 
                //       the correct unique key from sqlite
                if(str_ends_with($table_name, '_options')) {
                    $pk_name = 'option_name';
                } else if(str_ends_with($table_name, '_term_relationships')) {
                    $pk_name = 'object_id, term_taxonomy_id';
                } else {
                    $q = $sqlite->query('SELECT l.name FROM pragma_table_info("'.$table_name.'") as l WHERE l.pk = 1;');
                    $pk_name = $q->fetch()['name'];
                }
                $newlist->add(new Token($pk_name, Token::TYPE_KEYWORD, Token::FLAG_KEYWORD_KEY));
                $newlist->add(new Token(")", Token::TYPE_OPERATOR));
                $newlist->add(new Token(" ", Token::TYPE_WHITESPACE));
                $newlist->add(new Token("DO", Token::TYPE_KEYWORD));
                $newlist->add(new Token(" ", Token::TYPE_WHITESPACE));
                $newlist->add(new Token("UPDATE", Token::TYPE_KEYWORD));
                $newlist->add(new Token(" ", Token::TYPE_WHITESPACE));
                $newlist->add(new Token("SET", Token::TYPE_KEYWORD));
                $newlist->add(new Token(" ", Token::TYPE_WHITESPACE));
                // Naively remove the next "KEY" and "UPDATE" keywords from
                // the original token stream
                $j = $i;
                while(true) {
                    $peek = $list[++$j];
                    if($peek->type === Token::TYPE_KEYWORD && $peek->keyword === 'KEY') {
                        unset($list[$j]);
                        break;
                    }
                }
                while(true) {
                    $peek = $list[++$j];
                    if($peek->type === Token::TYPE_KEYWORD && $peek->keyword === 'UPDATE') {
                        unset($list[$j]);
                        break;
                    }
                }
                $is_in_duplicate_section = true;
                goto process_nesting;
            }
        }

        $newlist->add($token);

        process_nesting:
        if($token->type === Token::TYPE_KEYWORD) {
            if(
                $token->flags & Token::FLAG_KEYWORD_FUNCTION
                && !($token->flags & Token::FLAG_KEYWORD_RESERVED)
            ) {
                $j = $i;
                do {
                    $peek = $list[++$j];
                } while ($peek->type === Token::TYPE_WHITESPACE);
                if($peek->type === Token::TYPE_OPERATOR && $peek->value === '(') {
                    array_push($call_stack, [$token->value, $paren_nesting]);
                }
            }
        } else if($token->type === Token::TYPE_OPERATOR) {
            if($token->value === '(') {
                ++$paren_nesting;
            } else if($token->value === ')') {
                --$paren_nesting;
                if(
                    $current_call_stack_elem && 
                    $current_call_stack_elem[1] === $paren_nesting
                ) {
                    array_pop($call_stack);
                }
            }
        }
    }

    /**
     * Parser gets derailed by queries like:
     * * SELECT * FROM table LIMIT 0,1
     * * SELECT 'a' LIKE '%';
     * Let's try using raw tokens instead.
     */
    // $p = new \PhpMyAdmin\SqlParser\Parser($newlist);
    // $stmt = $p->statements[0];
    // 
    // if($stmt->options && $stmt->options->options){
    //     unset($stmt->options->options[SelectStatement::$OPTIONS['SQL_CALC_FOUND_ROWS']]);
    // }
    // 
    // Context::setMode(Context::SQL_MODE_ANSI);
    // $updated_query = $stmt->build();

    $updated_query = '';

    foreach($newlist->tokens as $token) {
        $updated_query .= $token->token ;
    }
    $extra_queries = array();
    echo '**SQLite queries:**'.PHP_EOL;
    try {
        $stmt = $sqlite->prepare($updated_query);
        foreach($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->execute();
    } catch(\Exception $e) {
        if(strpos($e->getMessage(), 'UNIQUE constraint failed:') === false) {
            throw $e;
        }
    } finally {
        echo PHP_EOL . PHP_EOL . $updated_query . PHP_EOL . PHP_EOL . PHP_EOL;
    }
    // foreach($extra_queries as $query) {
    //     $query = str_replace(' ID ',  '"ID" ', $query);
    //     try {
    //         $sqlite->exec($query);
    //     } catch(\Exception $e) {
    //         print_r($stmt);
    //         throw $e;
    //     } finally {
    //         echo $query . PHP_EOL . PHP_EOL;
    //     }
    // }
    file_put_contents('./last-select.txt', $k);
    echo '--------------------'.PHP_EOL.PHP_EOL;
}

