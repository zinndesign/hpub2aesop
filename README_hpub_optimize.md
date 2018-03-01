# Using the HPUB Optimizer
The HPUB optimizer script does two primary things, both intended to reduce the size of the final, optimized HPUB:

1. Removes all unused PNG thumbnail images
2. Applies compression utilities to image files, depending on type:
	* PNG files = pngquant followed by optipng
	* JPEG files = mozcjpeg

Additionally, the script checks for transparency in PNG files. If there is none present, the file is converted to a JPEG, and all references to it in the HTML and PSV files are updated. (This logic is now integrated into the XIN script, but is still here as a failover.)

Lastly, the script runs the same link validation processes as the separate link validation script (minus the cURL calls to load URLs). This is also a failover for the standalone script.

## How to use the script
The HPUB Optimizer is a PHP script that is run from the command line on any Mac or *nix machine. The dependencies are as follows:

* **PHP** (part of the standard install of Mac OS X)
* **ImageMagick** (requires installation)
* **mozcjpeg** (requires installation)
* **pngquant** (requires installation)
* **optipng** (requires installation)

To use the script, first copy it to your computer, making a note of the location - for example, `/Users/jzinn/Desktop/`. Then, open the Mac Terminal program and follow the steps below.

1. Navigate to the directory where you saved the script. You can do this by typing `cd` followed by a space, followed by the path to the directory. Alternatively, you can type `cd`, followed by a space, then select the folder in Finder and drag it to the terminal window. This will copy over the path to the directory. Then hit **enter** (return) to complete the command.
2. To run the script, type `php hpub_optimize.php`, followed by a space, followed by the path to the zipped HPUB you want to check. You can use the same method specified above (dragging from Finder) to fill in the path to the HPUB. Then hit **enter** to start the script.

The script creates a copy of the original, un-optimized HPUB zip file, runs through its processes, then saves them to a new zip file (the original final name with **_OPTIMIZED** appended). The original file is kept for size comparison or to re-run the process if necessary.

(Note that the HPUB optimize script should **NOT** be run on an HPUB zip that has already been optimized, as this would result in an unacceptable degree of image quality loss.)

## File Uploads and Additional Notes
At the time of this writing, the optimized HPUB zip is used for two purposes:

* Uploading to MAZ dashboard for digital editions
* Uploading to Dropbox for Texture

Ideally, two changes will happen that will make this script obsolete:

* Texture will update the Inception-to-HPUB conversion process to skip adding thumbnail PNGs to the package.
* WoodWing Digital Services will do some testing and analysis to determine why images aren't being adequately compressed during the XIN import process.

Regarding the latter item, side-by-side comparisons show differences of as much as 5mb between the images optimized by XIN versus the same images after post-processing by this script. If the discrepancy is due to double optimization - in other words, the optimizer script is simply compressing the images a second time - then it's likely that the image compression settings used by the XIN script are not aggressive enough to achieve an optimal balance of quality vs. file size. Further testing is warranted either way.

For an example of the impact, see the April 2018 issue of Cosmopolitan. The HPUB file size for this issue dropped from 265mb before optimization to 168mb after. The smaller payload size results in a better customer experience (faster loading and reduced download time).

_Last update: 03/01/18_