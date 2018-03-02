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
require('libraries/freepbx.php');
require("libraries/translation.class.php");

$help = array();
$help[] = array('--bump', 'Bump a modules version. You can specify the "octet" by adding a position '
			. 'I.e. --bump=2 will turn 3.4.5.6 in to 3.5.5.6. Leaving the position blank will bump the last "octet"');
$help[] = array('--debug=false', 'Debug only - just run through the command but don\'t make any changes');
$help[] = array('-c', 'Prompt for FreePBX.org Credentials');
$help[] = array('--help', 'Show this menu and exit');
$help[] = array('--log', 'Update module.xml\'s changelog. [Done by default if bumping]');
$help[] = array('--module', 'Module to be packaged. You can use one module per --module argument (for multiples)');
$help[] = array('--directory', 'Directory Location of modules root, always assumed to be ../freepbx from this location');
$help[] = array('--msg', 'Optional changelog/commit message.');
$help[] = array('--re', 'A ticket number to be referenced in all checkins (i.e. "re #627...")');
$help[] = array('--verbose', 'Run with extra verbosity and print each command before it\'s executed');
$help[] = array('--forcetag', 'Force this sha1 onto the server if the tag already exists');
$help[] = array('--remote', 'The Remote GIT Repository name, Default is origin');
//get cli opts
$longopts = array(
	'directory:',
	'bump::',
	'debug::',
	'help::',
	'log::',
	'module:',
	'msg::',
	'publish::',
	're::',
	'verbose::',
	'forcetag',
	'remote::',
	'sshuser::'
);
$vars = getopt('m:d::v::c::', $longopts);

if (isset($vars['d']) || isset($vars['L'])) {
	freepbx::showHelp('Package.php',$help);
	sleep(3);
}

//if help was requested, show help and exit
if (isset($vars['help'])) {
	freepbx::showHelp('Package.php',$help);
	exit(0);
}

//determine if we have a .freepbxconfig file in our home directory with
//settings, and setting them as $vars
$freepbx_conf = freepbx::getFreePBXConfig();
if (is_array($freepbx_conf) && !empty($freepbx_conf)) {
	foreach($freepbx_conf as $key => $value) {
		if (isset($value) && $value != '') {
			$vars[$key] = $value;
		}
	}
}

//set up some other settings
$vars['php_-l'] = 'php -l';
$vars['remote'] = isset($vars['remote']) ? $vars['remote'] : 'origin';
$vars['php_extens'] = array('php', 'agi'); //extens to be considered as php for syntax checking
$vars['directory'] = !empty($vars['repo_directory']) ? $vars['repo_directory'] : (!empty($vars['directory']) ? $vars['directory'] : '/usr/src/freepbx_packaging/repos');
$modules = array();
$final_status = array();//status message to be printed after script is run

//Combine shortopt -m with longopt --module
$vars['m'] = (isset($vars['m'])) ? (is_array($vars['m']) ? $vars['m'] : array($vars['m'])) : array();
$vars['module'] = (isset($vars['module'])) ? (is_array($vars['module']) ? $vars['module'] : array($vars['module'])) : array();
$modules = array_merge($vars['m'], $vars['module']);
//cleaup
unset($vars['m']);
unset($vars['module']);

//Check to make sure the module folder actually exists in the path provided
foreach($modules as $module) {
	if(!file_exists($vars['directory'].'/'.$module)) {
		freepbx::out('Module '.$module.' does not exist in path: '.$vars['directory']);
		exit(1);
	}
}

//bump or not and by how much
if (isset($vars['bump']) && $vars['bump'] != 'false') {
	$vars['bump'] = ctype_digit($vars['bump']) ? $vars['bump'] : true;
} else {
	$vars['bump'] = false;
}

//debug, shortops and longops
if (isset($vars['d'])) {
	$vars['debug'] = true;
	unset($vars['d']);
} elseif (isset($vars['debug']) && $vars['debug'] != 'false') {
	$vars['debug'] = true;
} else {
	$vars['debug'] = false;
}

//log or not
if (isset($vars['log']) && $vars['log'] != 'log') {
	$vars['log'] = true;
} else {
	$vars['log'] = false;
}

//force a tag to be updated instead of creating from scratch
if (isset($vars['forcetag']) && $vars['forcetag'] != 'forcetag') {
	$vars['forcetag'] = true;
} else {
	$vars['forcetag'] = false;
}

//verbosity
if (isset($vars['v'])) {
	$vars['verbose'] = true;
	unset($vars['L']);
} elseif (isset($vars['verbose']) && $vars['verbose'] != 'false') {
	$vars['verbose'] = true;
} else {
	$vars['verbose'] = false;
}

//check to see if this an interactive session
exec('test -t 0', $ret, $vars['interactive']);
$vars['interactive'] = !$vars['interactive'];

