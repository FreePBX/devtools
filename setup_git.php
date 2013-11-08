#!/usr/bin/php -q
<?php
require_once('libraries/freepbx.php');
$help = array(
	array('--setup', 'Setup new freepbx dev tools environment (use --force to resetup environment)'),
	array('--refresh', 'Updates all local modules with their remote changes'),
	array('--switch=<branch>', 'Switch all local modules to branch')
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
	freepbx::package_show_help('setup_git.php',$help);
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
freepbx::package_show_help('setup_git.php',$help);
exit(0);