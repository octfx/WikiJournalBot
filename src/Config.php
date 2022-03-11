<?php

declare( strict_types=1 );

namespace Octfx\WikiversityBot;

use Dotenv\Dotenv;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;

/**
 * Config object accessing values from '.env'
 */
final class Config {

	private static $instance;
	private array $config;

	/**
	 * @var Consumer OAuth Consumer token (and secret)
	 */
	private Consumer $consumerToken;

	/**
	 * @var Token OAuth Access token (and secret)
	 */
	private Token $accessToken;

	/**
	 * Constructs the instance and checks required keys
	 */
	private function __construct() {
		$dotenv = Dotenv::createImmutable( dirname( __DIR__ ) );
		$this->config = $dotenv->safeLoad();
		$dotenv->required( [
			'CONSUMER_TOKEN',
			'CONSUMER_SECRET',
			'ACCESS_TOKEN',
			'ACCESS_SECRET',
			'API_ENDPOINT',
			'SPARQL_ENDPOINT',
			'ARTICLE_VOLUME_LIST_TEMPLATE',
			'LIST_END_TEMPLATE',
		] )
			->notEmpty();

		$this->makeTokens();
	}

	/**
	 * Get the static instance
	 *
	 * @return static
	 */
	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get an entry from the config
	 *
	 * @param string $key The key to access
	 * @param null $default mixed Default value to return if key was not found
	 *
	 * @return string|null
	 */
	public function get( string $key, $default = null ): ?string {
		$value = $this->config[$key];
		if ( empty( $value ) ) {
			return $default ?? null;
		}

		return $value;
	}

	/**
	 * Convenience accessor for the api url
	 *
	 * @return string
	 */
	public function getApiUrl(): string {
		return $this->get( 'API_ENDPOINT' );
	}

	/**
	 * Convenience accessor for the sparql endpoint
	 *
	 * @return string
	 */
	public function getSparqlEndpoint(): string {
		return $this->get( 'SPARQL_ENDPOINT' );
	}

	public function getConsumerToken(): Consumer {
		return $this->consumerToken;
	}

	public function getAccessToken(): Token {
		return $this->accessToken;
	}

	/**
	 * Build the consumer and access token based on the values found in .env
	 *
	 * @return void
	 */
	private function makeTokens(): void {
		$this->consumerToken = new Consumer( $this->get( 'CONSUMER_TOKEN' ), $this->get( 'CONSUMER_SECRET' ) );
		$this->accessToken = new Token( $this->get( 'ACCESS_TOKEN' ), $this->get( 'ACCESS_SECRET' ) );
	}
}
