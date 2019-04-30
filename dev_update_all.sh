#!/bin/bash

#
# Updating *everything* of a FreePBX dev system can take several steps to
# update all the modules properly. This script attempts to walk through those
# steps.
#
# It's not well tested, so you probably want to snapshot your VM before you run
# it. You've been warned.
#

set -ex

# first update framework, because it apparently doesn't update with everything
# else
cd /usr/src/freepbx/framework
git pull
sudo ./install --dev-links -n

# prune extra branches, because updating breaks if they've been rebased
for i in /usr/src/freepbx/*; do
    if test -d $i/.git; then
        cd $i
        git branch | \
            grep -v '^\*' | \
            xargs --no-run-if-empty --max-args 1 git branch -d
    fi
done

# update
cd /usr/src/devtools
git pull
sudo SSH_AUTH_SOCK=${SSH_AUTH_SOCK} ./freepbx_git.php --refresh
sudo ./freepbx_git.php -s
sudo chown -R asterisk:asterisk /usr/src/freepbx
fwconsole ma installlocal
fwconsole reload
