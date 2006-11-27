<?php
//CustomerDB 1.00 written by Keith Dowell 2006-04-07
//Copyright (C) 2006 Keith Dowell (snowolfex@yahoo.com)
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

$display = isset($_REQUEST['display'])?$_REQUEST['display']:'customerdb';
$type = isset($_REQUEST['type'])?$_REQUEST['type']:'tool';

$action = isset($_REQUEST['action'])?$_REQUEST['action']:'';
$name = isset($_REQUEST['name'])?$_REQUEST['name']:'';
$addr1 = isset($_REQUEST['addr1'])?$_REQUEST['addr1']:'';
$addr2 = isset($_REQUEST['addr2'])?$_REQUEST['addr2']:'';
$city = isset($_REQUEST['city'])?$_REQUEST['city']:'';
$state = isset($_REQUEST['state'])?$_REQUEST['state']:'LA';
$zip = isset($_REQUEST['zip'])?$_REQUEST['zip']:'';
$sip = isset($_REQUEST['sip'])?$_REQUEST['sip']:'';
$did = isset($_REQUEST['did'])?$_REQUEST['did']:'';
$device = isset($_REQUEST['device'])?$_REQUEST['device']:'';
$ip = isset($_REQUEST['ip'])?$_REQUEST['ip']:'';
$serial = isset($_REQUEST['serial'])?$_REQUEST['serial']:'';
$account = isset($_REQUEST['account'])?$_REQUEST['account']:'';
$email = isset($_REQUEST['email'])?$_REQUEST['email']:'';
$username = isset($_REQUEST['username'])?$_REQUEST['username']:'';
$password = isset($_REQUEST['password'])?$_REQUEST['password']:'';

extract($_REQUEST);

$dispnum='customerdb';

if(!isset($action))
	$action='';
switch($action) {
	case "add":
		customerdb_add($name, $addr1, $addr2, $city, $state, $zip, $sip, $did, $device, $ip, $serial, $account, $email, $username, $password);
		$name='';
		$addr1='';
		$addr2='';
		$city='';
		$state='';
		$zip='';
		$sip='';
		$did='';
		$ip='';
		$serial='';
		$account='';
		$email='';
		$device='';
		$username='';
		$password='';
		//needreload(); 
		//right now... not writing config files... don't need to reload
		redirect_standard();
	break;
	case "del":
		customerdb_del($extdisplay);
		//needreload();
		redirect_standard();
	break;
	case "edit":
		customerdb_edit($extdisplay, $name, $addr1, $addr2, $city, $state, $zip, $sip, $did, $device, $ip, $serial, $account, $email, $username, $password);
		//needreload();
		redirect_standard('extdisplay');
	break;
	
}
?>
</div>
<div class="rnav">
<?php
$customers=customerdb_list();
drawListMenu($customers, $skip, $type, $dispnum, $extdisplay, _("Customer"));
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
		$customerInfo=customerdb_get($extdisplay);
		$name=$customerInfo['name'];
		$addr1=$customerInfo['addr1'];
		$addr2=$customerInfo['addr2'];
		$city=$customerInfo['city'];
		$state=$customerInfo['state'];
		$zip=$customerInfo['zip'];
		$sip=$customerInfo['sip'];
		$did=$customerInfo['did'];
		$device=$customerInfo['device'];
		$serial=$customerInfo['serial'];
		$ip=$customerInfo['ip'];
		$account=$customerInfo['account'];
		$email=$customerInfo['email'];
		$username=$customerInfo['username'];
		$password=$customerInfo['password'];
	}
		
	
	if(isset($customerInfo) && is_array($customerInfo)){
		$action="edit";
		echo "<h2> ".$extdisplay." ".$name."</h2>";
		echo "<p><a href=\"".$delURL."\">Delete Customer</a></p>";
	}
	else {
		echo "<h2>Add Customer</h2>";
	}

}

echo "<form name=\"addNew\" action=\"".$_SERVER['PHP_SELF']."\" method=\"post\" onsubmit=\"return addNew_onsubmit();\">";
echo "<input type=hidden name=type value='tool'>\n";
echo "<input type=hidden name=extdisplay value=$extdisplay>\n";
echo "<input type=hidden name=action value=\"";
echo ($action=="" ? "add" : $action);
echo "\">\n";
echo "<input type=hidden name=display value=\"customerdb\">";

echo "<table>";
	
echo "<tr><td colspan=2><h5>";
echo ($extdisplay ? _('Edit Customer') : _('Add Customer'));
echo "</h5></td></tr>\n";

//Name
echo "<tr ";
echo ($extdisplay ? '' : '');
echo "><td>";
echo "<a href=\"#\" class=\"info\">Name\n";
echo "<span>Name of business or person (REQUIRED)</span></a>\n";
echo "</td>";
echo "<td>";
echo "<input type=text name=\"name\" value=\"$name\">\n";
echo "</td></tr>\n";

//Address Line 1
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Address 1\n";
echo "<span>Address Line 1 (REQUIRED)</span></a>\n";
echo "</td><td>\n";
echo "<input type=text name=\"addr1\" value=\"$addr1\"\n";
echo "</td></tr>\n";

//Address Line 2
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Address 2\n";
echo "<span>Address Line 2</span></a>\n";
echo "</td><td>\n";
echo "<input type=text name=\"addr2\" value=\"$addr2\">\n";
echo "</td><tr>\n";

//City
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">City\n";
echo "<span>City (REQUIRED)</span></a>\n";
echo "</td><td>\n";
echo "<input type=text name=\"city\" value=\"$city\">\n";
echo "</td></tr>\n";

