<?php
if (version_compare(PHP_VERSION, '5.3.0') <= 0) {
	die("This Script Requires PHP 5.3.0+\n");
}
require_once('stash.php');
require_once('Git.php');
require_once('xml2Array.class.php');

class freepbx {

	/**
	 * Setup FreePBX Class to talk to FreePBX.org services
	 *
	 * @param   string $username FreePBX.org Username
	 * @param   string $password FreePBX.org password
	 * @return  array
	 */
	function __construct($username,$password) {
		$this->stash = new Stash($username,$password);
	}

	/**
	 * Setup GIT Repos from Stash
	 *
	 * Lists all repos from remote project and downloaded them, skipping devtools
	 *
	 * @param   string $directory Directory to work with
	 * @param   bool $force True or False on whether to rm -Rf and then recreate the repo
	 * @return  array
	 */
	function setupDevLinks($directory,$force=false) {
		$o = $this->stash->getAllRepos();

		foreach($o['values'] as $repos) {
			$dir = $directory.'/'.$repos['name'];
			if($repos['name'] == 'devtools') {
				continue;
			}
			freepbx::out("Cloning ".$repos['name'] . " into ".$dir);
			if(file_exists($dir) && $force) {
				freepbx::out($dir . " Already Exists but force is enabled so deleting and restoring");
				exec('rm -Rf '.$dir);
			} elseif(file_exists($dir)) {
				freepbx::out($dir . " Already Exists, Skipping (use --force to force)");
				continue;
			}
			Git::create($dir, $repos['cloneSSH']);
			freepbx::out("Done");
		}
	}

	/**
	 * Sets Up Module and framework symlinks
	 *
	 * @param   string $directory Directory to work with
	 * @return  array
	 */
	function setupSymLinks($directory) {
		$fwdir = $directory . '/framework';
		$fwmoddir = $fwdir . '/amp_conf/htdocs/admin/modules/';

		$dirs = array_filter(glob($directory.'/*'), 'is_dir');

		foreach($dirs as $dirkey => $dirpath) {
			if ($fwdir != $dirpath) {
				$modlink = $fwmoddir . '/' . basename($dirpath);

				//remove if we have a link
				if (is_link($modlink)) {
					freepbx::out($modlink . " is already linked...Unlinking");
					unlink($modlink);
				}

				$linkMsg = "Linking " . $dirpath . " to " . $modlink . "...";
				if(symlink($dirpath, $modlink)) {
					$linkMsg .= 'Success';
				} else {
					$linkMsg .= 'Failed';
				}
				freepbx::out($linkMsg);
			}
		}
	}

	/**
	 * Get .freepbxconfig from users home folder
	 *
	 * @return array of config variables and values
	 */
	public static function getFreePBXConfig() {
		$homedir = getenv("HOME");
		$freepbxconfig = $homedir . '/' . '.freepbxconfig';
		$config = array();

		if (file_exists($freepbxconfig)) {
			$config = parse_ini_file($freepbxconfig);
		}
		return $config;
	}

    /**
	 * Echo without newline
	 *
	 * @param   string $msg Message to echo
	 */
	public static function outn($msg) {
		echo $msg;
	}

	/**
	 * Echo with newline
	 *
	 * @param   string $msg Message to echo
	 */
	public static function out($msg) {
		echo $msg."\n";
	}

	/**
	 * Get User Input string from terminal STDIN
	 *
	 * @param   string $message The prompt to display to client
	 * @param   string $default The default value if client hits enter
	 * @return  string The final result string
	 */
	public static function getInput($msg,$default=null) {
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

	/**
	 * Get Password (hidden input) string from terminal STDIN
	 *
	 * @param   string $message The prompt to display to client
	 * @param   bool $starts True to display stars, false to display nothing
	 * @return  string The final result password
	 */
	public static function getPassword($msg,$stars = false) {
		fwrite(STDOUT, "$msg: ");
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
}
