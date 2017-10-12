# Using the PSD Optimize script
The PSD optimize script is used to convert a layered Photoshop file into a flattened PNG or JPEG, optimized with a variety of command-line utilities to achieve the smallest possible filesize while maintaining image quality. This makes the resulting images ideal for web use, email attachments, or uploading to Inception.

The PSD optimize script is intended to work with PSD files, but will also accept PNG files. When the script is passed an image file, the following initial checks are run:

* Confirm that the file has a `.psd` or `.png` extension.
* Determine whether the file is fully opaque (no transparent or semi-transparent pixels present).
* Calculate the number of colors in the image.
* Calculate the size on disk of the image file.

The info above is used to determine whether the image can be saved as a JPEG - with the highest reduction in filesize - or if it should be saved as a PNG instead.

## How to use the script
This is a PHP script which is run from the command line, with the following binary dependencies:

* imagemagick
* mozcjpeg
* optiPNG
* pngquant

(These scripts are also required for the HPUB optimization script, so they should already be installed.)

The script takes only one argument, the path to the PSD or PNG file to be optimized. Here is a sample command:  
`php psd_optimize.php COS 1710 ~/Documents/PSD/Sept2017/FeatureBgrd.psd`

The resulting PNG or JPEG file will be saved to the same directory as the original file, using the original filename with "OPTIMIZED" inserted at the end. So following the example above, the resulting image would be named **FeatureBgrd_OPTIMIZED.png** or **FeatureBgrd_OPTIMIZED.jpg**.

(The original file is not modified or removed.)

One important note: when converting a PSD file, only the **visible** layers in the saved file are rendered in the output. Any hidden layers in the file are exlcuded when the layers are flattened. However, any transparency in the composite image will be maintained when the layers are merged.

_Last update: 10/6/17_