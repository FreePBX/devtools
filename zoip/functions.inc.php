<?php
 /* $Id:$ */

// returns a associative arrays with keys 'destination' and 'description'
function zoip_destinations() {
	// return an associative array with destination and description
	$extens[] = array('destination' => 'app-zoip,s,1', 'description' => 'ZoIP (Zork)');
	return $extens;
}

function zoip_get_config($engine) {
        $modulename = 'zoip';

        // This generates the dialplan
        global $ext;
	global $amp_conf;

        switch($engine) {
		case "asterisk":
			$ext->add('app-zoip', 's', '', new ext_answer());
			$ext->add('app-zoip', 's', '', new ext_agi($amp_conf['AMPWEBROOT'].'/admin/modules/zoip/sphinx_server.pl'));
			$ext->add('app-zoip', 's', '', new ext_playback('wait-moment'));
			$ext->add('app-zoip', 's', '', new ext_noop('Letting the Sphinx server start.'));
			$ext->add('app-zoip', 's', '', new ext_wait(2));
			$ext->add('app-zoip', 's', '', new ext_agi($amp_conf['AMPWEBROOT'].'/admin/modules/zoip/zoip.agi'));
		break;
        }
}

?>
