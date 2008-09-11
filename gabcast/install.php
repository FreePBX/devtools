<?php

// Enable direct dial to Gabcast as a feature code
$connecttogabcast = _("Connect to Gabcast");
$fcc = new featurecode('gabcast', 'gabdial');
$fcc->setDescription($connecttogabcast);
$fcc->setDefault('*422');
$fcc->update();
unset($fcc);

?>
