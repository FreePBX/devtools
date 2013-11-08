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
$help = array();
$help[] = array('--bump', 'Bump a modules version. You can specify the "octet" by adding a position '
			. 'I.e. --bump=2 will turn 3.4.5.6 in to 3.5.5.6. Leaving the position blank will bump the last "octet"');
$help[] = array('--debug=false', 'Debug only - just run through the command but don\'t make any changes');
$help[] = array('-c', 'Prompt for FreePBX.org Credentials');
$help[] = array('--help', 'Show this menu and exit');
$help[] = array('--log', 'Update module.xml\'s changelog. [Done by default if bumping]');
$help[] = array('--module', 'Module to be packaged. You can use one module per --module argument (for multiples)');
$help[] = array('--directory', 'Directory Location of modules root, always assumed to be ../freepbx from this location');
$help[] = array('--msg', 'Optional commit message.');
$help[] = array('--re', 'A ticket number to be referenced in all checkins (i.e. "re #627...")');
$help[] = array('--verbose', 'Run with extra verbosity and print each command before it\'s executed');
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
	'verbose::'
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
$vars['git_ssh'] = 'ssh://git@git.freepbx.org/freep12/';
$vars['php_-l']	= 'php -l';
//TODO: This should be settable by the developer, again maybe keeping the information in a ,freepbxconfig?
$vars['remote'] = (isset($vars['remote'])) ? $vars['remote'] : 'origin';
$vars['php_extens'] = array('php', 'agi'); //extens to be considered as php for syntax checking
$vars['directory'] = !empty($vars['directory']) ? $vars['directory'] : dirname(dirname(__FILE__)) . '/freepbx';
$modules = array();
$final_status = array();//status message to be printed after script is run

//Combine shortopt -m with longopt --module
$vars['m'] = (isset($vars['m'])) ? (is_array($vars['m']) ? $vars['m'] : array($vars['m'])) : array();
$vars['module'] = (isset($vars['module'])) ? (is_array($vars['module']) ? $vars['module'] : array($vars['module'])) : array();
$modules = array_merge($vars['m'], $vars['module']);
//cleaup
unset($vars['m']);
unset($vars['module']);

foreach($modules as $module) {
	if(!file_exists($vars['directory'].'/'.$module)) {
		freepbx::out('Module '.$module.' does not exist in path: '.$vars['directory']);
		exit(1);
	}
}

if (isset($vars['bump']) && $vars['bump'] != 'false') {
	$vars['bump'] = ctype_digit($vars['bump']) ? $vars['bump'] : true;
} else {
	$vars['bump'] = false;
}

if (isset($vars['d'])) {
	$vars['debug'] = true;
	unset($vars['d']);
} elseif (isset($vars['debug']) && $vars['debug'] != 'false') {
	$vars['debug'] = true;
} else {
	$vars['debug'] = false;
}

if (isset($vars['log']) && $vars['log'] != 'log') {
	$vars['log'] = true;
} else {
	$vars['log'] = false;
}

if (isset($vars['v'])) {
	$vars['verbose'] = true;
	unset($vars['L']);
} elseif (isset($vars['verbose']) && $vars['verbose'] != 'false') {
	$vars['verbose'] = true;
} else {
	$vars['verbose'] = false;
}

$vars['git_q'] = $vars['debug'] || $vars['verbose'] ? '' : ' --quiet ';

//check to see if this an interactive session
exec('test -t 0', $ret, $vars['interactive']);
$vars['interactive'] = !$vars['interactive'];

