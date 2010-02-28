#!/usr/bin/php -q
<?php
  if ($argc != 3) {
    echo "Usage: $argv[0] current_revision new_revision\n";
    exit;
  }
  $current_revision = $argv[1];
  $new_revision = $argv[2];

  // Get all releases
  //
  $output = array();
  exec("svn list http://www.freepbx.org/v2/svn/modules/release/$current_revision",$output,$retcode);

  // Now hash them to find the highest revision
  //
  $release_hash = array();
  foreach ($output as $tarball) {
    preg_match("(.*-)",$tarball,$matches);
    if (!isset($release_hash[$matches[0]]) || version_compare($tarball, $release_hash[$matches[0]], "gt")) {
      $release_hash[$matches[0]] = $tarball;
    }
  }

  // Now check if the module exist in the module directory since it may have been discontinued
  //
  foreach ($release_hash as $module => $archive) {
    $dir = rtrim($module,"-");
    exec("svn list http://www.freepbx.org/v2/svn/modules/branches/$new_revision/$dir 2>&1",$output,$ret);
    if (!$ret) {
      echo "svn cp -m \"initiating $new_revision with latest $dir: $archive\" http://www.freepbx.org/v2/svn/modules/release/$current_revision/$archive  http://www.freepbx.org/v2/svn/modules/release/$new_revision\n";
      //system("svn cp -m \"initiating $new_revision with latest $dir: $archive\" http://www.freepbx.org/v2/svn/modules/release/$current_revision/$archive  http://www.freepbx.org/v2/svn/modules/release/$new_revision");
    } else {
      //echo "# skipping $archive as $dir not in new branch\n";
    }
  }

