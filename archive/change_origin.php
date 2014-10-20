#!/usr/bin/php -q
<?php
require_once('libraries/freepbx.php');
$help = array();
$help[] = array('--directory', 'Directory Location of modules root, always assumed to be ../freepbx from this location');
$freepbx_conf = freepbx::getFreePBXConfig();
if (is_array($freepbx_conf) && !empty($freepbx_conf)) {
	foreach($freepbx_conf as $key => $value) {
		if (isset($value) && $value != '') {
			$vars[$key] = $value;
		}
	}
}
$vars['directory'] = !empty($vars['repo_directory']) ? $vars['repo_directory'] : '';
$vars['directory'] = !empty($vars['directory']) ? $vars['directory'] : dirname(dirname(__FILE__)) . '/freepbx';
$modules = glob($vars['directory'].'/*', GLOB_ONLYDIR);
foreach($modules as $mod_dir) {
	freepbx::outn("Attempting to open module ".basename($mod_dir)."...");
	try {
		$repo = Git::open($mod_dir);
		freepbx::out("Done");
	} catch (Exception $e) {
		freepbx::out($e->getMessage());
		continue;
	}
	
	$remote = $repo->get_remote_uri('origin');
	if(preg_match('/org\/freep12/i',$remote)) {
		$newuri = preg_replace('/org\/freep12/i','org/freepbx',$remote);
		$repo->update_remote('origin', $newuri);
	}
}
