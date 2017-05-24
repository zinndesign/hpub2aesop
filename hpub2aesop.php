#!/usr/bin/php
<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);

define("MAX_IMG_WIDTH", 1800); //1080//1800/2400
define("MAX_IMG_HEIGHT", 2048);
define("MOZCJPEG_QUALITY", 70);
define("MIN_FILESIZE", 100000); // 100kb in bytes
define("MIN_COLORS", 16);

$script_dir = getcwd();
$convert_path = trim(`which convert`); // for debugging ImageMagick issues

// check for all required arguments
// first argument is always name of script!
if ($argc < 4) {
    die("Usage: hpub2aesop.php <issuename> <target_device> <\"path to hpub directory\"> <\"path to output directory (optional)\">\nTarget device options: iphone4, iphone5, iphone6, iphone6+, ipad_retina, ipad_nonretina, all");
}

// remove first argument
array_shift($argv);

// get and use remaining arguments
$issue = trim( $argv[0] );
$device = trim( $argv[1] );
$hpub_dir = trim( $argv[2] );
// if the output directory wasn't passed, use the script directory
$output_dir = ( count($argv)==4 ? trim( $argv[3] ) : $script_dir . '/' );

$devices = array(
	'ipad_retina' => array( 'dirname' => 'retina', 'pixels' => '1536*2048', 'zoom' => '2'),
	'iphone6+' => array( 'dirname' => 'iphone1080', 'pixels' => '1242*2208', 'zoom' => '3'),
	'iphone6' => array( 'dirname' => 'iphone6', 'pixels' => '750*1334', 'zoom' => '2'),
	'iphone5' => array( 'dirname' => 'iphone169', 'pixels' => '640*1136', 'zoom' => '2'),
	'ipad_nonretina' => array( 'dirname' => 'nonretina', 'pixels' => '768*1024', 'zoom' => '1'),
	'iphone4' => array( 'dirname' => 'iphone', 'pixels' => '640*960', 'zoom' => '2')
);

if(!array_key_exists($device, $devices) && $device!='all') {
	die("\n\nThe device '$device' is unknown. Supported input values are:\n\t* iphone4\n\t* iphone5\n\t* iphone6\n\t* iphone6+\n\t* ipad_retina\n\t* ipad_nonretina\n\t* all\n\n");
} else {
	if($device!='all') {
		// reduce $devices array to the specified device
		$temp = $devices[$device];
		$devices = [];
		$devices[$device] = $temp;
	}
}

// if the filepath has spaces, command line may have escaped; remove backslashes
$hpub_dir = str_replace('\\','',$hpub_dir);
$output_dir = str_replace('\\','',$output_dir);

// make sure closing slash is in directory paths
if(substr($hpub_dir, -1) != '/') {
    $hpub_dir = $hpub_dir . '/';
}

if(substr($output_dir, -1) != '/') {
    $output_dir = $output_dir . '/';
}

// temporarily, we will strip the inline base64 fonts
// by running a shell script from John Logan at Texture
chdir($hpub_dir);
$command = $script_dir . '/reduce-inline.sh';
//`$command`;
chdir($script_dir);

// book.json contents
$book_json = json_decode ( file_get_contents($hpub_dir . 'book.json'), true );

$tocpage = 1; // default

// for brands that want the title metadata suppressed
if(substr($issue, 0, 3) == 'ESQ') {
	$plist_page_template = file_get_contents('Replica_page_notitle.xml');
} else {
	$plist_page_template = file_get_contents('Replica_page.xml');	
}

$page_entry_xml = <<<EOD
<dict>
		<key>background-portrait</key>
		<string>background-portrait-data</string>
		<key>background-portrait-thumb</key>
		<string>background-portrait-thumb-data</string>
		<key>plist</key>
		<string>plist-data</string>
	</dict>
EOD;
	
$articles = array();
$article_IDs = array(); // flat array for creating intra-article links
// create an associative array of all articles in book.json
foreach ($book_json["contents"] as $article) {
	$pages = array();
	$story_array = array();
	$section = $article['metadata']['section'];
	$title = $article['title'];
	$id = $article['metadata']['id']; // article ID - use for articleref
	$article_IDs[] = $id;
	//$preview = $hpub_dir . $article["preview"]; // this should move into $contents soon (below)
	// multi-page article support
	foreach($article["contents"] as $contents) {
		$temp_array = array();
		$temp_array["page_id"] = $contents["metadata"]["id"]; // page ID - for sub-page linking, eventually
		$article_IDs[] = $temp_array["page_id"]; // for aesop-nav bug, we need all the sub-pages in the article IDs array
		$split = explode('/', $contents['url']);
		$temp_array['assets_dir'] = $split[0];
		$temp_array['preview'] = $hpub_dir . $contents["preview"]; // waiting for Texture to fix
		//$temp_array['preview'] = $preview; // hack
		$pages[] = $temp_array;
	}
	$story_array['title'] = $title;
	$story_array['section'] = $section;
	$story_array['pages'] = $pages;
	$articles[] = $story_array;
}

