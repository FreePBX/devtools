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
	echo "Checking if module $1 has an i18n directory"
	# spit out the module.xml in a <modulename>.i18.php so that we can grab it with the find
	if [ -d $1/i18n ]; then
	echo "Found directory $1/i18n, creating temp file"
	# This is needed for localization to actually pickup the enclosed text strings
	# This could probably be done better, but I lack the time for doing that so here it is
	echo -e "<?php \nif (false) {" > $1/$1.i18n.php
	/var/lib/asterisk/bin/module_admin i18n $1 >> $1/$1.i18n.php
	# This is needed for localization to actually pickup the enclosed text strings
	# This could probably be done better, but I lack the time for doing that so here it is
	echo -e "}\n?>\n" >> $1/$1.i18n.php
	echo "Creating $1.pot file, extracting text strings"
	# Save the file as a temp file
	find $1/*.php | xargs xgettext --no-location -L PHP -o $1/i18n/$1.tmp --keyword=_ -
	# Now add the copyright and the license info to the.pot file
	# Again, could be done better, but I lack the time and really need this out now
	echo "# This file is part of FreePBX." > $1/i18n/$1.pot
	echo "#" >> $1/i18n/$1.pot
	echo "#    FreePBX is free software: you can redistribute it and/or modify" >> $1/i18n/$1.pot
	echo "#    it under the terms of the GNU General Public License as published by" >> $1/i18n/$1.pot
	echo "#    the Free Software Foundation, either version 2 of the License, or" >> $1/i18n/$1.pot
	echo "#    (at your option) any later version." >> $1/i18n/$1.pot
	echo "#" >> $1/i18n/$1.pot
	echo "#    FreePBX is distributed in the hope that it will be useful," >> $1/i18n/$1.pot
	echo "#    but WITHOUT ANY WARRANTY; without even the implied warranty of" >> $1/i18n/$1.pot
	echo "#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the" >> $1/i18n/$1.pot
	echo "#    GNU General Public License for more details." >> $1/i18n/$1.pot
	echo "#" >> $1/i18n/$1.pot
	echo "#    You should have received a copy of the GNU General Public License" >> $1/i18n/$1.pot
	echo "#    along with FreePBX.  If not, see <http://www.gnu.org/licenses/>." >> $1/i18n/$1.pot
	echo "#" >> $1/i18n/$1.pot
	echo "# FreePBX language template for $1" >> $1/i18n/$1.pot
	echo "# Copyright (C) 2008, 2009, 2010 Bandwith.com" >> $1/i18n/$1.pot
	echo "#" >> $1/i18n/$1.pot
	# Remove the first six lines of the .tmp file and put it in the -pot file
	/bin/sed '1,6d' $1/i18n/$1.tmp >> $1/i18n/$1.pot
	echo "Removing temp files"
	rm $1/$1.i18n.php
	rm $1/i18n/$1.tmp
	fi
echo "Done, now don't forget to commit your work!"