//set re
//move re to an array if there are commas as part of the value
if (isset($vars['re'])) {
	switch (true) {
		//multiple references
		case is_array($vars['re']):
			foreach ($vars['re'] as $k => $v) {
				if ($v) {
					$vars['re'][$k] = 'FREEPBX-' . preg_replace("/[^0-9]/", '', $v);
				}
			}

			$vars['re'] = 're ' . implode(', ', $vars['re']) . ' ';
			break;
		//single references
		case is_string($vars['re']):
			$vars['re'] = 're FREEPBX-' . preg_replace("/[^0-9]/", '', $vars['re']) . ' ';
			break;
		default:
			break;
	}
} else {
	$vars['re'] = '';
}

//trim message
$vars['msg'] = isset($vars['msg']) ? trim($vars['msg']) . ' ' : '';
$vars['msg'] = $vars['re'] ? $vars['re'] . '- ' . $vars['msg'] : $vars['msg'];

//set username and password mode
//TODO: This will be used by JIRA at some point
if (isset($vars['c']) && $vars['interactive']) {
	$vars['username'] = freepbx::getInput('Username');
	if (empty($vars['username'])) {
		freepbx::out("Invalid Username");
		exit(1);
	}

	$vars['password'] = freepbx::getPassword('Password');
	if (empty($vars['password'])) {
		efreepbx::out("Invalid Password");
		exit(1);
	}
}

//ensure we have modules to package
if (empty($modules)) {
	freepbx::out("No modules specified. Please specify at least one module");
	freepbx::showHelp('Package.php',$help);
	exit(1);
}

