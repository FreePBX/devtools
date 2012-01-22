#!/usr/bin/php -q
<?php
/*
 * Copyright 2011 by Schmooze Com., Inc.
 * By installing, copying, downloading, distributing, inspecting or using the 
 * materials provided herewith, you agree to all of the terms of use as outlined
 * in our End User Agreement which can be found and reviewed at 
 * http://freepbxdistro.org/signup.php?view=tos
 *
 * @author mbrevda@schmoozecom.com
 *
 * options:
 *		-m module name to be packaged. You can pass more than one name
 *		-d debug mode - will echo the commands and not run them
 *		-v verbosity - will echo out the commands AND run them
 *
 */

//get cli opts
$longopts = array(
	'bump::',
	'checkphp::',
	'debug::',
	'help::',
	'log::',
	'module:',
	'msg::',
	're::',
	'verbose::'
);
$vars = getopt('m:d::L::v::', $longopts);


if (isset($vars['d']) || isset($vars['L'])) {
	echo package_show_help(true);
	sleep(3);
}

//set up some other settings
$vars['rver'] 		= '2.10';
$vars['fwbranch'] 	= 'branches/2.10';
$vars['fw']			= 'framework';
$vars['fw_ari']		= 'fw_ari';
$vars['fw_lang']	= 'fw_langpacks';
$vars['svn_path']	= 'http://svn.freepbx.org';
$vars['rm_files']	= array(); //files that will be deleted after the script completes
$vars['php_-l']		= 'php -l';
$vars['php_extens']	= array('php', 'agi'); //extens to be considered as php for syntax checking
$final_status		= array();//status message to be printed after script is run

//move cli args to longopts for clarity throught the script
//note: once we depend on 5.3, we can refactor this so that either short
//or long work. For now, short will overwrite the long
//print_r($vars);
if (isset($vars['m'])) {
	$vars['module'] = (array) $vars['m'];
	sort($vars['module']);
	unset($vars['m']);
} elseif(isset($vars['module'])) {
	$vars['module'] = (array) $vars['module'];
	sort($vars['module']);
} else {
	$vars['module'] = false;
}

