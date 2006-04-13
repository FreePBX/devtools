#!/usr/bin/perl


my $reldir = "release/";

while ($moddir = shift @ARGV) {
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
	# Now we know the version. Create the tar.gz
	$filename = "$rawname-$vers.tgz";
	system("tar zcf $filename $rawname");
	# Update the md5 info
	open MD5, "md5sum $filename|";
	$md5 = <MD5>;
	close MD5;
	($md5sum, $null) = split(/ /, $md5);
	$newxml =~ s/<md5sum>.+<\/md5sum>/<md5sum>$md5sum<\/md5sum>/;
	$newxml =~ s/<location>.+<\/location>/<location>$reldir$filename<\/location>/;
	open FH, ">$moddir/module.xml";
	print FH $newxml;
	close FH;
}