//get current working directory
$cwd = getcwd();
if (!file_exists($vars['directory'])) {
	freepbx::out("Directory Location: ".$vars['directory']." does not exist!");
	exit(1);
}
freepbx::out("Using ".$vars['directory']);
chdir($vars['directory']);
update_devtools();
foreach ($modules as $module) {
	$file_scan_exclude_list = ($module == 'framework') ? array("modules","Symfony","Composer") : array();
	freepbx::out("Processing ".$module."...");
	$mod_dir = $vars['directory'].'/'.$module;

	if(file_exists($mod_dir . '/.lintignore')) {
		$raw = file_get_contents($mod_dir . '/.lintignore');
		$ignores = explode("\n",$raw);
		$file_scan_exclude_list = array_merge($file_scan_exclude_list,$ignores);
	}

	//Bail out if module.xml doesnt exist....its sort-of-important
	if (!file_exists($mod_dir . '/module.xml')) {
		freepbx::out("\t".$mod_dir . '/module.xml does not exist');
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}

	freepbx::out("\tChecking GIT Status...");
	freepbx::outn("\t\tAttempting to open module...");
	//Attempt to open the module as a git repo, bail if it's not a repo
	try {
		$repo = Git::open($mod_dir);
		freepbx::out("Done");
	} catch (Exception $e) {
		freepbx::out($e->getMessage());
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}

	//fetch changes so that we can get new tags
	freepbx::outn("\t\tFetching remote changes (not applying)...");
	$repo->fetch();
	freepbx::out("Done");

	//now check to make sure the xml is valid
	freepbx::outn("\tChecking Module XML...");
	//test xml file and get some of its values
	list($rawname, $ver, $supported, $license, $licenselink) = freepbx::check_xml_file($mod_dir);
	//dont continue if there is an issue with the xml
	if ($rawname == false || $ver == false || $license == false || $licenselink == false) {
		$missing = ($rawname == false) ? 'rawname' : ($ver == false ? 'version' : ($license == false ? 'license' : ($licenselink == false ? 'licenselink' : 'Unknown')));
		freepbx::out('module.xml is missing '.$missing);
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}
	freepbx::out("Done");

	//Check to see if we are on the correct release branch
	freepbx::outn("\t\tChecking if on Release Branch...");
	$activeb = $repo->active_branch();
	//get ready to cross-compare the remote and local branches
	$lbranches = $repo->list_branches();
	$rbranches = $repo->list_remote_branches();
	//get module root version
	if(!preg_match('/(\d*\.\d*)\./i',$ver,$matches)) {
		freepbx::out("no");
		freepbx::out("Module Version numbers must be of format X.Y.Z, Where X.Y is the release branch. Yours was " + $ver);
		freepbx::out("Example: Module Version is 12.0.4, then the release branch would be release/12.0");
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}
	$mver = $matches[1];
	if(!preg_match('/release\/(.*)/i',$activeb,$matches)) {
		//we are not on our release branch for this 'module'
		freepbx::out("no");
		freepbx::out("Please Switch ".$module." to be on a release branch");
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	} else {
		freepbx::out("Yes (Working With ".$activeb.")");
	}
	$bver = $matches[1];

	freepbx::outn("\t\tFetching remote changes and applying to ".$activeb."...");
	try {
		$repo->pull($vars['remote'], $activeb);
	} catch (Exception $e) {
		freepbx::out("Merge Conflicts with this branch. Please fix");
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}
	freepbx::out("Done");

	//make sure the version inside the module matches the release version we are on
	//(the first 2 version IDs)
	if($bver != $mver) {
		freepbx::out("Module Version of ".$mver." does not match branch release version of ".$bver);
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}
	//cleaner to the eyes of us mortals
	natsort($rbranches);
	//now check all release branches and make sure our supported version isn't doubled
	freepbx::out("\tChecking to make sure supported version isn't doubled...");
	//run through remote branches
	foreach($rbranches as $branch) {
		//divide and conquer, only work with our remote and our releases on remote
		//but skip our active branch, we know about that one
		if(preg_match('/'.$vars['remote'].'\/release\/(.*)/i',$branch,$matches) && $branch != $vars['remote'].'/'.$activeb) {
			freepbx::outn("\t\tChecking ".$branch."...");
			//attempt to 'grab' the file from it's reference,
			//way less messy than a checkout
			try{
				$xml = $repo->show('refs/remotes/'.$branch,'module.xml');
			} catch (Exception $e) {
				//no module xml here...nothing to see move along and try next branch
				freepbx::out("No Module.xml, skipping");
				continue;
			}
			//load our xml string into our common parser
			//we only get back three of the tags, rawname, version and supported
			$bxml = freepbx::check_xml_string($xml);
			//check to make sure we aren't higher than the ones higher than us
			//and that we arent lower than the ones lower than us
			$type = version_compare($bver, $matches[1], '>') ? '<=' : '>=';
			if (!empty($bxml[2]['version']) && version_compare($supported['version'], $bxml[2]['version'], $type)) {
				$ntype = ($type == '<=') ? 'higher' : 'lower';
				$stype = ($type == '>=') ? 'higher' : 'lower';
				freepbx::out("Supported version of this branch (".$bxml[2]['version'].") on a ".$stype." release is ".$ntype." than branch ".$activeb."(".$supported['version'].")");
				//errored so die out
				freepbx::out("Module " . $module . " will not be tagged!");
				//completely exit out of attempting anything with this module, it has problems
				//'mo money mo' problems I always say
				continue(2);
			}
			//some visual aides for good branches
			if(!empty($bxml[2]['version'])) {
				$ntype = ($type == '>=') ? 'higher' : 'lower';
				freepbx::out("Passed (Supported version of this branch [".$bxml[2]['version']."] is ".$ntype." than ".$supported['version'].")");
			}
		}
	}
	//check php files for syntax errors
	freepbx::outn("\tChecking for PHP Syntax Errors...");
	$syntax_errors = array();
	$files = package_scandirr($mod_dir, true, $file_scan_exclude_list);
	foreach ($files as $f) {
		if (in_array(pathinfo($f, PATHINFO_EXTENSION), $vars['php_extens']) && (!run_cmd($vars['php_-l'] . ' ' . escapeshellarg($f), $outline, (!$vars['debug'] && !$vars['verbose']), true))) {
			//add errors to array
			$syntax_errors[] = 'syntax error detected in ' . $f . PHP_EOL;
		}
	}
	unset($files);

	//if there are syntax errors then display them
	if ($syntax_errors) {
		$final_status[$module] = implode(PHP_EOL, $syntax_errors);
		freepbx::out("\t".$final_status[$module]);
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}
	freepbx::out("There are no errors");

	// Make sure composer exists, in case someone renames or updates it or something
	$composer = __DIR__."/binaries/composer.phar";

	if (!file_exists($composer)) {
		throw new \Exception("Composer file missing. This repo should contain the composer phar");
	}

	// Do some framework-specifc things.
	if($module == 'framework') {
		freepbx::outn("\tFramework detected!\n\t\tPackaging javascripts...");
		// Package javascript because Andrew always forgets
		exec('/usr/bin/env php '.dirname(__FILE__).'/pack_javascripts.php --directory '.$vars['directory']);
		freepbx::out("Done");
		freepbx::outn("\t\tRebuilding Composer Autoloader...");
		$pushd = getcwd();
		chdir($vars['directory']."/framework/amp_conf/htdocs/admin/libraries/Composer");
		`$composer dump-autoload --optimize`;
		chdir($pushd);
		freepbx::out("Done");
	} else {
		// Framework is allowed to have symlinks
		freepbx::outn("\tChecking for symlinks...");
		$cmd = "find $mod_dir -path $mod_dir/node -prune -o -type l -print";
		exec($cmd, $output, $ret);
		if ($output) {
			freepbx::out("Error! Found Symlinks! Cannot package");
			var_dump($output);
			exit(255);
		} else {
			freepbx::out("None found");
		}
	}

	freepbx::outn("\tChecking for bad files...");
	$cmd = "find $mod_dir -path $mod_dir/.git -prune -o -name .DS_Store -o -name *swp -o -regex '.*/[0-9]+' -print";
	exec($cmd, $output, $ret);
	if ($output) {
		freepbx::out("Error! Found Bad files! Cannot package");
		var_dump($output);
		exit(255);
	} else {
		freepbx::out("None found");
	}

	//run unit tests
	if(file_exists($mod_dir.'/utests') && file_exists('/etc/freepbx.conf') && file_exists(__DIR__.'/phpunit.php')) {
		freepbx::outn("\tDetected Unit Tests...");
		$config = '';
		if(file_exists($mod_dir."/utests/utests.xml")) {
			$config = "-c ".$mod_dir."/utests/utests.xml";
		}
		if(!run_cmd(__DIR__.'/binaries/phpunit.phar --bootstrap "'.__DIR__.'/phpunitBootstrap.php" '.$config.' '.$mod_dir.'/utests',$outline,true)) {
			freepbx::out(__DIR__.'/binaries/phpunit.phar --bootstrap "'.__DIR__.'/phpunitBootstrap.php" '.$config.' '.$mod_dir.'/utests');
			freepbx::out("Unit tests failed");
			freepbx::out("Module " . $module . " will not be tagged!");
			continue;
		}
		freepbx::out("all unit tests passed");
	}

	//bump version if requested
	if ($vars['bump'] && !$vars['debug']) {
		freepbx::outn("\tBumping Version as Requested...");
		$ver = package_bump_version($module, $vars['bump']);
		freepbx::out("Done");
		$vars['log'] = true;
	}

	fix_publisher($module);

	//add changelog if requested
	if ($vars['log'] && !$vars['debug']) {
		freepbx::outn("\tUpdating Changelog...");
		$msg = $vars['msg'] ? $vars['msg'] : 'Packaging of ver ' . $ver;
		package_update_changelog($module, $msg);
		freepbx::out("Done");
	}

	// Run xml script through the exact method that FreePBX currently uses. There have
	// been cases where XML is valid but this method still fails so it won't be caught
	// with the proper XML checer, better here then breaking the online repository
	// -Philippe L.
	$parser = new xml2ModuleArray();
	$xmlarray = $parser->parseAdvanced(file_get_contents($mod_dir . '/module.xml'));

	//Check XML File one more time to be safe
	freepbx::outn("\tChecking Modified Module XML...");
	//test xml file and get some of its values
	list($rawname, $ver, $supported, $license, $licenselink) = freepbx::check_xml_file($mod_dir);
	//dont continue if there is an issue with the xml
	if ($rawname == false || $ver == false || $supported == false || $license == false || $licenselink == false) {
		$missing = ($rawname == false) ? 'rawname' : ($ver == false ? 'version' : ($supported == false ? 'supported' : ($license == false ? 'license' : ($licenselink == false ? 'licenselink' : 'Unknown'))));
		freepbx::out('module.xml is missing '.$missing);
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}
	//sanity check
	if($rawname != $xmlarray['module']['rawname'] || $ver != $xmlarray['module']['version']) {
		freepbx::out('simple_xml_object and xml2modulearray mismatch');
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}

	freepbx::out("Done");

	//If we have a license, which we are required to have by this point, get the
	//	licenselink tag and generate a LICENSE file
	if ($license) {
		if (!empty($licenselink)) {
			freepbx::outn("\tAttempting to get license from: ".$licenselink);
			$licensetext = freepbx::get_license_from_link($licenselink);

			if ($licensetext === false) {
				freepbx::out('Unable to add license text');
				continue;
			}

			if (!file_put_contents($mod_dir.'/LICENSE', $licensetext)) {
				freepbx::out('Unable to get License from License link in module.xml');
				continue;
			}
			freepbx::out("Done");
		}
	}

	$description = isset($modulexml['description'])?$modulexml['description']:'';

	$reps = array('!!NAME!!'=> $rawname,
		'!!DESCRIPTION!!'=> $description,
		'!!LICENSE!!' => $license,
		'!!LICENSELINK!!' => $licenselink);
	$template = <<<HERE
```
 ______             _____  ______   __
|  ____|           |  __ \|  _ \ \ / /
| |__ _ __ ___  ___| |__) | |_) \ V /
|  __| '__/ _ \/ _ \  ___/|  _ < > <
| |  | | |  __/  __/ |    | |_) / . \
|_|  |_|  \___|\___|_|    |____/_/ \_\
Your Open Source Asterisk PBX GUI Solution
```
### What?
!!NAME!!
This is a module for [FreePBX©](http://www.freepbx.org/ "FreePBX Home Page"). [FreePBX](http://www.freepbx.org/ "FreePBX Home Page") is an open source GUI (graphical user interface) that controls and manages [Asterisk©](http://www.asterisk.org/ "Asterisk Home Page") (PBX). FreePBX is licensed under GPL.
[FreePBX](http://www.freepbx.org/ "FreePBX Home Page") is a completely modular GUI for Asterisk written in PHP and Javascript. Meaning you can easily write any module you can think of and distribute it free of cost to your clients so that they can take advantage of beneficial features in [Asterisk](http://www.asterisk.org/ "Asterisk Home Page")
!!DESCRIPTION!!
### Setting up a FreePBX system
[See our WIKI](http://wiki.freepbx.org/display/FOP/Install+FreePBX)
### License
[This modules code is licensed as !!LICENSE!!](!!LICENSELINK!!)
### Contributing
To contribute code or modules back into the [FreePBX](http://www.freepbx.org/ "FreePBX Home Page") ecosystem you must fully read our Code License Agreement. We are not able to look at or accept patches or code of any kind until this document is filled out. Please take a look at [http://wiki.freepbx.org/display/DC/Code+License+Agreement](http://wiki.freepbx.org/display/DC/Code+License+Agreement) for more information
### Issues
Please file bug reports at http://issues.freepbx.org
HERE;
	$readmetxt = str_replace(array_keys($reps),array_values($reps), $template);
	if($module != "framework" && strtolower($license) != "commercial") {
		if (!file_put_contents($mod_dir.'/README.md', $readmetxt)) {
			freepbx::out('Unable to write to README.md');
			continue;
		}
	}

	//GIT Processing here
	freepbx::out("\tRunning GIT...");
	freepbx::outn("\t\tAdding/Updating Merge Drivers....");
	$gitatts = $repo->add_merge_driver();
	if(!empty($gitatts)) {
		file_put_contents($mod_dir.'/.gitattributes', $gitatts);
	}
	freepbx::out("Done");

	//merging languages
	$moduleMasterXmlString = $repo->show('origin/master','module.xml');
	$masterXML = simplexml_load_string($moduleMasterXmlString);
	freepbx::out("\t\tChecking Merge Status with master");
	if(freepbx::version_compare_freepbx((string)$masterXML->version, $ver, "<=")) {
		freepbx::outn("\t\t\tModule is higher than or equal to master, merging master into this branch...");
		$stashable = $repo->add_stash();
		$repo->fetch();
		try {
			$merged = $repo->pull('origin','master');
		} catch(\Exception $e) {
			$merged = false;
		}
		if(!$merged) {
			freepbx::out("\t\t\tMerge from master to this branch failed");
			freepbx::out("Module " . $module . " will not be tagged!");
			continue;
		}
		freepbx::out("Done");
		if($stashable) {
			$repo->apply_stash();
			$repo->drop_stash();
		}
	}

	freepbx::out("\tProcessing localizations...");
	freepbx::outn("\t\tUpdating master localization...");
	$translation = new Translation($mod_dir);
	if(!preg_match('/(core|framework)$/i',$mod_dir)) {
		//if no i18n folder then make a spanish one!
		if(!file_exists($mod_dir.'/i18n')) {
			$translation->makeLanguage("es_ES");
		}
		//pray that this works..
		$translation->update_i18n();
		freepbx::out("Done");
		foreach(glob($mod_dir.'/i18n/*',GLOB_ONLYDIR) as $langDir) {
			$lang = basename($langDir);
			freepbx::outn("\t\tUpdating individual localization for ".$lang);
			$o = $translation->merge_i18n($lang);
			freepbx::out($o);
		}
	} elseif(preg_match('/framework$/i',$mod_dir)) {
		$translation->update_i18n_amp();
		foreach(glob($mod_dir.'/amp_conf/htdocs/admin/i18n/*',GLOB_ONLYDIR) as $langDir) {
			$lang = basename($langDir);
			freepbx::outn("\t\tUpdating individual localization for ".$lang);
			$o = $translation->merge_i18n_amp($lang);
			freepbx::out($o);
		}
		freepbx::out("Done");
	} else {
		freepbx::out("Core is done through framework");
	}

	freepbx::out("\tRunning GIT (again)...");
	freepbx::outn("\t\tChecking for Modified or New files...");
	$status = $repo->status();
	$commitable = false;
	if(empty($status)) {
		freepbx::out("No Modified or New Files");
	} else {
		freepbx::out("Found ".count($status['modified'])." Modified files and ".count($status['untracked'])." New files");
		$commitable = true;
	}

	//Check to see if the tag already exists locally
	freepbx::outn("\t\tChecking to see if local tag already exists...");
	if($repo->tag_exist('release/'.$ver) && !$vars['forcetag']) {
		freepbx::out("Tag Already Exists (Use --forcetag to force this sha1 onto the remote)");
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	} elseif($repo->tag_exist('release/'.$ver) && $vars['forcetag']) {
		freepbx::out("It does");
		freepbx::outn("\t\t\t[FORCETAG] Removing Local Tag...");
		if(!$vars['debug']) {
			$repo->delete_tag('release/'.$ver);
			freepbx::out("Done");
		} else {
			freepbx::out("Debugging, Not Ran");
		}
	} else {
		freepbx::out("It doesn't");
	}

	//Check to see if the tag exists remotely
	$remote_tag = null;
	freepbx::outn("\t\tChecking to see if remote tag on ".$vars['remote']." already exists...");
	if($repo->remote_tag_exist($vars['remote'],'release/'.$ver) && !$vars['forcetag']) {
		freepbx::out("Tag Already Exists (Use --forcetag to force this sha1 onto the remote)");
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	} elseif($repo->remote_tag_exist($vars['remote'],'release/'.$ver) && $vars['forcetag']) {
		freepbx::out("It does..");
		freepbx::out("\t\t\t[FORCETAG] Remote tag stagged for removal");
		$remote_tag = 'release/'.$ver;
	} else {
		freepbx::out("It doesn't");
	}

	if($commitable) {
		freepbx::outn("\t\tAdding Module.xml...");
		//add module.xml separately from the rest of the changes, because I said so
		if(!$vars['debug']) {
			try {
				$repo->add('module.xml');
			} catch (Exception $e) {
				freepbx::out($e->getMessage());
				freepbx::out("Module " . $module . " will not be tagged!");
				continue;
			}
			freepbx::out("Done");
		} else {
			freepbx::out("Debugging, Not Ran");
		}

		freepbx::outn("\t\tAdding LICENSE...");
		//add module.xml separately from the rest of the changes, because I said so
		if(!$vars['debug']) {
			try {
				$repo->add('LICENSE');
			} catch (Exception $e) {
				freepbx::out($e->getMessage());
				freepbx::out("Module " . $module . " will not be tagged since we are unable to add the LICENSE file!");
				continue;
			}
			freepbx::out("Done");
		} else {
			freepbx::out("Debugging, Not Ran");
		}

		freepbx::outn("\t\tCheckin Outstanding Changes...");
		//-A will do more than ., it will add any unstaged files...
		if(!$vars['debug']) {
			try {
				$repo->add('-A');
			} catch (Exception $e) {
				freepbx::out($e->getMessage());
				freepbx::out("Module " . $module . " will not be tagged!");
				continue;
			}
			freepbx::out("Done");
		} else {
			freepbx::out("Debugging, Not Ran");
		}
		freepbx::outn("\t\tAdding Commit Message...");
		//Commit with old commit message from before, but call it tag instead of commit.
		if(!$vars['debug']) {
			try {
				$repo->commit('[Module Tag script: '.$rawname.' '.$ver.'] '.$vars['msg']);
			} catch (Exception $e) {
				freepbx::out($e->getMessage());
				freepbx::out("Module " . $module . " will not be tagged!");
				continue;
			}
			freepbx::out("Done");
		} else {
			freepbx::out("Debugging, Not Ran");
		}
	}
	//remove remote tag but only if we had forcetag enabled
	if(!empty($remote_tag) && $vars['forcetag']) {
		freepbx::outn("\t\t[FORCETAG] Removing Staged Remote Tag...");
		if(!$vars['debug']) {
			try {
				$repo->delete_remote_tag($vars['remote'],$remote_tag);
			} catch (Exception $e) {
				freepbx::out($e->getMessage());
				freepbx::out("Module " . $module . " will not be tagged!");
				continue;
			}
			freepbx::out("Done");
		} else {
			freepbx::out("Debugging, Not Ran");
		}
	}
	freepbx::outn("\t\tAdding Tag at this state...");
	//add a tag at this point in time
	if(!$vars['debug']) {
		try {
			$repo->add_tag('release/'.$ver);
		} catch (Exception $e) {
			freepbx::out($e->getMessage());
			freepbx::out("Module " . $module . " will not be tagged!");
			continue;
		}
		freepbx::out("Done");
	} else {
		freepbx::out("Debugging, Not Ran");
	}

	freepbx::outn("\t\tPushing to ".$vars['remote']." release/".$mver."...");
	//push branch and tag to remote
	//TODO: check to make sure the author/committer isn't 'root'
	if(!$vars['debug']) {
		try {
			$repo->push($vars['remote'], "release/".$mver);
		} catch (Exception $e) {
			freepbx::out($e->getMessage());
			freepbx::out("Module " . $module . " will not be tagged!");
			continue;
		}
		freepbx::out("Done");
	} else {
		freepbx::out("Debugging, Not Ran");
	}

	if(freepbx::version_compare_freepbx((string)$masterXML->supported->version, $supported['version'], "=") && (freepbx::version_compare_freepbx((string)$masterXML->version, $ver, "<="))) {
		freepbx::outn("\t\tMaster is the same supported release as this branch. Merging release/".$mver." into master...");
		if(!$vars['debug']) {
			$repo->checkout("master");
			$merged = $repo->pull($vars['remote'],"release/".$mver);
			if(!$merged) {
				freepbx::out("\t\t\tMerge from release/".$mver." into master failed");
				freepbx::out("Module " . $module . " will not be tagged!");
				continue;
			}
			$repo->push($vars['remote'], "master");
			$repo->checkout("release/".$mver);
		}
		freepbx::out("Done");
	}

	$tense = !$vars['debug'] ? 'has' : 'would have';
	$final_status[$module] = 'Module ' . $module . ' version ' . $ver . ' ' . $tense . ' been successfully tagged!';
	freepbx::out($final_status[$module]);
}

//print report
echo PHP_EOL . PHP_EOL . PHP_EOL;
echo 'Package Script Report:' . PHP_EOL;
echo '----------------------' . PHP_EOL;
foreach ($final_status as $module => $status) {
	echo $status . PHP_EOL;
}
echo '----------------------' . PHP_EOL . PHP_EOL;

if (!empty($supported['version']) && !empty($final_status)) {
	if($vars['interactive'] && !isset($vars['publish'])) {
		$publish = freepbx::getInput('Publish?','n');
	} else {
		$publish = isset($vars['publish']) ? 'yes' : '';
	}

	if($publish == 'y' || $publish == 'yes') {
		$user = posix_getpwuid(posix_geteuid());
		if($vars['interactive'] && !isset($vars['publish'])) {
			$username = freepbx::getInput('Username?',$user['name']);
		} else {
			$username = !empty($vars['sshuser']) ? $vars['sshuser'] : $user['name'];
		}


		$agent = new \phpseclib\System\SSH\Agent();
		$ssh = new phpseclib\Net\SSH2('mirror1.freepbx.org');

		if (!$ssh->login($username, $agent)) {
			freepbx::out('Authentication rejected by server');
			exit(1);
		}

		$ssh->setTimeout(false);

		$agent->startSSHForwarding($ssh);

		$packager = "/usr/src/freepbx-server-dev-tools/server_packaging.php";
		$ret = $ssh->exec('ls '.$packager);
		if(trim($ret) != $packager) {
			freepbx::out('Cant Find Package Scripts');
			exit(1);
		}

		foreach ($final_status as $module => $status) {
			if($vars['interactive'] && !isset($vars['publish'])) {
				$supported = freepbx::getInput('Supported Version to Publish '.$module.' for?',$supported['version']);
			} else {
				$supported = $supported['version'];
			}

			$ret = $ssh->exec($packager . " -s " . $supported . " -m " . $module . " --skipzendcheck", function($data) {
				echo $data;
			});
		}
	}
}

exit(0);
/**
 * function package_scandirr
 * scans a directory just like scandir(), only recursively
 * returns a hierarchical array representing the directory structure
 *
 * @pram string - directory to scan
 * @pram string - return absolute paths
 * @pram array - list of excluded files/directories to ignore
 * @returns array
 *
 * @author Moshe Brevda mbrevda => gmail ~ com
 */
function package_scandirr($dir, $absolute = false, $exclude_list=array()) {
	$list = array();
	if ($absolute) {
		global $list;
	}


	//get directory contents
	if (!empty($exclude_list) && in_array(basename($dir), $exclude_list)) {
		return $list;
	}
	foreach (scandir($dir) as $d) {

		//ignore any of the files in the array
		if (in_array($d, array('.', '..', '.git')) || (!empty($exclude_list) && in_array($d, $exclude_list))) {
			continue;
		}

		//if current file ($d) is a directory, call package_scandirr
		if (is_dir($dir . '/' . $d)) {
			if ($absolute) {
				package_scandirr($dir . '/' . $d, $absolute, $exclude_list);
			} else {
				$list[$d] = package_scandirr($dir . '/' . $d, $absolute, $exclude_list);
			}


			//otherwise, add the file to the list
		} elseif (is_file($dir . '/' . $d) || is_link($dir . '/' . $d)) {
			if ($absolute) {
				$list[] = $dir . '/' . $d;
			} else {
				$list[] = $d;
			}
		}
	}

	return $list;
}

function fix_publisher() {
	global $mod_dir, $vars;
	$invalidPublishers = array(
		"Schmooze Com Inc",
		"Sangoma Technologies, Inc",
		"Schmooze Com, Inc.",
		"POSSA",
		"Schmooze Com, Inc",
		"Schmoozecom.com",
		"Schmooze Com Inc.",
		"FreePBX",
		"Sangoma"
	);
	$xml = simplexml_load_file($mod_dir . '/module.xml');
	$publisher = trim((string)$xml->publisher);
	if(in_array($publisher,$invalidPublishers)) {
		freepbx::outn("\tInvalid Publisher...");
		$xml->publisher = "Sangoma Technologies Corporation";
		$xml = trim(preg_replace('/^\<\?xml.*?\?\>/', '', $xml->asXML()));
		file_put_contents($mod_dir . '/module.xml', $xml);
		freepbx::out("Fixed");
	}
}

//auto-bump module version, bumps last part by defualt
function package_bump_version($mod, $pos = '') {
	global $mod_dir, $vars;
	$xml = simplexml_load_file($mod_dir . '/module.xml');
	$ver = explode('.', (string) $xml->version);

	//if $pos === true, reset it
	if ($pos === true) {
		$pos = '';
	}
	//pick last part if requested part isn't found
	if (!isset($ver[$pos - 1])) {
		$pos = count($ver);
	}
	$pos = $pos - 1; //array start at 0, but people will count from 1.

	//if we have only digits in this part, add 1
	if (ctype_digit($ver[$pos])) {
		$ver[$pos] = $ver[$pos] + 1;
	} else {//find last groupe of digits and +1 them
		$num = preg_split('/[0-9]+$/', $ver[$pos], 1);
		$replace = strrpos($ver[$pos], $num);
		$num = $num[0] + 1;
		$ver[$pos] = substr($ver[$pos], 0, $replace -1) . $num;
	}

	if ($vars['verbose']) {
		echo 'Bumping ' . $mod . 's verison to ' . implode('.', $ver) . PHP_EOL;
	}

	$version = implode('.', $ver);
	$xml->version = $version;

	//simplexml adds a xml decleration that freepbx doesnt like. Remove it.
	$xml = trim(preg_replace('/^\<\?xml.*?\?\>/', '', $xml->asXML()));

	if ($vars['debug'] || $vars['verbose']) {
		echo 'Writing to ' . $mod_dir . '/module.xml :' . PHP_EOL;
		echo $xml;
	}
	if (!$vars['debug']) {
		file_put_contents($mod_dir . '/module.xml', $xml);
	}

	return $version;
}

//update module's changelog
function package_update_changelog($mod, $msg) {
	global $mod_dir, $vars, $ver;
	$xml = simplexml_load_file($mod_dir . '/module.xml');
	$log = explode("\n", (string) $xml->changelog);

	$msg = htmlspecialchars($msg, ENT_NOQUOTES);

	//firt element is ususally blank, remove it
	array_shift($log);

	//prune to last 5 entreis
	/* If pruning is to be added it should be configurable, please leave unless making that change
	 * as Bryan suggested, we may want to have it auto-prune comments from previous versions though
	 *
	$log = array_slice($log, 0, 4);
	 */

	//if the current message is already the last, dont duplicate it
	if ($log[0] == $ver . ' ' . $msg && ($vars['verbose'] || $vars['debug'])) {
		echo 'No need to update changelog - last entry matches proposed entry';
		return true;
	}

	//add new mesage
	array_unshift($log, '*' . $ver . '*' . ' ' . $msg);

	if ($vars['verbose']) {
		echo 'Adding to ' . $mod . 's changelog: ' . $ver . ' ' . $msg;
	}

	//fold changelog array back in to xml
	$xml->changelog = "\n\t\t" . trim(implode("\n", $log)) . "\n\t";

	if ($vars['verbose']) {
		echo 'Writing to ' . $mod_dir . '/module.xml :' . PHP_EOL;
	}

	//simplexml adds a xml decleration that freepbx doesnt like. Remove it.
	$xml = trim(preg_replace('/^\<\?xml.*?\?\>/', '', $xml->asXML()));

	if ($vars['debug']) {
		echo 'Writing to ' . $mod_dir . '/module.xml :' . PHP_EOL;
		echo $xml;
	}

	if (!$vars['debug']) {
		file_put_contents($mod_dir . '/module.xml', $xml);
	}

	return true;
}

// if $duplex set to true and in debug mode, it will echo the command AND run it
function run_cmd($cmd, &$outline='', $quiet = false, $duplex = false) {
	global $vars;
	$quiet = $quiet ? ' > /dev/null' : '';

	if ($vars['debug']) {
		echo $cmd . PHP_EOL;
		if (!$duplex) {
			return true;
		}
	}
	if ($vars['verbose']) {
		$bt = debug_backtrace();
		echo PHP_EOL . '+' . $bt[0]["file"] . ':' . $bt[0]["line"] . PHP_EOL;
		echo "\t" . $cmd . PHP_EOL;
		$outline = system($cmd . $quiet, $ret_val);
	} else {
		$outline = system($cmd . $quiet, $ret_val);
	}
	return ($ret_val == 0);
}

function update_devtools() {
	$mypath = __DIR__;
	freepbx::outn("Updating devtools\n\tOpening devtools...");
	//Attempt to open the module as a git repo, bail if it's not a repo
	try {
		$repo = Git::open($mypath);
		freepbx::out("Done");
	} catch (Exception $e) {
		freepbx::out($e->getMessage());
		exit();
	}
	freepbx::outn("\tIm going to update the master branch now...");
	$stashable = $repo->add_stash();
	$repo->fetch();
	try {
		$merged = $repo->pull('origin','master');
	} catch(\Exception $e) {
		$merged = false;
	}
	if(!$merged) {
		freepbx::out("\t\t\tMerge from master to this branch failed");
		freepbx::out("devtools will not be merged!");
		exit();
	}
	if($stashable) {
		$repo->apply_stash();
		$repo->drop_stash();
	}
	freepbx::out("Done. Thanks for using FreePBX!");
}
?>
