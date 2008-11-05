#!/bin/sh
#
# This script is intended to be run after an svnmerge.py branch merge
# in the modules directory. It resets the lastpublish property from any
# changes that may have been imposed from a merge since this property
# should reflect the last revision number of the current branch that was
# published.
#

for MODULE in `ls`
do
	if [ -d $MODULE ]
	then
		URL=`svn info $MODULE | grep URL: | cut -f 2 -d ' '`
		PROP=`svn propget lastpublish $URL`
		PROPNOW=`svn propget lastpublish $MODULE`

		echo checking $MODULE current $PROPNOW previous $PROP
		if [ -n $PROP ] && [ $PROP -ne $PROPNOW ]
		then
			echo setting lastpublish on $MODULE from $PROPNOW to $PROP
			svn ps lastpublish $PROP $MODULE
		fi
	fi
done

