<?php

declare( strict_types=1 );

namespace Octfx\WikiversityBot;

use InvalidArgumentException;
use Monolog\Handler\RotatingFileHandler;

final class Logger {

	private \Monolog\Logger $logger;
	private static $instance;

	/**
	 * Creates a monolog instance
	 */
	private function __construct() {
		$log = new \Monolog\Logger( Config::getInstance()->get( 'BOT_NAME', 'WikiJournalBot' ) );
		$level = \Monolog\Logger::INFO;

		try {
			\Monolog\Logger::toMonologLevel( Config::getInstance()->get( 'LOG_LEVEL', 'info' ) );
		} catch ( InvalidArgumentException $e ) {
			// discard
		}

		$handler = new RotatingFileHandler(
			sprintf( '%s/logs/botlog.log', dirname( __DIR__ ) ),
			14,
			$level
		);

		$log->pushHandler( $handler );

		$this->logger = $log;
	}

	/**
	 * Returns a static logging instance
	 *
	 * @return \Monolog\Logger
	 */
	public static function getInstance(): \Monolog\Logger {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance->logger;
	}
}
