<?php

declare( strict_types=1 );

namespace Octfx\WikiversityBot;

use DateTime;
use Monolog\Handler\StreamHandler;

final class Logger {

	private \Monolog\Logger $logger;
	private static $instance;

	/**
	 * Creates a monolog instance
	 */
	private function __construct() {
		$time = new DateTime();

		$log = new \Monolog\Logger( 'Wikiversity List Bot' );
		$log->pushHandler( new StreamHandler(
			sprintf( '%s/logs/botlog_%s.log', dirname( __DIR__ ), $time->format( 'Y_m_d' ) ),
			\Monolog\Logger::INFO )
		);

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
