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
passthru(__DIR__.'/binaries/phpunit.phar --bootstrap "'.__DIR__.'/phpunitBootstrap.php" '.$config.' '.$mod_dir.'/utests');
