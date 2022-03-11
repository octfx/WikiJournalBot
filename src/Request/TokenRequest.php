<?php

declare( strict_types=1 );

namespace Octfx\WikiversityBot\Request;

/**
 * Request for retrieving a csrf token
 */
final class TokenRequest extends AbstractBaseRequest {
	public function getUrlParams(): array {
		return [
			'action' => 'query',
			'meta' => 'tokens',
			'format' => 'json',
		];
	}
}
