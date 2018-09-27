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
* @author andrew ! nagy => the159 ! com
*
* options:
*	run with --help for options
*
*/
require_once('libraries/freepbx.php');
require_once 'vendor/autoload.php';
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
	array('--updatemasterfromrelease=<release>', 'Release branch to merge into master, eg 14.0'),
	array('--module=<module>', 'The module name, ed asteriskinfo'),
	array('--all', 'Process all modules from release directory'),
	array('--start=<module>', 'When running --all start on <module>')
);
$longopts  = array(
	"help",
	"module:",
	"updatemasterfromrelease:",
	"all",
	"start:"
);
$options = getopt("",$longopts);
if(empty($options) || isset($options['help'])) {
	freepbx::showHelp('merge.php',$help);
	exit(0);
}

$module = !empty($options['module']) ? $options['module'] : (!empty($options['m']) ? $options['m'] : "");

if(!file_exists($vars['repo_directory'].'/'.$module)) {
	die('Cant find '.$vars['repo_directory'].'/'.$module);
}

switch(true) {
	case isset($options['updatemasterfromrelease']):
		switch(true) {
			case isset($options['module']):
				if(empty($module)) {
					die('Undefined Module!');
				}
				mergeRepo($vars, $module, $options['updatemasterfromrelease']);
			break;
			case isset($options['all']):
				$run = empty($options['start']);
				foreach(glob($vars['repo_directory']."/*", GLOB_ONLYDIR) as $dir) {
					$module = basename($dir);
					if($module === 'framework') {
						freepbx::out("Skipping framework. Please run with --module=framework instead");
						continue;
					}
					if(!$run && $module !== $options['start']) {
						freepbx::out("Skipping ".$module);
						continue;
					} elseif(!$run && $module === $options['start']) {
						$run = true;
					}
					mergeRepo($vars, $module, $options['updatemasterfromrelease']);
				}
			break;
			default:
				freepbx::showHelp('merge.php',$help);
				exit(0);
			break;
		}
	break;
	default:
		freepbx::showHelp('merge.php',$help);
		exit(0);
	break;
}

function mergeRepo($vars, $module, $release) {
	$repodir = $vars['repo_directory'].'/'.$module;
	FreePBX::refreshRepo($repodir);
	try {
		$repo = Git::open($repodir);
	} catch(\Exception $e) {
		freepbx::out($e->getMessage());
		return;
	}
	$moduleMasterXmlString = $repo->show('origin/master','module.xml');
	$masterXML = simplexml_load_string($moduleMasterXmlString);
	try {
		$moduleBranchXmlString = $repo->show('origin/release/'.$release,'module.xml');
		$branchXML = simplexml_load_string($moduleBranchXmlString);
	} catch(\Exception $e) {
		freepbx::out($e->getMessage());
		return;
	}
	$rawname = (string)$branchXML->rawname;
	$name = (string)$branchXML->name;
	$description = (string)$branchXML->description;

	if(empty($vars['githubtoken'])) {
		freepbx::out("If you add 'githubtoken' to your .freepbxconfig file you wont have to enter these credentials");
		$username = freepbx::getInput("GitHub Username");
		$password = freepbx::getPassword("GitHub Password", true);
		$client = new \Github\Client();
		$client->authenticate($username, $password, Github\Client::AUTH_HTTP_PASSWORD);
	} else {
		$client = new \Github\Client();
		$client->authenticate($vars['githubtoken'], "", Github\Client::AUTH_HTTP_TOKEN);
	}

	$merge = true;
	if(!freepbx::version_compare_freepbx((string)$masterXML->supported->version, (string)$branchXML->supported->version, "<=")) {
		echo "Master is on a higher or equal supported version than ".$release."\n";
		$merge = false;
	}
	if(freepbx::version_compare_freepbx((string)$masterXML->version, (string)$branchXML->version, ">")) {
		echo "Master is a higher (".(string)$masterXML->version.") version than this release (".(string)$branchXML->version.")? Scary? Aborting\n";
		$merge = false;
	}
	if(freepbx::version_compare_freepbx((string)$masterXML->version, (string)$branchXML->version, "=")) {
		echo "Master IS already this version\n";
		$merge = false;
	}

	if($merge) {
		freepbx::outn("Attempting to merge release/".$release." into master...");
		$repo->checkout("master");
		$merged = $repo->pull("origin","release/".$release);
		if(!$merged) {
			freepbx::out("\t\t\tMerge from release/".$release." into master failed");
			freepbx::out("Module " . $module . " will not be tagged!");
			return;
		}
		$repo->push("origin", "master");
		freepbx::out("Done");
		freepbx::outn("Checking you out into release/".$release."...");
		$repo->checkout("release/".$release);
		freepbx::out("Done");
	}

	$organizationApi = $client->api('organization');

	$paginator  = new Github\ResultPager($client);
	$parameters = array('freepbx');
	$repos = $paginator->fetchAll($organizationApi, 'repositories', $parameters);
	$core = array();
	foreach($repos as $repo) {
		$core[] = $repo['name'];
	}

	$paginator  = new Github\ResultPager($client);
	$parameters = array('FreePBX-ContributedModules');
	$repos = $paginator->fetchAll($organizationApi, 'repositories', $parameters);
	$contrib = array();
	foreach($repos as $repo) {
		$contrib[] = $repo['name'];
	}
	if(in_array($rawname, $core)) {
		$org = "freepbx";
	} elseif(in_array($rawname, $contrib)) {
		$org = "FreePBX-ContributedModules";
	} else {
		return;
	}
	$repo = $client->api('repo')->update(
		$org,
		$rawname,
		array(
			'name' => $rawname,
			'description' => "Module of FreePBX (".trim($name).") :: ".trim(strip_tags(str_replace(array("\r", "\n"), '', $description))),
			'default_branch' => 'release/'.$release,
			'homepage' => 'http://www.freepbx.org',
			"has_issues" => false,
			"has_wiki" => false,
			"had_downloads" => false
		)
	);
	return;
}
