<?php

/*
 * Git.php
 *
 * A PHP git library
 *
 * @package    Git.php
 * @version    0.1.3
 * @author     James Brumond
 * @copyright  Copyright 2013 James Brumond
 * @repo       http://github.com/kbjr/Git.php
 */

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) die('Bad load order');

// ------------------------------------------------------------------------

/**
 * Git Interface Class
 *
 * This class enables the creating, reading, and manipulation
 * of git repositories.
 *
 * @class  Git
 */
class Git {

	/**
	 * Git executable location
	 *
	 * @var string
	 */
	protected static $bin = '/usr/bin/git';
	
	/**
	 * Sets git executable path
	 * 
	 * @param string $path executable location
	 */
	public static function set_bin($path) {
		self::$bin = $path;
	}
	
	/**
	 * Gets git executable path
	 */
	public static function get_bin() {
		return self::$bin;
	}

	/**
	 * Create a new git repository
	 *
	 * Accepts a creation path, and, optionally, a source path
	 *
	 * @access  public
	 * @param   string  repository path
	 * @param   string  directory to source
	 * @return  GitRepo
	 */
	public static function &create($repo_path, $source = null) {
		return GitRepo::create_new($repo_path, $source);
	}

	/**
	 * Open an existing git repository
	 *
	 * Accepts a repository path
	 *
	 * @access  public
	 * @param   string  repository path
	 * @return  GitRepo
	 */
	public static function open($repo_path) {
		return new GitRepo($repo_path);
	}

	/**
	 * Checks if a variable is an instance of GitRepo
	 *
	 * Accepts a variable
	 *
	 * @access  public
	 * @param   mixed   variable
	 * @return  bool
	 */
	public static function is_repo($var) {
		return (get_class($var) == 'GitRepo');
	}

}

// ------------------------------------------------------------------------

/**
 * Git Repository Interface Class
 *
 * This class enables the creating, reading, and manipulation
 * of a git repository
 *
 * @class  GitRepo
 */
class GitRepo {

	protected $repo_path = null;

	/**
	 * Create a new git repository
	 *
	 * Accepts a creation path, and, optionally, a source path
	 *
	 * @access  public
	 * @param   string  repository path
	 * @param   string  directory to source
	 * @return  GitRepo
	 */
	public static function &create_new($repo_path, $source = null) {
		if (is_dir($repo_path) && file_exists($repo_path."/.git") && is_dir($repo_path."/.git")) {
			throw new Exception('"'.$repo_path.'" is already a git repository');
		} else {
			$repo = new self($repo_path, true, false);
			if (is_string($source)) {
				$repo->clone_from($source);
			} else {
				$repo->run('init');
			}
			return $repo;
		}
	}

	/**
	 * Constructor
	 *
	 * Accepts a repository path
	 *
	 * @access  public
	 * @param   string  repository path
	 * @param   bool    create if not exists?
	 * @return  void
	 */
	public function __construct($repo_path = null, $create_new = false, $_init = true) {
		if (is_string($repo_path)) {
			$this->set_repo_path($repo_path, $create_new, $_init);
		}
	}

	/**
	 * Set the repository's path
	 *
	 * Accepts the repository path
	 *
	 * @access  public
	 * @param   string  repository path
	 * @param   bool    create if not exists?
	 * @return  void
	 */
	public function set_repo_path($repo_path, $create_new = false, $_init = true) {
		if (is_string($repo_path)) {
			if ($new_path = realpath($repo_path)) {
				$repo_path = $new_path;
				if (is_dir($repo_path)) {
					if (file_exists($repo_path."/.git") && is_dir($repo_path."/.git")) {
						$this->repo_path = $repo_path;
					} else {
						if ($create_new) {
							$this->repo_path = $repo_path;
							if ($_init) {
								$this->run('init');
							}
						} else {
							throw new Exception('"'.$repo_path.'" is not a git repository');
						}
					}
				} else {
					throw new Exception('"'.$repo_path.'" is not a directory');
				}
			} else {
				if ($create_new) {
					if ($parent = realpath(dirname($repo_path))) {
						mkdir($repo_path);
						$this->repo_path = $repo_path;
						if ($_init) $this->run('init');
					} else {
						throw new Exception('cannot create repository in non-existent directory');
					}
				} else {
					throw new Exception('"'.$repo_path.'" does not exist');
				}
			}
		}
	}

	/**
	 * Tests if git is installed
	 *
	 * @access  public
	 * @return  bool
	 */
	public function test_git() {
		$descriptorspec = array(
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		);
		$pipes = array();
		$resource = proc_open(Git::get_bin(), $descriptorspec, $pipes);

		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		foreach ($pipes as $pipe) {
			fclose($pipe);
		}

		$status = trim(proc_close($resource));
		return ($status != 127);
	}

