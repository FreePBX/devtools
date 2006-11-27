<?php
//inventoryDB 1.0.0 written by Richard Neese 2006-05-24
//Copyright (C) 2006 Richard Neese (r.neese@gmail.com)
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

//Set all the vars so there arent a ton of errors in the httpd error_log

$display = isset($_REQUEST['display'])?$_REQUEST['display']:'inventorydb';
$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:'';
$type = isset($_REQUEST['type'])?$_REQUEST['type']:'setup';
$action = isset($_REQUEST['action'])?$_REQUEST['action']:'';
$empnum = isset($_REQUEST['empnum'])?$_REQUEST['empnum']:'';
$empname = isset($_REQUEST['empname'])?$_REQUEST['empname']:'';
$building = isset($_REQUEST['building'])?$_REQUEST['building']:'';
$floor = isset($_REQUEST['floor'])?$_REQUEST['floor']:'';
$room = isset($_REQUEST['room'])?$_REQUEST['room']:'';
$section = isset($_REQUEST['section'])?$_REQUEST['section']:'';
$cubicle = isset($_REQUEST['cubicle'])?$_REQUEST['cubicle']:'';
$desk = isset($_REQUEST['desk'])?$_REQUEST['desk']:'';
$exten = isset($_REQUEST['exten'])?$_REQUEST['exten']:'';
$phusername = isset($_REQUEST['phusername'])?$_REQUEST['phusername']:'';
$phpassword = isset($_REQUEST['phpassword'])?$_REQUEST['phpassword']:'';
$mac = isset($_REQUEST['mac'])?$_REQUEST['mac']:'';
$serial = isset($_REQUEST['serial'])?$_REQUEST['serial']:'';
$device = isset($_REQUEST['device'])?$_REQUEST['device']:'';
$distdate = isset($_REQUEST['distdate'])?$_REQUEST['distdate']:'';
$ip = isset($_REQUEST['ip'])?$_REQUEST['ip']:'';
$pbxbox = isset($_REQUEST['pbxbox'])?$_REQUEST['pbxbox']:'';
$extrainfo = isset($_REQUEST['extrainfo'])?$_REQUEST['extrainfo']:'';

extract($_REQUEST);

$dispnum='inventorydb';

if(!isset($action))
	$action='';
switch($action) {
	case "add":
		inventorydb_add($empnum, $empname, $building, $floor, $room, $section, $cubicle, $desk, $exten, $phusername, $phpassword, $mac, $serial, $device, $distdate, $ip, $pbxbox, $extrainfo);
		$empnum='';
		$empname='';
		$building='';
		$floor='';
		$room='';
		$section='';
		$cubicle='';
		$desk='';
		$exten='';
		$phusername='';
		$phpassword='';
		$mac='';
		$serial='';
		$device='';
		$distdate='';
		$ip='';
		$pbxbox='';
		$extrainfo='';
		//needreload();
		//right now... not writing config files... don't need to reload
		redirect_standard();
	break;
	case "del":
		inventorydb_del($extdisplay);
		//needreload();
		redirect_standard();
	break;
	case "edit":
		inventorydb_edit($extdisplay, $empnum, $empname, $building, $floor, $room, $section, $cubicle, $desk, $exten, $phusername, phpassword, $mac, $serial, $device, $distdate, $ip, $pbxbox, $extrainfo);
		//needreload();
		redirect_standard();
	break;

}
?>
</div>
<div class="rnav">
<?php
$inventorys=inventorydb_list();
drawListMenu($inventorys, $skip, $type, $dispnum, $extdisplay, _("inventory"));
?>
</div>


<div class="content">
<?php
if($action=='del'){
	echo "<br><h3>ID ".$extdisplay." "._("deleted")."!</h3><br><Br><br><br><br><br><br>";
}
else if(!isset($extdisplay)) {


	echo "<h2>Add a user</h2>";
//	echo "<li><a href=\"".$_SERVER['PHP_SELF']."?$action=add\";>Add</a><br>";

}
else {
	$delURL = $_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING']."&action=del&extdisplay=$extdisplay";

	//If we have some data, load it up... this means we are editing.
	if($extdisplay!=""){
		$inventoryInfo=inventorydb_get($extdisplay);
		$empnum=$inventoryInfo['empnum'];
		$empname=$inventoryInfo['empname'];
		$building=$inventoryInfo['building'];
		$floor=$inventoryInfo['floor'];
		$room=$inventoryInfo['room'];
		$section=$inventoryInfo['section'];
		$cubicle=$inventoryInfo['cubicle'];
		$desk=$inventoryInfo['desk'];
		$exten=$inventoryInfo['exten'];
		$phusername=$inventoryInfo['phusername'];
		$phpassword=$inventoryInfo['phpassword'];
		$mac=$inventoryInfo['mac'];
		$serial=$inventoryInfo['serial'];
		$device=$inventoryInfo['device'];
		$distdate=$inventoryInfo['distdate'];		
		$ip=$inventoryInfo['ip'];
		$pbxbox=$inventoryInfo['pbxbox'];
		$extrainfo=$inventoryInfo['extrainfo'];
	}

	if(isset($inventoryInfo) && is_array($inventoryInfo)){
		$action="edit";
		echo "<h2> ".$extdisplay." ".$empname."</h2>";
		echo "<p><a href=\"".$delURL."\">Delete inventory</a></p>";
	}
	else {
		echo "<h2>Add inventory</h2>";
	}

}

