#! /bin/bash -f

#
#  Remove inline fonts.
#
find . -name design.css -exec sed -i -e '/#iefix/d' {} \;
find . -name fonts.css -exec sed -i -e '/#iefix/d' {} \;

#
#  Fix font file paths.
#
find . -name design.css -exec sed -i -e 's:src/assets/::g' {} \;
find . -name fonts.css -exec sed -i -e 's:src/assets/fonts/::g' {} \;

#
#  Remove sed backups (BSD/Mac sed creates these by default).
#
find . -name '*-e' -delete