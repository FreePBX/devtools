#!/usr/bin/env php
<?php

// This creates and signs a .tar.gz file with a valid private key on your keyring.
//
// Params: sign.php /location/of/module <keyfingerprint>
//
// If keyfingerprint is supplied, no sanity checking about the key is
// performed.

if (!isset($argv[1])) {
	print $argv[0].": /location/of/module [--local] <keyfingerprint>\n";
	print "Key fingerprint is optional\n";
	exit(1);
}

$loc = $argv[1];

if (isset($argv[2]) && $argv[2] == "--local") {
	if (posix_geteuid() !== 0) {
		throw new \Exception("--local must be run as root. You are not root");
	}
	$local = true;
	$keyindex = 3;
} else {
	$local = false;
	$keyindex = 2;
}

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
include(__DIR__."/libraries/GPG.class.php");

$gpg = new GPG();

// Make sure we have the FreePBX key
exec('gpg --list-key 9F9169F4B33B4659', $output, $retcode);
if ($retcode != 0) {
	recvKey('9F9169F4B33B4659');
}

// Now, figure out which key we want to use to sign this
// package with
if (isset($argv[$keyindex])) {
	$key = getSigningKey($argv[$keyindex]);
} else {
	$key = getSigningKey();
}
if (!$key) {
	print "Wasn't able to find a valid key. Sorry\n";
	exit(1);
}

if ($local) {
	$ldir = "/etc/freepbx.secure";
	print "Installing to local signing directory\n";
	if (is_link($ldir)) {
		throw new \Exception("Secure Directory ($ldir) is a link");
	}
	if (!file_exists($ldir)) {
		// Make it
		mkdir($ldir);
	}
	if (!is_dir($ldir)) {
		throw new \Exception("Secure Directory ($ldir) is not a dir");
	}
	$xml = new SimpleXmlElement(file_get_contents($loc."/module.xml"));
	$name = $xml->rawname;
	$sig = "$ldir/$name.sig";
} else {
	$sig = "$loc/module.sig";
}

print "Signing with $key\n";
print "\tGenerating file list...";
@unlink($sig);
$files = $gpg->getHashes($loc);
print "\n\tSigning $sig..";
$fh = popen("gpg --default-key $key --clearsign > $sig", "w");

fwrite($fh, ";################################################
;#        FreePBX Module Signature File         #
;################################################
;# Do not alter the contents of this file!  If  #
;# this file is tampered with, the module will  #
;# fail validation and be marked as invalid!    #
;################################################

");

fwrite($fh, "[config]\n");
fwrite($fh, "version=2\n");
fwrite($fh, "hash=sha256\n");
fwrite($fh, "signedwith=$key\n");
fwrite($fh, "signedby=sign.php\n");
fwrite($fh, "repo=manual\n");
if ($local) {
	fwrite($fh, "type=local\n");
} else {
	fwrite($fh, "type=public\n");
}
fwrite($fh, "[hashes]\n");
foreach ($files as $f => $h) {
	// Don't try to validate yourself.
	if ($f == "module.sig") {
		continue;
	}
	fwrite($fh, "$f = $h\n");
}
fwrite ($fh, ";# End\n");
pclose($fh);

print "\nDone\n";

if ($local) {
	print "Tagging module for local signing...";
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
	fwrite($fh, "version=2\n");
	fwrite($fh, "hash=sha256\n");
	fwrite($fh, "signedwith=$key\n");
	fwrite($fh, "signedby=sign.php\n");
	fwrite($fh, "repo=local\n");
	fwrite($fh, "type=local\n");
	fwrite($fh, "[hashes]\n");
	fwrite($fh, "$name.sig = ".hash_file("sha256", $sig)."\n");
	pclose($fh);
	print "\nDone\n";
}

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
