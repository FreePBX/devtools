#!/usr/bin/perl

#  Developers: Use $debug to keep all svn ci activity from occuring, messages will then be printed. All files will still be created
#              and module.xml modifications made since these can be reverted. Review carefully if you care concerned that it will
#              lose work.
#
$debug = 0;
$checkphp = 1;
$rver = "2.5";
$fwbranch = "branches/2.5";
$framework = "framework";
$fw_fop = "fw_fop";
$fw_ari = "fw_ari";
$fw_langpacks = "fw_langpacks";

my $reldir = "release/";

while ($moddir = shift @ARGV) {
	next if (!-d $moddir);
	if ($moddir =~ /$framework/) {

		# Framework module is special case. We export and pull in all the files of framework that we are going to want to udpate. For now this is
		# all files under htdocs, agi-bin and bin. We have not included astetc since such files should be done with core modules. We have also
		# temporarily chosen not to include FOP since it is likely FOP may be handled by a FOP module going forward. Othewise we will add it here.
		#
		# TODO: ADD FOP until we can get into a module
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
		# and change that. Also some others
		#
		# TODO: check if these are stiff there and need to be special cased
		#
		if (system("rm -rf $framework/htdocs/mainstyle.css")) {
			die "FATAL: failed to trim htdocs/mainstyle.css\n";
		}
		if (system("rm -rf $framework/htdocs/index.html")) {
			die "FATAL: failed to trim htdocs/index.html\n";
		}
		if (system("rm -rf $framework/htdocs/admin/modules/_cache")) {
			die "FATAL: failed to trim modules/_cache\n";
		}
	}
	if ($moddir =~ /$fw_fop/) {

		if (system("svn export http://svn.freepbx.org/freepbx/$fwbranch/amp_conf/htdocs_panel $fw_fop/htdocs_panel")) {
			die "FATAL: failed to export htdocs_panel directory\n";
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
			if (system("rm -rf $fw_langpacks/i18n")) {
				die "FATAL: failed to remove temp i18n dir\n";
			}
			if (!system("svn export $module_url/$module"."i18n $fw_langpacks/i18n 2> /dev/null")) {
				if (system("mkdir $fw_langpacks/htdocs/admin/modules/$module")) {
					die "FATAL: failed to create htdocs/admin/modules/$module\n";
				}
				if (system("mv $fw_langpacks/i18n $fw_langpacks/htdocs/admin/modules/$module")) {
					die "FATAL: failed to move i18n to htdocs/admin/modules/$module\n";
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
		}
	} elsif ($moddir =~ /$fw_fop/) {
			next if ($x =~ /module.xml/);
			next if ($x =~ /htdocs_panel/);
			next if ($x =~ /svnversion.txt/);
			$files .= "$x ";
	} elsif ($moddir =~ /$fw_ari/) {
			next if ($x =~ /module.xml/);
			next if ($x =~ /htdocs_ari/);
			next if ($x =~ /svnversion.txt/);
			$files .= "$x ";
	} elsif ($moddir =~ /$fw_langpacks/) {
			next if ($x =~ /module.xml/);
			next if ($x =~ /htdocs/);
			next if ($x =~ /svnversion.txt/);
			$files .= "$x ";
	} else {
		while ($x = shift @arr) {
			# Excluding module.xml which gets checked in later..
			next if ($x =~ /module.xml/);
			$files .= "$x ";

			# Quick and dirty check for php syntax errors at the top level of module directories. Should probably
			# do this recursively in the future. Also - checks all files now but php -l seems to be ok with that.
			#
			if (-f $x && $checkphp) {
				if (system("php -l $x")) {
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
	if ($debug) {
		print "mv $filename ../../release/$rver\n";
		print "svn add ../../release/$rver/$filename\n";
		print "svn ps svn:mime-type application/tgz ../../release/$rver/$filename\n";
		print "svn ci ../../release/$rver/$filename $rawname/module.xml -m \"Module Publish Script: $rawname $vers\"\n";
	} else {
		system("mv $filename ../../release/$rver");
		system("svn add ../../release/$rver/$filename");
		system("svn ps svn:mime-type application/tgz ../../release/$rver/$filename");
		system("svn ci ../../release/$rver/$filename $rawname/module.xml -m \"Module Publish Script: $rawname $vers\"");
	}
}