	/**
	 * Run a command in the git repository
	 *
	 * Accepts a shell command to run
	 *
	 * @access  protected
	 * @param   string  command to run
	 * @return  string
	 */
	protected function run_command($command) {
		$descriptorspec = array(
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		);
		$pipes = array();
		$resource = proc_open($command, $descriptorspec, $pipes, $this->repo_path);

		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		foreach ($pipes as $pipe) {
			fclose($pipe);
		}

		$status = trim(proc_close($resource));
		if ($status) throw new Exception($stderr);

		return $stdout;
	}

	/**
	 * Run a git command in the git repository
	 *
	 * Accepts a git command to run
	 *
	 * @access  public
	 * @param   string  command to run
	 * @return  string
	 */
	public function run($command) {
		return $this->run_command(Git::get_bin()." ".$command);
	}
	
	public function status() {
		$status = $this->run("status --porcelain");
		if(empty($status)) {
			return false;
		}
		$lines = explode("\n",$status);
		$final = array(
			'untracked' => array(),
			'modified' => array(),
		);
		foreach($lines as $line) {
			if(preg_match('/\?\?(.*)/',$line,$matches)) {
				$final['untracked'][] = trim($matches[1]);
			} elseif(preg_match('/M (.*)/',$line,$matches)) {
				$final['modified'][] = trim($matches[1]);
			}
		}
		return $final;
	}

	/**
	 * Runs a `git add` call
	 *
	 * Accepts a list of files to add
	 *
	 * @access  public
	 * @param   mixed   files to add
	 * @return  string
	 */
	public function add($files = "*") {
		if (is_array($files)) {
			$files = '"'.implode('" "', $files).'"';
		}
		return $this->run("add $files -v");
	}

	/**
	 * Runs a `git commit` call
	 *
	 * Accepts a commit message string
	 *
	 * @access  public
	 * @param   string  commit message
	 * @return  string
	 */
	public function commit($message = "") {
		return $this->run("commit -av -m ".escapeshellarg($message));
	}

	/**
	 * Runs a `git clone` call to clone the current repository
	 * into a different directory
	 *
	 * Accepts a target directory
	 *
	 * @access  public
	 * @param   string  target directory
	 * @return  string
	 */
	public function clone_to($target) {
		return $this->run("clone --local ".$this->repo_path." $target");
	}

	/**
	 * Runs a `git clone` call to clone a different repository
	 * into the current repository
	 *
	 * Accepts a source directory
	 *
	 * @access  public
	 * @param   string  source directory
	 * @return  string
	 */
	public function clone_from($source) {
		return $this->run("clone --local $source ".$this->repo_path);
	}

	/**
	 * Runs a `git clone` call to clone a remote repository
	 * into the current repository
	 *
	 * Accepts a source url
	 *
	 * @access  public
	 * @param   string  source url
	 * @return  string
	 */
	public function clone_remote($source) {
		return $this->run("clone $source ".$this->repo_path);
	}

	/**
	 * Runs a `git clean` call
	 *
	 * Accepts a remove directories flag
	 *
	 * @access  public
	 * @param   bool    delete directories?
	 * @return  string
	 */
	public function clean($force = false, $dirs = false) {
		return $this->run("clean".(($dirs) ? " -d" : "")." ".(($force) ? " -f" : ""));
	}

	/**
	 * Runs a `git branch` call
	 *
	 * Accepts a name for the branch
	 *
	 * @access  public
	 * @param   string  branch name
	 * @return  string
	 */
	public function create_branch($branch) {
		return $this->run("branch $branch");
	}

	/**
	 * Runs a `git branch -[d|D]` call
	 *
	 * Accepts a name for the branch
	 *
	 * @access  public
	 * @param   string  branch name
	 * @return  string
	 */
	public function delete_branch($branch, $force = false) {
		return $this->run("branch ".(($force) ? '-D' : '-d')." $branch");
	}
	
	/**
	 * Runs a `git branch` call
	 *
	 * @access  public
	 * @param   bool    keep asterisk mark on active branch
	 * @return  array
	 */
	public function list_remotes() {
		$remoteArray = explode("\n", $this->run("remote show"));
		foreach($remoteArray as $i => &$remote) {
			$remote = trim($remote);
			if ($remote == "") {
				unset($remoteArray[$i]);
			}
		}
		return $remoteArray;
	}
	
	public function show_remote($remote) {
		$remoteArray = explode("\n", $this->run("remote show ".$remote));
		$final = array();
		foreach($remoteArray as $i => &$remote) {
			$remote = trim($remote);
			if(preg_match('/(.*): (.*)/i',$remote,$matches)) {
				$k = $matches[1];
				$v = $matches[2];
				$final[$k] = $v;
			}
		}
		return $final;
	}

