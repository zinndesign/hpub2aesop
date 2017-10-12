#!/usr/bin/php
<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);

$script_dir = getcwd();

// check for all required arguments
// first argument is always name of script!
if ($argc < 2) {
    die("\nUsage: hpub_link_validator.php <\"path to hpub zip file\"> quick (optional - skips URL loads)\n\n");
}

// remove first argument
array_shift($argv);

// get and use remaining arguments
$hpub_zip = trim( $argv[0] );
$hpub_zip = str_replace('\\','',$hpub_zip);

if( substr($hpub_zip, -3) != 'zip') {
	die("\nERROR: Input file must be a zip file!\n\n");
}

if( $argc == 3 ) {
	$skip_url_loads = true;
} else {
	$skip_url_loads = false;
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
$emptylinks = array();
$article_IDs = array(); // flat array for validating intra-article links

echo "Generating array of article identifiers for articleref link validation...\n\n";
// create an associative array of all articles in book.json
foreach ($bookJSON_array["contents"] as $article) {
	$id = $article['metadata']['id']; // article ID - use for articleref
	$article_IDs[] = $id;
}

print_r($article_IDs);

foreach($bookJSON_array['contents'] as $article) {
	foreach($article['contents'] as $entry) {
		$outputHTML = $temp_dir . $entry['url'];
		$outputHTML_text = file_get_contents($outputHTML);
		
		// check the HTML for bad links - causes problems for Texture
		$pattern = '/<a href="(.*?)">(.*?)<\/a>/i';
		preg_match_all($pattern, $outputHTML_text, $matches, PREG_SET_ORDER);
		if( count($matches) > 0 ) {
			foreach($matches as $match) {
				$link = $match[1];
				$content = $match[2];
				// link around <br> tag or empty content
				/*if($content == '' || strpos($content, '<br') == 0) {
					$emptylinks[] = $outputHTML . ": " . $match[0] . ' (no linked content)';
					echo $match[0] . " (no linked content)\n";
				// must be either articleref://, mailto:, http://, https:// or #
				} else*/
				if( stripos($link, 'http://')===false &&
					stripos($link, 'https://')===false &&
					stripos($link, 'articleref://')===false &&
					stripos($link, 'mailto:')===false &&
					stripos($link, '#')===false ) {
						$badlinks[] = $outputHTML . ": " . $match[0] . ' (missing or invalid protocol)';
						echo $match[1] . " (missing or invalid protocol)\n";
				} else if(strpos($link, ' ')!==false) {
					$badlinks[] = $outputHTML . ": " . $match[0] . ' (contains 1 or more spaces)';
					echo $match[1] . " (contains 1 or more spaces)\n";
				} else if(stripos($link, 'articleref://')!==false) {
					// get just the article identifier from the full link
					$pattern = '/articleref:\/\/dc\/([a-zA-Z0-9-_&;]*)\/*.*?/i';
					preg_match($pattern, htmlspecialchars_decode($link), $matches);
					
					// check if the link matches an article identifier in $article_IDs array
					$matchtest = array_search($matches[1], $article_IDs);
					if($matchtest===false) {
						$badlinks[] = $outputHTML . ": " . $match[0] . ' (invalid article identifier)';
						echo $match[1] . " (invalid article identifier)\n";
					} else {
						echo $match[1] . " (valid articleref link)\n";
					}
				} else if(stripos($link, 'http') == 0 && !$skip_url_loads) { // validate web url - skipped if extra param is present
					$status = validateURL($link);
					// NOTE: 403 and 406 are a result of the call coming from cURL and not a browser
					if( ($status > 199 && $status < 400) || $status == 403 || $status = 406 ) {
						echo $match[1] . " (valid link format - code $status)\n";
					} else {
						$badlinks[] = $outputHTML . ": " . $match[0] . " (URL did not load - code $status)";
						echo $match[1] . " (URL did not load - code $status)\n";
					}
				} else {
					echo $match[1] . " (valid web URL)\n";
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
	echo "\n\n" . count($badlinks) . " bad links were found in this HPUB, listed below:\n\n";
	sort($badlinks);
	print_r($badlinks);
	//file_put_contents($logfile, implode("\n", $badlinks));
	//echo "\nPATH TO BAD LINKS LOG: $logfile\n\n";
	
	// remove __MACOSX folder
	$macosx = $temp_dir . '__MACOSX';
	`rm -rf "$macosx"`;
} else {
	echo "\nSUCCESS! No badly formatted links were found in the HPUB.\n\nNOTE: You may still want to review the links listed above for accuracy.\n\n";
	// remove the temp asset directory
	`rm -rf "$temp_dir"`;
}

//if( count($emptylinks) > 0 ) {
//	echo "\n\n" . count($emptylinks) . " empty links were found in this HPUB, listed below:\n\n";
//	sort($emptylinks);
//	print_r($emptylinks);
//	echo "\nThese links will not function. This is due to an Inception bug.";
//}

/**** FUNCTIONS ****/

function validateURL($url) {
    $curl = curl_init($url);
    
	curl_setopt($curl, CURLOPT_NOBODY, TRUE);
	// prevent false 403 or 406 by setting user agent
	curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/603.2.4 (KHTML, like Gecko) Version/10.1.1 Safari/603.2.4');
	curl_setopt($curl, CURLOPT_FAILONERROR, TRUE);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($curl, CURLOPT_TIMEOUT, 30);
	
    /* Get the HTML or whatever is linked in $url. */
    $response = curl_exec($curl);

    /* Check for 404 (file not found). */
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
	
	return $httpCode;
}
?>
