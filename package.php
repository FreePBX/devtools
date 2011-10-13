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
	"modules:",
	"debug::",
	"checkphp::",
	'verbose::'
);
$vars = getopt('m:d::L::v::');


//set up some other settings
$vars['rver'] 		= '2.10';
$vars['fwbranch'] 	= 'branches/2.10';
$vars['fw']			= 'framework';
$vars['fw_ari']		= 'fw_ari';
$vars['fw_lang']	= 'fw_langpacks';
$vars['reldir']		= 'reldir';
$vars['svn_path']	= 'http://svn.freepbx.org';
$vars['rm_files']	= array(); //files that will be deleted after the script completes

//move cli args to longopts for clarity throught the script
//note: once we depend on 5.3, we can refactor this so that either short
//or long work. For now, short will overwrite the long
if (isset($vars['m'])) {
	$vars['modules'] = (array) $vars['m'];
	unset($vars['m']);
} else {
	$vars['modules'] = false;
}

if (isset($vars['d'])) {
	$vars['debug'] = true;
	unset($vars['d']);
} else {
	$vars['debug'] = false;
}

if (isset($vars['L'])) {
	$vars['checkphp'] = false;
	unset($vars['L']);
} else {
	$vars['checkphp'] = true;
}

if (isset($vars['v'])) {
	$vars['verbose'] =  true;
	unset($vars['L']);
} else {
	$vars['verbose'] = false;
}

//find location of md5 command
if (!$vars['md5'] = run_cmd('which md5sum')) {
	if (!run_cmd('which md5')) {
		die("no md5sum command\n");
	} else {
		$vars['md5'] = 'md5 -r';
	}
}

//ensure we have modules to package
if (!$vars['modules']) {
	die('No modules specified. Please specify them one with the -m option (use multiple switches for more than one module)');
}

//print_r($vars);

//ensure the module and relase directorys are up to date
run_cmd('svn up');
run_cmd('svn up ../../release/' . $vars['rver']);

foreach ($vars['modules'] as $mod) {
	$mod 		= trim($mod, '/');
	$tar_dir	= $mod;
	$exclude[]	= '.*';
	$files 		=
	$filename	=
	$md5		=
	$xml 		=
	$rawname	=
	$ver 		= 
	$x			= '';
	
	echo 'Packaging ' . $mod . '...' . PHP_EOL;
	if (!file_exists($mod . '/module.xml')) {
		echo $mod . '/module.xml dose not exists, ' . $mod . ' will not be built!' . PHP_EOL;
		continue;
	}
	$xml = file_get_contents($mod . '/module.xml');
	
	//test xml file and get some of its values
	list($rawname, $ver) = check_xml($mod, $xml);
	
	//dont conitunue if there is an issue with the xml
	if ($rawname == false || $ver == false) {
		continue;
	}
	
	//include module specifc hook, if present
	if (file_exists($mod . '/' . 'package_hook.php')) {
		echo 'Running ' . $mod . '/' . 'package_hook.php...' . PHP_EOL;
		
		//test include so that includes can return false and prevent further execution if it fail
		if (!include($mod . '/' . 'package_hook.php')) {
			echo '[FATAL] retrurned from ' . $mod . '/' . 'package_hook.php with an error, ' 
				. $mod . ' wont be built' . PHP_EOL;
				continue;
		}
	}
	
	//include any global hooks, if present
	if (file_exists('package_hook.php')) {
		echo 'Running ' . 'package_hook.php...' . PHP_EOL;
		
		//test include so that includes can return false and prevent further execution if it fail
		if (!include('package_hook.php')) {
			echo '[FATAL] retrurned from  package_hook.php with an error, ' 
				. $mod . ' wont be built' . PHP_EOL;
				continue;
		}
	}
	
	//test xml file and get some of its values. We did this before, but the hooks
	//may have changed something
	list($rawname, $ver) = check_xml($mod, $xml);
	
	//dont conitunue if there is an issue with the xml
	if ($rawname == false || $ver == false) {
		continue;
	}
	
	//check php files for syntax errors
	$bail = false;
	$files = scandirr($mod, true);
	foreach ($files as $f) {
		if (pathinfo($f, PATHINFO_EXTENSION) == 'php') {
			$ret_val = 0;
			system('php -l ' . $f, $ret_val);
			if ($ret_val != 0) {
				echo('syntax error detected in ' . $f . ',' .  $mod . ' won\'t be packaged' . PHP_EOL);
				$bail=true; // finish scanning all files before bailing
			}
		}
	}
	if ($bail && $vars['checkphp']) {
		echo('syntax error detecteded in ' .  $mod . ' skipping packaging going to next' . PHP_EOL);
		continue;
	}
	
	//check in any out standing files
	if (run_cmd('svn st ' . $mod . '|wc -l') > 0) {
		run_cmd('svn ci -m "Auto Check-in of any outstanding changes in ' . $mod . '" ' . $mod);
	}
	
	//set tarball name var
	$filename = $rawname . '-' . $ver . '.tgz';
	
	//build tarball
	foreach ($exclude as $ex) {
		$x .= ' --exclude="' . $ex . '"';
	}

	//if our tar path isnt were we currently are now (i.e. one level up from the module ot be packaged)
	//tell tar to change directoires (-C) to one level above
	$tar_dir_path   = explode('/', trim($tar_dir, '/'));
	$tar_dir        = (is_array($tar_dir_path) && (count($tar_dir_path) > 1))? array_pop($tar_dir_path) : $mod;
	$tar_dir_path   = (is_array($tar_dir_path) && (count($tar_dir_path) > 1)) 
					? ' -C /' . implode('/', $tar_dir_path) : '';
	run_cmd('tar zcf ' . $filename . ' ' . $tar_dir_path . ' ' . $tar_dir . $x );
	
	//update md5 sum
	list($md5) = preg_split('/\s+/', run_cmd($vars['md5'] . ' ' . $filename));
	run_cmd('sed -i "s|<md5sum>.*</md5sum>|<md5sum>' . $md5 . '</md5sum>|" ' 
			. $mod . '/module.xml');
	
	//update location
	run_cmd('sed -i "s|<location>.*</location>|<location>release/' . $vars['rver'] . '/' . $filename . '</location>|" ' 
			. $mod . '/module.xml');
	
	//move tarbal to relase dir
	run_cmd('mv ' . $filename . ' ../../release/' . $vars['rver'] . '/');
	
	//add tarball to release repository
	run_cmd('svn add ../../release/' . $vars['rver'] . '/' . $filename);
	
	//set mimetype of tarball
	run_cmd('svn ps svn:mime-type application/tgz ../../release/' . $vars['rver'] . '/' . $filename);
	
	//set latpublished property
	$lastpub = run_cmd('svn info ' . $mod . ' | grep Revision: | awk \'{print $2}\'');
	run_cmd('svn ps lastpublish ' . $lastpub . ' ' . $mod);
	
	//check in new tarball and module.xml
	run_cmd('svn ci ../../release/' . $vars['rver'] . '/' . $filename . ' ' . $mod 
					. ' -m"Module package script: ' . $rawname . ' ' . $ver . '"');
					
	//cleanup any remaining files
/*	foreach($vars['rm_files'] as $f) {
		if (file_exists($f)) {
			run_cmd('rm -rf ' . $f);
		}
	}*/
	echo $mod . ' version ' . $ver . ' has been sucsessfuly packaged!' . PHP_EOL;
	
}