//set all modules if --modules was set to *
if (isset($vars['module']) && is_array($vars['module']) && in_array('*', $vars['module'])) {
	$vars['module'] = array();
	$ls = scandir(dirname(__FILE__));
	foreach($ls as $item) {
		if (strpos($item, '.') !== 0 && is_dir($item)) {
			$vars['module'][] = $item;
		}
	}
	sort($vars['module']);
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

if (isset($vars['help']) && $vars['help'] != 'help') {
	$vars['help'] = true;
} else {
	$vars['help'] = false;
}

if (isset($vars['L'])) {
	$vars['checkphp'] = false;
	unset($vars['L']);
} elseif (!isset($vars['checkphp']) || isset($vars['checkphp']) && $vars['checkphp'] != 'false') {
	$vars['checkphp'] = true;
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

$vars['svn_q'] = $vars['debug'] || $vars['verbose'] ? '' : ' --quiet ';
//set re
//move re to an array if there are commas as part of the value
/*if (isset($vars['re']) && strpos($vars['re'], ',') !== false) {
	$vars['re'] = explode(',', $vars['re']);
}*///while a nice idea, this is inconsistant with the rest of the script. use multiple --re options instead
if (isset($vars['re'])) {
	switch (true) {
		case is_array($vars['re']):
			foreach ($vars['re'] as $k => $v) {
				if ($v) {
					$vars['re'][$k] = '#' . preg_replace("/[^0-9]/", '', $v);
				}
			}
	
			$vars['re'] = 're ' . implode(', ', $vars['re']) . ' ';
			break;
		case is_string($vars['re']):
			$vars['re'] = 're #' . preg_replace("/[^0-9]/", '', $vars['re']) . ' ';
			break;
		default:
			break;
	}
} else {
	$vars['re'] = '';
}

$vars['msg'] = isset($vars['msg']) ? trim($vars['msg']) . ' ' : '';
$vars['msg'] = $vars['re'] ? $vars['re'] . '- ' . $vars['msg'] : $vars['msg'];

//if help was requested, show help and exit
if ($vars['help']) {
	echo package_show_help();
	exit();
}
//ensure we have modules to package
if (!$vars['module']) {
	die("No modules specified. Please specify at least one module" . PHP_EOL);
	echo package_show_help();
	exit();
}

/*
 * FreePBX supports devlopment with a file structure basically as followes:
 * framework/
 *			...
 *			admin/
 *				modules/ <--- svn switch'ed to the modules directory
 *
 * modules/
 *		branches/
 *			...
 *
 * While we can checkin and package a module from either directory, we can only
 * check in tarballs to modules/release/...
 * Here we attempt to auto-detect the release/ directory, and fail if we can't find it
 * NOTE: you still need to have the entire freepbx shebang in place to check in modules!
 */
if (is_dir('../../release/')) {//modules directory
	$vars['reldir'] = '../../release/' . $vars['rver'];
} elseif(is_dir('../../../../../../modules/release/')) {//trunk
		$vars['reldir'] = '../../../../../../modules/release/' . $vars['rver'];
} elseif(is_dir('../../../../../../../modules/release/')) {//framewokr branches
	$vars['reldir'] = '../../../../../../../modules/release/' . $vars['rver'];
} else {
	echo 'FATAL: release directory not found!' . PHP_EOL;
	exit();
}
//print_r($vars);exit();

//ensure the module and release directorys are up to date
run_cmd('svn up ' . $vars['svn_q'] . $vars['reldir'] . ' . ');

foreach ($vars['module'] as $mod) {
	$mod 		= trim($mod, '/');
	$mod_dir	= dirname(__FILE__) . '/' . $mod;
	$tar_dir	= $mod_dir;
	//files/dirs to be excluded from tar
	$exclude	= array();
	//argument to find for additional files/dirs to be excluded from tar
	$exclude_find_arg = '\( -iname ".*" ! -iname ".htaccess" \)';
	$files 		=
	$filename	=
	$md5		=
	$xml 		=
	$rawname	=
	$ver 		= 
	$x			= '';
	$file_scan_exclude_list = array();
	
	
	echo 'Packaging ' . $mod . '...' . PHP_EOL;
	if (!file_exists($mod_dir . '/module.xml')) {
		echo $mod_dir . '/module.xml dose not exists, ' . $mod . ' will not be built!' . PHP_EOL;
		continue;
	}

	//test xml file and get some of its values
	list($rawname, $ver) = check_xml($mod);
	
	//dont conitunue if there is an issue with the xml
	if ($rawname == false || $ver == false) {
		continue;
	}
	// Run xml script through the exact method that FreePBX currently uses. There have
	// been cases where XML is valid but this method still fails so it won't be caught
	// with the proper XML checer, better here then breaking the online repository
	//
	include_once('xml2Array.class.php');
	$parser = new xml2ModuleArray($xml);
	$xmlarray = $parser->parseAdvanced(file_get_contents($mod_dir . '/module.xml'));

	//bump version if requested, and reset $ver
	if ($vars['bump']) {
		package_bump_version($mod, $vars['bump']);
		//test xml file and get some of its values
		list($rawname, $ver) = check_xml($mod);
		$vars['log'] = true;
	}
	
	//add changelog if requested
	if ($vars['log']) {
		$msg = $vars['msg'] ? $vars['msg'] : 'Packaging of ver ' . $ver;
		package_update_changelog($mod, $msg);
	}
	
	//include module specifc hook, if present
	if (file_exists($mod_dir . '/' . 'package_hook.php')) {
		if ($vars['debug'] || $vars['verbose']) {
			echo 'Running ' . $mod_dir . '/' . 'package_hook.php...' . PHP_EOL;
		}
		
		//test include so that includes can return false and prevent further execution if it fail
		if (!include($mod_dir . '/' . 'package_hook.php')) {
			$final_status[$mod] = '[FATAL] retrurned from ' . $mod_dir . '/' . 'package_hook.php with an error, ' 
				. $mod . ' wont be built' . PHP_EOL;
			echo $final_status[$mod];
			continue;
		}
	}
	
	//include any global hooks, if present
	if (file_exists('package_hook.php')) {
		if ($vars['debug'] || $vars['verbose']) {
			echo 'Running ' . 'package_hook.php...' . PHP_EOL;
		}
		
		//test include so that includes can return false and prevent further execution if it fail
		if (!include('package_hook.php')) {
			$final_status[$mod] = '[FATAL] retrurned from  package_hook.php with an error, ' 
				. $mod . ' wont be built' . PHP_EOL;
			 echo $final_status[$mod];
			continue;
		}
	}
	
	//test xml file and get some of its values. We did this before, but the hooks
	//may have changed something
	list($rawname, $ver) = check_xml($mod);
	
	//dont conitunue if there is an issue with the xml
	if ($rawname == false || $ver == false) {
		continue;
	}
	
	//check php files for syntax errors if requested
	if ($vars['checkphp']) {	
		//get list of files
		$files = package_scandirr($tar_dir, true, $file_scan_exclude_list);
		foreach ($files as $f) {
			if (in_array(pathinfo($f, PATHINFO_EXTENSION), $vars['php_extens'])) {
				if (!run_cmd($vars['php_-l'] . ' ' . $f, $outline, (!$vars['debug'] && !$vars['verbose']), true)) {
					//add errors to array
					$syntaxt_errors[] = 'syntax error detected in ' . $f . ', ' .  $mod . ' won\'t be packaged' . PHP_EOL;
				}
			}
		}
		unset($files, $list);
		
		if (isset($syntaxt_errors)) {
			$final_status[$mod] = implode(PHP_EOL, $syntaxt_errors);
			echo $final_status[$mod];
			continue;
		}
	}

	
	//check in any out standing files
	run_cmd('svn ci ' . $vars['svn_q'] . '-m "[Auto Checking in outstanding changes in ' 
			. $mod . '] ' . $vars['msg'] . '" ' . $mod_dir);

	
	//set tarball name var
	$filename = $rawname . '-' . $ver . '.tgz';
	
	//add to exclude array, all '.*' except .htaccess
	$exclude_raw = array();
	exec('find ' . $mod_dir . ' ' .  $exclude_find_arg , $exclude_raw, $ret);

	if ($ret) {
		die("something went wrong with fund command looking for .* files to exclude");
	}
	foreach ($exclude_raw as $name) {
		$exclude[] = basename($name);
	}
	$exclude = array_unique($exclude);
	if ($vars['verbose'] || $vars['debug']) {
		echo "excluding patterns:\n";
		//print_r($exclude);
	}

	//build tarball
	foreach ($exclude as $ex) {
		$x .= ' --exclude="' . $ex . '"';
	}

	//if our tar path isnt were we currently are now (i.e. one level up from the module ot be packaged)
	//tell tar to change directoires (-C) to one level above
	$tar_dir_path   = explode('/', trim($tar_dir, '/'));
	$tar_dir        = (is_array($tar_dir_path) && (count($tar_dir_path) > 1))? array_pop($tar_dir_path) : $mod_dir;
	$tar_dir_path   = (is_array($tar_dir_path) && (count($tar_dir_path) > 1)) 
					? ' -C /' . implode('/', $tar_dir_path) : '';
	run_cmd('tar zcf ' . $filename . ' ' . $x . ' ' . $tar_dir_path . ' ' . $tar_dir);
	
	//update md5 sum
	$module_xml = file_get_contents($mod_dir . '/' . 'module.xml');
	if(file_exists($filename)) {
		$md5 = md5_file($filename);
		$module_xml = preg_replace('/<md5sum>(.*)<\/md5sum>/i','<md5sum>'.$md5.'</md5sum>',$module_xml);
	} else {
		echo "No Tarball Package found (in debug mode?)" . PHP_EOL;
	}
	
	//update location
	if(file_exists($filename)) {
		$module_xml = preg_replace('/<location>(.*)<\/location>/i','<location>release/' . $vars['rver'] . '/' . $filename . '</location>',$module_xml);
	}

	file_put_contents($mod_dir . '/' . 'module.xml', $module_xml);

	
	//move tarbal to relase dir
	run_cmd('mv ' . $filename . ' ' . $vars['reldir'] . '/');
	
	//add tarball to release repository
	run_cmd('svn add ' . $vars['reldir'] . '/' . $filename . ' ' . $vars['svn_q']);
	
	//set mimetype of tarball
	run_cmd('svn ps svn:mime-type application/tgz ' . $vars['reldir'] . '/' . $filename . ' ' . $vars['svn_q']);
	
	//set latpublished property
	run_cmd('svn ps lastpublish ' . $vars['svn_q'] 
			. '`svn info ' . $mod_dir . ' | grep Revision: | awk \'{print $2}\'`' 
			. ' ' . $mod_dir);
	
	// appears we need to do an svn up here or it fails, maybe because of the propset above?
	//Lets reaserch this more, SHMZ hasn't found this nesesary -MB
	//+1 although I dont (either) get why -MB
	run_cmd('svn up ' . $vars['svn_q'] . $vars['reldir'] . '/' . $filename . ' ' . $mod_dir);

	//check in new tarball and module.xml
	run_cmd('svn ci ' . $vars['svn_q'] . $mod_dir . ' ' . $vars['reldir'] . '/' . $filename 
					. ' -m"[Module package script: ' . $rawname . ' ' . $ver . '] ' . $vars['msg'] . '"');
					
	//cleanup any remaining files
	foreach($vars['rm_files'] as $f) {
		if (file_exists($f)) {
			run_cmd('rm -rf ' . $f);
		}
	}
	$final_status[$mod] = $mod . ' version ' . $ver . ' has been sucsessfuly packaged!' . PHP_EOL;
	echo $final_status[$mod];
	
}

//print report
echo PHP_EOL . PHP_EOL . PHP_EOL;
echo 'Package Script Report:' . PHP_EOL;
echo '---------------------' . PHP_EOL;
foreach ($final_status as $mod => $status) {
	echo $status;
}

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
		if (in_array($d, array('.', '..', '.svn')) || (!empty($exclude_list) && in_array($d, $exclude_list))) {
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
	$log = array_slice($log, 0, 4);
	
	//if the current message is already the last, dont duplicate it
	if ($log[0] == $ver . ' ' . $msg) {
		if ($vars['verbose'] || $vars['debug']) {
			echo 'No need to update changelag - last entry matches proposed entry';
			return true;
		}
	}
	
	//add new mesage
	array_unshift($log, $ver . ' ' . $msg);
	
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
		echo $mod_dir . '/module.xml seems corrupt, ' . $mod . ' won\'t be packaged' . PHP_EOL;
		return array(false, false);
	}
	
	//check that module name is set in module.xml
	$rawname = (string) $xml->rawname;
	if (!$rawname) {
		echo $mod_dir . '/module.xml is missing a module name, ' . $mod . ' won\'t be packaged' . PHP_EOL;
		$rawname = false;
	}
	
	//check that module version is set in module.xml
	$version = (string) $xml->version;
	if (!$version) {
		echo $mod_dir . '/module.xml is missing a version number, ' . $mod . ' won\'t be packaged' . PHP_EOL;
		$version = false;
	}

	return array($rawname, $version);
}

//show help menu
function package_show_help($short = false) {
	$final = '';
	$ret[] = 'Package.php';
	$ret[] = '-----------';
	$ret[] = '';
	if ($short) {
		$ret[] = 'SHORT OPS HAVE BEEN DEPRICATED - PLEASE USE ONLY LONG OPTS!';
	}
	$ret[] = 'Short options MUST come after all the long options, or the long options will be ignored';
	$ret[] = '';
	
	//args
	$ret[] = array('--bump', 'Bump a modules version. You can specify the "octet" by adding a position '
				. 'I.e. --bump=2 will turn 3.4.5.6 in to 3.5.5.6. Leaving the position blank will bump the last "octet"');
	$ret[] = array('--debug = false', 'Debug only - just run through the command but don\'t make any changes');
	$ret[] = array('--checkphp = true', 'Run PHP syntaxt check on php files (php -l <file name>)');
	$ret[] = array('--help', 'Show this menu and exit');
	$ret[] = array('--log', 'Update module.xml\'s changelog.');
	$ret[] = array('--module', 'Module to be packaged. You can use one module per --module argument (for multiples), or * for all');
	$ret[] = array('--msg', 'Optional commit message.');
	$ret[] = array('--re', 'A ticket number to be referenced in all checkins (i.e. "re #627...")');
	$ret[] = array('--verbose', 'Run with extra verbosity and print each command before it\'s executed');
	
	$ret[] = '';
	
	//generate formated help message
	foreach ($ret as $r) {
		if (is_array($r)) {
			//pad the option
			$option = '  ' . str_pad($r[0], 20);
			
			//explode the definition to manageable chunks
			$def = explode('§', wordwrap($r[1], 55, "§", true));
			 
			//and pad the with whitespace 20 chars to the left stating from the second line
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
?>
