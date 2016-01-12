#!/usr/bin/env php
<?php
require_once('libraries/freepbx.php');
$help = array();
$help[] = array('--directory', 'Directory Location of modules root, always assumed to be ../freepbx from this location');
$help[] = array('--lang_directory', 'Directory Location languages');
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
  "lang_directory::",
);
$options = getopt("",$longopts);

$directory = !empty($options['directory']) ? $options['directory'] : $vars['repo_directory'];
$langRepoPath = !empty($options['directory']) ? $options['directory'] : $vars['language_directory'];

if(!file_exists($directory) || !file_exists($langRepoPath)) {
  freepbx::out("Directories didn't exist! Aborting");
  exit(0);
}

try {
	$langrepo = Git::open($langRepoPath);
} catch (Exception $e) {
	exit(0);
}

$count = 1;
foreach(glob($langRepoPath.'/pot/*.pot') as $pot) {
	$fileinfo = pathinfo($pot);
	$module = $fileinfo['filename'];
	if($fileinfo['filename'] != 'amp') {
		$moduleDirectory = $directory."/".$module;
		$langDirectory = $moduleDirectory . "/i18n";
	} else {
		//framework is different
		$moduleDirectory = $directory."/framework";
		$langDirectory = $moduleDirectory . "/amp_conf/htdocs/admin/i18n";
	}
	if(file_exists($moduleDirectory)) {
		freepbx::outn('Opening '.$module.'...');
		freepbx::refreshRepo($moduleDirectory);
		try {
			$repo = Git::open($moduleDirectory);
			freepbx::out("Done");
			$ab = $repo->active_branch();
			$repo->checkout("master");
		} catch (Exception $e) {
			freepbx::out("Skipping");
			continue;
		}
		if(!file_exists($langDirectory)) {
			freepbx::out('WARNING: Creating Missing i18n folder in '.$module,1);
			mkdir($langDirectory,0777,true);
		}
		foreach(glob($langRepoPath.'/po/*',GLOB_ONLYDIR) as $langpath) {
			$lang = basename($langpath);
			if(file_exists($langpath."/".$module.".po")) {
				$author = $langrepo->get_last_author("po/".$lang."/".$module.".po");
				$authors = explode("\n",$author);
				if(file_exists($langDirectory."/".$lang."/LC_MESSAGES/".$module.".po")) {
					if(md5_file($langpath."/".$module.".po") != md5_file($langDirectory."/".$lang."/LC_MESSAGES/".$module.".po")) {
						freepbx::out('need to update '.$lang." last authored by ".$authors[0],1);
						copy_po($langpath."/".$module.".po",$langDirectory."/".$lang."/LC_MESSAGES/".$module.".po");
						make_mo($langDirectory."/".$lang."/LC_MESSAGES/".$module.".po",$lang,$langRepoPath);
						$repo->add();
						$repo->commit_author("Updated ".$lang,$authors[0]);
					} else {
						freepbx::out('No Changes needed on '.$lang,1);
					}
				} else {
					freepbx::out('WARNING: Adding '.$lang.' for '.$module. " last authored by ".$authors[0],1);
					add_language($lang,$langDirectory,$langRepoPath);
					copy_po($langpath."/".$module.".po",$langDirectory."/".$lang."/LC_MESSAGES/".$module.".po");
					make_mo($langDirectory."/".$lang."/LC_MESSAGES/".$module.".po",$lang,$langRepoPath);
					$repo->add();
					$repo->commit_author("Updated ".$lang,$authors[0]);
				}
			}
		}
	} else {
		freepbx::out('WARNING: Module '.$module.' is missing. Attempting to checkout');
	}
	$repo->push('origin','master');
	$repo->checkout($ab);
}

class languages {
	public $weblate_repo = null;
	public $module_repo = null;
}

function add_language($lang,$langDirectory) {
	mkdir($langDirectory."/".$lang."/LC_MESSAGES",0777,true);
}

function copy_po($src,$target) {
	copy($src,$target);
}

function make_mo($src,$lang,$langRepoPath) {
	$pi = pathinfo($src);
	$target = $pi['dirname']."/".$pi['filename'].".mo";
	exec("msgfmt -f -v ".$src." -o ".$target);
	if(!file_exists($langRepoPath."/mo/".$lang)) {
		mkdir($langRepoPath."/mo/".$lang,0777,true);
	}
	copy($target,$langRepoPath."/mo/".$lang."/".$pi['filename'].".mo");
}
?>
