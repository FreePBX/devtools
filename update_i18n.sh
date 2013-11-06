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
#

echo "Creating new POT template files for modules"
for modules in $(ls -d */); 
do
	echo "Checking if module ${modules%%/} has an i18n directory"
	# spit out the module.xml in a <modulename>.i18.php so that we can grab it with the find
	if [ -d ${modules}i18n ]; then
	echo "Found directory ${modules}i18n, creating temp file"
	# This is needed for localization to actually pickup the enclosed text strings
	# This could probably be done better, but I lack the time for doing that so here it is
	echo -e "<?php \nif (false) {" > $modules${modules%%/}.i18n.php
	/var/lib/asterisk/bin/module_admin i18n ${modules%%/} >> $modules${modules%%/}.i18n.php
	# This is needed for localization to actually pickup the enclosed text strings
	# This could probably be done better, but I lack the time for doing that so here it is
	echo -e "}\n?>\n" >> $modules${modules%%/}.i18n.php
	echo "Creating ${modules%%/}.pot file, extracting text strings"
	# Save the file as a temp file
	find ${modules%%/}/*.php | xargs xgettext --no-location -L PHP -o ${modules%%/}/i18n/${modules%%/}.tmp --keyword=_ -
	# Now add the copyright and the license info to the.pot file
	# Again, could be done better, but I lack the time and really need this out now
	echo "# This file is part of FreePBX." > ${modules%%/}/i18n/${modules%%/}.pot
	echo "#" >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "#    FreePBX is free software: you can redistribute it and/or modify" >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "#    it under the terms of the GNU General Public License as published by" >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "#    the Free Software Foundation, either version 2 of the License, or" >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "#    (at your option) any later version." >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "#" >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "#    FreePBX is distributed in the hope that it will be useful," >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "#    but WITHOUT ANY WARRANTY; without even the implied warranty of" >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the" >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "#    GNU General Public License for more details." >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "#" >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "#    You should have received a copy of the GNU General Public License" >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "#    along with FreePBX.  If not, see <http://www.gnu.org/licenses/>." >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "#" >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "# FreePBX language template for ${modules%%/}" >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "# Copyright (C) 2008, 2009, 2010 Bandwith.com" >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "#" >> ${modules%%/}/i18n/${modules%%/}.pot
	# Remove the first six lines of the .tmp file and put it in the -pot file
	/bin/sed '1,6d' ${modules%%/}/i18n/${modules%%/}.tmp >> ${modules%%/}/i18n/${modules%%/}.pot
	echo "Removing temp files"
	rm $modules${modules%%/}.i18n.php
	rm ${modules%%/}/i18n/${modules%%/}.tmp
	fi
done
echo "Done, now don't forget to commit your work!"
