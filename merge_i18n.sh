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
# The purpose of this script is to merge all language .po from .pot file
# For this script to work you need to so svn co for for branch and for modules and
# install this in the same tree so that the script can do all extraction at once.
#

    echo "Module $1"
        for j in $1/i18n/*; do
            echo "Found language $j"
            if [ -d ${j}/LC_MESSAGES ]; then
                echo "Language $j"
                if [ -f ${j}/LC_MESSAGES/$1.po ]; then
                    echo "Found $1.po for language $j"
                    # Merge the .po file from the .pot file
                    msgmerge -N -U ${j}/LC_MESSAGES/$1.po $1/i18n/$1.pot
                    # Remove the .po~ file
                    rm ${j}/LC_MESSAGES/$1.po~
                    # And compile the .po to .mo
                    msgfmt -v ${j}/LC_MESSAGES/$1.po -o ${j}/LC_MESSAGES/$1.mo
                fi
            fi
        done

