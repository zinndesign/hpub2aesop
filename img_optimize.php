#!/usr/bin/php
<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);

define("MAX_IMG_WIDTH", 1800); //1080//1800/2400

// remove first argument
array_shift($argv);

// get and use remaining arguments
$crops_dir = trim( $argv[0] );
$crops_dir = str_replace('\\','',$crops_dir);
$output = '';

// check for oversize images, downsize to MAX_IMG_WIDTH constant value
foreach (glob($crops_dir.'*.{jpg,jpeg,png}', GLOB_BRACE) as $image) {
	$size_before = filesize($image);
	$newImage = false;
	
	//$command = "mogrify -verbose -quality 75 -resize ". MAX_IMG_WIDTH ."x\> -strip \"$image\"";
	$command = "convert \"$image\" -verbose -density 150 -quality 75 -channel RGB -resize ". MAX_IMG_WIDTH ."x\> -strip \"$image\"";
	echo "\n" . $command . "\n";
	`$command`;
	
	// if PNG, run pngquant to reduce
	if(substr($image, -3) === 'png') {
		// first, is there transparency? If not, we can convert to JPEG
		$command = "identify -format '%[opaque]' $image";
		$result = `$command`;
		
		if(stripos($result, 'true') > -1) {
			echo "\n\nOPAQUE IMAGE: Converting to JPEG\n\n";
			$newImage = substr($image, 0, -3) . 'jpg';
			$command = "convert \"$image\" -verbose -quality 75 \"$newImage\"";
			`$command`;
			
			// remove the original image
			$command = "rm -f \"$image\"";
			`$command`;
			
			
		} else {
			$command = "pngquant --quality=60-80 --speed=1 --force --skip-if-larger --verbose --output $image $image";
			`$command`;
			$command = "optipng -o2 -clobber -verbose -fix -strip all $image";
			`$command`;
		}
	}
	
	clearstatcache(); // filesize caches
	$size_after = $newImage ? filesize($newImage) : filesize($image);
	
	$filename = basename( $newImage ? $newImage : $image );
	
	$output .= $filename . "\t" . human_filesize($size_before) . "\t" . human_filesize($size_after) . "\n";
}

echo $output;

// pretty filesize output
function human_filesize($bytes, $decimals = 2) {
	$sz = 'BKMGTP';
	$factor = floor((strlen($bytes) - 1) / 3);
	return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
}
?>