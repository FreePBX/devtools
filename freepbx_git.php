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
	array('-r', 'Declare Stash Project Key for single module checkout'),
	array('--setup', 'Setup new freepbx dev tools environment (use --force to re-setup environment)'),
  array('--setuplang', 'Setup language dev tools, requires that --setup was already run (use --force to re-setup environment)'),
	array('--clean', 'Prunes all tags and branches that do no exist on the remote, can be used with the -m command for individual'),
	array('--refresh', 'Updates all local modules with their remote changes (!!you will lose all untracked branches!!)'),
	array('--addmergedriver', 'Updates/Adds Relevant Merge Drivers'),
	array('--switch=<branch>', 'Switch all local modules to branch'),
	array('--mode=<ssh|http>', 'What Mode to Use GIT in, Default is SSH. Use HTTP if you dont have SSH access or you dont know'),
	array('--directory', 'The directory location of the modules, will default to: '.$vars['repo_directory'])
);
$longopts  = array(
	"help",
	"setup",
  "force",
	"refresh",
	"clean",
  "setuplang",
	"addmergedriver",
	"directory::",
	"switch::",
	"mode::"
);
$options = getopt("m:r:",$longopts);
if(empty($options) || isset($options['help'])) {
	freepbx::showHelp('freepbx_git.php',$help);
	exit(0);
}

$mode = !empty($options['mode']) ? $options['mode'] : $mode;
$directory = !empty($options['directory']) ? $options['directory'] : $vars['repo_directory'];

if(!file_exists($directory)) {
	$create = freepbx::getInput("Directory Doesnt Exist, Create? (y/n)",'y');
	if($create == 'n' || !mkdir($directory)) {
		die($directory . " Does Not Exist \n");
	}
}

if(isset($options['setuplang'])) {
  if(!file_exists($directory)) {
    die($directory . " Does Not Exist \n");
    exit(0);
  } else {
    if(!file_exists(dirname($directory).'/freepbxlocalization')) {
      $uri = ($mode == 'http') ? 'http://git.freepbx.org/scm/freepbx/freepbxlocalization.git' : 'ssh://git@git.freepbx.org/freepbx/freepbxlocalization.git';
      $dir = dirname($directory).'/freepbxlocalization';
      freepbx::out("Cloning FreePBX Localization Repo into ".$dir);
      $repo = Git::create($dir, $uri);
    } else {
      freepbx::out('Language Tools Already Exist?!?');
    }
    exit(0);
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
			continue;
		}
		freepbx::out('Done');
	}
	exit(0);
} elseif(isset($options['clean'])) {
	$modules = glob($vars['directory'].'/*', GLOB_ONLYDIR);
	foreach($modules as $mod_dir) {
		freepbx::outn('Cleaning '.$mod_dir.'...');
		try {
			$repo = Git::open($mod_dir);
			$repo->prune('origin');
			$repo->delete_all_tags();
		} catch (Exception $e) {
			freepbx::out($e->getMessage());
			continue;
		}
		freepbx::out('Done');
	}
	exit(0);
}

if(isset($options['m'])) {
	if(!file_exists($directory.'/'.$options['m'])) {
		$username = freepbx::getInput("FreePBX Username");
		$password = freepbx::getPassword("FreePBX Password", true);
		if(($mode == 'http') && version_compare(Git::version(),'1.7.9', '<')) {
			freepbx::out("HTTP Mode is only supported with GIT 1.7.9 or Higher");
			die();
		} elseif($mode == 'http') {
			Git::enable_credential_cache();
		}
		if(isset($options['r'])) {
			$stash->project_key = $project;
			$repo = $stash->getRepo($options['r']);
			if ($repo === false) {
				freepbx::out("[ERROR] Unable to find ".$options['m']);
				exit(0);
			}
		} else {
			$stash = new Stash($username,$password);
			foreach($projects as $project => $description) {
				$stash->project_key = $project;
				$repo = $stash->getRepo($options['m']);
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

			$uri = ($mode == 'http') ? $repo['cloneUrl'] : $repo['cloneSSH'];
			$dir = $directory.'/'.$options['m'];
			freepbx::out("Cloning ".$repo['name'] . " into ".$dir);
			$repo = Git::create($dir, $uri);
			$repo->add_merge_driver();
			freepbx::out("Done");
			$freepbx = new freepbx($username,$password);
			$freepbx->setupSymLinks($directory);
		}
	} else {
		freepbx::out("Module Already Exists");
		if(!file_exists($directory.'/framework/amp_conf/htdocs/admin/modules/'.$options['m'])) {
			$username = freepbx::getInput("FreePBX Username");
			$password = freepbx::getPassword("FreePBX Password", true);
			$freepbx = new freepbx($username,$password);
			$freepbx->setupSymLinks($directory);
		}
	}
	$ul = freepbx::getInput("Update Dev SymLinks?",'n');
	if(($ul == 'yes' || $ul == 'y') && file_exists($directory.'/framework/install_amp')) {
		freepbx::outn("Updating links through install_amp...");
		$pwd = getcwd();
		chdir($directory.'/framework');
		passthru('./install_amp --update-links');
		chdir($pwd);
		freepbx::out("Done");
	}
	exit(0);
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
		$repo->add_merge_driver();
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
	$freepbx->setupDevRepos($directory,$force,$mode);
	$freepbx->setupSymLinks($directory);
	exit(0);
}
freepbx::out("Invalid Command");
freepbx::showHelp('freepbx_git.php',$help);
exit;
