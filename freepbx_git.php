#!/usr/bin/env php
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

$projects = array(
	"FREEPBX" => "FreePBX Open Source",
	"FPBXCN" => "FreePBX Contributed Modules",
	"FPBXC" => "FreePBX Commercial Modules",
);

$freepbx_conf = freepbx::getFreePBXConfig();
//TODO Maybe move this inside of the function
if (is_array($freepbx_conf) && !empty($freepbx_conf)) {
	foreach($freepbx_conf as $key => $value) {
		if (isset($value) && $value != '') {
			$vars[$key] = $value;
		}
	}
}

$mode = !empty($vars['mode']) ? $vars['mode'] : 'ssh';
$vars['repo_directory'] = !empty($vars['repo_directory']) ? $vars['repo_directory'] : dirname(dirname(__FILE__));

$help = array(
	array('-m', 'Checkout a Single Module. Without the -r option will also search all available Stash Projects for said module'),
	array('-s', 'Setup symlinks into Framework for install'),
	array('-r', 'Declare Stash Project Key for single module checkout'),
	array('-y', 'Answer yes for all questions'),
	array('--setup', 'Setup new freepbx dev tools environment (use --force to re-setup environment)'),
	array('--clean', 'Prunes all tags and branches that do no exist on the remote, can be used with the -m command for individual'),
	array('--refresh', 'Updates all local modules with their remote changes (!!you will lose all untracked branches!!)'),
	array('--refreshhard', 'Updates all local modules with their remote changes (!!you will lose all untracked branches and work!!)'),
	array('--addmergedriver', 'Updates/Adds Relevant Merge Drivers'),
	array('--switch=<branch>', 'Switch all local modules to branch'),
	array('--mode=<ssh|http>', 'What Mode to Use GIT in, Default is SSH. Use HTTP if you dont have SSH access or you dont know'),
	array('--directory', 'The directory location of the modules, will default to: '.$vars['repo_directory']),
	array('--keys', 'Comma separated project keys [freepbx,mykey]')
);
$longopts  = array(
	"help",
	"setup",
	"force",
	"refresh",
	"refreshhard",
	"clean",
	"addmergedriver",
	"directory:",
	"switch:",
	"mode:",
	"keys:"
);
$options = getopt("m:r:sy",$longopts);
if(empty($options) || isset($options['help'])) {
	freepbx::showHelp('freepbx_git.php',$help);
	exit(0);
}

$mode = !empty($options['mode']) ? $options['mode'] : $mode;
$directory = !empty($options['directory']) ? $options['directory'] : $vars['repo_directory'];

if(!file_exists($directory)) {
	$create = isset($options['y']) ? 'y' : 
		freepbx::getInput("Directory Doesnt Exist, Create? (y/n)",'y');
	if($create == 'n' || !mkdir($directory)) {
		die($directory . " Does Not Exist \n");
	}
}

if(isset($options['clean']) && isset($options['m'])) {
	if(file_exists($directory.'/'.$options['m'])) {
		freepbx::outn('Cleaning '.$options['m'].'...');
		try {
			$repo = Git::open($directory.'/'.$options['m']);
			$repo->prune('origin');
			$repo->delete_all_tags();
		} catch (Exception $e) {
			freepbx::out($e->getMessage());
			exit(1);
		}
		freepbx::out('Done');
	}
	exit(0);
} elseif(isset($options['clean'])) {
	$modules = glob($directory.'/*', GLOB_ONLYDIR);
	foreach($modules as $mod_dir) {
		freepbx::outn('Cleaning '.$mod_dir.'...');
		try {
			$repo = Git::open($mod_dir);
			$repo->prune('origin');
			$repo->delete_all_tags();
		} catch (Exception $e) {
			freepbx::out($e->getMessage());
			exit(1);
		}
		freepbx::out('Done');
	}
	exit(0);
}

if(isset($options['s'])) {
	freepbx::setupSymLinks($directory);
	exit(0);
}

