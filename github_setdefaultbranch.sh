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

changeDefaultRepo() {
	i=$1
	gh repo edit --default-branch release/17.0 FreePBX/$i
}


for i in "${opensrcmodules[@]}"; do
		echo "$i repo "
		changeDefaultRepo $i
done
