#!/bin/bash

#
# Updating *everything* of a FreePBX dev system can take several steps to
# update all the modules properly. This script attempts to walk through those
# steps.
#
# It's not well tested, so you probably want to snapshot your VM before you run
# it. You've been warned.
#

set -ex

declare -a arr=(
	"superfecta" 
	"hotelwakeup" 
	"parking"
)


# first update framework, because it apparently doesn't update with everything
# else
cd /usr/src/freepbx/

for i in "${arr[@]}"; do
	cd /usr/src/freepbx/$i
	echo "pushing $i to Github"
	git fetch
	git pull
	git remote add github git@github.com:FreePBX/$i.git
	git remote -v
	git push --all github
	git push --tags github
	git remote remove github
done
