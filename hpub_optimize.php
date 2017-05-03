#!/usr/bin/php
<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);

define("MOZCJPEG_QUALITY", 70);
define("MAX_IMG_HEIGHT", 2048);

$script_dir = getcwd();
$convert_path = trim(`which convert`); // for debugging ImageMagick issues

// check for all required arguments
// first argument is always name of script!
if ($argc < 2) {
    die("Usage: hpub_optimize.php <\"path to hpub directory\">");
}

// remove first argument
array_shift($argv);

// get and use remaining arguments
$hpub_dir = trim( $argv[0] );
$hpub_dir = str_replace('\\','',$hpub_dir);

$output_dir = $hpub_dir . '_OPTIMIZED';

// if this is a repeat conversion, dump the existing optimized dir
`rm -rf "$output_dir"`;

// first, duplicate the folder
echo "Creating optimized copy at $output_dir\n\n";
`cp -aR  "$hpub_dir" "$output_dir"`;

// make sure closing slash is in directory path
$output_dir = $output_dir . '/';
$bookJSON = $output_dir . 'book.json';
$bookJSON_text = file_get_contents($bookJSON);

$bookJSON_array = json_decode ( file_get_contents($output_dir . 'book.json'), true );

foreach($bookJSON_array['contents'] as &$article) {
	foreach($article['contents'] as &$entry) {
		$outputHTML = $output_dir . $entry['url'];
		$outputPSV = substr($outputHTML, 0, -4) . 'psv';
		
		$outputPSV_text = file_get_contents($outputPSV);
		$outputHTML_text = file_get_contents($outputHTML);
		
		// Inception bug introduces line break before links - this fixes
		$outputHTML_text = preg_replace('/\(\n\s*<a/', '(<a', $outputHTML_text);
		file_put_contents($outputHTML, $outputHTML_text);
		
		// check the HTML for bad links - causes problems for Texture
		$pattern = '/<a href="(\S+)">/i';
		preg_match_all($pattern, $outputHTML_text, $matches, PREG_SET_ORDER);
		if( count($matches) > 0 ) {
			$counter = 1;
			foreach($matches as $match) {
				$link = $match[1];
				// must be either articleref://, mailto:, http://, or https://
				if( strpos($link, 'http://')===false &&
					strpos($link, 'https://')===false &&
					strpos($link, 'articleref://')===false &&
					strpos($link, 'mailto:')===false ) {
					die("\n\n**************** HALTING PROCESSING: bad link in " . $outputHTML . "(" . $match[0] . ")\n");
				} else {
					echo $counter . '. ' . $match[1] . "\n";
				}
				$counter++;
			}
		}
		
		foreach($entry['manifest'] as &$asset) {
			$assetURL = $output_dir . $asset['url'];
			$mediaType = $asset['media_type'];
			
			/********** We are only modifying PNG files - so here is where we check **************/
			if($mediaType == 'image/png') {
				// build the URL to the image
				$image = $assetURL;
				
				// first, is there transparency? If not, we can convert to JPEG
				$command = "identify -format '%[opaque]' $image";
				$result = `$command`;
				
				if(strpos($result, 'true') > -1) {
					echo "\n\nOPAQUE IMAGE: Converting to JPEG\n\n";
					$newImage = substr($image, 0, -3) . 'jpg';
					//$command = "convert \"$image\" -verbose -strip -quality 60% \"$newImage\"";
					$command = "convert \"$image\" -verbose -strip -resize x". MAX_IMG_HEIGHT ."\> pnm:- | mozcjpeg -quality ".MOZCJPEG_QUALITY." > \"$newImage\"";
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
					$command = "pngquant --quality=50-70 --speed=1 --force --skip-if-larger --verbose --output $image $image";
					`$command`;
					$command = "optipng -clobber -verbose -fix -strip all $image";
					`$command`;
				}
				// in either case, we've changed the hash, so we should update it
				$hash = sha1_file($assetURL, true);
				$asset['sha1'] = base64_encode($hash);
			} elseif($mediaType == 'image/jpeg') {
				// added April 2017 - mozjpeg compression
				$image = $assetURL;
				$tempImage = substr($image, 0, -4) . '_TMP.jpg';
				$command = "convert \"$image\" -verbose -strip -resize x". MAX_IMG_HEIGHT ."\> pnm:- | mozcjpeg -quality ".MOZCJPEG_QUALITY." > $tempImage";
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

echo "\n\n############################# OPTIMIZATION COMPLETE #############################\n\n";
echo "PATH TO CONVERTED ASSETS: $output_dir\n\n";
?>