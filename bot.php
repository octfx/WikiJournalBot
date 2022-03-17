<?php

declare( strict_types=1 );

require_once './vendor/autoload.php';

use Octfx\WikiJournalBot\Config;
use Octfx\WikiJournalBot\Logger;
use Octfx\WikiJournalBot\WikiJournalBot;

$config = Config::getInstance();
$logger = Logger::getInstance();

$bot = new WikiJournalBot();

if ( $argv === null || count( $argv ?? [] ) <= 1 ) {
	echo "Please specify the type of command to run. Available commands are: [updateArticleLists].\n";
	echo 'Call as: php cli.php <Command>';
	exit( 1 );
}

if ( $argv[1] === 'updateArticleLists' ) {
	try {
		$bot->populateArticleLists();
	} catch ( Exception $e ) {
		echo $e->getMessage();

		exit( 1 );
	}
}

exit( 0 );
