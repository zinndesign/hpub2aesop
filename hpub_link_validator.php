#!/usr/bin/php
<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);

$script_dir = getcwd();

// check for all required arguments
// first argument is always name of script!
if ($argc < 2) {
    die("\nUsage: hpub_link_validator.php <\"path to hpub zip file\">\n\n");
}

// remove first argument
array_shift($argv);

// get and use remaining arguments
$hpub_zip = trim( $argv[0] );
$hpub_zip = str_replace('\\','',$hpub_zip);

if( substr($hpub_zip, -3) != 'zip') {
	die("\nERROR: Input file must be a zip file!\n\n");
}

// create the directory based on the zipfile
$temp_dir = substr($hpub_zip, 0, -4);

// if this is a repeat conversion, dump the existing optimized dir
`rm -rf "$temp_dir"`;

// first, create the folder
echo "Creating temp directory at $temp_dir\n\n";
mkdir($temp_dir);

// make sure closing slash is in directory path
$temp_dir = $temp_dir . '/';

// next, unzip the files into the directory
`unzip -o "$hpub_zip" -d "$temp_dir"`;

$bookJSON = $temp_dir . 'book.json';
$bookJSON_text = file_get_contents($bookJSON);

$bookJSON_array = json_decode ( file_get_contents($temp_dir . 'book.json'), true );

$badlinks = array();

foreach($bookJSON_array['contents'] as $article) {
	foreach($article['contents'] as $entry) {
		$outputHTML = $temp_dir . $entry['url'];
		$outputHTML_text = file_get_contents($outputHTML);
		
		// check the HTML for bad links - causes problems for Texture
		$pattern = '/<a href="(.*?)">/i';
		preg_match_all($pattern, $outputHTML_text, $matches, PREG_SET_ORDER);
		if( count($matches) > 0 ) {
			foreach($matches as $match) {
				$link = $match[1];
				// must be either articleref://, mailto:, http://, https:// or #
				if( strpos($link, 'http://')===false &&
					strpos($link, 'https://')===false &&
					strpos($link, 'articleref://')===false &&
					strpos($link, 'mailto:')===false &&
					strpos($link, '#')===false ) {
						$badlinks[] = $outputHTML . ": " . $match[0];
				} else if(strpos($link, ' ')!==false) {
					$badlinks[] = $outputHTML . ": " . $match[0] . '(contains 1 or more spaces)';
				} else {
					echo $match[1] . "\n";
				}
			}
		}
	}
}

$logfile = $temp_dir . 'bad_links.log';

// remove it if already there from past check
if(file_exists($logfile)) {
	`rm -f "$logfile"`;
}

if( count($badlinks) > 0 ) {
	echo "\n\n" . count($badlinks) . " bad links were found in this HPUB, listed below and stored in a log file:\n\n";
	sort($badlinks);
	print_r($badlinks);
	file_put_contents($logfile, implode("\n", $badlinks));
	echo "\nPATH TO BAD LINKS LOG: $logfile\n\n";
	
	// remove __MACOSX folder
	$macosx = $temp_dir . '__MACOSX';
	`rm -rf "$macosx"`;
} else {
	echo "\nSUCCESS! No badly formatted links were found in the HPUB.\n\nNOTE: You may still want to review the links listed above for accuracy.\n\n";
	// remove the temp asset directory
	`rm -rf "$temp_dir"`;
}
?>
