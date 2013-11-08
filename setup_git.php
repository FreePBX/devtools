#!/usr/bin/php -q
<?php
require_once('libraries/freepbx.php');
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
	freepbx::out(package_show_help());
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
freepbx::out(package_show_help());
exit(0);

//show help menu
function package_show_help() {
	$final = '';
	$ret[] = 'setup_git.php';
	$ret[] = '-----------';
	$ret[] = '';

	//args
	$ret[] = array('--setup', 'Setup new freepbx dev tools environment (use --force to resetup environment)');
	$ret[] = array('--refresh', 'Updates all local modules with their remote changes');
	$ret[] = array('--switch=<branch>', 'Switch all local modules to branch');

	$ret[] = '';

	//generate formated help message
	foreach ($ret as $r) {
		if (is_array($r)) {
			//pad the option
			$option = '  ' . str_pad($r[0], 20);

			//explode the definition to manageable chunks
			$def = explode('§', wordwrap($r[1], 55, "§", true));

			//and pad the def with whitespace 20 chars to the left stating from the second line
			if (count($def) > 1) {
				$first = array_shift($def);
				foreach ($def as $my => $item) {
					$def[$my] = str_pad('', 22) . $item . PHP_EOL;
				}
			} elseif (count($def) == 1) {
				$first = implode($def);
				$def = array();
			} else {
				$first = '';
				$def = array();
			}

			$definition = $first . PHP_EOL . implode($def);
			$final .= $option . $definition;
		} else {
			$final .=  $r . PHP_EOL;
		}
	}
	return $final;
}