//set publish to true if requested, but always false if the file doesnt exist
$vars['publish'] = (isset($vars['publish']) && file_exists(dirname(__FILE__) . '/pkg_publish.php')) ? true : false;

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
	$vars['git_http']	= 'http://'.$vars["username"].'@git.freepbx.org/scm/freep12/';
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
foreach ($modules as $module) {
	//this isnt really used
	$file_scan_exclude_list = array();
	freepbx::out("Processing ".$module."...");
	$mod_dir = $vars['directory'].'/'.$module;

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

	//check to make sure the origin is set to FreePBX
	$oi = $repo->show_remote($vars['remote']);
	freepbx::outn("\t\tChecking To Make Sure Origin is set to FreePBX.org...");
	if($oi['Push  URL'] != $vars['git_ssh'] . $module . '.git') {
		//TODO: maybe set the correct origin?
		//we could set it here? git remote set-url origin git://new.url.here
		freepbx::out("Set Incorrectly, your origin is set to " . $oi['Push  URL']);
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}
	freepbx::out("Set Correctly");

	//Check to see if we are on the correct release branch
	freepbx::outn("\t\tChecking if on Release Branch...");
	$activeb = $repo->active_branch();
	//get ready to cross-compare the remote and local branches
	$lbranches = $repo->list_branches();
	$rbranches = $repo->list_remote_branches();
	//get module root version
	preg_match('/(\d*\.\d*)\./i',$ver,$matches);
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

	//make sure the version inside the module matches the release version we are on
	//(the first 2 version IDs)
	if($bver != $mver) {
		freepbx::out("Module Version of ".$mver." does not match release version of ".$bver);
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}
	
	//now check to make sure the xml is valid
	freepbx::outn("\tChecking Module XML...");
	//test xml file and get some of its values
	list($rawname, $ver, $supported) = check_xml($module);
	//dont continue if there is an issue with the xml
	if ($rawname == false || $ver == false || $supported == false) {
		freepbx::out('module.xml is missing rawname or version or is corrupt');
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}
	freepbx::out("Done");

	//now check all release branches and make sure our supported version isn't doubled
	//Stash locally uncommited changes if we need to do so before switching branches
	freepbx::out("\t\tChecking to make sure supported version isn't doubled...");
	$stash = $repo->add_stash();
	if(!empty($stash)) {
		freepbx::out("\t\t\tStashing Uncommited changes..Done");
	}
	//run through remote branches
	foreach($rbranches as $branch) {
		if(preg_match('/release\/(.*)/i',$branch,$matches) && $branch != $vars['remote'].'/'.$activeb) {
			freepbx::outn("\t\t\tChecking ".$branch."...");
			//checkout remote branch, headless mode!
			$repo->checkout($branch);
			$bxml = check_xml($module);
			//check to make sure we aren't higher than the ones higher than us
			//and that we arent lower than the ones lower than us
			$type = version_compare($bver, $matches[1], '>') ? '<=' : '>=';
			if (!empty($bxml[2]['version']) && version_compare($supported['version'], $bxml[2]['version'], $type)) {
				$ntype = ($type == '<=') ? 'higher' : 'lower';
				freepbx::out("Supported version of branch ".$branch."(".$bxml[2]['version'].") is ".$ntype." than branch ".$activeb."(".$supported['version'].")");
				//errored so checkout original branch
				$repo->checkout($activeb);
				//restore stash if we need to do so
				if(!empty($stash)) {
					freepbx::outn("\t\t\tRestoring Uncommited changes...");
					try {
						$repo->apply_stash();
						$repo->drop_stash();
						freepbx::out("Done");
					} catch (Exception $e) {
						freepbx::out("Failed to restore stash!, Please check your directory");
					}
				}
				freepbx::out("Module " . $module . " will not be tagged!");
				continue(2);
			}
			if(!empty($bxml[2]['version'])) {
				$ntype = ($type == '>=') ? 'higher' : 'lower';
				freepbx::out("Passed (Supported Version in this branch is ".$ntype.")");
			} else {
				freepbx::out("");
			}
		}
	}
	//checkout original branch
	$repo->checkout($activeb);
	//restore stash if needed
	if(!empty($stash)) {
		freepbx::outn("\t\t\tRestoring Uncommited changes...");
		try {
			$repo->apply_stash();
			$repo->drop_stash();
		} catch (Exception $e) {
			freepbx::out("Failed to restore stash!, Please check your directory");
			freepbx::out("Module " . $module . " will not be tagged!");
			continue;
		}
		freepbx::out("Done");
	}
	// Run xml script through the exact method that FreePBX currently uses. There have
	// been cases where XML is valid but this method still fails so it won't be caught
	// with the proper XML checer, better here then breaking the online repository
	// -Philippe L.
	$parser = new xml2ModuleArray();
	$xmlarray = $parser->parseAdvanced(file_get_contents($mod_dir . '/module.xml'));

	//bump version if requested, and reset $ver
	if ($vars['bump'] && !$vars['debug']) {
		freepbx::outn("\tBumping Version as Requested...");
		package_bump_version($module, $vars['bump']);
		freepbx::out("Done");
		$vars['log'] = true;
	}

	//add changelog if requested
	if ($vars['log'] && !$vars['debug']) {
		freepbx::outn("\tUpdating Changelog...");
		$msg = $vars['msg'] ? $vars['msg'] : 'Packaging of ver ' . $ver;
		package_update_changelog($module, $msg);
		freepbx::out("Done");
	}

	//Check XML File one more time to be safe
	freepbx::outn("\tChecking Modified Module XML...");
	//test xml file and get some of its values
	list($rawname, $ver, $supported) = check_xml($module);
	//dont continue if there is an issue with the xml
	if ($rawname == false || $ver == false || $supported == false) {
		freepbx::out('module.xml has gotten corrupt');
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}
	freepbx::out("Done");

	//check php files for syntax errors
	//left on regardless of phpcheck.. for now
	freepbx::outn("\tChecking for PHP Syntax Errors...");
	$files = package_scandirr($mod_dir, true, $file_scan_exclude_list);
	foreach ($files as $f) {
		if (in_array(pathinfo($f, PATHINFO_EXTENSION), $vars['php_extens'])) {
			if (!run_cmd($vars['php_-l'] . ' ' . $f, $outline, (!$vars['debug'] && !$vars['verbose']), true)) {
				//add errors to array
				$syntaxt_errors[] = 'syntax error detected in ' . $f . PHP_EOL;
			}
		}
	}
	unset($files);

	//TODO: clean up unused portions of module.xml at this stage: md5sum,location?
	//cleanup_xml_junk();

	if (isset($syntaxt_errors)) {
		$final_status[$mod] = implode(PHP_EOL, $syntaxt_errors);
		freepbx::out("\t".$final_status[$mod]);
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}
	freepbx::out("There are no errors");

	//GIT Processing here
	freepbx::out("\tRunning Git...");
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
	freepbx::outn("\t\tPushing to Origin...");
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

	$xml->version = implode('.', $ver);

	//simplexml adds a xml decleration that freepbx doesnt like. Remove it.
	$xml = trim(preg_replace('/^\<\?xml.*?\?\>/', '', $xml->asXML()));

	if ($vars['debug'] || $vars['verbose']) {
		echo 'Writing to ' . $mod_dir . '/module.xml :' . PHP_EOL;
		echo $xml;
	}
	if (!$vars['debug']) {
		file_put_contents($mod_dir . '/module.xml', $xml);
	}

	return true;
}

