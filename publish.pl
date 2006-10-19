#!/usr/bin/perl

$rver = "2.2";

my $reldir = "release/";

while ($moddir = shift @ARGV) {
	next if (!-d $moddir);
	open FH, "$moddir/module.xml"; 
	$newxml = "";
	$vers = "unset";
	$rawname = "unset";
	while (<FH>) {
		if (/<version>(.+)<\/version>/) { $vers = $1; }
		if (/<rawname>(.+)<\/rawname>/) { $rawname = $1; }
		$newxml .= $_;
	}
	close FH;
	die "Don't know version of $moddir" if ($vers eq "unset");
	die "Don't know rawname of $moddir" if ($rawname eq "unset");
	# Automatically check in any files that were modified but weren't checked into SVN
	chdir($moddir);
	@arr = <*>;
	$files = "";
	while ($x = shift @arr) {
		# Excluding module.xml which gets checked in later..
		next if ($x =~ /module.xml/);
		$files .= "$x ";
	}
	system("svn ci -m \"Auto Check-in of any outstanding patches\" $files");
	chdir("..");
	# Now we know the version. Create the tar.gz
	$filename = "$rawname-$vers.tgz";
	system("tar zcf $filename --exclude .svn $rawname");
	# Update the md5 info
	open MD5, "md5sum $filename|";
	$md5 = <MD5>;
	close MD5;
	($md5sum, $null) = split(/ /, $md5);
	$newxml =~ s/<md5sum>.+<\/md5sum>/<md5sum>$md5sum<\/md5sum>/;
	$newxml =~ s/<location>.+<\/location>/<location>$reldir$rver\/$filename<\/location>/;
	open FH, ">$moddir/module.xml";
	print FH $newxml;
	close FH;
	system("mv $filename ../../release/$rver");
	system("svn add ../../release/$rver/$filename");
	system("svn ps svn:mime-type application/tgz ../../release/$rver/$filename");
	system("svn ci ../../release/$rver/$filename $rawname/module.xml -m \"Module Publish Script: $rawname $vers\"");
}