/**
 * function scandirr
 * scans a directory just like scandir(), only recursively
 * returns a hierarchical array representing the directory structure
 *
 * @pram string - directory to scan
 * @pram strin - retirn absolute paths
 * @returns array
 *
 * @author Moshe Brevda mbrevda => gmail ~ com
 */
function scandirr($dir, $absolute = false) {
	$list = array();
	if ($absolute) {
		global $list;
	}
	
	
	//get directory contents
	foreach (scandir($dir) as $d) {
		
		//ignore any of the files in the array
		if (in_array($d, array('.', '..'))) {
			continue;
		}
		
		//if current file ($d) is a directory, call scandirr
		if (is_dir($dir . '/' . $d)) {
			if ($absolute) {
				scandirr($dir . '/' . $d, $absolute);
			} else {
				$list[$d] = scandirr($dir . '/' . $d, $absolute);
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

function run_cmd($cmd, $quiet = false) {
	global $vars;
	$quiet = $quiet ? ' > /dev/null' : '';

	if ($vars['debug']) {
		echo $cmd . PHP_EOL;
		return true;
	} elseif($vars['verbose']) {
		$bt = debug_backtrace();
		echo PHP_EOL . '+' . $bt[0]["file"] . ':' . $bt[0]["line"] . PHP_EOL;
		echo "\t" . $cmd . PHP_EOL;
		system($cmd . $quiet, $ret_val);
	} else {
		system($cmd . $quiet, $ret_val);
	}
	return ($ret_val == 0);
}

//test xml file for validity and extract some info from it
function check_xml($mod, $xml) {
	//check the xml script integrity
	$xml_contents = file_get_contents($mod . '/' . 'module.xml');
	$xml_loaded_contents = simplexml_load_string($xml_contents);
	if($xml_loaded_contents === FALSE) { 
		echo $mod . '/module.xml seems corrupt, ' . $mod . ' won\'t be packaged' . PHP_EOL;
		return array(false, false);
	}
	
	//check that module name is set in module.xml
	if (!preg_match('/<rawname>(.*?)<\/rawname>/', $xml, $rawname)) {
		echo $mod . '/module.xml is missing a module name, ' . $mod . ' won\'t be packaged' . PHP_EOL;
		$rawname = false;
	} else {
		$rawname = $rawname[1];
	}
	
	//check that module version is set in module.xml
	if (!preg_match('/<version>(.*?)<\/version>/', $xml, $version)) {
		echo $mod . '/module.xml is missing a version number, ' . $mod . ' won\'t be packaged' . PHP_EOL;
		$ver = false;
	} else {
		$ver = $version[1];
	}
	
	return array($rawname, $ver);
}

?>
