#!/usr/bin/perl

#  Developers: Use $debug to keep all svn ci activity from occuring, messages will then be printed. All files will still be created
#              and module.xml modifications made since these can be reverted. Review carefully if you care concerned that it will
#              lose work.
#
system("which md5sum"); # typical linux format
if ($? == 0) {
		$md5_command = 'md5sum';
} else {
	system("which md5"); # OSX format
	if ($? == 0) {
		$md5_command = 'md5 -r';
	} else {
		die "no md5sum command\n";
	}
}

$debug = 0;
$checkphp = 1;
$rver = "2.10";
$fwbranch = "branches/2.10";
$framework = "framework";
$fw_fop = "fw_fop";
$fw_ari = "fw_ari";
$fw_langpacks = "fw_langpacks";

my $reldir = "release/";

while ($moddir = shift @ARGV) {
	next if (!-d $moddir);
	# Check the XML integrity using the FreePBX XML parser
	if (system("./check_xml.php $moddir")) {
		die "Fatal syntax error with $moddir module.xml file, aborting\n";
	}
	if ($moddir =~ /$framework/) {

		# Framework module is special case. We export and pull in all the files of framework that we are going to want to udpate. For now this is
		# all files under htdocs, agi-bin and bin. We have not included astetc since such files should be done with core modules. We have also
		# temporarily chosen not to include FOP since it is likely FOP may be handled by a FOP module going forward. Othewise we will add it here.
		#
		#
		if (system("rm -rf $framework/agi-bin $framework/bin $framework/htdocs $framework/htdocs_panel $framework/upgrades $framework/libfreepbx.install.php $framework/CHANGES")) {
			die "FATAL: failed to remove previoulsly exported directories\n";
		}
		if (system("svn export http://svn.freepbx.org/freepbx/$fwbranch/amp_conf/agi-bin $framework/agi-bin")) {
			die "FATAL: failed to export agi-bin directory\n";
		}
		if (system("svn export http://svn.freepbx.org/freepbx/$fwbranch/amp_conf/bin $framework/bin")) {
			die "FATAL: failed to export bin directory\n";
		}
		if (system("svn export http://svn.freepbx.org/freepbx/$fwbranch/amp_conf/htdocs $framework/htdocs")) {
			die "FATAL: failed to export htdocs directory\n";
		}
		if (system("svn export http://svn.freepbx.org/freepbx/$fwbranch/upgrades $framework/upgrades")) {
			die "FATAL: failed to export upgrades directory\n";
		}
		# svn doesn't seem to allow for a single file to be checked out so we need to do a kludgey workaround
		# this is what we wanted:
		#
		# if (system("svn export http://svn.freepbx.org/freepbx/$fwbranch/libfreepbx.install.php $framework/libfreepbx.install.php"))
		#
		if (system("svn co --non-recursive http://svn.freepbx.org/freepbx/$fwbranch $framework/tmp")) {
			die "FATAL: failed to checkout branch with libfreepbx.install.php\n";
		}
		# Create the svnversion information for this framework snapshot
		#
		if (system("echo SVN VERSION: `svnversion $framework/tmp` > $framework/svnversion.txt")) {
			die "FATAL: svnversion failed to create svnversion.txt\n";
		}

		if (system("mv $framework/tmp/libfreepbx.install.php $framework/")) {
			die "FATAL: failed to mv libfreepbx.install.php to $framework\n";
		}
		if (system("mv $framework/tmp/CHANGES $framework/")) {
			die "FATAL: failed to mv CHANGES to $framework\n";
		}
		if (system("rm -rf $framework/tmp")) {
			die "FATAL: failed to remove $framework/tmp\n";
		}

		# Now we must remove a few files which users may have legitimately edited. For now, all of ARI moved to new module
		# ARI file used for editing paramters and options.
		#
		if (system("rm -rf $framework/htdocs/recordings")) {
			die "FATAL: failed to trim main.conf.php ARI file\n";
		}
		
		# Remove from htdocs root mainstyle.css and index.html as these are owned by root (need to investigate why these are there
		# and change that. Remove retrieve_op_conf_from_mysql.pl now handled by fw_fop. Also some others.
		#
		# TODO: check if these are stiff there and need to be special cased
		#
		if (system("rm -rf $framework/htdocs/mainstyle.css")) {
			die "FATAL: failed to trim htdocs/mainstyle.css\n";
		}
		if (system("rm -rf $framework/htdocs/index.html")) {
			die "FATAL: failed to trim htdocs/index.html\n";
		}
		if (system("rm -rf $framework/htdocs/robots.txt")) {
			die "FATAL: failed to trim htdocs/robots.txt\n";
		}
		if (system("rm -rf $framework/htdocs/admin/modules/_cache")) {
			die "FATAL: failed to trim modules/_cache\n";
		}
		if (system("rm -rf $framework/bin/retrieve_op_conf_from_mysql.php")) {
			die "FATAL: failed to trim bin/retrieve_op_conf_from_mysql.php\n";
		}
	}
	if ($moddir =~ /$fw_fop/) {
		if (system("rm -rf $fw_fop/bin $fw_fop/htdocs_panel")) {
			die "FATAL: failed to remove previoulsly exported directories\n";
		}
		if (system("svn export http://svn.freepbx.org/freepbx/$fwbranch/amp_conf/htdocs_panel $fw_fop/htdocs_panel")) {
			die "FATAL: failed to export htdocs_panel directory\n";
		}
		if (system("svn co --non-recursive http://svn.freepbx.org/freepbx/$fwbranch/amp_conf/bin $fw_fop/tmp")) {
			die "FATAL: failed to checkout bin\n";
		}
		if (system("mkdir $fw_fop/bin")) {
			die "FATAL: failed to create $fw_fop/bin\n";
		}
		if (system("mv $fw_fop/tmp/retrieve_op_conf_from_mysql.php $fw_fop/bin")) {
			die "FATAL: failed to mv retrieve_op_conf_from_mysql.php to $fw_fop/bin\n";
		}
		if (system("rm -rf $fw_fop/tmp")) {
			die "FATAL: failed to remove $fw_fop/tmp\n";
		}

		# Create the svnversion information for this framework snapshot
		#
		if (system("echo SVN VERSION: `svn log -q -r HEAD http://svn.freepbx.org/ | cut -s -f 1 -d ' ' | cut -b '2-'` > $fw_fop/svnversion.txt")) {
			die "FATAL: svnversion failed to create svnversion.txt\n";
		}

	}
	if ($moddir =~ /$fw_ari/) {

		if (system("rm -rf $fw_ari/htdocs_ari")) {
			die "FATAL: failed to remove previoulsly exported directories\n";
		}

		# Create the svnversion information for this framework snapshot
		#
		if (system("echo SVN VERSION: `svn log -q -r HEAD http://svn.freepbx.org/ | cut -s -f 1 -d ' ' | cut -b '2-'` > $fw_ari/svnversion.txt")) {
			die "FATAL: svnversion failed to create svnversion.txt\n";
		}

		if (system("svn export http://svn.freepbx.org/freepbx/$fwbranch/amp_conf/htdocs/recordings $fw_ari/htdocs_ari")) {
			die "FATAL: failed to export htdocs directory\n";
		}

		# Now we must remove a few files which users may have legitimately edited. For now this is the main.conf.php file which is the current
		# ARI file used for editing paramters and options.
		#
		if (system("rm -rf $fw_ari/htdocs_ari/includes/main.conf.php")) {
			die "FATAL: failed to trim main.conf.php ARI file\n";
		}

	}
	if ($moddir =~ /$fw_langpacks/) {

		my $module_url="http://svn.freepbx.org/modules/branches/$rver";
		my $base_url="http://svn.freepbx.org/freepbx/$fwbranch/amp_conf/htdocs";
		my $framework_url="$base_url/admin";
		my $recordings_url="$base_url/recordings";

		@modules=`svn list $module_url | grep '/'`;

		if (system("rm -rf $fw_langpacks/htdocs")) {
			die "FATAL: failed to remove old htdocs dir\n";
		}
		if (system("mkdir -p $fw_langpacks/htdocs/admin/modules")) {
			die "FATAL: failed to create htdocs/admin/modules\n";
		}
		if (system("mkdir -p $fw_langpacks/htdocs/recordings")) {
			die "FATAL: failed to create htdocs/recordings\n";
		}

		if (system("svn export $framework_url/i18n $fw_langpacks/htdocs/admin/i18n")) {
			die "FATAL: failed to export $framework_url/i18n\n";
		}
		if (system("svn export $recordings_url/locale $fw_langpacks/htdocs/recordings/locale")) {
			die "FATAL: failed to export $recurdings_url/locale\n";
		}
		foreach my $module ( @modules ) {
			chomp($module);
			if (system("rm -rf $fw_langpacks/i18n-tmp")) {
				die "FATAL: failed to remove temp i18n-tmp dir\n";
			}
			system("svn export $module_url/$module"."i18n $fw_langpacks/i18n-tmp 2> /dev/null");
			if (($? != -1) && (-d "$fw_langpacks/i18n-tmp")) {
				if (system("mkdir $fw_langpacks/htdocs/admin/modules/$module")) {
					die "FATAL: failed to create htdocs/admin/modules/$module\n";
				}
				if (system("mv $fw_langpacks/i18n-tmp $fw_langpacks/htdocs/admin/modules/$module/i18n")) {
					die "FATAL: failed to move i18n-tmp to htdocs/admin/modules/$module/i18n\n";
				}
			} else {
				print "No i18n files for $module"."\n";
			}
		}


		# Create the svnversion information for this framework snapshot
		#
		if (system("echo SVN VERSION: `svn log -q -r HEAD http://svn.freepbx.org/ | cut -s -f 1 -d ' ' | cut -b '2-'` > $fw_langpacks/svnversion.txt")) {
			die "FATAL: svnversion failed to create svnversion.txt\n";
		}

	}

	open FH, "$moddir/module.xml"; 
	$newxml = "";
	$vers = "unset";
	$rawname = "unset";
	while (<FH>) {
		if ($vers == 'unset' && /<version>(.+)<\/version>/) { $vers = $1; }
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
	if ($moddir =~ /$framework/) {
		while ($x = shift @arr) {
			# Excluding module.xml which gets checked in later..
			next if ($x =~ /module.xml/);
			next if ($x =~ /agi-bin/);
			next if ($x =~ /bin/);
			next if ($x =~ /htdocs/);
			next if ($x =~ /htdocs_panel/);
			next if ($x =~ /upgrades/);
			next if ($x =~ /libfreepbx.install.php/);
			next if ($x =~ /svnversion.txt/);
			$files .= "$x ";
			if (-f $x && $checkphp) {
				if (system("php -l $x")) {
					die "FATAL: php syntax error detected in $x\n";
				}
			}
		}
	} elsif ($moddir =~ /$fw_fop/) {
		while ($x = shift @arr) {
			next if ($x =~ /module.xml/);
			next if ($x =~ /htdocs_panel/);
			next if ($x =~ /svnversion.txt/);
			$files .= "$x ";
			if (-f $x && $checkphp) {
				if (system("php -l $x")) {
					die "FATAL: php syntax error detected in $x\n";
				}
			}
		}
	} elsif ($moddir =~ /$fw_ari/) {
		while ($x = shift @arr) {
			next if ($x =~ /module.xml/);
			next if ($x =~ /htdocs_ari/);
			next if ($x =~ /svnversion.txt/);
			$files .= "$x ";
			if (-f $x && $checkphp) {
				if (system("php -l $x")) {
					die "FATAL: php syntax error detected in $x\n";
				}
			}
		}
	} elsif ($moddir =~ /$fw_langpacks/) {
		while ($x = shift @arr) {
			next if ($x =~ /module.xml/);
			next if ($x =~ /htdocs/);
			next if ($x =~ /svnversion.txt/);
			$files .= "$x ";
			if (-f $x && $checkphp) {
				if (system("php -l $x")) {
					die "FATAL: php syntax error detected in $x\n";
				}
			}
		}
	} else {
		while ($x = shift @arr) {
			# Excluding module.xml which gets checked in later..
			next if ($x =~ /module.xml/);
			$files .= "$x ";

			# Quick and dirty check for php syntax errors at the top level of module directories. Should probably
			# do this recursively in the future. Also - checks all files now but php -l seems to be ok with that.
			#
			if (-f $x && $checkphp) {
				if (!($x =~ /.*\.jar/) && system("php -l $x") ) {
					die "FATAL: php syntax error detected in $x\n";
				}
			}
		}
	}
	if ($debug) {
		print "svn ci -m \"Auto Check-in of any outstanding patches\" $files\n";
	} else {
		system("svn ci -m \"Auto Check-in of any outstanding patches\" $files");
	}
	chdir("..");
	# Now we know the version. Create the tar.gz
	$filename = "$rawname-$vers.tgz";
	system("tar zcf $filename --exclude '.*' $rawname");
	# Update the md5 info
	open MD5, "$md5_command $filename|";
	$md5 = <MD5>;
	close MD5;
	($md5sum, $null) = split(/ /, $md5);

       unless ($newxml =~ s/<md5sum>.*<\/md5sum>/<md5sum>$md5sum<\/md5sum>/)
       {
               $newxml =~ s|</module>|<md5sum>$md5sum></md5sum>\n</module>|;
       }

       unless ($newxml =~ s/<location>.*<\/location>/<location>$reldir$rver\/$filename<\/location>/)
       {
               $newxml =~ s|</module>|<location>$reldir$rver></location>\n</module>|;
       }

	open FH, ">$moddir/module.xml";
	print FH $newxml;
	close FH;

	system("svn update $rawname");
	my $lastpublish = `svn info $rawname | grep Revision: | cut -f 2 -d ' '`;
	chomp($lastpublish);

	if ($debug) {
		print "mv $filename ../../release/$rver\n";
		print "svn add ../../release/$rver/$filename\n";
		print "svn ps svn:mime-type application/tgz ../../release/$rver/$filename\n";
		print "svn ps lastpublish '$lastpublish' $moddir\n";
		print "svn ci ../../release/$rver/$filename $rawname/module.xml -m \"Module Publish Script: $rawname $vers\"\n";
	} else {
		system("mv $filename ../../release/$rver");
		system("svn add ../../release/$rver/$filename");
		system("svn ps svn:mime-type application/tgz ../../release/$rver/$filename");
		system("svn ps lastpublish '$lastpublish' $rawname");
		system("svn ci ../../release/$rver/$filename $rawname/module.xml ./$rawname -m \"Module Publish Script: $rawname $vers\"");
	}
}
