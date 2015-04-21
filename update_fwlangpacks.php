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
$longopts  = array(
	"directory::",
);
$options = getopt("",$longopts);

$directory = !empty($options['directory']) ? $options['directory'] : $vars['repo_directory'];

if(!file_exists($directory)) {
  freepbx::out("Directories didn't exist! Aborting");
  exit(0);
}

$fwlangpacksReop = $directory . "/fw_langpacks";
if(!file_exists($fwlangpacksReop) ) {
	freepbx::out("Language Packs didn't exist! Aborting");
	exit(0);
}

foreach(glob($directory."/*", GLOB_ONLYDIR) as $moduleDir) {
	$module = basename($moduleDir);
	if($module == 'fw_langpacks') {
		continue;
	}
	try {
		$repo = Git::open($moduleDir);
	} catch (Exception $e) {
		freepbx::out("Unable to open " . $moduleDir . " as a repo");
		exit(0);
	}

	$branch = $repo->active_branch();
	$stashed = $repo->add_stash();
	freepbx::outn("Checking out master in ".$module."...");
	$repo->checkout('master');
	$repo->pull('origin', 'master');
	freepbx::out("Done");

        freepbx::outn("\tChecking for i18n folder in ".$module."...");
        if(!file_exists($moduleDir."/i18n")) {
                if($module != "framework") {
                        freepbx::out("Not Present, Skipping");
                        continue;
                } else {
                        freepbx::out("Framework, Special Case");
                }
        } else {
                freepbx::out("found");
        }

	if($module == "framework") {
		$moduleDir = $moduleDir . '/amp_conf/htdocs/admin';
		$module = "amp";
	}
	foreach(glob($moduleDir."/i18n/*", GLOB_ONLYDIR) as $langDir) {
		$lang = basename($langDir);
		if(file_exists($langDir."/LC_MESSAGES/".$module.".mo")) {
			$file = $langDir."/LC_MESSAGES/".$module.".mo";
			if(!file_exists($fwlangpacksReop."/mo/".$lang)) {
				mkdir($fwlangpacksReop."/mo/".$lang);
			}
			freepbx::out("\tProcessing ".$lang.".mo in ".$module);
			copy($file, $fwlangpacksReop."/mo/".$lang."/".$module.".mo");
		}
		if(file_exists($langDir."/LC_MESSAGES/".$module.".po")) {
			$file = $langDir."/LC_MESSAGES/".$module.".po";
			if(!file_exists($fwlangpacksReop."/po/".$lang)) {
				mkdir($fwlangpacksReop."/po/".$lang);
			}
			freepbx::out("\tProcessing ".$lang.".po in ".$module);
			copy($file, $fwlangpacksReop."/po/".$lang."/".$module.".po");
		}
	}

	$repo->checkout($branch);
	if($stashed) {
		$repo->apply_stash();
		$repo->drop_stash();
	}
}
freepbx::out("Finished!");