// hackery to workaround aesop-nav bug
// array_filter preserves keys, so we'll re-write the values to $article_IDs
$nav_array = array_filter($article_IDs, "removeNavDupes");
$article_IDs = [];
foreach($nav_array as $val) {
	$article_IDs[] = $val;
}

print_r($articles);

// set up base directory, if it doesn't exist
$base_dir = $output_dir . $issue . '/';
if(!is_dir($base_dir)) {
	mkdir( $base_dir, 0777 );	
}

// loop through each device and create assets
foreach($devices as $device => $device_type) {
	$total_asset_size = 0; // we reset for each device
	$issue_dir = $base_dir . $device_type['dirname'];
	$thumbs_dir = $issue_dir . '/' . $issue . '_thumbs';
	
	`rm -rf "$issue_dir"`; // clear if existing
	mkdir( $issue_dir, 0777 );
	mkdir( $thumbs_dir, 0777 );
	
	$pages_plist = array();
	
	// loop through all articles
	foreach ($articles as $key => $article) {
		$label = $issue . '_' . str_pad($key, 3, '0', STR_PAD_LEFT) . '_000';
		$article_dir = $issue . '/' . $label;
		$article_pages_dir = $article_dir . '/pages/';
		$sub_pages_plist = array(); // to hold each page for Replica.plist entry
		
		// add the directories, recursively
		mkdir( $article_pages_dir . 'images/', 0777, true );
		
		// identify the TOC - usually page 1 or 2
		if(stripos($article['title'], 'table of contents') > -1) {
			$tocpage = $key;
			echo "TABLE OF CONTENTS: $key\n\n";
		}
		
		// for multi-page, we need to loop through pages here
		foreach($article['pages'] as $key => $page) {
			
			// variables for each page
			$assets = $page['assets_dir'];
			$assets_dir = $article_pages_dir . 'images/' . $assets;
			
			$page = substr( $label, 0, -3) . str_pad($key, 3, '0', STR_PAD_LEFT);
			$plist = $issue.'/'.$label.'/'. $page .'.plist';
			
			// create the page plist and copy the story assets over
			$cmd = 'cp -R ' . $hpub_dir . $assets . " " . $article_pages_dir . 'images/';
			`$cmd`;
			
			$output = $assets_dir . '/output.html';
			$output_html = file_get_contents($output);
			
			// check for oversize images, downsize to MAX_IMG_WIDTH constant value
			// NOTE: as of December 2016, this should be happening in the .xin script -- commenting out exec calls
			// NOTE: May 2017, switching to cap on height due to giant partial adverts
			$imgpath = $article_pages_dir . 'images/' . $assets . '/img/';
			foreach (glob($imgpath.'*.{jpg,jpeg,png}', GLOB_BRACE) as $image) {
				$command = "convert \"$image\" -verbose -resize ". MAX_IMG_HEIGHT ."x". MAX_IMG_HEIGHT ."\> -strip \"$image\"";
				`$command`;
				
				// if PNG, run pngquant to reduce
				if(substr($image, -3) === 'png') {
					// first, is there transparency? If not, we can potentially convert to JPEG
					$command = "identify -format '%[opaque]' $image";
					$opaque = `$command`;
					
					// next, how many colors?
					$command = "identify -format '%k' $image";
					$colors = `$command`;
					
					if(strpos($opaque, 'true') > -1 && $colors >= MIN_COLORS && filesize($image) > MIN_FILESIZE) {
						echo "\n\nOPAQUE IMAGE: Converting to JPEG\n\n";
						$newImage = substr($image, 0, -3) . 'jpg';
						//$command = "convert \"$image\" -verbose -strip -quality MOZCJPEG_QUALITY% \"$newImage\"";
						$command = "convert \"$image\" -verbose -strip pnm:- | mozcjpeg -quality ". MOZCJPEG_QUALITY ." > \"$newImage\"";
						`$command`;
						
						// remove the original image, update the HTML
						$command = "rm -f \"$image\"";
						`$command`;
						
						// since the paths vary, just do a replace on the base filename
						$parts = explode('/', $image);
						$find = array_pop($parts); // image.png
						$replace = substr($find, 0, -3) . 'jpg';
						$output_html = str_replace($find, $replace, $output_html);
					} else {
						//$command = "convert \"$image\" -strip \"$image\""; // strip image metadata - optipng is handling this now
						//`$command`;
						$command = "pngquant --quality=50-75 --speed=1 --force --skip-if-larger --verbose --output $image $image";
						`$command`;
						$command = "optipng -clobber -verbose -fix -strip all $image";
						`$command`;
					}
				} elseif(substr($image, -3) === 'jpg') {
					$tempImage = substr($image, 0, -4) . '_TMP.jpg';
					$command = "convert -verbose -strip $image pnm:- | mozcjpeg -quality ". MOZCJPEG_QUALITY ." > $tempImage";
					`$command`;
					
					// remove the original image, rename the temp image
					$command = "rm -f \"$image\"";
					`$command`;
					$command = "mv $tempImage $image";
					`$command`;
				}
			}
			
			// add shownav.js to the template dir, inject into the HTML
			// this is to recognize taps to toggle the HUD display from webelement
			$cmd = 'cp ' . $script_dir . '/shownav.js ' . $assets_dir . '/template/shownav.js';
			`$cmd`;
			
			$output_html = str_replace('</body>', "<script src=\"template/shownav.js\"></script>\n</body>", $output_html);
			
			// update the viewport meta tag maximum-scale to 3.0, in order to support zooming
			$output_html = str_replace('maximum-scale=1', 'maximum-scale=3.0', $output_html);
			
			// Inception bug introduces line break before links - this fixes
			$output_html = preg_replace('/\(\n\s*<a/', '(<a', $output_html);
			
			// search for articleref:// links and convert to aesop-nav:// -- sub-page linking is broken in Aesop
			$aesoplink = 'aesop-nav://issue?storyIndex=';
			$pattern = '/articleref:\/\/dc\/([a-zA-Z0-9-_&;]*)/i';
			preg_match_all($pattern, $output_html, $matches, PREG_SET_ORDER);
			if( count($matches) > 0 ) {
				foreach($matches as $match) {
					// get the index in the articles array
					// this requires looping through the "pages" array for each article
					//for($o = 0; $o < count($articles); $o++) {
					//	$page_array = $articles[$o]['pages'];
					//	print_r($page_array);
					//	// need to fix this for multi
					//	for($i = 0; $i < count($page_array); $i++) {
					//		echo "%%%%%%%%%%%%%%%%%%%%% MATCH: ". $match[1] . "\n";
					//		echo "%%%%%%%%%%%%%%%%%%%%% ARTICLE_ID: ". $page_array[$i]['page_id'] . "\n\n";
					//		if($match[1] == $page_array[$i]['page_id']) {
					//			$newLink = $aesoplink . $o . ( $i > 0 ? "&verticalPageIndex=$i":'');
					//		}
					//	}
					//}
					// line below for making this work with broken aesop-nav:// linking
					// verticalPageIndex is ignored, page count is linear,
					// so we search the special $article_IDs array (hack!)
					$matchtest = array_search(htmlspecialchars_decode($match[1]), $article_IDs);
					if($matchtest) {
						$newLink = $aesoplink . $matchtest;
						echo $match[0] . ' CONVERTED TO ' . $newLink . "\n\n";
						$output_html = str_replace($match[0], $newLink, $output_html);
					} else {
						echo '****************************' . $match[0] . " is not a valid articleref link!\n\n";	
					}
				}
			}
			file_put_contents($output, $output_html);
			
			$page_plist = file_get_contents('Page_plist.xml');
			$page_plist = str_replace('url-data', 'pages/images/'. $assets . '/output.html', $page_plist);
			
			if($device == 'iphone4') {
				$page_plist = str_replace('{1080,1920}', '{640,960}', $page_plist);
			} elseif(strpos($device, 'ipad') > -1) {
				$page_plist = str_replace('{1080,1920}', '{1536,2048}', $page_plist);
			}
			file_put_contents($plist, $page_plist);
			
			// handle page backgrounds and thumbs
			$phantom_img = $page . '.png';
			$page_img = $page . '_portrait.jpg';
			$thumb_img = str_replace('_portrait', '_portrait_thumb', $page_img);
			
			// PhantomJS creation of page images, based on device
			$cmd = "phantomjs thirdparty/phantomjs/examples/rasterize.js $output $article_pages_dir$phantom_img " . $device_type['pixels'] . "px " . $device_type['zoom'];
			echo "\n\n$cmd\n\n";
			`$cmd`;
			
			$command = "convert \"$image\" -verbose -strip pnm:- | mozcjpeg -quality ". MOZCJPEG_QUALITY ." > \"$newImage\"";
			
			// ImageMagick: convert PNGs to JPEGs in Aesop assets
			$cmd = 'convert ' . $article_pages_dir . $phantom_img . ' -verbose -strip pnm:- | mozcjpeg -quality '. MOZCJPEG_QUALITY .' > ' . $article_pages_dir . $page_img;
			`$cmd`;
			
			// resize thumb during conversion
			$cmd = 'convert ' . $article_pages_dir . $phantom_img . ' -resize 270x -verbose -strip pnm:- | mozcjpeg -quality '. MOZCJPEG_QUALITY .' > ' . $article_pages_dir . $thumb_img;
			`$cmd`;
			
			// copy thumbnail to thumbs dir
			`cp $article_pages_dir$thumb_img $thumbs_dir/$thumb_img`;
			
			// drop the PhantomJS PNG
			`rm -rf $article_pages_dir$phantom_img`;
			
			// Replica.plist entry for pages
			$find2 = array(
						  'background-portrait-data',
						  'background-portrait-thumb-data',
						  'plist-data'
						  );
			$replace2 = array(
							 'pages/' . $page_img,
							 'pages/' . $thumb_img,
							 '/' . $page . '.plist'
							 );
			$tempfile2 = $page_entry_xml;
			$sub_pages_plist[] = str_replace( $find2, $replace2, $tempfile2 );
		}
		
		$subpages = implode("\n", $sub_pages_plist);
		
		// set up the Replica.plist article entry
		$find = array(
					  'section-data',
					  'title-data',
					  'story-zip-url-data',
					  '<pages></pages>'
					);
		$replace = array(
						 htmlspecialchars( $article['section'] ),
						 htmlspecialchars( $article['title'] ),
						 $label . '.zip',
						 $subpages
						);
		$tempfile = $plist_page_template;
		$pages_plist[] = str_replace( $find, $replace, $tempfile );
		
		// zip the directory and delete it
		chdir($article_dir); // so the file hierarchy is right
		$zipfile = $issue_dir . '/' . $label . '.zip';
		$input = `zip -rv "$zipfile" ./`;
		echo "\n\n************************** ZIPPING ARTICLE $label **************************\n\n" . $input . "\n";
		echo "\n\n************************** ARTICLE ZIP COMPLETE **************************\n\n";
		
		chdir($script_dir);
		$total_asset_size += dirSize($article_dir); // not pre-zipped size
		`rm -rf "$article_dir"`;
		
		$pagecount++;
	}
	
	print_r($pages_plist);
	
	// zip the thumbs directory and delete it
	chdir($issue_dir);
	$zipfile = $thumbs_dir . '.zip';
	$zipcmd = 'zip -rv "' . $zipfile. '" ' . $issue .'_thumbs';
	$input = `$zipcmd`;
	echo $input . "\n\n";
	
	chdir($script_dir);
	// not including the thumbs in the total asset size
	//$total_asset_size += dirSize($thumbs_dir);
	`rm -rf "$thumbs_dir"`;
	
	$replica_plist = file_get_contents('Replica_plist.xml');
	$replica_plist = str_replace('<array></array>', "<array>\n" . implode("\n", $pages_plist) . "\n</array>", $replica_plist);
	$replica_plist = str_replace('progressive-download-thumbs-data', $issue . '_thumbs.zip', $replica_plist);
	$replica_plist = str_replace('toc-page-number-data', $tocpage, $replica_plist);
	
	$total_asset_size += strlen($replica_plist);
	echo "\n\nTOTAL ASSET SIZE: $total_asset_size bytes \n\n";
	$replica_plist = str_replace('total-asset-size-data', $total_asset_size, $replica_plist);
	
	file_put_contents($issue_dir.'/Replica.plist', $replica_plist);
	unset($pages_plist);
}
// end devices loop

echo "############################# OUTPUT COMPLETE #############################\n\n";
echo "PATH TO CONVERTED ASSETS: $base_dir\n\n";

/** 
* Get the directory size 
* @param directory $directory 
* @return integer 
*/ 
function dirSize($directory) { 
    $size = 0; 
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file){ 
        $size+=$file->getSize(); 
    } 
    return $size; 
}
// parse zip summary to get total bytes zipped
function zippedBytes($input) {
	$pattern = '/total bytes=([0-9]*)/i';
	preg_match($pattern, $input, $matches);
	return $matches[1];
}
/* hack for removing -0 entries from articleIDs array
 * this is due to a bug in the aesop-nav:// protocol
 * Example:
    [2] => OPR040117TOC_lo
    [3] => OPR040117TOC_lo-0
    [4] => OPR040117TOC_lo-1
    [5] => OPR040117TOC_lo-2
   We want "OPR040117TOC_lo-0" removed
*/
function removeNavDupes($val) {
	return strpos($val, '-0')===false;
}
?>