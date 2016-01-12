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
 * @author bryan.walters => schmoozecom ! com
 *
 * options:
 *      run with --help for options
 *
 */

require_once('libraries/freepbx.php');


//get cli opts
$longopts = array(
	'directory::',
	'help::',
	'module::'
);
$vars = getopt('d:h::m:', $longopts);

$helpArray = array(
	array('--help', 'Show this menu and exit'),
	array('--module', 'Module to be packaged. You can use one module per --module argument (for multiples)'),
	array('--directory', 'Directory Location of modules root, always assumed to be ../freepbx from this location')
);

//if help was requested, show help and exit
if (isset($vars['h'])) {
	freepbx::out(freepbx::showHelp(basename(__FILE__), $helpArray, true));
        exit(0);
}

if(isset($vars['help'])) {
	freepbx::out(freepbx::showHelp(basename(__FILE__), $helpArray));
	exit(0);
}

//setup some other settings
$vars['directory'] = !empty($vars['directory']) ? $vars['directory'] : dirname(dirname(__FILE__)) . '/freepbx';
//Combine shortopt -m with longopt --module
$vars['m'] = (isset($vars['m'])) ? (is_array($vars['m']) ? $vars['m'] : array($vars['m'])) : array();
$vars['module'] = (isset($vars['module'])) ? (is_array($vars['module']) ? $vars['module'] : array($vars['module'])) : array();
$modules = array_merge($vars['m'], $vars['module']);

//if no modules are set, grab all of them
if (isset($modules) && empty($modules)) {
	$allmods = glob($vars['directory'].'/*', GLOB_ONLYDIR); 
	foreach($allmods as $mod) {
		$modules[] = str_replace($vars['directory'] . '/', '', $mod);
	}
}

//display our log on a per module basis, based on the last tag
foreach($modules as $module) {
	$moddir = $vars['directory'].'/'.$module;
	if (!file_exists($moddir)) {
		freepbx::out('Module '.$module.' does not exist in path: '.$vars['directory']);
       	        exit(1);
	} else {
		$repo = Git::open($moddir);
		
		freepbx::out('================================== ' . $module . ' ('.$repo->active_branch().') START =========================================');
		freepbx::out(checklog($moddir));
		freepbx::out('================================== ' . $module . ' STOP =========================================');
	}
}

/**
 * Check Log
 *
 * This function finds that last tag for the current release and shows you 
 * any changes between them
 *
 * @param The git module directory
 * @return String of `git log` output
 */
function checklog($moddir) {
	$repo = Git::open($moddir);
	$ltags = $repo->list_tags();

	if ($ltags === false) {
		return 'No Tags found!';
	}
	list($rawname, $ver, $supported) = freepbx::check_xml_file($moddir);

	//Get current module version
	preg_match('/(\d*\.\d*)\./i',$ver,$matches);
	$rver = $matches[1];

	//cycle through the tags and create a new array with relavant tags
	$tagArray = array();
	foreach ($ltags as $tag) {
		if(preg_match('/release\/(.*)/i',$tag,$matches)) {
			if (strpos($matches[1],$rver) !== false) {	
				$tagArray[] = $matches[1];
			}
		}
	}

	if (!empty($tagArray)) {
		usort($tagArray,"freepbx::version_compare_freepbx");

		$htag = array_pop($tagArray);

		$tagref = $repo->show_ref_tag($htag);
	
		return $repo->log($tagref,'HEAD');
	} 
	return;
}
?>
