#!/usr/bin/env php
<?php
$config = '';
$mod_dir = getcwd();
if(file_exists($mod_dir."/utests/utests.xml")) {
	$config = "-c ".$mod_dir."/utests/utests.xml";
}
if(!file_exists($mod_dir.'/utests')) {
	echo "No Unit Test Folder!\n";
	exit(1);
}

if(version_compare(phpversion(), "5.6", ">=")) {
	$bin = 'phpunit-5.7.21.phar';
} else {
	$bin = 'phpunit-4.8.36.phar';
}
passthru(__DIR__.'/binaries/'.$bin.' --bootstrap "'.__DIR__.'/phpunitBootstrap.php" '.$config.' '.$mod_dir.'/utests');
