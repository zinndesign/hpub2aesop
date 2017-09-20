#!/usr/bin/php
<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);

define("MAX_IMG_HEIGHT", 2048);
define("MAX_IMG_WIDTH", 1800);
define("MOZCJPEG_QUALITY", 70);
define("MIN_FILESIZE", 100000); // 100kb in bytes
define("MIN_COLORS", 16);

$script_dir = getcwd();
$convert_path = trim(`which convert`); // for debugging ImageMagick issues

// check for all required arguments
// first argument is always name of script!
if ($argc < 2) {
    die("\nUsage: hpub_optimize.php <\"path to hpub zip file\">\n\n");
}

// remove first argument
array_shift($argv);

// get and use remaining arguments
$hpub_zip = trim( $argv[0] );
$hpub_zip = str_replace('\\','',$hpub_zip);
$optimized_zip = substr($hpub_zip, 0, -4) . '_OPTIMIZED.zip';

if( substr($hpub_zip, -3) != 'zip') {
	die("\nERROR: Input file must be a zip file!\n\n");
}

// create the directory based on the zipfile
$output_dir = substr($hpub_zip, 0, -4) . '_OPTIMIZED';

// if this is a repeat conversion, dump the existing optimized dir
`rm -rf "$output_dir"`;

// first, create the folder
echo "Creating asset directory at $output_dir\n\n";
mkdir($output_dir);

// make sure closing slash is in directory path
$output_dir = $output_dir . '/';

// next, unzip the files into the directory
$output = `unzip -o "$hpub_zip" -d "$output_dir"`;
echo "UNZIPPING FILES:\n" . $output . "\n";

$bookJSON = $output_dir . 'book.json';
$bookJSON_text = file_get_contents($bookJSON);

$bookJSON_array = json_decode ( file_get_contents($output_dir . 'book.json'), true );

$fontfamily = array();
$article_IDs = array(); // flat array for validating intra-article links
$badlinks = array();

// create an associative array of all articles in book.json
foreach ($bookJSON_array["contents"] as $article) {
	$id = $article['metadata']['id']; // article ID - use for articleref
	$article_IDs[] = $id;
}

