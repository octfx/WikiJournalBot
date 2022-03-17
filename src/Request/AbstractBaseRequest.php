<?php

declare( strict_types=1 );

namespace Octfx\WikiJournalBot\Request;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use MediaWiki\OAuthClient\Request;
use MediaWiki\OAuthClient\SignatureMethod\HmacSha1;
use Octfx\WikiJournalBot\Config;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractBaseRequest {

	/**
	 * @return string
	 */
	public function getRequestType(): string {
		return 'GET';
	}

	/**
	 * Either query of post params for this request
	 *
	 * @return array
	 */
	abstract public function getUrlParams(): array;

	/**
	 * Build the request
	 * Each request is signed through OAuth
	 *
	 * @return Request
	 */
	public function buildRequest(): Request {
		$consumer = Config::getInstance()->getConsumerToken();
		$access = Config::getInstance()->getAccessToken();

		$request = Request::fromConsumerAndToken(
			$consumer,
			$access,
			$this->getRequestType(),
			Config::getInstance()->getApiUrl(),
			$this->getUrlParams()
		);

		$request->signRequest( new HmacSha1(), $consumer, $access );

		return $request;
	}

	/**
	 * Do the actual request
	 *
	 * @param AbstractBaseRequest $request Request build through AbstractBaseRequest::buildRequest
	 * @return ResponseInterface
	 * @throws GuzzleException
	 */
	public static function makeRequest( AbstractBaseRequest $request ): ResponseInterface {
		$mwRequest = $request->buildRequest();

		$client = new Client( [
			'base_uri' => Config::getInstance()->getApiUrl(),
			'timeout' => 60,
		] );

		try {
			$header = $mwRequest->toHeader();
		} catch ( Exception $e ) {
			$header = 'Authorization: OAuth';
		}

		$header = explode( ':', $header );

		$key = $request->getRequestType() === 'GET' ? 'query' : 'form_params';

		return $client->request( $request->getRequestType(), '', [
			$key => $request->getUrlParams(),
			'http_errors' => false,
			'headers' => [
				$header[0] => $header[1]
			]
		] );
	}
}
