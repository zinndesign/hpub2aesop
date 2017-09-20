#!/usr/bin/php
<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// check for all required arguments
// first argument is always name of script!
if ($argc < 4) {
    die("\nUsage: php cover_resize.php <3-letter Brand Code> <Issue Date - format YYMM> <\"path to cover PNG file\">\n\n");
}

// constants
define("QUALITY", 90);
define("IMG_WIDTH", 1536);

// associative array of brands to define variable image height
$img_height_by_brand = array(
	'ABB' => 2048,
	'CDB' => 2048,
	'CLX' => 2048,
	'COS' => 2048,
	'EDC' => 1994,
	'ELM' => 1900,
	'ESQ' => 2048,
	'FNM' => 1882,
	'GHK' => 2048,
	'HBX' => 2048,
	'HBZ' => 1856,
	'HGV' => 2048,
	'MCX' => 1900,
	'OPR' => 2048,
	'PMX' => 2048,
	'RBK' => 2048,
	'ROA' => 2048,
	'SEV' => 2048,
	'TCX' => 1882,
	'VER' => 2048,
	'WDY' => 2048
);

// remove first argument
array_shift($argv);

$brand = strtoupper( trim( $argv[0] ) );
$issue_date = trim( $argv[1] );
$image_png = trim( $argv[2] );

// run error checks
$errors = array();

if(!array_key_exists($brand, $img_height_by_brand) ) {
	$errors[] = "Unknown brand code. Please check the list of available brand codes below:\n \t* " . implode("\n\t* ", array_keys($img_height_by_brand));
}

if(strlen($issue_date) != 4) {
	$errors[] = 'Issue date should be exactly four numbers (e.g. 1703); please fix.';
}

if(strtolower( substr($image_png, -3)) != 'png') {
	$errors[] = 'Input file must be a PNG. Please obtain a valid PNG file of the cover image.';
} else {
	// we have a PNG, so we can get the dimensions
	$imgsize = getimagesize($image_png);
	if($imgsize[0] < 1536) {
		$errors[] = 'Input file must be at least 1536 pixels wide. Please obtain a cover image of appropriate size.';
	}
}

if(count($errors) > 0) {
	echo "\n\nCover conversion failed. Please address the error(s) listed below:\n";
	print_r($errors);
	die();
}

// format the new filename
$newImage = $brand . '_' . $issue_date . '@2x.jpg';

// add the path
$split = explode('/', $image_png);
array_pop($split);
$split[] = $newImage;
$image_jpeg = implode('/', $split);

echo $image_jpeg . "\n\n";

$command = "convert \"$image_png\" -verbose -strip -resize ". IMG_WIDTH ."x". $img_height_by_brand[$brand] ."\> pnm:- | mozcjpeg -quality ". QUALITY ." > \"$image_jpeg\"";

echo $command . "\n";
`$command`;
?>