echo "<form name=\"addNew\" action=\"".$_SERVER['PHP_SELF']."\" method=\"post\" onsubmit=\"return addNew_onsubmit();\">";
echo "<input type=hidden name=extdisplay value=$extdisplay>\n";
echo "<input type=hidden name=action value=\"";
echo ($action=="" ? "add" : $action);
echo "\">\n";
echo "<input type=hidden name=display value=\"inventorydb\">";

echo "<table>";

echo "<tr><td colspan=2><h5>";
echo ($extdisplay ? _('Edit inventory') : _('Add inventory'));
echo "</h5></td></tr>\n";

//empnum
echo "<tr ";
echo ($extdisplay ? '' : '');
echo "><td>";
echo "<a href=\"#\" class=\"info\">Employee #\n";
echo "<span>Employee Number</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"empnum\" value=\"$empnum\">\n";
echo "</td></tr>\n";

//empname
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Employee Name\n";
echo "<span>Employee Name</span></a>\n";
echo "</td>";
echo "<td>";
echo "<input type=text name=\"empname\" value=\"$empname\">\n";
echo "</td></tr>\n";

//building
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Building Located\n";
echo "<span>Building where the phone is located</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"building\" value=\"$building\"\n";
echo "</td></tr>\n";

//floor
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Floor #\n";
echo "<span>Floor # phone is on</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"floor\" value=\"$floor\">\n";
echo "</td><tr>\n";

//room
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Room #\n";
echo "<span>Room phone is in</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"room\" value=\"$room\">\n";
echo "</td></tr>\n";

//section
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Floor Section #\n";
echo "<span>Floor Section # the phone is in</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"section\" value=\"$section\">\n";
echo "</td></tr>\n";

//cubicle
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Cubicle #\n";
echo "<span>Cubicle phone is in</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"cubicle\" value=\"$cubicle\">\n";
echo "</td></tr>\n";

//desk
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Desk #\n";
echo "<span>Desk Number phone is on</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"desk\" value=\"$desk\">\n";
echo "</td></tr>\n";

//exten
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Extension #\n";
echo "<span>Exten Assigned to the phone</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"exten\" value=\"$exten\">\n";
echo "</td></tr>\n";

//phusername
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Phone UserName\n";
echo "<span>Phone Admin Username</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"phusername\" value=\"$phusername\">\n";
echo "</td></tr>\n";

//phpassword
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Phone Password\n";
echo "<span>Phone Admin Password </span></a>\n";
echo "</td><td>\n";
echo "<input name=\"phpassword\" value=\"$phpassword\">\n";
echo "</td></tr>\n";

//mac
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">MAC Address\n";
echo "<span>MAC Address of phone</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"mac\" value=\"$mac\">\n";
echo "</td></tr>\n";

//Serial
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Serial #\n";
echo "<span>Serial Number of the phone</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"serial\" value=\"$serial\">\n";
echo "</td></tr>\n";

//Device
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Phone/Device\n";
echo "<span>Device <font size=-1>(example... Linksys PAP-2, Sipura)</font></span></a>\n";
echo "</td><td>\n";
echo "<input type=text name=\"device\" value=\"$device\">\n";
echo "</td></tr>\n";

//DistDate
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Distributed Date\n";
echo "<span>Distribution Date</font></span></a>\n";
echo "</td><td>\n";
echo "<input name=\"distdate\" value=\"$distdate\">\n";
echo "</td></tr>\n";

//IP
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">IP Address\n";
echo "<span>IP Address Assigned If not DHCP</font></span></a>\n";
echo "</td><td>\n";
echo "<input name=\"ip\" value=\"$ip\">\n";
echo "</td></tr>\n";

//pbxbox
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">PBX Box Name\n";
echo "<span>PBX Box Name</span></a>\n";
echo "</td><td>\n";
echo "<input type=text name=\"pbxbox\" value=\"$pbxbox\">\n";
echo "</td></tr>\n";

//extrainfo
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Extra Info\n";
echo "<span>Extra Information</span></span></a>\n";
echo "</td><td>\n";
echo "<input name=\"extrainfo\" value=\"$extrainfo\">\n";
echo "</td></tr>\n";


?>
<tr><td></td><td><input type=submit Value="Submit Changes"></td></tr></table>

</script>



</form>
