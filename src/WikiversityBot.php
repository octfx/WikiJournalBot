<?php

declare( strict_types=1 );

namespace Octfx\WikiversityBot;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Octfx\WikiversityBot\Request\AbstractBaseRequest;
use Octfx\WikiversityBot\Request\EditRequest;
use Octfx\WikiversityBot\Request\PageContentRequest;
use Octfx\WikiversityBot\Request\TranscludedInRequest;

final class WikiversityBot {

	/**
	 * SPARQL Query retrieving all 'scholarly_article' (P31)
	 * Published in 'WikiJournal of Medicine' (P1433 = Q24657325)
	 * With a given volume (P478) and issue (P433)
	 *
	 * @var string
	 */
	public static string $PUBLISHED_ARTICLES = <<< 'SPARQL'
SELECT DISTINCT ?item ?itemLabel ?image WHERE {
  SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE]". }
  {
    SELECT DISTINCT ?item ?image WHERE {
      ?item p:P31 ?statement0.
      ?statement0 (ps:P31/(wdt:P279*)) wd:Q13442814.

      ?item p:P1433 ?statement1.
      ?statement1 (ps:P1433/(wdt:P279*)) wd:Q24657325.

      ?item p:P478 ?statement2.
      ?statement2 (ps:P478) "%s".
      ?item p:P433 ?statement3.
      ?statement3 (ps:P433) "%s".

      OPTIONAL{?item wdt:P18 ?image .}
    }
    LIMIT 100
  }
}
SPARQL;

	/**
	 * Create an instance
	 */
	public function __construct() {
		$this->logger = Logger::getInstance();
	}

	/**
	 * Work on all pages that transclude the template set in 'ARTICLE_VOLUME_LIST_TEMPLATE'.
	 * For each page retrieve all articles that match the query in $PUBLISHED_ARTICLES
	 * Replace the content between {{ARTICLE_VOLUME_LIST_TEMPLATE}} and {{ListEnd}} with WikiText
	 *
	 * @return void
	 */
	public function populateArticleLists(): void {
		$this->logger->info( 'Starting population of article lists.' );

		$template = Config::getInstance()->get( 'ARTICLE_VOLUME_LIST_TEMPLATE' );

		if ( $template === null ) {
			$this->logger->error( 'Please set "ARTICLE_VOLUME_LIST_TEMPLATE" in .env.' );
			return;
		}

		$template = sprintf( 'Template:%s', $template );

		$pages = new TranscludedInRequest( $template );
		try {
			$response = AbstractBaseRequest::makeRequest( $pages );
			$pages = json_decode( (string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException | GuzzleException $e ) {
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
			$this->logger->info( sprintf( 'Populating list of articles for title "%s".', $page['title'] ) );

			try {
				$this->updatePage( $page['title'] );
			} catch ( JsonException | GuzzleException $e ) {
				$this->logger->error( $e->getMessage() );
			}

			sleep( (int)Config::getInstance()->get( 'THROTTLE', 1 ) );
		}
	}

	/**
	 * Does the actual page edit request
	 *
	 * @throws GuzzleException
	 * @throws JsonException
	 */
	private function updatePage( string $title ): void {
		$request = new PageContentRequest( $title );

		try {
			$response = AbstractBaseRequest::makeRequest( $request );
		} catch ( GuzzleException $e ) {
			$this->logger->error( sprintf( 'Could not retrieve page content for title "%s".', $title ), [ 'message' => $e->getMessage() ] );

			return;
		}

		$contentCreator = new ContentCreator( json_decode( (string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR ) );

		$content = $contentCreator->getUpdatedPageContent();

		// Content is null if an error occurred OR the content did NOT change
		if ( $content !== null ) {
			$edit = new EditRequest( $title, $content );
			$response = AbstractBaseRequest::makeRequest( $edit );
			$this->logger->info( sprintf( 'Updated title "%s".', $title ) );

			$response = json_decode( (string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR );
			if ( isset( $response['edit']['result'] ) && $response['edit']['result'] === 'success' ) {
				$this->logger->info( sprintf( 'Successfully updated list for title "%s".', $title ) );
			}
		}
	}
}
