<?php
global $amp_conf, $db;
global $asterisk_conf;

// HELPER FUNCTIONS:

if (! function_exists('out')) {
	function out($text) {
		echo $text."<br>";
	}
}

if (! function_exists('outn')) {
	function outn($text) {
		echo $text;
	}
}

/* If Blacklist module version less than 2.7.0.2 is not present then
 * upgrading to 2.9 will result in a crash. The module MUST be updated
 * even if it is currenlty disabled.
 * We don't check this in the module.xml dependencies because if they
 * don't have blacklist installed then it is ok for them to upgrade and
 * there is no dependency mode that can express that.
 */
$ver = modules_getversion('blacklist');
if ($ver !== null && version_compare($ver,'2.7.0.2','lt')) {
  out(_("Blacklist Module MUST be updated before installing."));
  out(sprintf(_("You have %s installed, 2.7.0.2 or higher is required."),$ver));
  return false;
}
