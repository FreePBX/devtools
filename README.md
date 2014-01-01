FreePBX Development Tools
=========

FreePBX is an opensource GUI (graphical user interface) that controls and manages Asterisk (PBX). FreePBX is licensed under the GPL v2, GPL v3, and AGPL v3.
FreePBX is a Registered Trademark of Schmooze Com Inc.

These tools provide functionality for developers of FreePBX and its modules

#Tools

##package.php
This is used to package FreePBX modules for server side publishing.

###Help Options
```
  --bump              Bump a modules version. You can specify the "octet" by
                      adding a position I.e. --bump=2 will turn 3.4.5.6 in to
                      3.5.5.6. Leaving the position blank will bump the last
                      "octet"
  --debug=false       Debug only - just run through the command but don't
                      make any changes
  -c                  Prompt for FreePBX.org Credentials
  --help              Show this menu and exit
  --log               Update module.xml's changelog. [Done by default if
                      bumping]
  --module            Module to be packaged. You can use one module per
                      --module argument (for multiples)
  --directory         Directory Location of modules root, always assumed to
                      be ../freepbx from this location
  --msg               Optional commit message.
  --re                A ticket number to be referenced in all checkins (i.e.
                      "re #627...")
  --verbose           Run with extra verbosity and print each command before
                      it's executed
```
###Usage

```sh
./package.php -m=announcement
```

##freepbx_git.php
Check FreePBX out from git and enables you to run ./install_amp with --dev-links

###Help Options
```
  --setup             Setup new freepbx dev tools environment (use --force to
                      re-setup environment)
  --refresh           Updates all local modules with their remote changes
  --switch=<branch>   Switch all local modules to branch
  --directory         The directory location of the modules, will default to:
                      /usr/src/freepbx
```
###Usage

```sh
./freepbx_git.php --setup
```

##checklog.php
Provides a list of changes since the last time modules were published

###Help Options
```
  --help              Show this menu and exit
  --module            Module to be packaged. You can use one module per
                      --module argument (for multiples)
  --directory         Directory Location of modules root, always assumed to
                      be ../freepbx from this location
```

###Usage

```sh
./checklog.php -m=announcement
./checklog.php 
```
##pack_javascripts.php
This script packages javascript files in frameworks assets/js directory in pbxlib.js

###Help Options
```  --help              Show this menu and exit
  --directory         Directory Location of framework root, always assumed to
                      be ../freepbx from this location
```

###Usage
```sh
./pack_javascripts.php --setup
```

License
----

GPLv2 or Later

>FreePBX. An opensource GUI (graphical user interface) that controls and manages Asterisk (PBX)
>Copyright (C) 2013, Schmoozecom, INC

>This program is free software; you can redistribute it and/or
>modify it under the terms of the GNU General Public License
>as published by the Free Software Foundation; either version 2
>of the License, or (at your option) any later version.

>This program is distributed in the hope that it will be useful,
>but WITHOUT ANY WARRANTY; without even the implied warranty of
>MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
>GNU General Public License for more details.

>You should have received a copy of the GNU General Public License
>along with this program; if not, write to the Free Software
>Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*Free Software, Hell Yeah!*
