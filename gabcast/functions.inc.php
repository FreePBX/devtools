<?php
 /* $Id:$ */

function gabcast_hook_core($viewing_itemid, $target_menuid) {
	if ($viewing_itemid != "" & ($target_menuid == 'extensions' | $target_menuid == 'users')) { 
		$list = gabcast_get($viewing_itemid);
		if (is_array($list)) {
			$res = "<p><a href='".$_SERVER['PHP_SELF']."?display=gabcast&amp;ext=$viewing_itemid&amp;action=edit'>";
			$res .= _("Edit Gabcast Settings")."</a></p>";
		} else {
			$res = "<p><a href='".$_SERVER['PHP_SELF']."?display=gabcast&amp;ext=$viewing_itemid&amp;action=add'>";
			$res .= _("Add Gabcast Settings")."</a></p>";
		}
		return $res;
	}
}

// returns a associative arrays with keys 'destination' and 'description'
function gabcast_destinations() {
	//get the list of meetmes
	$results = gabcast_list();

	// return an associative array with destination and description
	if (isset($results)) {
		foreach($results as $result){
				$extens[] = array('destination' => 'gabcast,'.$result['0'].',1', 'description' => 'gabcast channel '. $result['1'].' <'.$result[0].'>');
		}
	return $extens;
	} else {
	return null;
	}
}

function gabcast_get_config($engine) {
        $modulename = 'gabcast';

        // This generates the dialplan
        global $ext;
        switch($engine) {
			case "asterisk":
					if (is_array($featurelist = featurecodes_getModuleFeatures($modulename))) {
							foreach($featurelist as $item) {
									$featurename = $item['featurename'];
									$fname = $modulename.'_'.$featurename;
									if (function_exists($fname)) {
											$fcc = new featurecode($modulename, $featurename);
											$fc = $fcc->getCodeActive();
											unset($fcc);

											if ($fc != '')
													$fname($fc);
									} else {
											$ext->add('from-internal-additional', 'debug', '', new ext_noop($modulename.": No func $fname"));
											var_dump($item);
									}
							}
				   } else {
						$ext->add('from-internal-additional', 'debug', new ext_noop($modulename.": No modules??"));
				   }
				   
				   $context = "gabcast";
				   $ext->add($context, '_X.', '', new ext_dial('IAX2/iax.gabcast.com/422,120'));
				   $ext->add($context, 's', '', new ext_dial('IAX2/iax.gabcast.com/422,120'));
				   
				   $gablist = gabcast_list();
				   if($gablist) {
					   foreach ($gablist as $gab) {
						   $extension = $gab[0];
						   $channbr = $gab[1];
						   $pin = $gab[2];
						   
						   $ext->add($context, $extension, '', new ext_dial('IAX2/iax.gabcast.com/'.$channbr.'*'.$pin.',120'));
					   }
				   }
			break;
        }
}

function gabcast_list() {
        global $db;

        $sql = "SELECT * FROM gabcast ORDER BY channbr";
        $results = $db->getAll($sql);
        if(DB::IsError($results)) {
                $results = null;
        }
        return $results;
}

function gabcast_get($xtn) {
        global $db;

        $sql = "SELECT * FROM gabcast where ext='$xtn'";
        $results = $db->getRow($sql);
        if(DB::IsError($results)) {
                $results = null;
        }
        return $results;
}

function gabcast_add($xtn, $channbr, $pin) {
		// fail if this exten already exists in DB
		if(is_array(gabcast_get($xtn))) {
			echo "<div class=error>An error occured when writing to database</div>";
			return;
		}
        sql("INSERT INTO gabcast (ext, channbr, pin) values ('{$xtn}','{$channbr}','{$pin}')");
}

function gabcast_del($xtn) {
	sql("DELETE FROM gabcast WHERE ext = '{$xtn}'");
}

function gabcast_edit($xtn, $channbr, $pin) {
	gabcast_del($xtn);
	sql("INSERT INTO gabcast (ext, channbr, pin) values ('{$xtn}','{$channbr}','{$pin}')");
}

function gabcast_gabdial($c) {
        global $ext;

        $id = "app-gabcast"; // The context to be included
		$ext->addInclude('from-internal-additional', $id);
		$ext->add($id, $c, '', new ext_goto('1','${CALLERID(num)}','gabcast'));
/*		
        $ext->add($id, $c, '', new ext_macro('user-callerid'));
        $ext->add($id, $c, '', new ext_noop('Checking for ${CALLERID(num)}'));
	$ext->add($id, $c, '', new ext_gotoif('$[ ${DB_EXISTS(GABCAST/${CALLERID(num)} = 1 ]', 'hasgabcast:nogabcast'));
	$ext->add($id, $c, 'hasgabcast', new ext_setvar('DIALSTRING', 'IAX2/iax.gabcast.com/${DB_RESULT}'));
	$ext->add($id, $c, '', new ext_goto('dodial'));
	$ext->add($id, $c, 'nogabcast', new ext_setvar('DIALSTRING', 'IAX2/iax.gabcast.com/gab'));
	$ext->add($id, $c, 'dodial', new ext_dial('${DIALSTRING},120'));
*/
}

?>
