<?php

/**
 * MySQL token.
 *
 * This class represents a MySQL SQL token that is produced by WP_MySQL_Lexer,
 * and consumed by WP_MySQL_Parser during the parsing process.
 */
class WP_MySQL_Token extends WP_Parser_Token {
	/**
	 * Get the name of the token.
	 *
	 * This method is intended to be used only for testing and debugging purposes,
	 * when tokens need to be presented by their names in a human-readable form.
	 * It should not be used in production code, as it's not performance-optimized.
	 *
	 * @return string The token name.
	 */
	public function get_name(): string {
		$name = WP_MySQL_Lexer::get_token_name( $this->id );
		if ( null === $name ) {
			$name = 'UNKNOWN';
		}
		return $name;
	}

	/**
	 * Get the token representation as a string.
	 *
	 * This method is intended to be used only for testing and debugging purposes,
	 * when tokens need to be presented in a human-readable form. It should not
	 * be used in production code, as it's not performance-optimized.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->value . '<' . $this->id . ',' . $this->get_name() . '>';
	}
}