//update module's changelog
function package_update_changelog($mod, $msg) {
	global $mod_dir, $vars, $ver;
	$xml = simplexml_load_file($mod_dir . '/module.xml');
	$log = explode("\n", (string) $xml->changelog);

	//firt element is ususally blank, remove it
	array_shift($log);

	//prune to last 5 entreis
	/* If pruning is to be added it should be configurable, please leave unless making that change
	 * as Bryan suggested, we may want to have it auto-prune comments from previous versions though
	 *
	$log = array_slice($log, 0, 4);
	 */

	//if the current message is already the last, dont duplicate it
	if ($log[0] == $ver . ' ' . $msg) {
		if ($vars['verbose'] || $vars['debug']) {
			echo 'No need to update changelog - last entry matches proposed entry';
			return true;
		}
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

//test xml file for validity and extract some info from it
function check_xml($mod) {
	global $mod_dir;
	//check the xml script integrity
	$xml = simplexml_load_file($mod_dir . '/' . 'module.xml');
	if($xml === FALSE) {
		freepbx::outn('module.xml seems corrupt');
		return array(false, false);
	}

	//check that module name is set in module.xml
	$rawname = (string) $xml->rawname;
	if (!$rawname) {
		freepbx::outn('module.xml is missing a module name');
		$rawname = false;
	}

	//check that module version is set in module.xml
	$version = (string) $xml->version;
	if (!$version) {
		freepbx::outn('module.xml is missing a version number');
		$version = false;
	}

	//check that module version is set in module.xml
	$supported = (array) $xml->supported;
	if (!$supported) {
		freepbx::outn('module.xml is missing supported tag');
		$supported = false;
	}

	return array($rawname, $version, $supported);
}