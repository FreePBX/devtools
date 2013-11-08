#!/usr/bin/php -q
<?php
/**
 * Copyright 2013 by Schmooze Com, Inc.
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author mbrevda => gmail ! com
 * @author andrew ! nagy => the159 ! com
 *
 * options:
 *	run with --help for options
 *
 */
require_once('libraries/freepbx.php');
$help = array(
	array('--setup', 'Setup new freepbx dev tools environment (use --force to re-setup environment)'),
	array('--refresh', 'Updates all local modules with their remote changes'),
	array('--switch=<branch>', 'Switch all local modules to branch'),
	array('--directory', 'The directory location of the modules, will default to: '.dirname(dirname(__FILE__)).'/freepbx')
);
$longopts  = array(
	"help",
	"setup",
    "force",
	"refresh",
	"directory::",
	"switch::",
);
$options = getopt("",$longopts);
if(empty($options) || isset($options['help'])) {
	freepbx::showHelp('freepbx_git.php',$help);
	exit(0);
}

$directory = !empty($options['directory']) ? $options['directory'] : freepbx::getInput("Development Directory (NOT WEBROOT)",dirname(dirname(__FILE__)).'/freepbx');

if(!file_exists($directory)) {
	$create = freepbx::getInput("Directory Doesnt Exist, Create? (y/n)",'y');
	if($create == 'n' || !mkdir($directory)) {
		die($directory . " Does Not Exist \n");
	}
}

if(isset($options['switch']) && !empty($options['switch'])) {
	foreach(glob($directory."/*", GLOB_ONLYDIR) as $dir) { 
		freepbx::switchBranch($dir,$options['switch']);
	}
	exit(0);
}

if(isset($options['refresh'])) {
	foreach(glob($directory."/*", GLOB_ONLYDIR) as $dir) { 
		freepbx::refreshRepo($dir);
	}
	exit(0);
}

if(isset($options['setup'])) {
	$username = freepbx::getInput("FreePBX Username");
	$password = freepbx::getPassword("FreePBX Password", true);
	try {
		$freepbx = new freepbx($username,$password);
	} catch (Exception $e) {
		freepbx::out("Invalid Username/Password Combination");
		exit(1);
	}

	$force = isset($options['force']) ? true : false;
	//TODO: release branch is hardcoded...
	$freepbx->setupDevRepos($directory,$force,'2.11');
	$freepbx->setupSymLinks($directory);
	exit(0);
}
freepbx::out("Invalid Command");
freepbx::showHelp('freepbx_git.php',$help);
exit(0);