	/**
	 * Runs a `git branch` call
	 *
	 * @access  public
	 * @param   bool    keep asterisk mark on active branch
	 * @return  array
	 */
	public function list_branches($keep_asterisk = false) {
		$branchArray = explode("\n", $this->run("branch"));
		foreach($branchArray as $i => &$branch) {
			$branch = trim($branch);
			if (! $keep_asterisk) {
				$branch = str_replace("* ", "", $branch);
			}
			if ($branch == "") {
				unset($branchArray[$i]);
			}
		}
		return $branchArray;
	}

	/**
	 * Lists remote branches (using `git branch -r`).
	 *
	 * Also strips out the HEAD reference (e.g. "origin/HEAD -> origin/master").
	 *
	 * @access  public
	 * @return  array
	 */
	public function list_remote_branches() {
		$branchArray = explode("\n", $this->run("branch -r"));
		foreach($branchArray as $i => &$branch) {
			$branch = trim($branch);
			if ($branch == "" || strpos($branch, 'HEAD -> ') !== false) {
				unset($branchArray[$i]);
			}
		}
		return $branchArray;
	}

	/**
	 * Returns name of active branch
	 *
	 * @access  public
	 * @param   bool    keep asterisk mark on branch name
	 * @return  string
	 */
	public function active_branch($keep_asterisk = false) {
		$branchArray = $this->list_branches(true);
		$active_branch = preg_grep("/^\*/", $branchArray);
		reset($active_branch);
		if ($keep_asterisk) {
			return current($active_branch);
		} else {
			return str_replace("* ", "", current($active_branch));
		}
	}
	
	/**
	* Runs a `git ls-remote --tags` call
	*
	* Determines if a tag already exists on a remote server
	*
	* @access  public
	* @param   string $tag The tag name
	* @return  bool
	*/
	public function remote_tag_exist($remote, $tag) {
		$o = $this->run("ls-remote --tags $remote $tag");
		if(empty($o)) {
			return false; 
		}
		return true;
	}
	
	/**
	* Runs a `git tag` call
	*
	* Determines if a tag already exists
	*
	* @access  public
	* @param   string $tag The tag name
	* @return  bool
	*/
	public function tag_exist($tag) {
		$tags = $this->list_tags();
		return(in_array($tag,$tags));
	}

	/**
	* Runs a `git tag` call
	*
	* @access  public
	* @param   bool    keep asterisk mark on active branch
	* @return  array
	*/
	public function list_tags($keep_asterisk = false) {
		$tagArray = explode("\n", $this->run("tag"));
		if (!empty($tagArray)) {
			foreach($tagArray as $i => &$tag) {
				$tag = trim($tag);
				if (! $keep_asterisk) {
					$tag = str_replace("* ", "", $tag);
				}
				if ($tag == "") {
					unset($tagArray[$i]);
				}
			}
		}
		if (empty($tagArray)) {
			return array();
		}
		return $tagArray;
	}

	/**
	* Runs a `git show-ref` tag call
	*
	* Accepts a name of a tag
	*
	* @access  public
	* @param   string  tag name
	* @return  string
	* @todo    We might want to expand on this to handle refs that come 
	* 		back with more than one result
	*/
	public function show_ref_tag($tag) {
		return trim($this->run("show-ref -s " . $tag));
	}

	/**
	* Runs a `git show`
	*
	* Accepts the tree and the file we want to see
	*
	* $access public
	* @param  string tree
	* @param  string filename
	* @return string
	*/
	public function show($tree,$file) {
		return $this->run("show $tree:$file");
	}

	/**
	* Runs a `git log`
	*
	* Accepts the from sha1, to sha1, and format
	*
	* @access  public
	* @param   string  from sha1 hash
	* @param   string  to sha1 hash
	* @param   string  format string
	* @return  string
	*/
	public function log($from=null, $to=null, $format=null) {
		$cmd[] = 'log';
		if (isset($format) && $format != '') {
			$cmd[] = $format;
		}
		if (isset($from) && $from != '') {
			$diff = '';
			if (isset($to) && $to != '') {
				$diff .= '...';
				$diff .= $to;
			}
			$cmd[] = $diff;
		}
		return $this->run(implode(' ',$cmd));
	}
	
	public function log_search($greps=array(), $format=null) {
		$cmd[] = 'log --all -i';
		foreach($greps as $grep) {
			$cmd[] = '--grep="'.$grep.'"';
		}
		if (isset($format) && $format != '') {
			$cmd[] = '--pretty=format:"'.$format.'"';
		}
		$o = $this->run(implode(' ',$cmd));
		$z = explode("\n",$o);
		return $z;
	}

