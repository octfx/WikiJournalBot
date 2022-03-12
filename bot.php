<?php

declare( strict_types=1 );

require_once './vendor/autoload.php';

use Octfx\WikiversityBot\Config;
use Octfx\WikiversityBot\Logger;
use Octfx\WikiversityBot\WikiversityBot;

$config = Config::getInstance();
$logger = Logger::getInstance();

$bot = new WikiversityBot();

try {
	$bot->populateArticleLists();
} catch ( Exception $e ) {
	echo $e->getMessage();

	exit( 1 );
}

exit( 0 );
