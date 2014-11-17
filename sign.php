#!/usr/bin/env php
<?php

// This creates and signs a .tar.gz file with a valid private key on your keyring.
//
// Params: sign.php /location/of/module <keyfingerprint>
//
// If keyfingerprint is supplied, no sanity checking about the key is
// performed.

if (!isset($argv[1])) {
	print $argv[0].": /location/of/module <keyfingerprint>\n";
	print "Key fingerprint is optional\n";
	exit(1);
}

$loc = $argv[1];

if (substr($loc, -1) == "/") {
	// Strip off any trailing slash
	$loc = substr($loc, 0, -1);
}

if (!is_dir($loc)) {
	print "$loc is not a directory\n";
	exit(1);
}

if (!file_exists($loc."/module.xml")) {
	print "module.xml does not exists in $loc\n";
	exit(1);
}

// Things are looking good, initalize freepbx
$restrict_mods=true;
$bootstrap_settings['freepbx_auth']=false;
include '/etc/freepbx.conf';

restore_error_handler();
restore_exception_handler();
error_reporting(E_ALL);

$gpg = FreePBX::GPG();

// Make sure we have the FreePBX key
exec('gpg --list-key 9F9169F4B33B4659', $output, $retcode);
if ($retcode != 0) {
	recvKey('9F9169F4B33B4659');
}

// Now, figure out which key we want to use to sign this
// package with
if (isset($argv[2])) {
	$key = getSigningKey($argv[2]);
} else {
	$key = getSigningKey();
}
if (!$key) {
	print "Wasn't able to find a valid key. Sorry\n";
	exit(1);
}

print "Signing $loc with $key\n";
print "\tGenerating file list...";
$files = $gpg->getHashes($loc);
print "\n\tSigning $loc/module.sig..";
@unlink("$loc/module.sig");
$fh = popen("gpg --default-key $key --clearsign > $loc/module.sig", "w");

fwrite($fh, ";################################################
;#        FreePBX Module Signature File         #
;################################################
;# Do not alter the contents of this file!  If  #
;# this file is tampered with, the module will  #
;# fail validation and be marked as invalid!    #
;################################################

");

fwrite($fh, "[config]\n");
fwrite($fh, "version=1\n");
fwrite($fh, "hash=sha256\n");
fwrite($fh, "signedwith=$key\n");
fwrite($fh, "signedby=sign.php\n");
fwrite($fh, "repo=manual\n");
fwrite($fh, "[hashes]\n");
foreach ($files as $f => $h) {
	fwrite($fh, "$f = $h\n");
}
fwrite ($fh, ";# End\n");
pclose($fh);

print "\nDone\n";

// We're just assuming the filename is the last part of the directory.
$filename = basename($loc).".tar.gz";
function getSigningKey($key = false) {
	// Figure out what our valid signing key is.
	if ($key) {
		// TODO: Add sanity check here?
		return $key;
	}

	// Ask GPG for a list of our known private keys.
	$keys = listPrivateKeys();
	foreach ($keys as $k) {
		if (validateKey($k)) {
			return $k;
		}
	}
	// Wasn't able to find a key. Boo.
	return false;
}


function listPrivateKeys() {
	exec('gpg --with-colons --list-secret-keys', $output, $retvar);
	if ($retvar != 0) {
		print "Error running gpg --list-secret-keys ($retvar) - ".join("\n",$output)."\n";
		exit($retvar);
	}
	$retarr = array();
	foreach ($output as $l) {
		if (preg_match('/^sec::\d+:\d:([0-9A-F]+):/', $l, $out)) {
			$retarr[] = $out[1];
		}
	}

	return $retarr;
}

function validateKey($key) {
	$trusted = '9F9169F4B33B4659';
	// Ask GPG for valid signatures
	exec("gpg --with-colons --check-sigs $key", $output, $retvar);
	foreach ($output as $l) {
		$tmparr = explode(':', $l);
		if ($tmparr[0] != 'sig') {
			continue;
		}
		if ($tmparr[4] == $trusted) {
			return true;
		}
	}
	return false;
}

function recvKey($key) {
	exec("gpg --recv-key $key");
}

