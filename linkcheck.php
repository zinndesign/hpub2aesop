<?php

// must be either articleref://, mailto:, http://, https:// or #
$protocols = ['articleref://','http://','https://','mailto:','#'];
$validates = false; // init

// variables for testing
$link = 'https://www.google.com'; // valid
$link = 'WDY070117foodfeaturesalad_lo'; // invalid

foreach($protocols as $protocol) {
	if( strpos($link, $protocol)!==false ) {
		$validates = true;
		break;
	}
}

echo "\n" . $link . ($validates ? ' is valid.' : ' is not valid.') . "\n\n";
?>