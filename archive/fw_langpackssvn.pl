#!/usr/bin/perl

# This script (sculpted from publish.pl) will check the last tarball (which must be there)
# based on the fw_langpacks xml version number and then run svn log against all updates since
# fw_langpacks was last published.
#
$rver = "2.11";
$fwbranch = "branches/2.11";
$fw_langpacks = "fw_langpacks";

$moddir = 'fw_langpacks';

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
	$filename = "../../$reldir"."$rver/$fw_langpacks-$vers.tgz";
	print "CHECKING VERSION: ..... ";
	#print "CHECKING VERSION WITH: tar -zxOf $filename $moddir/svnversion.txt: ...  ";
	system("tar -zxOf ".$filename." ".$moddir."/svnversion.txt");
	print "Geting svn log of language updates since that version for $rver : .... \n\n";
	system("svn log http://svn.freepbx.org/freepbx/$fwbranch/amp_conf/htdocs/recordings/locale -v -r `tar -zxOf ".$filename." ".$moddir."/svnversion.txt | sed -e s/SVN\\\ VERSION:// | tr -cd '0-9'`:HEAD | grep 'htdocs/recordings/locale'");
	system("svn log http://svn.freepbx.org/freepbx/$fwbranch/amp_conf/htdocs/admin -v -r `tar -zxOf ".$filename." ".$moddir."/svnversion.txt | sed -e s/SVN\\\ VERSION:// | tr -cd '0-9'`:HEAD | grep 'i18n/' | grep 'htdocs/admin'");
	system("svn log http://svn.freepbx.org/modules/branches/$rver -v -r `tar -zxOf ".$filename." ".$moddir."/svnversion.txt | sed -e s/SVN\\\ VERSION:// | tr -cd '0-9'`:HEAD | grep 'i18n/' | grep 'modules/branches'");


	# Test calls
	# system("svn log http://svn.freepbx.org/freepbx/branches/$rver/amp_conf/htdocs/recordings/locale -v -r 4000:HEAD | grep 'htdocs/recordings/locale'");
	# system("svn log http://svn.freepbx.org/freepbx/branches/$rver/amp_conf/htdocs/admin -v -r 4000:HEAD | grep 'i18n/' | grep 'htdocs/admin'");
	# system("svn log http://svn.freepbx.org/modules/branches/$rver -v -r 4000:HEAD | grep 'i18n/' | grep 'modules/branches'");