	/**
	 * Runs a `git checkout` call
	 *
	 * Accepts a name for the branch
	 *
	 * @access  public
	 * @param   string  branch name
	 * @return  string
	 */
	public function checkout($branch) {
		return $this->run("checkout $branch");
	}

	/**
	 * Runs a `git checkout -b` call
	 *
	 * Accepts a name for the new branch
	 * Accepts a name for the branch/tag being checked out
	 *
	 * @access  public
	 * @param   string new branch name
	 * @param   string branch/tag to checkout
	 * @return  string
	 */
	public function checkout_branch($new_branch, $branch) {
		return $this->run("checkout -b $new_branch $branch");
	}

	/**
	 * Runs a `git merge` call
	 *
	 * Accepts a name for the branch to be merged
	 *
	 * @access  public
	 * @param   string $branch
	 * @return  string
	 */
	public function merge($branch) {
		return $this->run("merge $branch --no-ff");
	}


	/**
	 * Runs a git fetch on the current repository
	 *
	 * @access  public
	 * @return  string
	 */
	public function fetch() {
		$this->run("fetch -q");
		$this->run("fetch --tags -q");
		return true;
	}

	/**
	 * Runs a git fetch --tags on the current branch
         *
         * @access  public
         * @return  string
         */
        public function fetch_tags() {
                return $this->run("fetch --tags");
        }

	/**
	 * Add a new tag on the current position
	 *
	 * Accepts the name for the tag and the message
	 *
	 * @param string $tag
	 * @param string $message
	 * @return string
	 */
	public function add_tag($tag, $message = null, $ref = '') {
		if ($message === null) {
			$message = $tag;
		}
		return $this->run("tag -a $tag -m $message $ref");
	}
	
	/**
	 * Delete a local tag
	 *
	 * @param string $tag
	 * @return string
	 */
	public function delete_tag($tag) {
		return $this->run("tag -d $tag");
	}
	
	/**
	 * Deletes all Local Tags
	 *
	 * http://stackoverflow.com/questions/1841341/remove-local-tags-that-are-no-longer-on-the-remote-repository
	 *
	 * @return string
	 */
	public function delete_all_tags() {
		return $this->run("tag -l | xargs git tag -d");
	}
	
	/**
	 * Deletes all stale remote-tracking branches under
	 *
	 * These stale branches have already been removed from the remote repository referenced by $remote
	 * but are still locally available in "remotes/$remote"
	 *
	 * @param string $remote
	 * @return string
	 */
	public function prune($remote) {
		return $this->run("remote prune $remote");
	}
	
	/**
	 * Delete a remote tag
	 *
	 * @param string $remote
	 * @param string $tag
	 * @return string
	 */
	public function delete_remote_tag($remote, $tag) {
		return $this->run("push $remote :$tag");
	}

	/**
	 * Add a new stash
	 *
	 * @return mixed false if nothing to stash, otherwise result
	 */
	public function add_stash() {
		$stash = trim($this->run("stash"));
		if(preg_match('/No local changes to save/i',$stash)) {
			return false;
		}
		return $stash;
	}
	
	/**
	 * Delete a Stash
	 *
	 * @return string result of drop
	 */
	public function drop_stash() {
		return $this->run("stash drop");
	}
	
	/**
	 * Apply a stash
	 *
	 * @return string result of apply
	 */
	public function apply_stash() {
		return $this->run("stash apply");
	}

	/**
	 * Push specific branch to a remote
	 *
	 * Accepts the name of the remote and local branch
	 *
	 * @param string $remote
	 * @param string $branch
	 * @return string
	 */
	public function push($remote, $branch) {
		return $this->run("push --tags $remote $branch");
	}
	
	/**
	 * Pull specific branch from remote
	 *
	 * Accepts the name of the remote and local branch
	 *
	 * @param string $remote
	 * @param string $branch
	 * @return string
	 */
	public function pull($remote, $branch) {
		return $this->run("pull $remote $branch");
	}

	/**
	 * Sets the project description.
	 *
	 * @param string $new
	 */
	public function set_description($new) {
		file_put_contents($this->repo_path."/.git/description", $new);
	}

	/**
	 * Gets the project description.
	 *
	 * @return string
	 */
	public function get_description() {
		return file_get_contents($this->repo_path."/.git/description");
	}

	/**
	 * Archive a git tag to gzip
	 *
	 * @param string $prefix
	 * @param string $tag
	 * @param string $filename
	 * @return string
	 */
	public function gzip_archive_tag($prefix, $tag, $filename) {
		return $this->run("archive --format=tar --prefix=$prefix/ release/$tag | gzip > $filename");
	}	
}

/* End of file */
