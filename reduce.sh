#! /bin/bash -f

#
#  Remove inline fonts.
#
find . -name design.css -exec sed -i -e '/#iefix/d' {} \;

if [ "$1" == "delete-fonts-css" ] ; then
    #
    #  Remove the fonts.css files entirely.
    #  You should manually remove fonts.css entries from your book.json if you use this option.
    #
    find . -name output.html -exec sed -i -e "s/\<link rel='stylesheet' href='template\/fonts\/fonts.css'\/\>//" {} \;
    find . -name fonts.css -delete
else
    #
    #  Truncate fonts.css files to zero length.
    #
    find . -name fonts.css -exec dd if=/dev/null of={} \;
fi

#
#  Get rid of sed backups, if we ran on a Mac.
#
find . -name '*-e' -delete
