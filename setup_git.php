#!/usr/bin/php -q
<?php
require_once('libraries/stash.php');
require_once('libraries/Git.php');
$longopts  = array(
    "force"
);
$options = getopt("",$longopts);

$username = getInput("Stash Username");
fwrite(STDOUT, "Stash Password: ");
$password = getPassword(true);
$stash = new Stash($username,$password);
$directory = getInput("Setup Directory",dirname(dirname(__FILE__)).'/freepbx');

if(!file_exists($directory)) {
	die($directory . " Does Not Exist \n");
}

$o = $stash->getAllRepos();

foreach($o['values'] as $repos) {
	$dir = $directory.'/'.$repos['name'];
	if($repos['name'] == 'devtools' || $repos['name'] == 'modules') {
		continue;
	}
	echo "Cloning ".$repos['name'] . " into ".$dir."\n";
	if(file_exists($dir) && isset($options['force'])) {
		echo $dir . " Already Exists but force is enabled so deleting and restoring\n";
		exec('rm -Rf '.$dir);
	} elseif(file_exists($dir)) {
		echo $dir . " Already Exists, Skipping (use --force to force)\n";
		continue;
	}
	Git::create($dir, $repos['cloneSSH']);
	echo "Done\n";
}





function getInput($msg,$default=null){
	if(!empty($default)) {
		$msg = $msg . " [$default]";
	}
	fwrite(STDOUT, "$msg: ");
	$varin = trim(fgets(STDIN));
	if(empty($varin)) {
		return $default;
	}
	return $varin;
}

function getPassword($stars = false)
{
    // Get current style
    $oldStyle = shell_exec('stty -g');

    if ($stars === false) {
        shell_exec('stty -echo');
        $password = rtrim(fgets(STDIN), "\n");
    } else {
        shell_exec('stty -icanon -echo min 1 time 0');

        $password = '';
        while (true) {
            $char = fgetc(STDIN);

            if ($char === "\n") {
                break;
            } else if (ord($char) === 127) {
                if (strlen($password) > 0) {
                    fwrite(STDOUT, "\x08 \x08");
                    $password = substr($password, 0, -1);
                }
            } else {
                fwrite(STDOUT, "*");
                $password .= $char;
            }
        }
    }

    // Reset old style
    shell_exec('stty ' . $oldStyle);
	echo "\n";
    // Return the password
    return $password;
}