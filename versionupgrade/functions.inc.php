<?php

/*  Copyright (C) 2008 Astrogen LLC (philippe at freepbx dot org)
 *
 */

// Change the version numbers and text and publish
$versionupgrade_upgrade_ver     = '2.10';
$versionupgrade_status = 'Final Release';

$versionupgrade_alpha_version   = $versionupgrade_upgrade_ver.'.0alpha0';
$versionupgrade_beta1_version   = $versionupgrade_upgrade_ver.'.0beta1';
$versionupgrade_beta1_0_version = $versionupgrade_beta1_version.'.0';
$versionupgrade_upgrade_final   = $versionupgrade_upgrade_ver.'.0';

function versionupgrade_setversion($version) {
	global $db;
	sql('DELETE FROM module_xml WHERE id = "xml"');
	$sql = "UPDATE admin SET value = '".$version."' WHERE variable = 'version'";
	$result = $db->query($sql); 
	if(DB::IsError($result)) {     
		return false;
	}
	return true;
}

function versionupgrade_configpageinit($pagename) {
	global $currentcomponent;
	$currentcomponent->addprocessfunc('versionupgrade_configprocess', 9);
} 

function versionupgrade_configprocess() {
	global $db;
	global $versionupgrade_alpha_version;
	global $versionupgrade_beta1_version;
	global $versionupgrade_beta1_0_version;

	$moduleinfo = module_getinfo('core');
	$mainversion = getversion();

	if (version_compare_freepbx($mainversion,$versionupgrade_alpha_version,"ge") && version_compare_freepbx($moduleinfo['core']['version'], $versionupgrade_alpha_version, "lt")) {
		$sql = "UPDATE admin SET value = 'false' WHERE variable = 'need_reload'"; 
		$result = $db->query($sql);
		if(DB::IsError($result)) {
			//can't do much?
		}
	} else if (version_compare_freepbx($mainversion,$versionupgrade_beta1_version,"ge") && version_compare_freepbx($moduleinfo['core']['version'], $versionupgrade_beta1_0_version, "ge")) {
		$results = module_delete('versionupgrade');
	}
}

function versionupgrade_allowed_modules($module) {
  global $versionupgrade_alpha_version;
  static $version;
  static $framework_version;
  static $core_version;

  // These should be cached by the functions but that was added late so in case not we will
  //
  if (!isset($version) || !$version) {
    $version = getversion();
  }
  if (!isset($framework_version) || !$framework_version) {
    $framework_version = get_framework_version();
  } 
  
  if (version_compare_freepbx($version, $versionupgrade_alpha_version,"lt")) {
    return true;
  } elseif (version_compare_freepbx($version, $versionupgrade_alpha_version,"eq")) {
    return ($module['rawname'] == 'framework');
  } else {
    if (!isset($core_version) || !$core_version) {
      $core_version = modules_getversion('core');
    }
    if (version_compare_freepbx($core_version, $versionupgrade_alpha_version,"lt")) {
      return ($module['rawname'] == 'core' || $module['rawname'] == 'framework' || $module['rawname'] == 'fw_ari');
    } else {
      return true;
    }
  }
}
?>
