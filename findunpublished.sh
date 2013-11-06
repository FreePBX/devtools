#!/bin/bash

# Warning: this script is quite slow, as it does SVN log
#          on each directory one at a time!

for i in *; do
	if [ -d $i ]; then
		echo $i
		svn log -r PREV  $i 
	fi
done
