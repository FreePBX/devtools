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
* @author andrew ! nagy => the159 ! com
*
* options:
*	run with --help for options
*
*/
require_once('libraries/freepbx.php');
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
	array('--updatemaster', ''),
	array('-m', ''),
);
$longopts  = array(
	"help",
	"module:",
	"updatemaster:"
);
$options = getopt("m:",$longopts);
if(empty($options) || isset($options['help'])) {
	freepbx::showHelp('merge.php',$help);
	exit(0);
}

$module = !empty($options['module']) ? $options['module'] : (!empty($options['m']) ? $options['m'] : "");

if(empty($module)) {
	die('Undefined Module!');
}
if(!file_exists($vars['repo_directory'].'/'.$module)) {
	die('Cant find '.$vars['repo_directory'].'/'.$module);
}

$repodir = $vars['repo_directory'].'/'.$module;

switch(true) {
	case isset($options['updatemaster']):
		FreePBX::refreshRepo($repodir);
		$repo = Git::open($repodir);
		$moduleMasterXmlString = $repo->show('origin/master','module.xml');
		$masterXML = simplexml_load_string($moduleMasterXmlString);
		try {
			$moduleBranchXmlString = $repo->show('origin/release/'.$options['updatemaster'],'module.xml');
		} catch(\Exception $e) {
			die($e->getMessage());
		}
		$branchXML = simplexml_load_string($moduleBranchXmlString);
		if(!freepbx::version_compare_freepbx((string)$masterXML->supported->version, (string)$branchXML->supported->version, "<=")) {
			die("Master is on a higher or equal supported version than ".$options['updatemaster']."\n");
		}
		if(freepbx::version_compare_freepbx((string)$masterXML->version, (string)$branchXML->version, ">")) {
			die("Master is a higher (".(string)$masterXML->version.") version than this release (".(string)$branchXML->version.")? Scary? Aborting\n");
		}
		if(freepbx::version_compare_freepbx((string)$masterXML->version, (string)$branchXML->version, "=")) {
			die("Master IS already this version\n");
		}
		freepbx::outn("Attempting to merge release/".$options['updatemaster']." into master...");
		$repo->checkout("master");
		$merged = $repo->pull("origin","release/".$options['updatemaster']);
		if(!$merged) {
			freepbx::out("\t\t\tMerge from release/".$options['updatemaster']." into master failed");
			freepbx::out("Module " . $module . " will not be tagged!");
			continue;
		}
		$repo->push("origin", "master");
		freepbx::out("Done");
		freepbx::outn("Checking you out into release/".$options['updatemaster']."...");
		$repo->checkout("release/".$options['updatemaster']);
		freepbx::out("Done");
	break;
	default:
		freepbx::showHelp('merge.php',$help);
		exit(0);
	break;
}
