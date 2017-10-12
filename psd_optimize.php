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
    die("\nUsage: php psd_optimize.php <\"path to PSD or PNG file\">\n\n");
}

// remove first argument
array_shift($argv);

// get and use remaining arguments
$image = trim( $argv[0] );
$image = str_replace('\\','', $image);
$ext = strtolower( substr($image, -3) );
$fsize = filesize($image);

if( $ext != 'psd' && $ext != 'png' ) {
	die("\nERROR: Input file must be a PSD or PNG file!\n\n");
}

$final_image = substr($image, 0, -4) . '_OPTIMIZED.png';

// if image is a PSD, we only want the visible layers
if($ext == 'psd') {
	$image = $image . '[0]';
}

// first, is there transparency? If not, we can potentially convert to JPEG
$command = "identify -format '%[opaque]' \"$image\"";
$opaque = `$command`;

// next, how many colors?
$command = "identify -format '%k' \"$image\"";
$colors = `$command`;

echo "\nOPAQUE: $opaque\nCOLORS: $colors\n";

// if conditions are met, save as a JPEG
if(stripos($opaque, 'true') > -1 && trim($colors) >= MIN_COLORS && $fsize > MIN_FILESIZE) {
	echo "Saving as JPEG\n";
	$final_image = substr($final_image, 0, -3) . 'jpg';
	$command = "convert \"$image\" -verbose -strip -resize ". MAX_IMG_WIDTH ."x". MAX_IMG_HEIGHT ."\> pnm:- | mozcjpeg -quality ".MOZCJPEG_QUALITY." > \"$final_image\"";
	`$command`;
} else { // save as PNG
	echo "Saving as PNG\n";
	$command = "convert \"$image\" -verbose -strip -resize ". MAX_IMG_WIDTH ."x". MAX_IMG_HEIGHT ."\> \"$final_image\"";
	`$command`;
	$command = "pngquant --quality=50-70 --speed=1 --force --skip-if-larger --verbose --output \"$final_image\" \"$final_image\"";
	`$command`;
	$command = "optipng -clobber -verbose -fix -strip all \"$final_image\"";
	`$command`;
}

echo "\n############################# OPTIMIZATION COMPLETE #############################\n\n";
echo "PATH TO OPTIMIZED IMAGE: $final_image\n\n";

?>
