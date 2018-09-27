<?php
require_once __DIR__.'/vendor/autoload.php';
global $amp_conf, $db;
$bootstrap_settings['freepbx_error_handler'] = false;
include '/etc/freepbx.conf';
error_reporting(-1);
error_reporting(E_ALL & ~(E_STRICT | E_NOTICE));
ini_set('display_startup_errors', '1');
ini_set('display_errors', '1');
ini_set('error_reporting', E_ALL);
