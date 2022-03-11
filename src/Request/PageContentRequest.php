<?php

declare( strict_types=1 );

namespace Octfx\WikiversityBot\Request;

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
}
