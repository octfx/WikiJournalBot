<?php

declare( strict_types=1 );

namespace Octfx\WikiversityBot\Request;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Octfx\WikiversityBot\Logger;
use RuntimeException;

/**
 * Request for editing a page
 */
final class EditRequest extends AbstractBaseRequest {
	/**
	 * @var string The title to work on
	 */
	private $title;

	/**
	 * @var string The content to send
	 */
	private $content;

	/**
	 * @var string|null The csrf token retrieved by meta tokens
	 */
	private $token;

	/**
	 * @return string
	 */
	public function getRequestType(): string {
		return 'POST';
	}

	/**
	 * Retrieves a csrf Token on instantiation
	 *
	 * @param string $title
	 * @param string $content
	 * @throws RuntimeException
	 */
	public function __construct( string $title, string $content ) {
		$this->title = $title;
		$this->content = $content;

		$tokenRequest = new TokenRequest();
		try {
			$response = self::makeRequest( $tokenRequest );
			$response = json_decode( (string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR );

			if ( isset( $response['batchcomplete'], $response['query'] ) ) {
				$this->token = $response['query']['tokens']['csrftoken'];
			}
		} catch ( JsonException | GuzzleException $e ) {
			$this->token = null;
			Logger::getInstance()->error( $e->getMessage() );
		}

		if ( $this->token === null ) {
			throw new RuntimeException( 'Could not retrieve CSRF token.' );
		}
	}

	public function getUrlParams(): array {
		return [
			'action' => 'edit',
			'title' => $this->title,
			'format' => 'json',
			'text' => $this->content,
			'summary' => 'Automated Bot List Update',
			'md5' => md5( $this->content ),
			'token' => $this->token,
			'assert' => 'user',
		];
	}
}
