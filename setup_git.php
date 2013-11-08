#!/usr/bin/php -q
<?php
require_once('libraries/freepbx.php');
$longopts  = array(
    "force",
	"refresh",
	"switch",
);
$options = getopt("",$longopts);

$username = freepbx::getInput("FreePBX Username");
$password = freepbx::getPassword("FreePBX Password", true);
try {
	$freepbx = new freepbx($username,$password);
} catch (Exception $e) {
	freepbx::out("Invalid Username/Password Combination");
	exit(1);
}
$directory = freepbx::getInput("Development Directory (NOT WEBROOT)",dirname(dirname(__FILE__)).'/freepbx');

if(!file_exists($directory)) {
	if(!mkdir($directory)) {
		die($directory . " Does Not Exist \n");
	}
}

if(isset($options['switch'])) {
	foreach(glob($directory."/*", GLOB_ONLYDIR) as $dir) { 
		freepbx::switchBranch($dir,'release/2.11');
	}
	exit(0);
}

if(isset($options['refresh'])) {
	foreach(glob($directory."/*", GLOB_ONLYDIR) as $dir) { 
		freepbx::refreshRepo($dir);
	}
	exit(0);
}

$force = isset($options['force']) ? true : false;
//TODO: release branch is hardcoded...
$freepbx->setupDevRepos($directory,$force,'2.11');
$freepbx->setupSymLinks($directory);
exit(0);
