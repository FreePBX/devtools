#!/usr/bin/env php
<?php
require_once('libraries/freepbx.php');
$help = array();
$help[] = array('--directory', 'Directory Location of modules root, always assumed to be ../freepbx from this location');
$help[] = array('--remote', 'The Remote GIT Repository name, Default is origin');

$longopts = array(
	'directory::',
	'remote',
);
$vars = getopt('d::,r', $longopts);
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

$vars['directory'] = !empty($vars['directory']) ? $vars['directory'] : dirname(dirname(__FILE__)) . '/freepbx';
$vars['remote'] = (isset($vars['remote'])) ? $vars['remote'] : 'origin';

$modules = glob($vars['directory'].'/*', GLOB_ONLYDIR);

foreach($modules as $mod_dir) {
	freepbx::outn("Attempting to open module ".basename($mod_dir)."...");
	try {
		$repo = Git::open($mod_dir);
		freepbx::out("Done");
	} catch (Exception $e) {
		freepbx::out($e->getMessage());
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}
	freepbx::outn("\tFetching remote changes (not applying)...");
	$stash = $repo->fetch();
	freepbx::out("Done");
	
	$stash = $repo->add_stash();
	if(!empty($stash)) {
		freepbx::out("\tStashing Uncommited changes..Done");
	}
	$o = $repo->log_search($greps=array('Module package script', 'Module publish script'), '%h%x09%an%x09%ad%x09%s');
	foreach($o as $log) {
		$parts = explode("\t",$log);
		if(!empty($parts[3]) && preg_match('/(\d*\.\d*)/',$parts[3],$out)) {
			if(version_compare($out[1],'2.10','ge') && version_compare($out[1],'2.13','lt')) {
				if(preg_match('/('.$out[1].'\S*)/',$parts[3],$out)) {
					$tag = str_replace(']','',$out[1]);
					$tag = str_replace('.tgz','',$tag);
					if(!$repo->tag_exist('release/'.$tag)) {
						freepbx::outn("\t\tAdding Tag " .$tag." at ".$parts[0]."...");
						//add a tag at this point in time
						try {
							$repo->add_tag('release/'.$tag,null,$parts[0]);
						} catch (Exception $e) {
							freepbx::out($e->getMessage());
							freepbx::out("Module " . $module . " will not be tagged!");
							continue;
						}
						freepbx::out("Done");
					}
				}
			}
		}
	}
	try {
		$repo->push($vars['remote'], $repo->active_branch());
	} catch (Exception $e) {
		freepbx::out($e->getMessage());
		freepbx::out("Module " . $module . " will not be tagged!");
		continue;
	}
	freepbx::out("Done");
	if(!empty($stash)) {
		freepbx::outn("\tRestoring Uncommited changes...");
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
}
?>
