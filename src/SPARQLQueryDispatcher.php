<?php

declare( strict_types=1 );

namespace Octfx\WikiJournalBot;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final class SPARQLQueryDispatcher {
	/**
	 * @var string The SPARQL Endpoint URL
	 */
	private $endpointUrl;

	/**
	 * @param string $endpointUrl
	 */
	public function __construct( string $endpointUrl = '' ) {
		if ( $endpointUrl === '' ) {
			$endpointUrl = Config::getInstance()->getSparqlEndpoint();
		}

		$this->endpointUrl = $endpointUrl;
	}

	/**
	 * Queries the SPARQL service with the given query
	 *
	 * @param string $sparqlQuery The query to run
	 *
	 * @throws GuzzleException
	 * @throws RuntimeException
	 *
	 * @return array
	 */
	public function query( string $sparqlQuery ): array {
		$client = new Client( [
			'base_uri' => $this->endpointUrl,
			'timeout' => 10,
		] );

		$response = $client->request( 'GET', '', [
			'query' => [
				'query' => $sparqlQuery,
			],
			'headers' => [
				'Accept' => 'application/sparql-results+json',
				'User-Agent' => 'WikiJournalBot/1.0 (https://en.wikiversity.org/wiki/User:Octfx; info@octofox.de) PHP/' . PHP_VERSION,
			],
		] );

		$responseData = json_decode( (string)$response->getBody(), true );

		if ( $responseData === null ) {
			throw new RuntimeException( (string)$response->getBody() );
		}

		return $responseData;
	}
}
