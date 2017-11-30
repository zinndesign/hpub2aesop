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
The HPUB Link Validator is a PHP script that is run from the command line on any Mac or *nix machine. The only dependencies are **PHP** and **cURL**, both of which are part of the standard install of Mac OS X.

To use the script, first copy it to your computer, making a note of the location - for example, `/Users/jzinn/Desktop/`. Then, open the Mac Terminal program and follow the steps below.

1. Navigate to the directory where you saved the script. You can do this by typing `cd` followed by a space, followed by the path to the directory. Alternatively, you can type `cd`, followed by a space, then select the folder in Finder and drag it to the terminal window. This will copy over the path to the directory. Then hit **enter** (return) to complete the command.
2. To run the script, type `php hpub_link_validator.php`, followed by a space, followed by the path to the zipped HPUB you want to check. You can use the same method specified above (dragging from Finder) to fill in the path to the HPUB. Then hit **enter** to start the script.
3. Optionally, you can type the word `skip` following the path to the HPUB. This will skip the action of loading each web link via cURL, and reduce the run-time of the script to a few seconds. Running the script without this option will normally take from 5 to 10 minutes, depending on the number of articles in the issue, and the number of links in each article.

When all links have been checked, the script will output a list of any bad links that have been encountered, as well as the **article ID** to indicate where the link appears. A numeric value will be appended to each article ID to identify sub-articles (the top-level article will be followed by a zero - e.g. `COS110117FOBEdletter_LO-0`).

_Last update: 11/30/17_