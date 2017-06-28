#!/usr/bin/php
<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);

array_shift($argv);

// get and use remaining arguments
$issue = trim( $argv[0] );

$assets = rglob('{*.png,*.jpg,*.jpeg}',GLOB_BRACE,$issue);

$total_asset_size = 0;
$output = [];

foreach ($assets as $image) {
	if(strpos($image, 'portrait')===false && strpos($image, '_lo')===false && strpos($image, 'COS040117')===false) {
		$bytes = filesize($image);
		$output[] = basename($image) . "\t" . $bytes;
		$total_asset_size += $bytes;
	}
}

sort($output);

$contents = implode("\n", $output) . "\n"  . 'TOTAL' . "\t" . human_filesize($total_asset_size) . "\n";

$logfile = $issue . '.log';
file_put_contents($logfile, $contents);
echo $contents;

/**
 * http://us3.php.net/manual/en/function.glob.php#87221
 * Recursive glob()
 * @param int $pattern
 *  the pattern passed to glob()
 * @param int $flags
 *  the flags passed to glob()
 * @param string $path
 *  the path to scan
 * @return mixed
 *  an array of files in the given path matching the pattern.
 */

function rglob($pattern='*', $flags = 0, $path='')
{
    $paths=glob($path.'*', GLOB_MARK|GLOB_ONLYDIR|GLOB_NOSORT);
    $files=glob($path.$pattern, $flags);
    foreach ($paths as $path) { $files=array_merge($files,rglob($pattern, $flags, $path)); }
    return $files;
}

function human_filesize($bytes, $decimals = 2) {
	$sz = 'BKMGTP';
	$factor = floor((strlen($bytes) - 1) / 3);
	return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
}
?>