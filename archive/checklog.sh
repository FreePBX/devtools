#!/bin/sh
#
# Do an svn log in each directory for better visibility since last update
#
for NAME in `ls`
do
	if [ -d $NAME ]
	then
		lastpublish=`svn propget lastpublish $NAME`
		let lastpublish=$lastpublish+2
		echo
		echo ================================== $NAME START =========================================
		echo SVN LOG: $NAME
		#svn log --stop-on-copy $NAME
		echo ----------FILE MODS-----------
		svn log -v -r $lastpublish:HEAD $NAME | grep -v 'i18n' | grep $NAME
		echo ----------LOG ENTRIES---------
		svn log -r $lastpublish:HEAD $NAME 
		echo
		echo ================================== $NAME STOP ==========================================
		echo
	fi
done
