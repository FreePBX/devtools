#!/bin/bash

#
# This script attempts to fix broken modules on a FreePBX dev system.
#
# Note: Before running this script, make sure to define your freepbx username
# and password in ~/.freepbxconfig
#

set -e

MODULES="$(fwconsole ma list | grep Broken)"
echo "$MODULES" | while read line; do
	MODULE="$(echo $line | cut -d '|' -f 2 -s | tr -d '[:space:]')"
	STATUS="$(echo $line | cut -d '|' -f 4 -s | tr -d '[:space:]')"
	if [ "$STATUS" == "Broken" ]; then
		echo ""
		echo ""
		echo "Installing Broken Module: $MODULE"
		./freepbx_git.php -m "$MODULE" -y
		fwconsole ma install "$MODULE"
	fi
done

fwconsole reload