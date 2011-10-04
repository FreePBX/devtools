#!/usr/bin/php -q
<?php


//get cli opts
$longopts = array(
	"modules:",
	"debug::",
	"checkphp::",
	'verbose::'
);
$vars = getopt('m:d::L::v::');

//move cli args to longopts for clarity throught the script
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

//set up some other settings
$vars['rver'] 			= '2.10';
$vars['fwbranch'] 		= 'branches/2.10';
$vars['fw']				= 'framework';
$vars['fw_ari']			= 'fw_ari';
$vars['fw_langpacks']	= 'fw_langpacks';
$vars['reldir']			= 'reldir';
$vars['svn_path']		= 'http://svn.freepbx.org/freepbx';

//ensure we have modules to package
if (!$vars['modules']) {
	die('No modules specified. Please specify them one with the -m option (use multiple switches for more than one module)');
}
//print_r($vars);
//ensure the directory is up to date
run_cmd('svn up');

foreach ($vars['modules'] as $mod) {
	$mod 		= trim($mod, '/');
	$tar_dir	= $mod;
	$exclude	=
	$files 		=
	$filename	=
	$md5		=
	$xml 		=
	$rawname	=
	$ver 		= '';
	
	echo 'Packaging ' . $mod . '...' . PHP_EOL;
	$xml = file_get_contents($mod . '/module.xml');
	
	//check the xml script integrity
	include_once('xml2Array.class.php');
	$parser = new xml2ModuleArray($xml);
	$xmlarray = $parser->parseAdvanced($xml);
	
	//include module specifc hook, if present
	if (file_exists($mod . '/' . 'package_hook.php')) {
		echo 'Running ' . $mod . '/' . 'package_hook.php...' . PHP_EOL;
		include($mod . '/' . 'package_hook.php');
	}
	
	//include any global hooks, if present
	if (file_exists('package_hook.php')) {
		echo 'Running ' . 'package_hook.php...' . PHP_EOL;
		include('package_hook.php');
	}
	
	//TODO: not sure how to detect a broken xml --MB
	if (!true) {
		echo $mod . '/module.xml seems corrupt, ' . $mod . ' won\'t be packaged' . PHP_EOL;
		continue;
	}
	
	//check that module name is set in module.xml
	if (!preg_match('/<rawname>(.*?)<\/rawname>/', $xml, $rawname)) {
		echo $mod . '/module.xml is missing a module name, ' . $mod . ' won\'t be packaged' . PHP_EOL;
		continue;
	} else {
		$rawname = $rawname[1];
	}
	
	//check that module version is set in module.xml
	if (!preg_match('/<version>(.*?)<\/version>/', $xml, $version)) {
		echo $mod . '/module.xml is missing a version number, ' . $mod . ' won\'t be packaged' . PHP_EOL;
		continue;
	} else {
		$ver = $version[1];
	}
	
	//check php files for syntax errors
	if ($vars['checkphp']) {
		$files = scandirr($mod, true);
		foreach ($files as $f) {
			if (pathinfo($f, PATHINFO_EXTENSION) == 'php') {
				if (!system('php -l ' . $f)) {
					echo('syntaxt error detected in ' . $f . ',' .  $mod . ' won\'t be packaged' . PHP_EOL);
					continue 2;
				}
			}
		}
	}
	
	//check in out standing files
	if (run_cmd('svn st ' . $mod . '|wc -l') > 0) {
		run_cmd('svn ci -m "Auto Check-in of any outstanding changes in ' . $mod . '" ' . $mod);
	}
	
	//set tarball name var
	$filename = $rawname . '-' . $ver . '.tgz';
	
	//build tarball
	run_cmd('tar zcf ' . $filename . ' ' . $tar_dir . ' --exclude ".*" ' . $exclude . ' -C ' . $tar_dir);
	
	//update md5 sum
	list($md5) = preg_split('/\s+/', run_cmd($vars['md5'] . ' ' . $filename));
	run_cmd('sed -i "s|<md5sum>.*</md5sum>|<md5sum>' . $md5 . '</md5sum>|" ' 
			. $mod . '/module.xml');
	
	//update location
	run_cmd('sed -i "s|<location>.*</location>|<location>release/' . $vars['rver'] . '/' . $filename . '</location>|" ' 
			. $mod . '/module.xml');
	
	//move tarbal to relase dir
	run_cmd('mv ' . $filename . ' ../../release/' . $vars['rver']);
	
	//add tarball to repository
	run_cmd('svn add ../../release/' . $vars['rver'] . '/' . $filename);
	
	//set mietype of tarball
	run_cmd('svn ps svn:mime-type application/tgz ../../release/' . $vars['rver'] . '/' . $filename);
	
	//set latpublished property
	$lastpub = run_cmd('svn info ' . $mod . ' | grep Revision: | awk \'{print $2}\'');
	run_cmd('svn ps lastpublish ' . $lastpub . ' ' . $mod);
	
	//check in new tarball and module.xml
	run_cmd('svn ci ../../release/' . $vars['rver'] . '/' . $filename . ' ' . $mod 
					. ' -m"Module package script: ' . $rawname . ' ' . $ver . '"');
					
	
	echo $mod . ' version ' . $ver . ' has ben sucsessfuly packaged!' . PHP_EOL;
	
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

function run_cmd($cmd, $quite = false) {
	global $vars;
	$quite = $quite ? ' > /dev/null' : '';

	if ($vars['debug']) {
		echo $cmd . PHP_EOL;
		return true;
	} elseif($vars['verbose']) {
		echo '+ ' . $cmd . PHP_EOL;
		return system($cmd . $quite);
	} else {
		return system($cmd . $quite);
	}
}
?>