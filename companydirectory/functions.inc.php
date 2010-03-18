<?php
function companydirectory_configpageload() {
	global $currentcomponent,$display;
	if ($display == 'companydirectory' && ($_REQUEST['action']=='add'||$_REQUEST['id']!='')) { 
		$currentcomponent->addguielem('_top', new gui_pageheading('title', _('Company Directory')), 0);
		$dir=companydirectory_get_dir_details($_REQUEST['id']);
		$currentcomponent->addguielem('', new gui_textbox('dirname', $dir['dirname'], _('Directory Name'), _('Name of this directory.')));
		$currentcomponent->addguielem('', new gui_textbox('description', $dir['description'], _('Directory description'), _('Description of this directory.')));
		$section = _('Directory Options');
		
		//build recordings select list
		$currentcomponent->addoptlistitem('recordings', '', _('None'));
		foreach(recordings_list() as $r){
			$currentcomponent->addoptlistitem('recordings', $r['id'], _($r['displayname']));
		}
		//build repeat_loops select list and defualt it to 3
		for($i=0; $i <11; $i++){
			$currentcomponent->addoptlistitem('repeat_loops', $i, $i);
		}
		if($dir['repeat_loops']==''){$repeat_loops=3;}
		
		//generate page
		$currentcomponent->addguielem($section, new gui_selectbox('announcement', $currentcomponent->getoptlist('recordings'), $dir['announcement'], 'Announcement', 'Greetinging to be played on entry to the directory', false));
		$currentcomponent->addguielem($section, new gui_selectbox('valid_recording', $currentcomponent->getoptlist('recordings'), $dir['valid_recording'], 'Valid Recording', 'Promt to be played to caller prior to sending them to their requested destination.', false));
		$currentcomponent->addguielem($section, new gui_textbox('callid_prefix', $dir['callid_prefix'], _('CallerID Name Prefix'), _('Prefix to be appended to current CallerID Name.')));
		$currentcomponent->addguielem($section, new gui_textbox('alert_info', $dir['alert_info'], _('Alert Info'), _('ALERT_INFO to be sent with called from this Directory. Can be used for ditinctive ring for SIP devices.')));
		$currentcomponent->addguielem($section, new gui_selectbox('repeat_loops', $currentcomponent->getoptlist('repeat_loops'), $dir['repeat_loops'], 'Invalid Retries', 'Number of times to retry when receving an invalid/unmatched responce from the caller', false));
		$currentcomponent->addguielem($section, new gui_selectbox('repeat_recording', $currentcomponent->getoptlist('recordings'), $dir['repeat_recording'], 'Invalid Retry  Recording', 'Promt to be played when an invalid/unmatched responce is received, before promting the caller to try again', false));
		$currentcomponent->addguielem($section, new gui_selectbox('invalid_recording', $currentcomponent->getoptlist('recordings'), $dir['invalid_recording'], 'Invalid Recording', 'Promt to be played when before send the caller to an allternate destinatio due to receivng the maximum amount of invalid/unmatched responce (as determaind by Invalid Retires)', false));
		$currentcomponent->addguielem($section, new gui_drawselects('invalid_destination', 0, $dir['invalid_destination'], _('Invalid Destination'), _('Destination to send the call to after Invalid Recording is played.'), false));
		$currentcomponent->addguielem($section, new gui_hidden('id', $dir['id']));
		$currentcomponent->addguielem($section, new gui_hidden('action', 'edit'));
		//draw the entries part of the table. A bit hacky perhaps, but hey - it works!
		$currentcomponent->addguielem('Directory_Entries', new guielement('rawhtml', companydirectory_draw_entires($_REQUEST['id']), ''));
	}
}

function companydirectory_configpageinit($pagename) {
	global $currentcomponent;
	$currentcomponent->addprocessfunc('companydirectory_configprocess',1);
	$currentcomponent->addprocessfunc('companydirectory_configpageload',1);
	//$currentcomponent->addprocessfunc('companydirectory_shpwpage',5);
}


//prosses recived arguments
function companydirectory_configprocess() {
	global $db,$amp_conf;
	//get variables for directory_details
	$requestvars=array('id','dirname','description','announcement','valid_recording','callid_prefix',
									'alert_info','repeat_loops','repeat_recording','invalid_recording','invalid_destination');
	foreach($requestvars as $var){
		$vars[$var]=isset($_REQUEST[$var])?$_REQUEST[$var]:'';
	}

	$action=isset($_REQUEST['action'])?$_REQUEST['action']:'';
	$entries=isset($_REQUEST['entries'])?$_REQUEST['entries']:'';
	$entries=array_values($entries);//reset keys

	//get real dest
	$vars['invalid_destination']=$_REQUEST[$_REQUEST[$_REQUEST['invalid_destination']].str_replace('goto','',$_REQUEST['invalid_destination'])];

	if($action=='edit'){
		companydirectory_save_dir_details($vars);
		//if there was no id set, get the latest one that was saved
		if($vars['id']==''){
			if($amp_conf["AMPDBENGINE"]=="sqlite3"){
				$sql='SELECT last_insert_rowid()';
			}else{
				$sql='SELECT LAST_INSERT_ID()';
			}
			$vars['id']=$db->getOne($sql);
		}
		companydirectory_save_dir_entries($vars['id'],$entries);
		}
}

function companydirectory_get_dir_entries($id){
	global $db;
	$sql='SELECT * FROM directory_entries WHERE ID = ?';
	$results=$db->getAll($sql,array($id),DB_FETCHMODE_ASSOC);
	dbug('get_dir_entries',$results);
	return $results;
}

