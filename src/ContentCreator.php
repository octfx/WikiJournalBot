<?php

declare( strict_types=1 );

namespace Octfx\WikiversityBot;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Octfx\WikiversityBot\Request\PageContentRequest;
use RuntimeException;

/**
 * Handles replacing the text in a given page
 */
final class ContentCreator {

	/**
	 * @var string The title to work on
	 */
	private $title;

	/**
	 * @var string The current page content
	 */
	private $content;

	/**
	 * @var \Monolog\Logger Logging instance
	 */
	private $logger;

	/**
	 * @param array $pageContentResult Result from the MW API
	 * @see PageContentRequest
	 */
	public function __construct( array $pageContentResult ) {
		$this->logger = Logger::getInstance();

		if ( empty( $pageContentResult ) ) {
			$this->logger->error( 'Page content API result has errors.' );
			return;
		}

		$this->title = $pageContentResult['title'];
		$this->content = $pageContentResult['content'];
	}

	/**
	 * Updates the page content with all WikiData articles for the given volume and issue
	 * Requires 'row_template' to be set in the page
	 * As well as at least the volume (retrieved either through the page title or set in the template through |Volume=)
	 *
	 * @throws RuntimeException
	 *
	 * @return string|null Null if content did not change or the SPARQL query could not be executed
	 */
	public function getUpdatedPageContent(): ?string {
		[ $volume, $issue ] = $this->getVolumeIssue();

		if ( $volume === -1 || $this->content === null ) {
			throw new RuntimeException( sprintf( 'Could not parse Volume and Issue for page %s', $this->title ) );
		}

		if ( !WikiversityBot::allowBots( $this->content ?? '', Config::getInstance()->get( 'BOT_NAME', 'WikiversityListBot' ) ) ) {
			$this->logger->info( sprintf( 'Found {{nobots}} template in page "%s", skipping.', $this->title ) );

			return null;
		}

		$found = preg_match( '/\|row_template\s?=\s?(\w+)/', $this->content, $matches );
		if ( $found === 0 || $found === false ) {
			throw new RuntimeException( sprintf( 'Could not parse row_template for page %s', $this->title ) );
		}

		$template = $matches[1];

		$query = sprintf( WikiversityBot::$PUBLISHED_ARTICLES, $volume, $issue );
		$request = new SPARQLQueryDispatcher();

		try {
			$result = $request->query( $query );
		} catch ( JsonException | GuzzleException $e ) {
			$this->logger->error( 'Could not retrieve SPARQL query.', [ 'message' => $e->getMessage() ] );

			return null;
		}

		$out = [];

		foreach ( $result['results']['bindings'] as $article ) {
			$image = '';
			if ( isset( $article['image']['value'] ) ) {
				$parts = explode( 'Special:FilePath/', $article['image']['value'] );
				$image = urldecode( $parts[1] );
			}

			$out[] = sprintf(
				'{{%s|item=%s|image=%s}}',
				$template,
				$article['itemLabel']['value'],
				$image,
			);
		}

		$newText = preg_replace(
			'/{{WikiversityBotList([\w\s|=]+)}}(.*){{ListEnd}}/s',
			sprintf( "{{WikiversityBotList$1}}\n%s\n{{ListEnd}}", implode( "\n\n", $out ) ),
			$this->content
		);

		// Check if strings differ
		if ( strcmp( ( $newText ?? $this->content ), $this->content ) === 0 ) {
			$this->logger->debug( 'Page content did not change.' );
			return null;
		}

		return $newText;
	}

	/**
	 * Retrieve the volume and issue from the page content or title
	 * Volume and Issue set in the template through |Volume=N |Issue=N takes precedence over the title
	 *
	 * Page title is expected to be in the format ...Volume N Issue N
	 *
	 * @return int[]
	 */
	private function getVolumeIssue(): array {
		$volume = -1;
		$issue = 1;

		$found = preg_match( '/\|Volume\s?=\s?(\d+)/', $this->content, $matches );
		if ( $found === 1 ) {
			$volume = $matches[1];
		}

		$found = preg_match( '/\|Issue\s?=\s?(\d+)/', $this->content, $matches );
		if ( $found === 1 ) {
			$issue = $matches[1];
		}

		if ( $volume === -1 ) {
			$found = preg_match( '/Volume\s(\d+)\sIssue\s(\d+)/', $this->title, $matches );

			if ( $found === false || $found === 0 ) {
				return [ -1, -1 ];
			}

			$volume = $matches[1];
			$issue = $matches[2] ?? 1;
		}

		return [
			$volume,
			$issue,
		];
	}
}
