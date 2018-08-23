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
	'version:',
	'directory:',
	'help::'
);
$vars = getopt('d:v:h::', $longopts);

$helpArray = array(
	array('--help', 'Show this menu and exit'),
	array('--version', 'release/15.0'),
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
$vars['version'] = !empty($vars['version']) ? $vars['version'] : (!empty($vars['v']) ? $vars['v'] : '');

if(empty($vars['version'])) {
	freepbx::out("Must Define Version!");
	exit();
}

$allmods = glob($vars['directory'].'/*', GLOB_ONLYDIR);
foreach($allmods as $mod) {
	$modules[] = str_replace($vars['directory'] . '/', '', $mod);
}

//display our log on a per module basis, based on the last tag
foreach($modules as $module) {
	$moddir = $vars['directory'].'/'.$module;
	if (!file_exists($moddir)) {
		freepbx::out('Module '.$module.' does not exist in path: '.$vars['directory']);
		exit(1);
	} else {
		try {
			$repo = Git::open($moddir);
		} catch(\Exception $e) {
			freepbx::out('Not a valid path: '.$vars['directory'].'/'.$module);
			continue;
		}
		$repo->fetch();
		$branches = $repo->list_remote_branches();

		freepbx::out('================================== ' . $module . ' START =========================================');
		if(in_array('origin/release/'.$vars['version'],$branches,true)) {
			$status = $repo->status();
			if(empty($status)) {
				freepbx::out('Found '.$vars['version'].' in this module');
				$repo->checkout('release/'.$vars['version']);
				$repo->pull('origin','release/'.$vars['version']);
				if(needsPublish($moddir)) {
					freepbx::out('Needs to be published');
					$supported = freepbx::check_xml_file($moddir)[2]['version'];
					if($supported !== $vars['version']) {
						freepbx::out('Supported version needs to be updated');
						$xml = simplexml_load_file($moddir . '/module.xml');
						$xml->supported->version = $vars['version'];
						$xml = trim(preg_replace('/^\<\?xml.*?\?\>/', '', $xml->asXML()));
						file_put_contents($moddir . '/module.xml', $xml);
					}
					system(__DIR__.'/package.php -m '.$module.' --skipunittests --publish --bump');
				} else {
					freepbx::out('Nothing to publish');
				}
			} else {
				freepbx::out('There are working changes in this module. Cant switch branches');
				print_r($status);
			}
		} else {
			freepbx::out($vars['version'].' does not exist in this module');
		}

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
function needsPublish($moddir) {
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
		if(preg_match('/release\/(.*)/i',$tag,$matches) && (strpos($matches[1],$rver) !== false)) {
			$tagArray[] = $matches[1];
		}
	}

	if (!empty($tagArray)) {
		usort($tagArray,"freepbx::version_compare_freepbx");

		$htag = array_pop($tagArray);

		$tagref = $repo->show_ref_tag($htag);

		$out = $repo->log($tagref,'HEAD');

		return !empty($out);
	}
	return true;
}
