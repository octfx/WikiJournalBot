<?php

declare( strict_types=1 );

namespace Octfx\WikiJournalBot;

use GuzzleHttp\Exception\GuzzleException;
use Octfx\WikiJournalBot\Request\PageContentRequest;
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
	 * @var string Template used to start a list
	 */
	private $startTemplate;

	/**
	 * @var string Template used to end a list
	 */
	private $endTemplate;

	/**
	 * @param array $pageContentResult Result from the MW API
	 * @see PageContentRequest
	 */
	public function __construct( array $pageContentResult, string $startTemplate, string $endTemplate ) {
		$this->logger = Logger::getInstance();

		if ( empty( $pageContentResult ) ) {
			$this->logger->error( 'Page content API result has errors.' );
			return;
		}

		$this->title = $pageContentResult['title'] ?? '';
		$this->content = $pageContentResult['content'] ?? '';

		$this->startTemplate = $startTemplate;
		$this->endTemplate = $endTemplate;
	}

	/**
	 * Returns the page content with all WikiData articles for the given volume and issue
	 * Requires 'row_template' to be set in the page
	 * As well as at least the volume (retrieved either through the page title or set in the template through |Volume=)
	 *
	 * @throws RuntimeException
	 *
	 * @return string|null Null if content did not change or the SPARQL query could not be executed
	 */
	public function getUpdatedPageContent(): ?string {
		[ $journal, $volume, $issue ] = $this->getJournalVolumeIssue();

		if ( $journal === null || $volume === -1 || $this->content === null ) {
			throw new RuntimeException( sprintf( 'Could not parse Journal, Volume and Issue for page %s', $this->title ) );
		}

		$found = preg_match( '/\|row_template\s?=\s?([\w\s-]+)/', $this->content, $matches );
		if ( $found === 0 || $found === false ) {
			$template = Config::getInstance()->get( 'DEFAULT_ROW_TEMPLATE' );
		} else {
			$template = trim( $matches[1] );
		}

		$query = sprintf( WikiJournalBot::$PUBLISHED_ARTICLES_QUERY, $journal, $volume, $issue );

		$request = new SPARQLQueryDispatcher();

		try {
			$result = $request->query( $query );
		} catch ( RuntimeException | GuzzleException $e ) {
			$this->logger->error( 'Could not retrieve SPARQL query.', [ 'message' => $e->getMessage() ] );

			return null;
		}

		if ( !isset( $result['results']['bindings'] ) ) {
			$this->logger->error( 'SPARQL query did not return any bindings.' );
			$result['results'] = [
				'bindings' => [],
			];
		}

		$items = [];

		foreach ( $result['results']['bindings'] as $article ) {
			$image = '';
			if ( isset( $article['image']['value'] ) ) {
				$parts = explode( 'Special:FilePath/', $article['image']['value'] );
				$image = urldecode( $parts[1] );
			}

			$items[] = sprintf(
				'{{%s|Q=%s|image=%s}}',
				$template,
				$article['itemLabel']['value'],
				$image
			);
		}

		// Replace the content between the start- and end-template
		$newText = preg_replace(
			sprintf( '/{{%s([\w\s|=]+)?}}(.*){{%s}}/si', $this->startTemplate, $this->endTemplate ),
			sprintf( "{{%s$1}}\n%s\n{{%s}}", $this->startTemplate, implode( "\n\n", $items ), $this->endTemplate ),
			$this->content
		);

		// Check if strings differ
		if ( empty( $items ) || strcmp( ( $newText ?? $this->content ), $this->content ) === 0 ) {
			$this->logger->debug( 'Page content did not change.' );
			return null;
		}

		return $newText;
	}

	/**
	 * Retrieve the journal, volume and issue from the page content or title
	 * Journal, Volume and Issue set in the template through |Journal=X |Volume=Y |Issue=Z take precedence over the title
	 *
	 * Page title is expected to be in the format WikiJournal .../Volume N Issue N
	 *
	 * @return int[]
	 */
	private function getJournalVolumeIssue(): array {
		$volume = -1;
		$issue = 1;

		$found = preg_match( '/\|[Vv]olume\s?=\s?(\d+)/', $this->content, $matches );
		if ( $found === 1 ) {
			$volume = $matches[1];
		}

		$found = preg_match( '/\|[Ii]ssue\s?=\s?(\d+)/', $this->content, $matches );
		if ( $found === 1 ) {
			$issue = $matches[1];
		}

		if ( $volume === -1 ) {
			$found = preg_match( '/Volume\s(\d+)\sIssue\s(\d+)/', $this->title, $matches );

			if ( $found === false || $found === 0 ) {
				return [ $this->getJournalId(), -1, -1 ];
			}

			$volume = $matches[1];
			$issue = $matches[2] ?? 1;
		}

		return [
			$this->getJournalId(),
			$volume,
			$issue,
		];
	}

	/**
	 * Returns the journal id based on the name set in |journal= or a journal found in the page title
	 * Null otherwise
	 *
	 * @return string|null
	 */
	private function getJournalId(): ?string {
		$journal = null;

		$found = preg_match( '/\|[Jj]ournal\s?=\s?([\w\s]+)/', $this->content, $matches );
		if ( $found === 1 ) {
			$journal = trim( $matches[1] );
			if ( $journal[0] === 'Q' ) {
				return $journal;
			}
		}

		foreach ( Config::getInstance()->getSupportedJournals() as $name => $id ) {
			// Check if template argument or page title matches a known journal
			if ( $journal === $name || strpos( $this->title, $name ) !== false ) {
				return $id;
			}
		}

		$this->logger->error( sprintf( 'Could not find journal for title "%s".', $this->title ) );

		return null;
	}
}
