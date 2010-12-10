#!/usr/bin/php -q
<?php

  $module = trim($argv[1],'/');

  if (! @include_once('xml2Array.class.php')) {
    exec("svn export http://www.freepbx.org/v2/svn/freepbx/trunk/amp_conf/htdocs/admin/libraries/xml2Array.class.php xml2Array.class.php",$exec_out,$ret);
    if ($ret != 0) die("Could not find xml2Array.class.php, got ret: $ret\n");
    require_once('xml2Array.class.php');
  }

  function die_freepbx($string) {
    echo "Error Parsing XML File:\n";
    echo $string."\n";
    exit(10);
  }

  $xml_data = file_get_contents("$module/module.xml");
  $parser = new xml2ModuleArray($xml_data);
  $xmlarray = $parser->parseAdvanced($xml_data);
  exit;

