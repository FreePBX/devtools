#! /bin/sh
# Copyright (c) 2008, 20i0 Mikael Carlsson
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
# The purpose of this script is to merge all language .po from .pot file
# For this script to work you need to so svn co for branch and for modules and
# install this in the same tree so that the script can do all extraction at once.
#
for i in *; do
	#dont do anything for these modules
	[ $i = 'fw_ari' ] || [ $i = 'core' ] || [ $i = 'fw_fop' ] && continue
	echo "Module $i"
        for j in $i/i18n/*; do
            echo "Found language $j"
            if [ -d ${j}/LC_MESSAGES ]; then
                echo "Language $j"
                if [ -f ${j}/LC_MESSAGES/$i.po ]; then
                    echo "Found $i.po for language $j"
                    # Merge the .po file from the .pot file
                    msgmerge -N -U ${j}/LC_MESSAGES/$i.po $i/i18n/$i.pot
                    # Remove the .po~ file
                    rm ${j}/LC_MESSAGES/$i.po~
                    # And compile the .po to .mo
                    msgfmt -v ${j}/LC_MESSAGES/$i.po -o ${j}/LC_MESSAGES/$i.mo
                fi
            fi
        done
done