foreach($bookJSON_array['contents'] as &$article) {
	foreach($article['contents'] as &$entry) {
		$outputHTML = $output_dir . $entry['url'];
		$outputPSV = substr($outputHTML, 0, -4) . 'psv';
		
		$outputPSV_text = file_get_contents($outputPSV);
		$outputHTML_text = file_get_contents($outputHTML);
		
		// Inception bug introduces line break before links - this fixes
		$outputHTML_text = preg_replace('/\(\n\s*<a/', '(<a', $outputHTML_text);
		
		// new introduction of HTML doctype is breaking CSS - strip it out
		$outputHTML_text = str_replace('<!doctype html>', '', $outputHTML_text);
		
		file_put_contents($outputHTML, $outputHTML_text);
		
		// check the HTML for bad links - causes problems for Texture
		$pattern = '/<a href="(.*?)">(.*?)<\/a>/i';
		preg_match_all($pattern, $outputHTML_text, $matches, PREG_SET_ORDER);
		if( count($matches) > 0 ) {
			foreach($matches as $match) {
				$link = $match[1];
				$content = $match[2];
				// must be either articleref://, mailto:, http://, https:// or #
				if( strpos($link, 'http://')===false &&
					strpos($link, 'https://')===false &&
					strpos($link, 'articleref://')===false &&
					strpos($link, 'mailto:')===false &&
					strpos($link, '#')===false ) {
					$badlinks[] = $outputHTML . ": " . $match[0] . ' (missing or invalid protocol)';
					echo $match[1] . " (missing or invalid protocol)\n";
				} else if(strpos($link, ' ')!==false) {
					// added 7/21/17 - fix bad spaces in link and update HTML
					$fixed_link = str_replace(' ','',$link);
					$outputHTML_text = str_replace($link, $fixed_link, $outputHTML_text);
					file_put_contents($outputHTML, $outputHTML_text);
					echo $match[1] . " (spaces removed)\n";
				} else if(strpos($link, 'articleref://')!==false) { // check if the link matches an article identifier in $article_IDs array
					// get just the article identifier from the full link
					$pattern = '/articleref:\/\/dc\/([a-zA-Z0-9-_&;]*)\/*.*?/i';
					preg_match($pattern, htmlspecialchars_decode($link), $matches);
					
					// check if the link matches an article identifier in $article_IDs array
					$matchtest = array_search($matches[1], $article_IDs);
					if($matchtest===false) {
						$badlinks[] = $outputHTML . ": " . $match[0] . ' (invalid article identifier)';
						echo $match[1] . " (invalid article identifier)\n";
					}
				} else {
					echo $match[1] . " (valid link format)\n";
				}
			}
		}
		
		// added 7/25/17: check for comma-separated font list - causes display problems in MAZ
		$pattern = '/<span style="font-family:\s*(.*?)">.*?<\/span>/i';
		preg_match_all($pattern, $outputHTML_text, $matches, PREG_SET_ORDER);
		if( count($matches) > 0 ) {
			foreach($matches as $match) {
				if(strpos($match[1], ',')) {
					$fontfamily[$entry['metadata']['id']][] = $match[0];
				}
			}
		}
		
		foreach($entry['manifest'] as &$asset) {
			$assetURL = $output_dir . $asset['url'];
			$mediaType = $asset['media_type'];
			
			/********** We are only modifying PNG files - so here is where we check **************/
			if($mediaType == 'image/png') {
				// build the URL to the image
				$image = $assetURL;
				
				// first, is there transparency? If not, we can potentially convert to JPEG
				$command = "identify -format '%[opaque]' \"$image\"";
				$opaque = `$command`;
				
				// next, how many colors?
				$command = "identify -format '%k' \"$image\"";
				$colors = `$command`;
				
				if(strpos($opaque, 'true') > -1 && trim($colors) >= MIN_COLORS && filesize($image) > MIN_FILESIZE) {
					echo "\n\nOPAQUE IMAGE: Converting to JPEG\n\n";
					$newImage = substr($image, 0, -3) . 'jpg';
					//$command = "convert \"$image\" -verbose -strip -quality 60% \"$newImage\"";
					$command = "convert \"$image\" -verbose -strip -resize ". MAX_IMG_WIDTH ."x". MAX_IMG_HEIGHT ."\> pnm:- | mozcjpeg -quality ".MOZCJPEG_QUALITY." > \"$newImage\"";
					`$command`;
					
					// remove the original image, update the filename where it appears
					$command = "rm -f \"$image\"";
					`$command`;
					
					// contents of each for find-and-replace
					$outputPSV_text = file_get_contents($outputPSV);
					$outputHTML_text = file_get_contents($outputHTML);
					//$manifestXML_text = file_get_contents($manifestXML);
					
					// since the paths vary, just do a replace on the base filename
					$parts = explode('/', $image);
					$find = array_pop($parts); // image.png
					$replace = substr($find, 0, -3) . 'jpg';
					$outputPSV_text = str_replace($find, $replace, $outputPSV_text);
					$outputHTML_text = str_replace($find, $replace, $outputHTML_text);
					
					// update the modified files -- we do this each time because at the end, we'll update the sha1 for the PSV
					file_put_contents($outputPSV, $outputPSV_text);
					file_put_contents($outputHTML, $outputHTML_text);
					
					// update the media_type; we'll do the sha1 below
					$asset['media_type'] = 'image/jpeg';
					$asset['url'] = substr($asset['url'], 0, -3) . 'jpg';
					
					// update the asset URL path for sha1 below
					$assetURL = $output_dir . $asset['url'];
				} else {
					$command = "pngquant --quality=50-70 --speed=1 --force --skip-if-larger --verbose --output \"$image\" \"$image\"";
					`$command`;
					$command = "optipng -clobber -verbose -fix -strip all \"$image\"";
					`$command`;
				}
				// in either case, we've changed the hash, so we should update it
				$hash = sha1_file($assetURL, true);
				$asset['sha1'] = base64_encode($hash);
			} elseif($mediaType == 'image/jpeg') {
				// added April 2017 - mozjpeg compression
				$image = $assetURL;
				$tempImage = substr($image, 0, -4) . '_TMP.jpg';
				$command = "convert \"$image\" -verbose -strip -resize ". MAX_IMG_WIDTH ."x". MAX_IMG_HEIGHT ."\> pnm:- | mozcjpeg -quality ".MOZCJPEG_QUALITY." > $tempImage";
				`$command`;
				
				// remove the original image, rename the temp image
				$command = "rm -f \"$image\"";
				`$command`;
				$command = "mv $tempImage $image";
				`$command`;
				
				$hash = sha1_file($assetURL, true);
				$asset['sha1'] = base64_encode($hash);
			} elseif($mediaType == 'application/xml') {
				// the only other entry to update is PSV
				// it may not have changed, but we'll check that here
				$hash = sha1_file($assetURL, true);
				$asset['sha1'] = base64_encode($hash);
			}
		}
	}
}

print_r($bookJSON_array);

// update book.json after all directories have been processed
$new_json = json_encode($bookJSON_array, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

$output_file = $output_dir . 'book.json';
file_put_contents($output_file, $new_json);

// delete all the thumbs in the root directory
$command = "rm -f ". $output_dir ."*.png";
`$command`;

// remove the bad links log, if present
$logfile = $output_dir . 'bad_links.log';
if(file_exists($logfile)) {
	`rm -f "$logfile"`;
}

// zip the files
chdir($output_dir);
$input = `zip -rv "$optimized_zip" ./ --exclude="*/._*"`;
echo "RE-ZIPPING FILES:\n" . $input . "\n";

chdir($script_dir);

// remove the asset directory
`rm -rf "$output_dir"`;

echo "\n\n############################# OPTIMIZATION COMPLETE #############################\n\n";
echo "PATH TO OPTIMIZED ZIPFILE: $optimized_zip\n\n";

if(count($fontfamily) > 0) {
	echo "PLEASE NOTE: THERE MAY BE FONT DISPLAY ISSUES IN THE ARTICLES BELOW:\n";
	print_r($fontfamily);
}

if( count($badlinks) > 0 ) {
	echo "\n\n" . count($badlinks) . " bad links were found in this HPUB, listed below:\n\n";
	sort($badlinks);
	print_r($badlinks);
}
?>
