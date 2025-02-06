<?php

class WP_SQLite_Driver_Exception extends Exception {
	/**
	 * The SQLite driver that originated the exception.
	 *
	 * @var WP_SQLite_Driver
	 */
	private $driver;

	public function __construct(
		WP_SQLite_Driver $driver,
		string $message,
		int $code = 0,
		Throwable $previous = null
	) {
		parent::__construct( $message, $code, $previous );
		$this->driver = $driver;
	}

	public function getDriver(): WP_SQLite_Driver {
		return $this->driver;
	}
}
