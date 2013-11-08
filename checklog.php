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
$vars = getopt('d::h::m::', $longopts);

//if help was requested, show help and exit
if (isset($vars['h'])) {
	freepbx::out(checklog_show_help(true));
        exit(0);
}

if(isset($vars['help'])) {
	freepbx::out(checklog_show_help());
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
	print_r($glob);
}

//display our log on a per module basis, based on the last tag
foreach($modules as $module) {
	$moddir = $vars['directory'].'/'.$module;
	if (!file_exists($moddir)) {
		freepbx::out('Module '.$module.' does not exist in path: '.$vars['directory']);
       	        exit(1);
	} else {
		freepbx::out('================================== ' . $module . ' START =========================================');
		echo checklog($moddir);
		freepbx::out('================================== ' . $module . ' STOP =========================================');
	}
}

//show help menu
function checklog_show_help($short = false){
	$final = '';
	$ret[] = 'Checklog.php';
        $ret[] = '-----------';
        $ret[] = '';
        if ($short) {
                $ret[] = 'SHORT OPS HAVE BEEN DEPRICATED - PLEASE USE ONLY LONG OPTS!';
        }

        //args
        $ret[] = array('--help', 'Show this menu and exit');
        $ret[] = array('--module', 'Module to be packaged. You can use one module per --module argument (for multiples)');
        $ret[] = array('--directory', 'Directory Location of modules root, always assumed to be ../freepbx from this location');

        $ret[] = '';

	//generate formated help message
        foreach ($ret as $r) {
                if (is_array($r)) {
                        //pad the option
                        $option = '  ' . str_pad($r[0], 20);

                        //explode the definition to manageable chunks
                        $def = explode('§', wordwrap($r[1], 55, "§", true));

                        //and pad the def with whitespace 20 chars to the left stating from the second line
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

	list($rawname, $ver, $supported) = check_xml($moddir);

	//Get current module version
	preg_match('/(\d*\.\d*)\./i',$ver,$matches);
	$mver = $matches[1];

	//Format the module version to 2 decimals so we know the release
	$rver = number_format($mver,2);

	//cycle through the tags and create a new array with relavant tags
	foreach ($ltags as $tag) {
		if(preg_match('/release\/(.*)/i',$tag,$matches)) {
			if (strpos($matches[1],$rver) !== false) {	
				$tagArray[] = $matches[1];
			}
		}
	}

	natsort($tagArray);

	$htag = array_pop($tagArray);

	$tagref = $repo->show_ref_tag($htag);
	
	return $repo->log($tagref,'HEAD');
}

//test xml file for validity and extract some info from it
//TODO: Make this a helper file as we use it elsewhere
function check_xml($mod = null) {
	if (isset($mod)) {
		$mod_dir = $mod;
	} else {
        	global $mod_dir;
	}

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
