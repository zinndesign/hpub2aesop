# Using the HPUB Link Validator
The HPUB link validation script is used to verify that all links contained in the HTML content of an HPUB file are valid. These are the criteria which are used to determine validity:

* A link must have a valid protocol matching one of the following:
	* http:// or https://
	* articleref://
	* mailto:
	* #
* A link must not contain any spaces, either between characters or between the link and the opening and closing attribute quotes (e.g. " http://google.com ")

The script also checks the existence of web links and article links as follows:

* For a web link, the script loads the URL via cURL to ensure that a bad HTTP status code is not returned (e.g. 404, 500, etc.)
* For an article link, the script checks that the article identifier portion of the link matches an existing article identifier in the `book.json` file from the HPUB.

## How to use the script
The HPUB Link Validator is a PHP script that is run from the command line on any Mac or *nix machine. The only dependencies are PHP and cURL, both of which are part of the standard install of Mac OS X.

To use the script, first copy it to your computer, making a note of the location - for example, `/Users/jzinn/Desktop/`. Then, open the Mac Terminal program and follow the steps below.

1. Navigate to the directory where you saved the script. You can do this by typing `cd` followed by a space, followed by the path to the directory. Alternatively, you can type `cd`, followed by a space, then select the folder in Finder and drag it to the terminal window. This will copy over the path to the directory. Then hit **enter** (return) to complete the command.
2. To run the script, type `php hpub_link_validator.php`, followed by a space, followed by the path to the zipped HPUB you want to check. You can use the same method specified above (dragging from Finder) to fill in the path to the HPUB. Then hit **enter** to start the script.

The script will take a few minutes to run, depending on the size of the HPUB you're checking. When all links have been checked, the script will output a list of any bad links that have been encountered, as well as the path to the location of the **output.html** file for each link. This path can be used to preview the output.html file (select the file and press the space bar) to determine the article(s) that need to be updated.

_Last update: 8/29/17_