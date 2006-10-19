#!/bin/bash

# Warning: this script is quite slow, as it does SVN log
#          on each directory one at a time!

for i in *; do
	if [ -d $i ]; then
		svn log --limit 1 $i | grep -q "Module Publish Script"
		if [ $? -ne 0 ]; then
			echo $i
		fi
	fi
done
