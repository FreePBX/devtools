#!/usr/bin/php -q
<?php
require_once('libraries/freepbx.php');
$longopts  = array(
    "force"
);
$options = getopt("",$longopts);

$username = freepbx::getInput("FreePBX Username");
$password = freepbx::getPassword("FreePBX Password: ", true);
try {
	$freepbx = new freepbx($username,$password);
} catch (Exception $e) {
	freepbx::out("Invalid Username/Password Combination");
	exit(1);
}
$directory = freepbx::getInput("Setup Directory",dirname(dirname(__FILE__)).'/freepbx');

if(!file_exists($directory)) {
	if(!mkdir($directory)) {
		die($directory . " Does Not Exist \n");
	}
}

$force = isset($options['force']) ? true : false;
$freepbx->setupDevLinks($directory,$force);
$freepbx->setupSymLinks($directory);
