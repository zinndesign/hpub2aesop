# Using the hpub2aesop script
Quick and dirty instructions for using this script on ITP01 (will edit later)

1. Download the HPUB that will be converted. The URL comes in an email from __NIM Export__ with an AWS download link. You can copy and paste this into Chrome or Safari to start the download.
2. For better organization, save the zip file to the `/hpubs` folder, in the sub-directory that corresponds to the brand (e.g. `/cos`). So a download for a Cosmopolitan issue would go into `/hpubs/cos/`.
3. When the download completes, unzip the file into the same directory. Keep the directory open in Finder, as you'll be dragging from it to get the path into a terminal window.
4. Open a terminal window and `cd` into the directory `~/Desktop/SCRIPTS/hpub2aesop/`

## Running the script
Here are the usage guidelines that are displayed if the script is run without all required paramaters:
```Usage: hpub2aesop.php <issuename> <target_device> <"path to hpub directory"> <"path to output directory (optional)">```
```Target device options: iphone4, iphone5, iphone6, iphone6+, ipad_retina, ipad_nonretina, all```

Here is an example for the April issue of Cosmo:
```php hpub2aesop.php COS040117 all /Users/jzinn/Git/hpub2aesop/hpubs/cos_April2017_hearst_6595571674275307946-hpub```

Breakdown as follows:

* The call to run the PHP script: `php hpub2aesop.php` (this will always be the same)
* Issuename: this can really be anything, but for consistency, we usually do the three-letter brand code, followed by the issue date - e.g. `COS040117` (date format mmddyy).
* Target device will almost always be `all` - this will output four packages for iPhone, and two for iPad. Should there be a need to replace only one package, the options are:
	* iphone4
	* iphone5
	* iphone6
	* iphone6+
	* ipad_retina
	* ipad_nonretina
* The path to the HPUB directory would be an enormous pain to type - fortunately on the Mac OS, you can drag the folder to the terminal window instead. Just enter all of the other parts of the script call first (e.g. `php hpub2aesop.php COS040117 all `). Then drag the unzipped HPUB folder from the Finder to the terminal window, and it will fill in the path for you.
* The path to the output directory for the converted files is optional. If it is omitted, the default is the base directory of the script itself (such as `~/Desktop/SCRIPTS/hpub2aesop/`)

## FTP to Hearstmags
On ITP01, the FTP program Filezilla has been set up with an entry for the Hearst Magazines asset server at hearstmags.upload.akamai.com (it is labeled **Hearstmags** in the site manager). By default, when connecting to the server, the program will open the **hpub2aesop** directory from the local machine, and the **stage** directory on the remote machine. Unless otherwise specified, the standards are to use the following directories for issues, depending on the build:

* **pre-build** - `[brand]/issues/issue.1002`
* **build 1 or later** - `[brand]/issues/issue.yymm` (e.g. **issue.1704** for April 2017). You will need to create this directory within **issues** if it doesn't already exist.

Before uploading, delete any existing sub-directories (with the exception of **replica** if it's present). For Aesop, the following asset packages will always be required:

* **retina** (for all retina iPads)
* **nonretina** (for nonretina iPads)
* **iphone1080** (for iPhone 6+/7+)
* **iphone6** (for iPhone 6/7)
* **iphone169** (for iPhone 5)
* **iphone** (for iPhone 4 and below)

All of these packages are output by the conversion script and named appropriately for easy uploading. A future update to the script might include the option to automatically FTP to the appropriate remote directory, but for the time being, the upload will be done manually as described above.