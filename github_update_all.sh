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

declare -a opensrcmodules=(
	"superfecta" 
	"hotelwakeup" 
	"parking"
	"dahdiconfig"
	"announcement"
	"asterisk-cli"
	"asteriskinfo"
	"backup"
	"blacklist"
	"callback"
	"callforward"
	"callrecording"
	"callwaiting"
	"cdr"
	"cidlookup"
	"conferences"
	"core"
	"customappsreg"
	"dashboard"
	"daynight"
	"dictate"
	"digiumaddoninstaller"
	"digium_phones"
	"directory"
	"disa"
	"donotdisturb"
	"dundicheck"
	"extensionsettings"
	"fax"
	"featurecodeadmin"
	"findmefollow"
	"iaxsettings"
	"infoservices"
	"ivr"
	"languages"
	"logfiles"
	"manager"
	"miscapps"
	"miscdests"
	"music"
	"outroutemsg"
	"paging"
	"pbdirectory"
	"phonebook"
	"pinsets"
	"printextensions"
	"queueprio"
	"queues"
	"recordings"
	"restart"
	"ringgroups"
	"setcid"
	"sipsettings"
	"speeddial"
	"timeconditions"
	"tts"
	"vmblast"
	"voicemail"
	"weakpasswords"
	"framework"
	"webrtc"
	"userman"
	"arimanager"
	"presencestate"
	"ucp"
	"certman"
	"cxpanel"
	"contactmanager"
	"cel"
	"bulkhandler"
	"soundlang"
	"firewall"
	"configedit"
	"xmpp"
	"ttsengines"
	"calendar"
	"pm2"
	"api"
	"filestore"
	"amd"
)

downloadCode() {
	i=$1
	echo "$i downloading from git.freepbx.org "
	./freepbx_git.php -m "$MODULE" -y
}
pushToGit() {
	i=$1
	cd /usr/src/freepbx/$i
	echo "pushing $i to Github"
	git fetch
	git checkout release/16.0 || true
	git pull origin release/16.0 || true
	git checkout release/17.0 || true
	git pull origin release/17.0 || true
	git remote add github git@github.com:FreePBX/$i.git
	git remote -v
	git push --all github
	git push --tags github
	git remote remove github
}


# first update framework, because it apparently doesn't update with everything
# else
cd /usr/src/freepbx/

for i in "${opensrcmodules[@]}"; do
	dir=/usr/src/freepbx/$i

	if [ -d $dir ]; then
		pushToGit $i 
	else
		echo "$i directory does not exists"
		downloadCode $i 
		pushToGit $i 
	fi
done



