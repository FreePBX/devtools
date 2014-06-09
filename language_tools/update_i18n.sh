#! /bin/sh
# Copyright (c) 2008, 2010 Mikael Carlsson
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# The purpose of this script is to extract all text strings from all FreePBX code that can
# be translated and create template files under each modules/<module>/i18n directory.
# This script is intended to run in the checked out svn modules directory.

# However, it's not enough to just have the modules' code checked out from SVN. In order 
# 	to detect all translatable strings, we are required to run the module_admin command,
# 	which can only provide information for modules that are *actually* installed on the
# 	system. Therefore, you will also have to install all of the available modules that also
# 	appear in svn before running this script. If you don't, the script will finish without 
# 	complaint, but you won't have obtained complete results.


# Usage: update_i18n.sh [module name] [directory]

# Always work out of the directory that this script is running in, as framework is not a real module, so we need to compensate for that.
if [ "$2" == "" ]; then
	pwd=`dirname $(readlink -f $0)`
else 
	pwd=$2
fi
cd $pwd

if [ -d $pwd/$1 ] && [ "$1" != "" ]; then
	mod=$1/
else
	mod=$(ls -d */)
fi

echo "Creating new POT template files for modules"

for modules in $mod
do
	# Special Case Framework
	# Added the following section for generating amp.pot, which uses a completely separate 
	# 	format for the find command. This actually talks about core, but we actually handle
	# 	the pseudo-modules "core" and "framework" at the same time as seen on lines 76-79.
	# 	That's why later on we have to put in code to skip framework, since it gets taken
	# 	care of already here with core.
	if [ "${modules%%/}" = "core" ]; then
		# 
		modules='../'
		rawname='amp'
		module_admin='core'
	        echo "Checking if module ${modules%%/} has an i18n directory"
        	# Using module_admin, spit out the module.xml for core and framework into core.i18.php so that it gets included in find results below
		echo -e "<?php \nif (false) {" > ./core/core.i18n.php
		/var/lib/asterisk/bin/module_admin i18n core >> ./core/core.i18n.php
		/var/lib/asterisk/bin/module_admin i18n framework >> ./core/core.i18n.php
		echo -e "}\n?>\n" >> ./core/core.i18n.php

        	if [ -d ${modules}i18n ]; then
        	echo "Found directory ${modules}i18n, creating temp file for core/framework"
        	# This is needed for localization to actually pickup the enclosed text strings
        	# This could probably be done better, but I lack the time for doing that so here it is
        	echo -e "}\n?>\n" >> $modules${rawname%%/}.i18n.php
        	echo "Creating ${rawname%%/}.pot file, extracting text strings"
		# >>> Change directory to admin where the PHP files for amp.pot can be found
		pushd $modules
        	# Save the file as a temp file
		# Use find and grep to get a list of all PHP files not in the modules directory, except for modules/core which we need
		# This is an ugly way to do this, but I couldn't figure out a single command that would give me the output I wanted
		# Maybe someone good at sed could combine these next two lines so we don't need to make a .tmp file
        	find . -name "*.php" | grep --invert-match "./modules/" > ampfiles.tmp
		find ./modules/core -name "*.php" >> ampfiles.tmp  
		find ./modules/framework -name "*.php" >> ampfiles.tmp
		cat ampfiles.tmp | xargs xgettext --no-location --from-code=UTF-8 -L PHP -o ./i18n/${rawname%%/}.tmp --keyword=_ -
        	sed --in-place ./i18n/${rawname%%/}.tmp  --expression='s/CHARSET/utf-8/'
        	# Now add the copyright and the license info to the.pot file
        	# Again, could be done better, but I lack the time and really need this out now
        	echo "# This file is part of FreePBX." > ./i18n/${rawname%%/}.pot
        	echo "#" >> ./i18n/${rawname%%/}.pot
        	echo "#    FreePBX is free software: you can redistribute it and/or modify" >> ./i18n/${rawname%%/}.pot
        	echo "#    it under the terms of the GNU General Public License as published by" >> ./i18n/${rawname%%/}.pot
        	echo "#    the Free Software Foundation, either version 2 of the License, or" >> ./i18n/${rawname%%/}.pot
        	echo "#    (at your option) any later version." >> ./i18n/${rawname%%/}.pot
        	echo "#" >> ./i18n/${rawname%%/}.pot
        	echo "#    FreePBX is distributed in the hope that it will be useful," >> ./i18n/${rawname%%/}.pot
        	echo "#    but WITHOUT ANY WARRANTY; without even the implied warranty of" >> ./i18n/${rawname%%/}.pot
        	echo "#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the" >> ./i18n/${rawname%%/}.pot
        	echo "#    GNU General Public License for more details." >> ./i18n/${rawname%%/}.pot
        	echo "#" >> ./i18n/${rawname%%/}.pot
        	echo "#    You should have received a copy of the GNU General Public License" >> ./i18n/${rawname%%/}.pot
        	echo "#    along with FreePBX.  If not, see <http://www.gnu.org/licenses/>." >> ./i18n/${rawname%%/}.pot
        	echo "#" >> ./i18n/${rawname%%/}.pot
        	echo "# FreePBX language template for core and framework" >> ./i18n/${rawname%%/}.pot
        	echo "# Copyright (C) 2008, 2009, 2010 Bandwith.com" >> ./i18n/${rawname%%/}.pot
        	echo "#" >> ./i18n/${rawname%%/}.pot
        	# Remove the first six lines of the .tmp file and tack it onto the .pot file
        	/bin/sed '1,6d' ./i18n/${rawname%%/}.tmp >> ./i18n/${rawname%%/}.pot
		# Go back to script directory which should be ./modules/
		popd
		fi
	else
		# Now that we're done making amp.pot from core/framework, we can move on to the other modules which are handled normally.

		rawname=$modules
		module_admin=$modules
	
		echo "Checking if module ${modules%%/} has an i18n directory"
		# spit out the module.xml in a <modulename>.i18.php so that we can grab it with the find
		if [ ! -d ${modules}i18n ]; then
			echo "Creating i18n directory"
			mkdir ${modules}i18n
		fi

		if [ -d ${modules}i18n ]; then
			echo "Found directory ${modules}i18n, creating temp file"
			# This is needed for localization to actually pickup the enclosed text strings
			# This could probably be done better, but I lack the time for doing that so here it is
			echo -e "<?php \nif (false) {" > $modules${rawname%%/}.i18n.php
			/var/lib/asterisk/bin/module_admin i18n ${modules%%/} >> $modules${rawname%%/}.i18n.php
			# This is needed for localization to actually pickup the enclosed text strings
			# This could probably be done better, but I lack the time for doing that so here it is
			echo -e "}\n?>\n" >> $modules${rawname%%/}.i18n.php
			echo "Creating ${rawname%%/}.pot file, extracting text strings"
			# Save the file as a temp file
			# >>> I changed the find command here to use -wholename so we get recursive results for modules with nested php code
			find . -wholename "./${modules%%/}/*.php" | xargs xgettext --no-location --from-code=UTF-8 -L PHP -o ${modules%%/}/i18n/${rawname%%/}.tmp --keyword=_ -
			sed --in-place ${modules%%/}/i18n/${rawname%%/}.tmp  --expression='s/CHARSET/utf-8/'
		
			
			# Now add the copyright and the license info to the.pot file
			# Again, could be done better, but I lack the time and really need this out now
			echo "# This file is part of FreePBX." > ${modules%%/}/i18n/${rawname%%/}.pot
			echo "#" >> ${modules%%/}/i18n/${rawname%%/}.pot
			echo "#    FreePBX is free software: you can redistribute it and/or modify" >> ${modules%%/}/i18n/${rawname%%/}.pot
			echo "#    it under the terms of the GNU General Public License as published by" >> ${modules%%/}/i18n/${rawname%%/}.pot
			echo "#    the Free Software Foundation, either version 2 of the License, or" >> ${modules%%/}/i18n/${rawname%%/}.pot
			echo "#    (at your option) any later version." >> ${modules%%/}/i18n/${rawname%%/}.pot
			echo "#" >> ${modules%%/}/i18n/${rawname%%/}.pot
			echo "#    FreePBX is distributed in the hope that it will be useful," >> ${modules%%/}/i18n/${rawname%%/}.pot
			echo "#    but WITHOUT ANY WARRANTY; without even the implied warranty of" >> ${modules%%/}/i18n/${rawname%%/}.pot
			echo "#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the" >> ${modules%%/}/i18n/${rawname%%/}.pot
			echo "#    GNU General Public License for more details." >> ${modules%%/}/i18n/${rawname%%/}.pot
			echo "#" >> ${modules%%/}/i18n/${rawname%%/}.pot
			echo "#    You should have received a copy of the GNU General Public License" >> ${modules%%/}/i18n/${rawname%%/}.pot
			echo "#    along with FreePBX.  If not, see <http://www.gnu.org/licenses/>." >> ${modules%%/}/i18n/${rawname%%/}.pot
			echo "#" >> ${modules%%/}/i18n/${rawname%%/}.pot
			echo "# FreePBX language template for ${modules%%/}" >> ${modules%%/}/i18n/${rawname%%/}.pot
			thisyear=`date +"%Y"`
			echo "# Copyright (C) 2008-${thisyear} Schmoozecom, INC" >> ${modules%%/}/i18n/${rawname%%/}.pot
			echo "#" >> ${modules%%/}/i18n/${rawname%%/}.pot
			# Remove the first six lines of the .tmp file and put it in the -pot file
			/bin/sed '1,6d' ${modules%%/}/i18n/${rawname%%/}.tmp >> ${modules%%/}/i18n/${rawname%%/}.pot

			echo "Removing temp files"
			rm ${modules%%/}/i18n/${rawname%%/}.tmp
		fi
	fi 
	# Remove the .tmp file created above for amp.pot generation
	if [ -f ../ampfiles.tmp ]; then
		rm ../ampfiles.tmp
	fi
done
echo "Done, now don't forget to commit your work!"
