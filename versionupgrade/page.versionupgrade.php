<?php
/* $Id: page.versionupgrade.php 3790 2007-02-16 18:52:53Z p_lindheimer $ */
//Copyright (C) 2008 Astrogen LLC 
//
//This program is free software; you can redistribute it and/or
//modify it under the terms of the GNU General Public License
//as published by the Free Software Foundation; either version 2
//of the License, or (at your option) any later version.
//
//This program is distributed in the hope that it will be useful,
//but WITHOUT ANY WARRANTY; without even the implied warranty of
//MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//GNU General Public License for more details.

$dispnum = 'versionupgrade'; //used for switch on config.php
$type = isset($_REQUEST['type'])?$_REQUEST['type']:'tool';
$upgraderequest = isset($_REQUEST['upgraderequest'])?$_REQUEST['upgraderequest']:'';

?>
</div>
<div class="content">

<?php
$version = getversion();
$framework_version = get_framework_version();
$core_xml = module_getinfo('core');
$core_version = $core_xml['core']['version'];

// These are from functions.inc.php, set all versions there
global $versionupgrade_status;
global $versionupgrade_alpha_version;
global $versionupgrade_upgrade_ver;
global $versionupgrade_upgrade_final;
global $versionupgrade_beta1_version;

if (version_compare_freepbx($version, $versionupgrade_alpha_version,"lt") && $upgraderequest == "UPGRADE") {
        versionupgrade_setversion($versionupgrade_alpha_version);
	$version = getversion();
}

echo "<h2>".sprintf(_("%s Upgrade Module"),$versionupgrade_upgrade_ver)."</h2>";
echo "<p><b>"._("Current Version Info (Updated each stage of upgrade process):")."</b></p>";
?>
<table>
<tr>
	<td><b>
		<?php echo _("FreePBX Base Version:") ?>
	</td></b>
	<td><b>
		<?php echo $version ?>
	</td></b>
</tr>
<tr>
	<td><b>
		<?php echo _("FreePBX Framework Version:") ?>
	</td></b>
	<td><b>
		<?php echo $framework_version ?>
	</td></b>
</tr>
<tr>
	<td><b>
		<?php echo _("FreePBX Core Version:") ?>
	</td></b>
	<td><b>
		<?php echo $core_version ?>
	</td></b>
</tr>
</table>
<?php
if (version_compare_freepbx($version, $versionupgrade_alpha_version,"lt")) {
          echo sprintf(_("<h2 align='center' style='color:red'>WARNING - YOU WILL BE UPGRADED TO VERSION %s %s</h2><p>This module will allow you to upgrade to FreePBX version %s from the current version, %s, that you are running.</p><p>The upgrade process is a simple but multi-step process that you should complete all at one time. As always when upgrading a system, it is advisable that you complete this process when the system is not actively being used.</p>The steps you will take are:<ol><li>Press the upgrade button below.</li><li>Go to Module Admin, check for online updates, and upgrade the <b>FreePBX Framework</b> module ONLY from the online repository which will now be connected to version %s.</li><li>Now, Check for online updates again and upgrade all other modules that have upgrades available. If you get dependency warnings, just repeat the process until all your modules have been upgraded.</li><li>Now press the Apply Configuration bar.</li></ol><p>   Once you have completed
          these step you will be upgraded to the new %s version and you may disable or remove this module if it does not do so automatically.</p>"),$versionupgrade_upgrade_final,$versionupgrade_status,$versionupgrade_upgrade_ver,$version,$versionupgrade_upgrade_ver,$versionupgrade_upgrade_ver);
?>

<br />
<form name="upgrade" action="<?php  $_SERVER['PHP_SELF'] ?>" method="post" onsubmit="return upgrade_onsubmit();">
	<input type="hidden" name="display" value="<?php echo $dispnum?>">
	<input type="hidden" name="type" value="<?php echo $type?>">
	<input type="hidden" name="upgraderequest" value="UPGRADE">
	<table>
		<tr>
			<td valign="top" class="label">
				<input type="submit" class="button" value="<?php echo _("UPGRADE NOW")?>">
			</td>
		</tr>
		
		<tr>
			<td height="8"></td>
			<td></td>
		</tr>
	</table>

	<script language="javascript">
	<!--
	var theForm = document.upgrade;
	function upgrade_onsubmit() {
		var msgConfirmUpgrade = "<?php echo sprintf(_("You are about to upgrade your system to FreePBX %s %s. You can not reverse this process. Do you want to continue?"),$versionupgrade_upgrade_final, $versionupgrade_status); ?>";
	
		if (!confirm(msgConfirmUpgrade)) {
				return false;
		}
	}
	//-->
	</script>
</form>

<?php
} else if (version_compare_freepbx($version, $versionupgrade_beta1_version,"lt")) {

	echo "<br />".sprintf(_("<p>You appear to have partially upgraded to version %s, your current version number is %s. The remaining steps you should take are:</p><ol><li>Go to Module Admin, press check for online updates, and upgrade the <b>FreePBX Framework</b> module from the online repository. Do not upgrade other modules.</li><li>Check for online updates again and upgrade all other modules that have upgrades available. If you get dependency warnings, simply repeat the process until all modules are upgraded.</li><li>Press the Apply Configuration Changes bar.</li></ol><p>Once you have completed these steps you will be upgraded to the new %s version and you may disable or remove this module if it it does not do so automatically.</p>"),$versionupgrade_upgrade_ver,$version,$versionupgrade_upgrade_ver);

}	else if (version_compare_freepbx($core_version, $versionupgrade_alpha_version, "lt")) {

	echo "<br />".sprintf(_("<p>You appear to have upgraded Framework to version %s, your current version number is %s. You still need to upgrade the Core Module and any other modules that show upgrades available. The remaining steps you should take are:</p><ol><li>Check for online updates again and upgrade all other modules that have upgrades available including Core. If you get dependency warnings simply repeat the process until all modules are upgraded.</li><li>Press the Apply Configuration Changes bar.</li></ol><p>Once you have completed these steps you will be upgraded to the new %s version and you may disable or remove this module if it it does not do so automatically.</p>"),$versionupgrade_upgrade_ver,$version,$versionupgrade_upgrade_ver);

} else {

	$results = module_delete($dispnum);
	redirect("config.php");

	echo sprintf(_("You have successfully upgraded to FreePBX version %s. If this module has not been automatically disabled you should go to Module Admin and disabled or uninstall this module at this time"),$version);
}

?>
