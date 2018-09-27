<?php
/**
 * Purpose of this file is to add empty objects that phpunit would commonly need
 * to work with FreePBX without FreePBX. This way devtools + module can be
 * checked out to run unit tests but FreePBX/Asterisk never needs to be installed
 * @var [type]
 */
namespace FreePBX;
require_once __DIR__.'/vendor/autoload.php';
date_default_timezone_set('UTC');
interface BMO {

}

class FreePBX_Helpers {

}

class Request_Helper {

}

class DB_Helper {

}

class Freepbx_conf {

}

class_alias('FreePBX\BMO', 'BMO');
class_alias('FreePBX\FreePBX_Helpers', 'FreePBX_Helpers');
class_alias('FreePBX\Request_Helper', 'Request_Helper');
class_alias('FreePBX\DB_Helper', 'DB_Helper');
class_alias('FreePBX\Freepbx_conf', 'Freepbx_conf');
