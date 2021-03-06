<?php

declare( strict_types=1 );

namespace Octfx\WikiJournalBot;

use GuzzleHttp\Exception\GuzzleException;
use Octfx\WikiJournalBot\Request\AbstractBaseRequest;
use Octfx\WikiJournalBot\Request\EditRequest;
use Octfx\WikiJournalBot\Request\PageContentRequest;
use Octfx\WikiJournalBot\Request\TranscludedInRequest;
use RuntimeException;

final class WikiJournalBot {

	/**
	 * SPARQL Query retrieving all 'scholarly_article' (P31)
	 * Published in a known journal (P1433)
	 * With a given volume (P478) and issue (P433)
	 * Sorted by publication date (P577) and page number (P304)
	 *
	 * @see Config::getSupportedJournals()
	 *
	 * @var string
	 */
	public static $PUBLISHED_ARTICLES_QUERY = <<< 'SPARQL'
SELECT DISTINCT ?item ?itemLabel ?image ?pages ?publication WHERE {
  SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE]". }
  {
    SELECT DISTINCT ?item ?image ?pages ?publication WHERE {
      # Instances of 'scholarly_article'
      ?item p:P31 ?statement0.
      ?statement0 (ps:P31/(wdt:P279*)) wd:Q13442814.

      # From a given journal
      ?item p:P1433 ?statement1.
      ?statement1 (ps:P1433/(wdt:P279*)) wd:%s.

      # Filter by volume and issue
      ?item p:P478 ?statement2.
      ?statement2 (ps:P478) "%s".
      ?item p:P433 ?statement3.
      ?statement3 (ps:P433) "%s".

      OPTIONAL{?item wdt:P18 ?image .}
      OPTIONAL{?item wdt:P304 ?pages .}
      OPTIONAL{?item wdt:P577 ?publication .}
    }
    LIMIT 100
  }
}
ORDER BY DESC(?publication) DESC(xsd:integer(?pages))
SPARQL;

	/**
	 * Create an instance
	 */
	public function __construct() {
		$this->logger = Logger::getInstance();
	}

	/**
	 * Check if the page content contains the {{bots}} or {{nobots}} template
	 * Copied from https://en.wikipedia.org/wiki/Template:Bots
	 *
	 * @param string $text
	 * @param string $user
	 * @return bool
	 */
	public static function allowBots( string $text, string $user ): bool {
		if ( preg_match( '/{{(nobots|bots\|allow=none|bots\|deny=all|bots\|optout=all|bots\|deny=.*?' . preg_quote( $user, '/' ) . '.*?)}}/iS', $text ) ) {
			return false;
		}

		if ( preg_match( '/{{(bots\|allow=all|bots\|allow=.*?' . preg_quote( $user, '/' ) . '.*?)}}/iS', $text ) ) {
			return true;
		}

		if ( preg_match( '/{{(bots\|allow=.*?)}}/iS', $text ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Work on all pages that transclude the template set in 'ARTICLE_VOLUME_LIST_TEMPLATE'.
	 * For each page retrieve all articles that match the query in $PUBLISHED_ARTICLES
	 * Replace the content between {{ARTICLE_VOLUME_LIST_TEMPLATE}} and {{LIST_END_TEMPLATE}} with new WikiText
	 *
	 * @return void
	 */
	public function populateArticleLists(): void {
		$this->logger->info( 'Starting population of article lists.' );

		if ( !$this->checkBotAllowed() ) {
			$this->logger->info( 'Bot userpage contains {{nobots}} template, exiting.' );

			return;
		}

		$template = Config::getInstance()->get( 'ARTICLE_VOLUME_LIST_TEMPLATE' );

		if ( $template === null ) {
			$this->logger->error( 'Please set "ARTICLE_VOLUME_LIST_TEMPLATE" in .env.' );
			return;
		}

		$template = sprintf( 'Template:%s', $template );

		$pages = new TranscludedInRequest( $template );
		try {
			$response = AbstractBaseRequest::makeRequest( $pages );
			$pages = json_decode( (string)$response->getBody(), true );
			if ( $pages === null ) {
				$this->logger->error( 'Could not parse body of TranscludedInRequest.' );
				return;
			}
		} catch ( GuzzleException $e ) {
			$this->logger->error( $e->getMessage() );
			$pages = [];
		}

		if ( !isset( $pages['batchcomplete'] ) || isset( $pages['error'] ) ) {
			$this->logger->error( sprintf( 'Could not retrieve list of pages that transclude template "%s".', $template ) );
			return;
		}

		$pages = $pages['query']['pages'];
		$pages = array_shift( $pages ) ?? [];

		foreach ( $pages['transcludedin'] ?? [] as $page ) {
			$this->logger->info( sprintf( 'Working on title "%s".', $page['title'] ) );

			try {
				$this->updatePage( $page['title'] );
			} catch ( RuntimeException | GuzzleException $e ) {
				$this->logger->error( $e->getMessage() );
			}

			sleep( (int)Config::getInstance()->get( 'THROTTLE', 1 ) );
		}

		$this->logger->info( sprintf( 'Done. Processed %d pages.', count( $pages['transcludedin'] ?? [] ) ) );
	}

	/**
	 * Does the actual page edit request
	 *
	 * @throws GuzzleException
	 * @throws RuntimeException
	 */
	private function updatePage( string $title ): void {
		$request = new PageContentRequest( $title );

		try {
			$response = PageContentRequest::getContentFromRequest( $request );
		} catch ( GuzzleException $e ) {
			$this->logger->error( sprintf( 'Could not retrieve page content for title "%s".', $title ), [ 'message' => $e->getMessage() ] );

			return;
		}

		if ( !self::allowBots( $response['content'] ?? '', Config::getInstance()->get( 'BOT_NAME', 'WikiJournalBot' ) ) ) {
			$this->logger->info( sprintf( 'Found {{nobots}} template in page "%s", skipping.', $title ) );

			return;
		}

		$contentCreator = new ContentCreator(
			$response,
			Config::getInstance()->get( 'ARTICLE_VOLUME_LIST_TEMPLATE' ),
			Config::getInstance()->get( 'LIST_END_TEMPLATE' )
		);

		$content = $contentCreator->getUpdatedPageContent();

		// Content is null if an error occurred OR the content did NOT change
		if ( $content !== null ) {
			$edit = new EditRequest( $title, $content );
			$response = AbstractBaseRequest::makeRequest( $edit );
			$this->logger->info( sprintf( 'Updating content for title "%s".', $title ) );

			$response = json_decode( (string)$response->getBody(), true );

			if ( $response === null ) {
				throw new RuntimeException( 'Could not parse response of edit request.' );
			}

			if ( isset( $response['edit']['result'] ) && $response['edit']['result'] === 'Success' ) {
				$this->logger->info( sprintf( 'Successfully updated list for title "%s".', $title ) );
			} else {
				$this->logger->error( sprintf( 'Edit request for title "%s" was unsuccessful.', $title ), $response );
			}
		} else {
			$this->logger->debug( sprintf( 'Skipping page "%s" due to no content change.', $title ) );
		}
	}

	/**
	 * Disables the bot if the '{{nobots}}' template was found on the userpage
	 *
	 * @return bool True if the bot can proceed
	 */
	private function checkBotAllowed(): bool {
		$username = Config::getInstance()->get( 'BOT_NAME', 'WikiJournalBot' );
		$request = new PageContentRequest( sprintf( 'User:%s', $username ) );
		try {
			$response = PageContentRequest::getContentFromRequest( $request );
		} catch ( GuzzleException $e ) {
			$this->logger->error( $e->getMessage() );
			return false;
		}

		if ( empty( $response ) ) {
			return false;
		}

		return self::allowBots( $response['content'], $username );
	}
}
