#!/usr/bin/env php
<?php

$options = getopt('',array("skipfreepbxbootstrap","moddir:"));

$config = '';
$mod_dir = isset($options['moddir']) ? $options['moddir'] : getcwd();
$test_dir = $mod_dir .'/utests';
if(file_exists($mod_dir."/phpunit.xml")) {
	$configFile = $mod_dir."/phpunit.xml";
}
if(file_exists($mod_dir."/utests/utests.xml")) {
	$configFile = $mod_dir."/utests/utests.xml";
}
if(!empty($configFile)) {
	$xml = simplexml_load_file($configFile);
	if(isset($xml['freepbxBootstrap']) && $xml['freepbxBootstrap'] == 'false') {
		$options['skipbootstrap'] = true;
	}
	$config = "-c ".$configFile;
}

if(!file_exists($test_dir)) {
	echo "No Unit Test Folder!\n";
	exit(1);
}

if(version_compare(phpversion(), "5.6", ">=")) {
	$bin = 'phpunit-5.7.21.phar';
} else {
	$bin = 'phpunit-4.8.36.phar';
}
if(isset($options['skipbootstrap'])) {
	passthru(__DIR__.'/binaries/'.$bin.' --bootstrap "'.__DIR__.'/phpunitNoFreePBXBootstrap.php" '.$config.' '.$test_dir);
} else {
	passthru(__DIR__.'/binaries/'.$bin.' --bootstrap "'.__DIR__.'/phpunitBootstrap.php" '.$config.' '.$test_dir);
}
