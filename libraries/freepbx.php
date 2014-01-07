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
	 * Switch Branch on Repo
	 *
	 * Switch Branch on repo, will automatically checkout from remote if not exist
	 *
	 * @param   string $directory Location of repo
	 * @param	string $branch The branch to checkout
	 * @return  bool
	 */
	public static function switchBranch($directory,$branch) {
		freepbx::outn("Attempting to open ".$directory."...");
		//Attempt to open the module as a git repo, bail if it's not a repo
		try {
			$repo = Git::open($directory);
			freepbx::out("Done");
		} catch (Exception $e) {
			freepbx::out("Skipping");
			return false;
		}
		$stash = $repo->add_stash();
		if(!empty($stash)) {
			freepbx::out("\tStashing Uncommited changes..Done");
		}
		freepbx::outn("\tCleaning Untracked Files...");
		$repo->clean(true,true);
		freepbx::out("Done");
		freepbx::outn("\tFetching Changes...");
		$repo->fetch();
		freepbx::out("Done");
		freepbx::outn("\tChecking out ".$branch." ...");
		try {
			$repo->checkout($branch);
			freepbx::out("Done");
		} catch (Exception $e) {
			freepbx::out("Branch Doesnt Exist...Skipping");
		}
		if(!empty($stash) && empty($final_branch)) {
			freepbx::outn("\tRestoring Uncommited changes...");
			try {
				$repo->apply_stash();
				$repo->drop_stash();
				freepbx::out("Done");
			} catch (Exception $e) {
				freepbx::out("Failed to restore stash!, Please check your directory");
			}
		}
	}
	
	/**
	 * Refresh a local repo with changes from remote
	 *
	 * Updates from remote, it will attempt to stash your working changes first
	 *
	 * @param   string $directory Location of repo
	 * @param   string $remote The name of the remote origin
	 * @param	string $final_branch The final branch to checkout after updating (null means whatever it was on before)
	 * @return  bool
	 */
	public static function refreshRepo($directory, $remote = 'origin', $final_branch = null) {
		freepbx::outn("Attempting to open ".$directory."...");
		//Attempt to open the module as a git repo, bail if it's not a repo
		try {
			$repo = Git::open($directory);
			freepbx::out("Done");
		} catch (Exception $e) {
			freepbx::out("Skipping");
			return false;
		}
		$stash = $repo->add_stash();
		if(!empty($stash)) {
			freepbx::out("\tStashing Uncommited changes..Done");
		}
		freepbx::outn("\tCleaning Untracked Files...");
		$repo->clean(true,true);
		freepbx::out("Done");
		freepbx::outn("\tFetching Changes...");
		$repo->fetch();
		freepbx::out("Done");
		freepbx::outn("\tDetermine Active Branch...");
		$activeb = $repo->active_branch();
		freepbx::out($activeb);
		$lbranches = $repo->list_branches();
		$rbranches = $repo->list_remote_branches();
		foreach($rbranches as $k => &$rbranch) {
			if(preg_match('/'.$remote.'\/(.*)/i',$rbranch)) {
				$rbranch = str_replace($remote.'/','',$rbranch);
			}
		}
		freepbx::out("\tUpdating Branches...");
		$ubranches = array();
		foreach($lbranches as $branch) {
			freepbx::outn("\t\tUpdating ".$branch."...");
			$repo->checkout($branch);
			$repo->pull($remote, $branch);
			freepbx::out("Done");
			$ubranches[] = $branch;
		}
		foreach($rbranches as $branch) {
			if(!in_array($branch,$ubranches)) {
				freepbx::outn("\t\tChecking Out ".$branch."...");
				try {
					$repo->checkout($branch);
					freepbx::out("Done");
				} catch (Exception $e) {
					freepbx::out("Branch Doesnt Exist...Skipping");
				}
			}
		}
		
		$lbranches = $repo->list_branches();
		$branch = (!empty($final_branch) && in_array($final_branch,$lbranches)) ? $final_branch : $activeb;
		freepbx::out("\tPutting you back on ".$branch." ...");
		$repo->checkout($branch);
		freepbx::out("Done");
		if(!empty($stash) && empty($final_branch)) {
			freepbx::outn("\tRestoring Uncommited changes...");
			try {
				$repo->apply_stash();
				$repo->drop_stash();
				freepbx::out("Done");
			} catch (Exception $e) {
				freepbx::out("Failed to restore stash!, Please check your directory");
			}
		}
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
	function setupDevRepos($directory,$force=false) {
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
			$repo = Git::create($dir, $repos['cloneSSH']);
			freepbx::out("Done");
			
			freepbx::outn("\tChecking you out into the develop branch...");
			try {
				$repo->checkout('develop');
				freepbx::out("Done");
			} catch (Exception $e) {
				freepbx::out('develop does not exist');
			}
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
		$fwmoddir = $fwdir . '/amp_conf/htdocs/admin/modules';

		if(!file_exists($fwmoddir)) {
			mkdir($fwmoddir);
		}

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
	 * @param   int $tab Amount of tabs to prefix
	 */
	public static function outn($msg,$tab=0) {
		if(php_sapi_name() === 'cli') {
			$tab = !empty($tab) ? str_repeat("\t", $tab) : '';
			echo $tab.$msg;
		} else {
			$tab = !empty($tab) ? str_repeat("&nbsp;", $tab) : '';
			echo $tab.$msg;
		}
	}

	/**
	 * Echo with newline
	 *
	 * @param   string $msg Message to echo
	 * @param   int $tab Amount of tabs to prefix
	 */
	public static function out($msg,$tab=0) {
		if(php_sapi_name() === 'cli') {
			$tab = !empty($tab) ? str_repeat("\t", $tab) : '';
			echo $tab.$msg."\n";
		} else {
			$tab = !empty($tab) ? str_repeat("&nbsp;", $tab) : '';
			echo $tab.$msg."<br />";
		}
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
	
	public static function showHelp($script,$message,$short = false) {
		$final = '';
		$ret[] = $script;
		$ret[] = '-----------';
		$ret[] = '';
		if ($short) {
			$ret[] = 'SHORT OPS HAVE BEEN DEPRICATED - PLEASE USE ONLY LONG OPTS!';
		}

		//args
		foreach($message as $msg) {
			$ret[] = $msg;
		}

		$ret[] = '';

		//generate formated help message
		foreach ($ret as $r) {
			if (is_array($r)) {
				//pad the option
				$option = '  ' . str_pad($r[0], 20);

				//explode the definition to manageable chunks
				$def = explode('§', wordwrap($r[1], 55, "§", true));

				//and pad the def with whitespace 20 chars to the left stating from the second line
				if (count($def) > 1) {
					$first = array_shift($def);
					foreach ($def as $my => $item) {
						$def[$my] = str_pad('', 22) . $item . PHP_EOL;
					}
				} elseif (count($def) == 1) {
					$first = implode($def);
					$def = array();
				} else {
					$first = '';
					$def = array();
				}

				$definition = $first . PHP_EOL . implode($def);
				$final .= $option . $definition;
			} else {
				$final .=  $r . PHP_EOL;
			}
		}
		freepbx::out($final);
	}
	
	//test xml file for validity and extract some info from it
	public static function check_xml_file($mod_dir) {
		if(!file_exists($mod_dir . '/' . 'module.xml')) {
			freepbx::out('module.xml is missing');
			return array(false, false, false);
		}
		//check the xml script integrity
		libxml_use_internal_errors(true);
		$xml = @simplexml_load_file($mod_dir . '/' . 'module.xml');
		return self::check_xml($xml); 
	}
	
	public static function check_xml_string($xml) {
		libxml_use_internal_errors(true);
		$xml = @simplexml_load_string($xml);
		return self::check_xml($xml);
	}

	public static function check_xml($xml) {
		if($xml === FALSE) {
			freepbx::out('module.xml seems corrupt');
		    $errors = libxml_get_errors();

		    foreach ($errors as $error) {
			    switch ($error->level) {
			        case LIBXML_ERR_WARNING:
			            $ereturn = "Warning $error->code: ".trim($error->message);
			            break;
			         case LIBXML_ERR_ERROR:
			            $ereturn = "Error $error->code: ".trim($error->message);
			            break;
			        case LIBXML_ERR_FATAL:
			            $ereturn = "Fatal Error $error->code: ".trim($error->message);
			            break;
			    }
				freepbx::out("\t\t\t\tXML ".$ereturn);
		    }
			libxml_clear_errors();
			return array(false, false, false);
		}	
		//check that module name is set in module.xml
		$rawname = (string) $xml->rawname;
		if (!$rawname) {
			freepbx::out('module.xml is missing a module name');
			$rawname = false;
		}

		//check that module version is set in module.xml
		$version = (string) $xml->version;
		if (!$version) {
			freepbx::out('module.xml is missing a version number');
			$version = false;
		}

		//check that module version is set in module.xml
		$supported = (array) $xml->supported;
		if (!$supported) {
			freepbx::out('module.xml is missing supported tag');
			$supported = false;
		}		
		return array($rawname, $version, $supported);
	}

	//return the xml as an object
	public static function get_xml_file($mod_dir) {
		if(!file_exists($mod_dir . '/' . 'module.xml')) {
			freepbx::out('module.xml is missing');
			return false;
		}
		//check the xml script integrity
		$xml = simplexml_load_file($mod_dir . '/' . 'module.xml');
		return self::get_xml($xml);
	}

	public static function get_xml_string($xml) {
		$xml = simplexml_load_string($xml);
		return self::get_xml($xml);
	}

	public static function get_xml($xml) {
		if($xml === FALSE) {
			freepbx::out('module.xml seems corrupt');
			return false;
		}
		return $xml;
	}
	
	public static function run_dmc($cmd, &$outline='', $quiet = false, $duplex = false) {
		freepbx::out('Hip-hop is here to stay, Run-D.M.C. is here to stay.');
		freepbx::run_cmd($cmd, $outline, $quiet, $duplex);
	}
	
	// if $duplex set to true and in debug mode, it will echo the command AND run it
	public static function run_cmd($cmd, &$outline='', $quiet = false, $duplex = false) {
		global $vars;
		$quiet = $quiet ? ' 2>&1' : '';

		if (isset($vars['debug'])) {
			echo $cmd . PHP_EOL;
			if (!$duplex) {
				return true;
			}
		}
		ob_start();
		if (isset($vars['verbose'])) {
			$bt = debug_backtrace();
			echo PHP_EOL . '+' . $bt[0]["file"] . ':' . $bt[0]["line"] . PHP_EOL;
			echo "\t" . $cmd . PHP_EOL;
			exec($cmd . $quiet, $outline, $ret_val);
		} else {
			exec($cmd . $quiet, $outline, $ret_val);
		}
		ob_end_clean();
		return ($ret_val == 0);
	}
	
	// version_compare that works with FreePBX version numbers 
	public static function version_compare_freepbx($version1, $version2, $op = null) {
		$version1 = str_replace("rc","RC", strtolower($version1));
		$version2 = str_replace("rc","RC", strtolower($version2));
		if (!is_null($op)) {
			return version_compare($version1, $version2, $op);
		} else {
			return version_compare($version1, $version2);
		}
	}	
}
