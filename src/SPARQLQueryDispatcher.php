<?php

declare( strict_types=1 );

namespace Octfx\WikiversityBot;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;

final class SPARQLQueryDispatcher {
	/**
	 * @var string The SPARQL Endpoint URL
	 */
	private string $endpointUrl;

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
	 * @throws JsonException
	 * @throws GuzzleException
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

		return json_decode( (string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR );
	}
}
