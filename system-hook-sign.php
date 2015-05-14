#!/usr/bin/env php
<?php

// This creates and signs a system module (which must be stored in 
// /usr/local/asterisk/HOOKNAME and /usr/local/asterisk/HOOKNAME.sig
//
// Params: sign-system.php /location/of/file <keyfingerprint>
//
// If keyfingerprint is supplied, no sanity checking about the key is
// performed.

if (!isset($argv[1])) {
	print $argv[0].": /location/of/file <keyfingerprint>\n";
	print "Key fingerprint is optional\n";
	exit(1);
}

$loc = $argv[1];

if (substr($loc, -1) == "/") {
	// Strip off any trailing slash
	$loc = substr($loc, 0, -1);
}

if (is_dir($loc)) {
	print "$loc is a directory. This only signs single files\n";
	exit(1);
}

if (!file_exists($loc)) {
	print "Can't locate file to sign\n";
	exit(1);
}

// Things are looking good, initalize freepbx
include __DIR__."/libraries/GPG.class.php";

$gpg = new GPG();

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
$sigfile = "$loc.sig";
if (file_exists($sigfile)) {
	unlink($sigfile);
}

$hash = hash_file('sha256', $loc);
$outfile = basename($loc);
print "\n\tSigning $sigfile (as /usr/local/asterisk/$outfile)";
$fh = popen("gpg --default-key $key --clearsign > $sigfile", "w");

fwrite($fh, ";################################################
;#        FreePBX SYSTEM Signature File         #
;################################################
;# Do not alter the contents of this file!  If  #
;# this file is tampered with, the application  #
;# will fail validation and WILL NOT BE RUN!    #
;################################################

");

fwrite($fh, "[config]\n");
fwrite($fh, "xtype=system\n");
fwrite($fh, "version=1\n");
fwrite($fh, "hash=sha256\n");
fwrite($fh, "signedwith=$key\n");
fwrite($fh, "signedby=sign-system.php\n");
fwrite($fh, "repo=manual\n");
fwrite($fh, "[hashes]\n");
fwrite($fh, "/usr/local/asterisk/$outfile = $hash\n");
fwrite($fh, ";# End\n");
pclose($fh);

print "\nDone\n";

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
		if (preg_match('/^sec::\d+:\d+:([0-9A-F]+):/', $l, $out)) {
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
