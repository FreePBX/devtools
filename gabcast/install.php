<?php

// Enable direct dial to Gabcast as a feature code
$fcc = new featurecode('gabcast', 'gabdial');
$fcc->setDescription('Connect to Gabcast');
$fcc->setDefault('*422');
$fcc->update();
unset($fcc);

?>
