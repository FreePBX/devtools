<?php
require_once('stash.php');
require_once('Git.php');

class freepbx {
	
	/**
	 * Gets all information about said user from Stash
	 *
	 * @param   string $username Stash Username 
	 * @param   string $password Stash password
	 * @return  array
	 */
	function __construct($username,$password) {
		$this->stash = new Stash($username,$password);
	}
	
	/**
	 * Gets all information about said user from Stash
	 *
	 * @param   string $directory Directory to
	 * @param   bool $force True or False on whether to rm and recreate the lib
	 * @return  array
	 */
	function setupDevLinks($directory,$force=false) {
		$o = $this->stash->getAllRepos();

		foreach($o['values'] as $repos) {
			$dir = $directory.'/'.$repos['name'];
			if($repos['name'] == 'devtools' || $repos['name'] == 'modules') {
				continue;
			}
			echo "Cloning ".$repos['name'] . " into ".$dir."\n";
			if(file_exists($dir) && $force) {
				echo $dir . " Already Exists but force is enabled so deleting and restoring\n";
				exec('rm -Rf '.$dir);
			} elseif(file_exists($dir)) {
				echo $dir . " Already Exists, Skipping (use --force to force)\n";
				continue;
			}
			Git::create($dir, $repos['cloneSSH']);
			echo "Done\n";
		}
	}
	
	/**
	 * Gets all information about said user from Stash
	 *
	 * @param   string $username Stash Username 
	 * @return  array
	 */
	function setupSymLinks($directory,$force=false) {
		$fwdir = $directory . '/framework';
		$fwmoddir = $fwdir . '/amp_conf/htdocs/admin/modules/';

		$dirs = array_filter(glob($directory.'/*'), 'is_dir');	

		foreach($dirs as $dirkey => $dirpath) {
			if ($fwdir != $dirpath) {
				$modlink = $fwmoddir . '/' . basename($dirpath);
				
				//remove if we have a link
				if (is_link($modlink)) {
					echo $modlink . " is already linked...Unlinking\n";
					unlink($modlink);
				}
				
				$linkMsg = "Linking " . $dirpath . " to " . $modlink . "...";
				if(symlink($dirpath, $modlink)) {
					$linkMsg .= 'Success';
				} else {
					$linkMsg .= 'Failed';
				}
				echo $linkMsg . "\n";
			}
		}
	}	

	/**
	 * Gets all information about said user from Stash
	 *
	 * @param   string $username Stash Username 
	 * @return  array
	 */
	public static function getInput($msg,$default=null){
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
	 * Gets all information about said user from Stash
	 *
	 * @param   string $username Stash Username 
	 * @return  array
	 */
	public static function getPassword($stars = false)
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
}
