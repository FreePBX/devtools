<?php
$mod_string = implode(' ', $successful_mods);
echo 'Publishing module(s)...' . PHP_EOL;
$ret = run_cmd('ssh mirror.freepbx.org "' 
	. '/var/www/html/mirror/publish_modules '
	. $vars['rver'] . ' '
	. $mod_string
	. '"',
	$foo, true);

if ($ret) {
	echo $mod_string . ' successfuly published!' . PHP_EOL;
}
?>