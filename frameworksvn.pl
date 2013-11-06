#!/usr/bin/perl

# This script (sculpted from publish.pl) will check the last tarball (which must be there)
# based on the framework xml version number and then run svn log against all updates since
# framework was last published.
#
$rver = "2.11";
$fwbranch = "branches/2.11";
$framework = "framework";

$moddir = 'framework';

my $reldir = "release/";

	open FH, "$moddir/module.xml"; 
	$newxml = "";
	$vers = "unset";
	while (<FH>) {
		if ($vers == 'unset' && /<version>(.+)<\/version>/) { $vers = $1; }
		$newxml .= $_;
	}
	close FH;

	die "Don't know version of $moddir" if ($vers eq "unset");
	# Automatically check in any files that were modified but weren't checked into SVN

	# Now we know the version. Get the svnversion.txt from the last update.
	$filename = "../../$reldir"."$rver/$framework-$vers.tgz";
	print "CHECKING VERSION: ..... ";
	#print "CHECKING VERSION WITH: tar -zxOf $filename $moddir/svnversion.txt: ...  ";
	system("tar -zxOf ".$filename." ".$moddir."/svnversion.txt");
	print "Geting svn log since that version for $rver : .... \n\n";
	$svnver = system("svn log http://svn.freepbx.org/freepbx/$fwbranch -r `tar -zxOf ".$filename." ".$moddir."/svnversion.txt | sed -e s/SVN\\\ VERSION:// | tr -cd '0-9'`:HEAD");
