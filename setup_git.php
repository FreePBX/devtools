#!/usr/bin/php -q
<?php
require_once('libraries/freepbx.php');
$longopts  = array(
    "force"
);
$options = getopt("",$longopts);

$username = freepbx::getInput("FreePBX Username");
fwrite(STDOUT, "FreePBX Password: ");
$password = freepbx::getPassword(true);
try {
	$freepbx = new freepbx($username,$password);
} catch (Exception $e) {
	die("Invalid Username/Password Combination\n");
}
$directory = freepbx::getInput("Setup Directory",dirname(dirname(__FILE__)).'/freepbx');

if(!file_exists($directory)) {
	die($directory . " Does Not Exist \n");
}

$force = isset($options['force']) ? true : false;
$freepbx->setupDevLinks($directory,$force);