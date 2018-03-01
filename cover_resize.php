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
// add new brands to this list as needed
$img_height_by_brand = array(
	'ABB' => 2048,
	'CDB' => 2148,
	'CLX' => 2048,
	'COS' => 2048,
	'EDC' => 1994,
	'ELM' => 1880,
	'ESQ' => 2022,
	'ESB' => 2048,
	'FNM' => 1964,
	'GHK' => 2080,
	'HBX' => 2022,
	'HBZ' => 1880,
	'HGV' => 1937, // 2048 thru 11/2017
	'MCX' => 1880,
	'OPR' => 1936,
	'PMX' => 2048,
	'PWM' => 2048,
	'RBK' => 2148,
	'ROA' => 2088,
	'SEV' => 2114,
	'TCX' => 1882,
	'VER' => 2022,
	'WDY' => 2048
);

// remove first argument
array_shift($argv);

$brand = strtoupper( trim( $argv[0] ) );
$issue_date = trim( $argv[1] );
$src_image = trim( $argv[2] );

$src_ext = strtolower( substr($src_image, -3)); // png or pdf

// run error checks
$errors = array();

if(!array_key_exists($brand, $img_height_by_brand) ) {
	$errors[] = "Unknown brand code. Please check the list of available brand codes below:\n \t* " . implode("\n\t* ", array_keys($img_height_by_brand));
}

if(strlen($issue_date) != 4) {
	$errors[] = 'Issue date should be exactly four numbers (e.g. 1703); please fix.';
}

if($src_ext != 'png' && $src_ext != 'pdf') {
	$errors[] = 'Input file must be a PNG or PDF. Please obtain a valid PNG or PDF file of the cover image.';
} elseif(strpos($src_image, ' ') > -1) {
	$errors[] = 'Input filename has spaces - please remove and try again.';
} elseif($src_ext == 'png') { // if we have a PNG, check the dimensions
	$imgsize = getimagesize($src_image);
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
$split = explode('/', $src_image);
array_pop($split);
$split[] = $newImage;
$final_img = implode('/', $split);

echo $final_img . "\n\n";

$command = "convert \"$src_image\" -verbose -strip -resize ". IMG_WIDTH ."x". $img_height_by_brand[$brand] ."\! pnm:- | mozcjpeg -quality ". QUALITY ." > \"$final_img\"";

echo $command . "\n";
`$command`;
?>