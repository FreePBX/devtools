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
 	git clone git@github.com:FreePBX/$i.git /usr/src/freepbx/$i	
}
changeRemoteToGithub() {
	i=$1
	cd /usr/src/freepbx/$i
	git remote set-url origin git@github.com:FreePBX/$i.git
	git remote -v
	git fetch
}

# first update framework, because it apparently doesn't update with everything
# else
cd /usr/src/freepbx/

for i in "${opensrcmodules[@]}"; do
	dir=/usr/src/freepbx/$i

	if [ -d $dir ]; then
		changeRemoteToGithub $i 
	else
		echo "$i directory does not exists"
		downloadCode $i 
	fi
done