if(isset($options['m'])) {
	if(!file_exists($directory.'/'.$options['m'])) {
		if (empty($vars['username'])) {
			$username = freepbx::getInput("FreePBX Username");
		} else {
			$username = $vars['username'];
		}
		if (empty($vars['password'])) {
			$password = freepbx::getPassword("FreePBX Password", true);
		} else {
			$password = $vars['password'];
		}
		if(($mode == 'http') && version_compare(Git::version(),'1.7.9', '<')) {
			freepbx::out("HTTP Mode is only supported with GIT 1.7.9 or Higher");
			die();
		} elseif($mode == 'http') {
			Git::enable_credential_cache();
		}

		try{
			$stash = new Stash($username,$password);
		}catch(Exception $e){
			echo $e->getMessage().PHP_EOL;
			return false;
		}

		$repo = false;
		if(isset($options['r'])) {
			$repo = $stash->getRepo($options['m'], $options['r']);
			if ($repo === false) {
				freepbx::out("[ERROR] Unable to find ".$options['m']);
				exit(0);
			}
		} else {
			foreach($projects as $project => $description) {
				$repo = $stash->getRepo($options['m'],$project);
				if ($repo === false) {
					freepbx::out("[WARN] ".$options['m']." is NOT in the ".$description);
				} else {
					break;
				}
			}
			if ($repo === false) {
				freepbx::out("[ERROR] Unable to find ".$options['m']);
				exit(0);
			}
		}

		$uri = ($mode == 'http') ? $repo['cloneUrl'] : $repo['cloneSSH'];
		$dir = $directory.'/'.$options['m'];
		freepbx::out("Cloning ".$repo['name'] . " into ".$dir);
		$repo = Git::create($dir, $uri);
		if(isset($options['switch']) && !empty($options['switch'])) {
			freepbx::switchBranch($dir,$options['switch']);
		}
		$repo->add_merge_driver();
		freepbx::out("Done");
		$freepbx = new freepbx($username,$password);
		$freepbx->setupSymLinks($directory);
	} else {
		freepbx::out("Module Already Exists");
		if(!file_exists($directory.'/framework/amp_conf/htdocs/admin/modules/'.$options['m'])) {
			if (empty($vars['username'])) {
				$username = freepbx::getInput("FreePBX Username");
			} else {
				$username = $vars['username'];
			}
			if (empty($vars['password'])) {
				$password = freepbx::getPassword("FreePBX Password", true);
			} else {
				$password = $vars['password'];
			}

			$freepbx = new freepbx($username,$password);
			$freepbx->setupSymLinks($directory);
		}
	}
	if (empty($vars['dev_symlinks'])) {
		$ul = isset($options['y']) ? 'y' :
			freepbx::getInput("Update Dev SymLinks?",'n');
	} else {
		$ul = $vars['dev_symlinks'];
	}
	if($ul == 'yes' || $ul == 'y') {
		if (file_exists($directory.'/framework/install')) {
			freepbx::outn("Updating links through install...");
			$pwd = getcwd();
			chdir($directory.'/framework');
			passthru('./install --dev-links -n');
			chdir($pwd);
			freepbx::out("Done");
		} else if (file_exists($directory.'/framework/install_amp')) {
			freepbx::outn("Updating links through install_amp...");
			$pwd = getcwd();
			chdir($directory.'/framework');
			passthru('./install_amp --update-links');
			chdir($pwd);
			freepbx::out("Done");
		}
	}
	exit(0);
}

if(!isset($options['setup']) && isset($options['switch']) && !empty($options['switch'])) {
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

if(isset($options['refreshhard'])) {
	foreach(glob($directory."/*", GLOB_ONLYDIR) as $dir) {
		freepbx::refreshRepo($dir,'origin',null,true);
	}
	exit(0);
}

if(isset($options['addmergedriver'])) {
	foreach(glob($directory."/*", GLOB_ONLYDIR) as $dir) {
		freepbx::outn("Attempting to open ".$dir."...");
		//Attempt to open the module as a git repo, bail if it's not a repo
		try {
			$repo = Git::open($dir);
			freepbx::out("Done");
		} catch (Exception $e) {
			freepbx::out("Skipping");
			continue;
		}
		$gitatts = $repo->add_merge_driver();
		if(!empty($gitatts)) {
                	file_put_contents($dir.'/.gitattributes', $gitatts);
        	}
	}
	exit(0);
}

if(isset($options['setup'])) {
	if(is_link($directory)) {
		freepbx::out("Confused. $directory is a symbolic link. Please resolve then run this again");
		exit(1);
	}

	if (empty($vars['username'])) {
		$username = freepbx::getInput("FreePBX Username");
	} else {
		$username = $vars['username'];
	}
	if (empty($vars['password'])) {
		$password = freepbx::getPassword("FreePBX Password", true);
	} else {
		$password = $vars['password'];
 	}
	try {
		$freepbx = new freepbx($username,$password);
	} catch (Exception $e) {
		freepbx::out("Invalid Username/Password Combination");
		exit(1);
	}

	if(isset($options['keys'])) {
		$pkeys = explode(",",$options['keys']);
	} else {
		$pkeys = array("freepbx");
	}

	$force = isset($options['force']);
	$branch = isset($options['switch']) && !empty($options['switch']) ? $options['switch'] : 'develop';
	foreach($pkeys as $k) {
		$freepbx->setupDevRepos($directory,$force,$mode,$branch,$k);
	}
	$freepbx->setupSymLinks($directory);
	exit(0);
}
freepbx::out("Invalid Command");
freepbx::showHelp('freepbx_git.php',$help);
exit;