function companydirectory_get_dir_details($id){
	global $db;
	$sql='SELECT * FROM directory_details WHERE ID = ?';
	$row=$db->getRow($sql,array($id),DB_FETCHMODE_ASSOC);
	return $row;
}

function companydirectory_drawListMenu(){
	global $db;
	$sql='SELECT id,dirname FROM directory_details ORDER BY dirname';
	$results=$db->getAll($sql,DB_FETCHMODE_ASSOC);
	echo '<div class="rnav"><ul>'."\n";
	echo "\t<li><a ".($id==''?'class="current"':'').' href="config.php?type=tool&display=companydirectory&action=add">'._('Add Company Directory')."</a></li>\n";
	if($results){
		foreach ($results as $key=>$result){
			if(!$result['dirname']){$result['dirname']='Directory '.$result['id'];}
			echo "\t<li><a".($id==$result['id'] ? ' class="current"':''). ' href="config.php?type=tool&display=companydirectory&id='.$result['id'].'">'.$result['dirname']."</a></li>\n";
		}
	}
	echo "</ul>\n<br /></div>";
}

function companydirectory_draw_entires($id){
	global $db;
	$sql='SELECT id,name FROM directory_details ORDER BY name';
	$results=$db->getAll($sql,DB_FETCHMODE_ASSOC);
	$html='';
	$html.='<table id="dir_entires_tbl">';
	//$html.='<th>Name</th><th>Name Announcement</th><th>Dial</th>';
	$newuser='<select id="addusersel">';
	$newuser.='<option value="" selected></option>';
	$newuser.='<option value="">Custom</option>';
	foreach(core_users_list() as $user){
		$newuser.='<option value="'.$user[0].'|'.$user[1].'">('.$user[0].') '.$user[1].'</option>';
	}
	$newuser.='</select>';
	$html.='<tfoot><tr><td id="addbut"><a href="#" class="info"><input type="image" src="images/core_add.png" name="image" style="border:none;"><span>'._('Add new entire.').'</span></a></td><td id="addrow">'.$newuser.'</td></tr></tfoot>';
	$html.='<tbody>';
	$entries=companydirectory_get_dir_entries($id);
	$arraynum=1;
	$entries[]=array('name'=>'','audio'=>'','dial'=>'');
	//$entries[]=array('name'=>'','audio'=>'','dial'=>'');
	//$entries[]=array('name'=>'','audio'=>'','dial'=>'');
	foreach($entries as $e){
		$html.=companydirectory_draw_entires_tr($e['name'],$e['audio'],$e['dial'],$arraynum++);
	}
	$html.='</tbody></table>';
	return $html;
}

//used to add row's the the entries table
function companydirectory_draw_entires_tr($name='',$audio='',$num='',$id=''){
	global $directory_draw_recordings_list;//make gloabl, so its only drawn once
	if(!$directory_draw_recordings_list){$directory_draw_recordings_list=recordings_list();}
	if(!$id){$id=rand(100000,999999);}
	$audio='<select name="entries['.$id.'][audio]">';
	$audio.='<option value="vm" '.(($audion=='vm')?'SELECTED':'').'>'._('Voicemail Greeting').'</option>';
	$audio.='<option value="tts" '.(($audion=='vm')?'SELECTED':'').'>'._('Text to Speech').'</option>';
	$audio.='<option value="spell" '.(($audion=='vm')?'SELECTED':'').'>'._('Spell Name').'</option>';
	$audio.='<optgroup label="'._('System Recordings:').'">';
	foreach($directory_draw_recordings_list as $r){
		$audio.='<option value="'.$r['id'].'" '.(($audion==$r['id'])?'SELECTED':'').'>'.$r['displayname'].'</option>';
	}
	$audio.='</select>';

	
	$delete='<img src="images/trash.png" style="cursor:pointer;" alt="'._('remove').'" title="'._('Click here to remove this pattern').'" onclick="$(\'.entrie'.$id.'\').fadeOut(500,function(){$(this).remove()})">';
		
	$html='<tr class="entrie'.$id.'"><td><input type="text" name="entries['.$id.'][name]" value="'.$name.'" /></td><td>'.$audio.'</td><td><input type="text" name="entries['.$id.'][num]" value="'.$num.'" /></td><td>'.$delete.'</td></tr>';
	return $html;
}

function companydirectory_save_dir_details($vals){
	global $db;
	$sql='REPLACE INTO directory_details VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
	$foo=$db->query($sql,$vals);
	if (DB::IsError($foo)){
    dbug($foo->getDebugInfo());
	}
}

function companydirectory_save_dir_entries($id,$entries){
	global $db;
	$sql='DELETE FROM directory_entries WHERE id =?';
	$foo=$db->query($sql,array($id));
	if (DB::IsError($foo)){
    dbug($foo->getDebugInfo());
	}
	if($entries){
		$insert='';
		foreach($entries as $idx => $row){
			$insert.='("'.$id.'","'.$row['name'].'","'.$row['audio'].'","'.$row['num'].'")';
			if(count($entries) != $idx+1){//add a , if its not the last entrie
				$insert.=',';
			}
		}		
		$sql='INSERT INTO directory_entries VALUES '.$insert;
		dbug('entires $sql',$sql);
		$foo=$db->query($sql);
		if (DB::IsError($foo)){
	    dbug($foo->getDebugInfo());
		}
	}
}
?>