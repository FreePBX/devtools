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
require("libraries/translation.class.php");
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
	array('-m', 'The module to update'),
);
$longopts  = array(
	"help",
	"module:",
);
$options = getopt("m:",$longopts);
if(empty($options) || isset($options['help'])) {
	freepbx::showHelp('update_language.php',$help);
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
	case !empty($module):
		FreePBX::refreshRepo($repodir);
		$repo = Git::open($repodir);
		$moduleMasterXmlString = $repo->show('origin/master','module.xml');
		$masterXML = simplexml_load_string($moduleMasterXmlString);

		$activeb = $repo->active_branch();
		$sver = (string)$masterXML->supported->version;

		$rbranches = $repo->list_remote_branches();
		foreach($rbranches as $branch) {
			if(!preg_match("/^origin\/release\/(.*)/",$branch,$bmatch)) {
				continue;
			}
			try{
				$xml = $repo->show('refs/remotes/'.$branch,'module.xml');
			} catch (Exception $e) {
				//no module xml here...nothing to see move along and try next branch
				freepbx::out("No Module.xml, skipping");
				continue;
			}
			//load our xml string into our common parser
			//we only get back three of the tags, rawname, version and supported
			$bxml = freepbx::check_xml_string($xml);
			if($bxml[2]['version'] == $sver) {
				$mver = $bmatch[1];
				break;
			}
		}
		if(empty($mver)) {
			freepbx::out("Could not find supported branch to work with!");
			die();
		}

		$repo->checkout("release/".$mver);

		freepbx::outn("\t\tMerging master into this branch...");
		$stashable = $repo->add_stash();
		$repo->fetch();
		try {
			$merged = $repo->pull('origin','master');
		} catch(\Exception $e) {
			$merged = false;
		}
		if(!$merged) {
			freepbx::out("\t\tMerge from master to this branch failed");
			freepbx::out("Module " . $module . " will not be tagged!");
			continue;
		}
		freepbx::out("Done");
		if($stashable) {
			$repo->apply_stash();
			$repo->drop_stash();
		}

		freepbx::out("\tProcessing localizations...");
		freepbx::outn("\t\tUpdating master localization...");
		$translation = new Translation($repodir);
		if(!preg_match('/(core|framework)$/i',$repodir)) {
			//if no i18n folder then make an english one!
			if(!file_exists($repodir.'/i18n')) {
				$translation->makeLanguage("en_US");
			}
			//pray that this works..
			$translation->update_i18n();
			freepbx::out("Done");
			foreach(glob($repodir.'/i18n/*',GLOB_ONLYDIR) as $langDir) {
				$lang = basename($langDir);
				freepbx::outn("\t\tUpdating individual localization for ".$lang);
				$o = $translation->merge_i18n($lang);
				freepbx::out($o);
			}
		} elseif(preg_match('/framework$/i',$repodir)) {
			$translation->update_i18n_amp();
			foreach(glob($repodir.'/amp_conf/htdocs/admin/i18n/*',GLOB_ONLYDIR) as $langDir) {
				$lang = basename($langDir);
				freepbx::outn("\t\tUpdating individual localization for ".$lang);
				$o = $translation->merge_i18n_amp($lang);
				freepbx::out($o);
			}
			freepbx::out("Done");
		} else {
			freepbx::out("Core is done through framework");
		}

		freepbx::outn("\tChecking for Modified or New files...");
		$status = $repo->status();
		$commitable = false;
		if(empty($status)) {
			freepbx::out("No Modified or New Files");
		} else {
			freepbx::out("Found ".count($status['modified'])." Modified files and ".count($status['untracked'])." New files");
			$commitable = true;
		}

		if($commitable) {
			freepbx::outn("\t\tCheckin Outstanding Changes...");
			//-A will do more than ., it will add any unstaged files...
			try {
				$repo->add('-A');
			} catch (Exception $e) {
				freepbx::out($e->getMessage());
				freepbx::out("Module " . $module . " will not be tagged!");
				continue;
			}
			freepbx::out("Done");
			freepbx::outn("\t\tAdding Commit Message...");
			//Commit with old commit message from before, but call it tag instead of commit.
			try {
				$repo->commit('[Automatic Language Updates]');
			} catch (Exception $e) {
				freepbx::out($e->getMessage());
				freepbx::out("Problem Committing");
				continue;
			}
			freepbx::out("Done");
		}

		freepbx::outn("\t\tPushing to origin...");
		//push branch and tag to remote
		//TODO: check to make sure the author/committer isn't 'root'
		try {
			$repo->push("origin", "release/".$mver);
		} catch (Exception $e) {
			freepbx::out($e->getMessage());
			freepbx::out("Module " . $module . " will not be pushed!");
			continue;
		}
		freepbx::out("Done");

		freepbx::outn("\tMaster is the same supported release as this branch. Merging this release into master...");
		if(!$vars['debug']) {
			$repo->checkout("master");
			$merged = $repo->pull("origin","release/".$mver);
			if(!$merged) {
				freepbx::out("\t\tMerge from release/".$mver." into master failed");
				freepbx::out("Module " . $module . " will not be tagged!");
				continue;
			}
			$repo->push("origin", "master");
			$repo->checkout("release/".$mver);
		}
		freepbx::out("Done");

		freepbx::outn("\tChecking you back into ".$activeb."...");
		$repo->checkout($activeb);
		freepbx::out("Done");
	break;
	default:
		freepbx::showHelp('update_language.php',$help);
		exit(0);
	break;
}
