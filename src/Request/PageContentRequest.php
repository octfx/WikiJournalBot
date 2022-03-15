<?php

declare( strict_types=1 );

namespace Octfx\WikiversityBot\Request;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Octfx\WikiversityBot\Logger;

/**
 * Request for retrieving the main content of a page
 */
final class PageContentRequest extends AbstractBaseRequest {
	/**
	 * @var string The title to work on
	 */
	private string $title;

	public function __construct( string $title ) {
		$this->title = $title;
	}

	public function getUrlParams(): array {
		return [
			'action' => 'query',
			'format' => 'json',
			'prop' => 'revisions',
			'rvprop' => 'content',
			'rvslots' => 'main',
			'titles' => $this->title,
		];
	}

	/**
	 * Returns the pages title and content in a keyed array
	 * Array is empty if an error occurred
	 *
	 * @param AbstractBaseRequest $request
	 * @return array
	 * @throws GuzzleException
	 */
	public static function getContentFromRequest( AbstractBaseRequest $request ): array {
		$response = AbstractBaseRequest::makeRequest( $request );

		try {
			$response = json_decode( (string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			Logger::getInstance()->error( $e->getMessage() );

			return [];
		}

		if ( isset( $response['error'] ) || !isset( $response['query'] ) ) {
			Logger::getInstance()->error( 'Page content API result has errors.', $response['error'] ?? [] );

			return [];
		}

		$page = array_shift( $response['query'] );
		$page = array_shift( $page );

		return [
			'title' => $page['title'],
			'content' => $page['revisions'][0]['slots']['main']['*'] ?? '',
		];
	}
}
