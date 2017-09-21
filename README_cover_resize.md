# Using the Cover Resize script
The cover resize script is used to resize and optimize cover images for upload to MAZ. The input file must meet the following requirements:

* At least 1536x2048 pixels in dimensions
* PNG file format
* No spaces in the filename

## How to use the script
This is a PHP script which is run from the command line, with the following binary dependencies:

* imagemagick
* mozcjpeg

(These scripts are also required for the HPUB optimization script, so they should already be installed.)

The script takes three arguments, formatted as described:

1. The three-letter brand code (e.g. COS for Cosmopolitan). This is not case-sensitive, as the script will convert it to uppercase.
2. The four-number issue date, in the format YYMM (e.g. 1710 for October 2017).
3. The path to the PNG file to be converted. If there are any spaces in the path, it should be entered in quotes.

Here is a sample command:  
`php cover_resize.php COS 1710 ~/Covers/October2017/COS100117Cover_USNEWS.png`

The resulting JPEG file will be saved to the same directory as the original PNG file, using the naming convention `<brand code>_<issue date>@2x.jpg`. So following the Cosmopolitan example above, the resulting image would be named **COS_1710@2x.jpg**.

The resulting JPEG will be significantly reduced in size and ready for upload via the MAZ dashboard.

_Last update: 9/21/17_