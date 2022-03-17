<?php

declare( strict_types=1 );

namespace Octfx\WikiJournalBot\Request;

/**
 * Request for retrieving all pages that transclude a given page
 */
final class TranscludedInRequest extends AbstractBaseRequest {

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
			'prop' => 'transcludedin',
			'titles' => $this->title,
			'tiprop' => 'pageid|title',
			'tinamespace' => '2',
			'tishow' => '!redirect',
			'tilimit' => '500',
		];
	}
}
