<?php

declare( strict_types=1 );

namespace Octfx\WikiJournalBot;

use InvalidArgumentException;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;

final class Logger {

	private $logger;
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

		$log->pushHandler( new RotatingFileHandler(
			sprintf( '%s/logs/botlog.log', dirname( __DIR__ ) ),
			14,
			$level
		) );

		$log->pushHandler( new StreamHandler( 'php://stdout', $level ) );

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