//State
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">State\n";
echo "<span>State (REQUIRED)</span></a>\n";
echo "</td><td>\n";
$state=($extdisplay ? $state : "LA");
$states = array('AL', 'AK', 'AR', 'AZ', 'CA', 'CO', 'CT', 'DC', 'DE', 'FL', 'GA', 'HI', 'IA', 'ID', 'IL', 'IN', 'KS', 'KY', 'LA', 'MA','MD', 'ME', 'MI', 'MN', 'MO', 'MS', 'MT', 'NC', 'ND', 'NE', 'NH', 'NJ', 'NM', 'NV', 'NY', 'OH', 'OK', 'OR', 'PA', 'PR', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VA', 'VT', 'WA', 'WV', 'WI', 'WY', 'TAS', 'VIC', 'NSW', 'ACT', 'QLD', 'NT', 'SA'); 
echo "&nbsp;&nbsp;<select name=\"state\">\n";
foreach ($states as $s){
	echo "<option value=\"$s\"";
	if($state==$s) echo " SELECTED";
	echo ">$s</option>\n";
}
echo "</select>\n";
echo "</td></tr>\n";

//Zip
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Zip/Post Code\n";
echo "<span>Zip (REQUIRED)</span></a>\n";
echo "</td><td>\n";
echo "<input type=text name=\"zip\" value=\"$zip\">\n";
echo "</td></tr>\n";

//Sip
echo "<tr><td align=left>\n";
echo "<input type=radio checked name=\"sipbtn\" onclick=\"switchit_sip();return true;\"><a 
href=\"#\" class=\"info\">Sip Account\n";
echo "<span>Sip Account <font size=-1>(must have this or a did tied to the account)</font></span></a>\n";
echo "</td><td>\n";
$sips=customerdb_getsip();
echo "&nbsp;&nbsp;<select name=\"sip\" onchange=\"switchit_sip(); return true;\">\n";
echo "<option value=\"\">";
foreach ($sips as $sipid){
	echo "<option value=\"$sipid[0]\"";
	if($sip==$sipid[0]) echo " SELECTED";
	echo ">$sipid[0]</option>\n";
}
echo "</select>\n";
echo "</td></tr>\n";

//Did
echo "<tr><td>\n";
echo "<input type=radio name=\"didbtn\" onclick=\"switchit_did();return true;\"><a href=\"#\" class=\"info\">DID 
Number\n";
echo "<span>DID Number <font size=-1>(must have this or sip tied to the account)</font></span></a>\n";
echo "</td><td>\n";
$dids=customerdb_getdid();
echo "&nbsp;&nbsp;<select name=\"did\" onchange=\"switchit_did(); return true;\">\n";
echo "<option value=\"\">";
foreach ($dids as $didnum){
	echo "<option value=\"$didnum[0]\"";
	if($did==$didnum[0]) echo " SELECTED";
	echo ">$didnum[0]</option>\n";
}
echo "</select>\n";
echo "</td></tr>\n";

//Device
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Device\n";
echo "<span>Device <font size=-1>(example... Linksys PAP-2, Sipura)</font></span></a>\n";
echo "</td><td>\n";
echo "<input type=text name=\"device\" value=\"$device\">\n";
echo "</td></tr>\n";

//Serial
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Serial\n";
echo "<span>Serial Number</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"serial\" value=\"$serial\">\n";
echo "</td></tr>\n";

//IP
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">IP Address\n";
echo "<span>IP Address </font></span></a>\n";
echo "</td><td>\n";
echo "<input type=text name=\"ip\" value=\"$ip\">\n";
echo "</td></tr>\n";

//Account
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Account\n";
echo "<span>Account Number (internal use)</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"account\" value=\"$account\">\n";
echo "</td></tr>\n";

//Email
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Email\n";
echo "<span>Email Address</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"email\" value=\"$email\">\n";
echo "</td></tr>\n";

//Username
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Username\n";
echo "<span>Username for the device</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"username\" value=\"$username\">\n";
echo "</td></tr>\n";

//Password
echo "<tr><td>\n";
echo "<a href=\"#\" class=\"info\">Password\n";
echo "<span>Password for device</span></a>\n";
echo "</td><td>\n";
echo "<input name=\"password\" value=\"$password\">\n";
echo "</td></tr>\n";

?>
<tr><td></td><td><input type=submit Value="Submit Changes"></td></tr></table>

<script language="javascript">
var cform = document.addNew;
if(cform.name.value == ""){
	cform.name.focus();
}

if(cform.did.selectedIndex>0){
	cform.sipbtn.checked=false;
	cform.didbtn.checked=true;
}
else{
	if(cform.sip.selectedIndex>0){
		cform.sipbtn.checked=true;
		cform.didbtn.checked=false;
	}
	else{
		cform.sipbtn.checked=false;
		cform.didbtn.selected=false;
	}
}


function addNew_onsubmit() {
	if(isEmpty(cform.name.value)){
		return warnInvalid(cform.name, "Please enter a name for this customer");
	}
	if(isEmpty(cform.addr1.value)){
		return warnInvalid(cform.addr1, "Please enter an address for this customer");
	}
	if(isEmpty(cform.city.value)){
		return warnInvalid(cform.city, "Pleast enter a city for this customer");
	}
	if(isEmpty(cform.zip.value)){
		return warnInvalid(cform.zip, "Please enter a zip for this customer");
	}
	if(cform.sip.selectedIndex==0 && cform.did.selectedIndex==0){
		return warnInvalid(cform.sipbtn, "You must choose either a sip or did number for this customer.");
	}
}

function switchit_sip() {
	cform.sipbtn.checked=true;
	cform.didbtn.checked=false;
	cform.did[0].selected=true;
}

function switchit_did() {
	cform.sipbtn.checked=false;
	cform.didbtn.checked=true;
	cform.sip[0].selected=true;
}

</script>



</form>
