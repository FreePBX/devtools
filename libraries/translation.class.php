<?php
/**
 * Translation
 *
 * A small class to handle i18n file generation for FreePBX
 *
 * @package FreePBX
 * @author Bryan Walters <secretop@gmail.com>
 * @author Kevin McCoy <kevin@qloog.com>
 * @author Andrew Nagy <andrew.nagy@the159.com>
 * @license AGPLv3
 * @copyright 2014 Schmooze Com, Inc.
 */

class Translation {
	//core and framework are special and need different treatment than other modules
	var $specialModules = array('core','framework'),
	$cwd = '',
	$xml = null;

	public function __construct($cwd) {
		$this->cwd = $cwd;
		if(!file_exists($cwd."/module.xml")) {
			echo "This doesnt appear to be a module";
			exit();
		}
		$xml = simplexml_load_file($cwd."/module.xml");
		$this->xml = json_decode(json_encode($xml),true);
	}

	/**
	 * Create an i18n file header
	 * @return {string}
	 */
	private function createHeader() {
		//Now add the copyright and the license info to the.pot file
		//Again, could be done better, but I lack the time and really need this out now
		date_default_timezone_set("America/Los_Angeles");
		$year = date('Y');
		$module = $this->xml['rawname'];

		//Note the newline after the final "#" dont remove this!!!!
		$string = <<<EOF
# This file is part of FreePBX.
#
# For licensing information, please see the file named LICENSE located in the module directory
#
# FreePBX language template for $module
# Copyright (C) 2008-$year Sangoma, Inc.
#

EOF;

		return $string;
	}

	/**
	 * Make new Language directory
	 * @param  {string} $language Language code
	 */
	public function makeLanguage($language) {
		// We shouldn't be able to run makeLanguage on core or framework since they are special
		if (in_array($this->xml['rawname'], $this->specialModules)) {
			echo "ERROR: " . $this->xml['rawname'] . " should not have makeLanguage() run on it; its localization is handled differently than regular modules.\n";
			exit();
		}

		$i18n = $this->cwd . '/i18n';
		if (!is_dir($i18n)) {
			mkdir($i18n);
		}

		$lcMessages = $i18n . '/' . $language . '/LC_MESSAGES';
		if (!is_dir($lcMessages)) {
			mkdir($lcMessages,0777,true);
		}

		$poFile = $lcMessages .'/' . $this->xml['rawname'] . '.po';
		$potFile = $i18n . '/' . $this->xml['rawname'] . '.pot';

		if(!file_exists($potFile)) {
			$this->update_i18n();
		}

		if (!file_exists($poFile)) {
			copy($i18n . '/' . $this->xml['rawname'] .'.pot', $poFile);
		} else {
			exec("msgmerge --backup=none -N -U $poFile $potFile 2>&1");
		}

		$globalMoFile = $i18n .'/' . $this->xml['rawname'] . '.mo';
		$globalPoFile = $i18n . '/' . $this->xml['rawname'] . '.po';
		if (file_exists($globalMoFile) && file_exists($globalPoFile)) {
			exec("mv " . $i18n . "/" . $this->xml['rawname'] . "/" . $this->xml['rawname'] . ".mo $lcMessages/");
			exec("mv " . $i18n . "/" . $this->xml['rawname'] . "/" . $this->xml['rawname'] . ".po $lcMessages/");
			exec("msgmerge --backup=none -N -U $poFile $potFile 2>&1");
		}
	}

	/**
	 * Merge i18n files
	 * @param  {string} $module Module rawname
	 */
	function merge_i18n($language) {
		$i18n = $this->cwd . '/i18n';

		$poFile =  $i18n . '/' . $language . '/LC_MESSAGES' .'/' . $this->xml['rawname'] . '.po';
		$moFile = $i18n . '/' . $language . '/LC_MESSAGES' .'/' . $this->xml['rawname'] . '.mo';
		$potFile = $i18n . '/' . $this->xml['rawname'] . '.pot';
		$o = "";
		if (file_exists($poFile) && file_exists($potFile)) {
			$o .= exec("msgmerge --backup=none -N -U " . $poFile . " " . $potFile . " 2>&1", $output);
			$o .= exec("msgfmt -v " . $poFile . " -o " . $moFile . " 2>&1", $output);
		}
		return $o;
	}

	function find_gitignore_files($dir) {
		$files = array();
		while (true) {
			$file = "$dir/.gitignore";
			if (is_file($file)) $files[] = $file;
			if (is_dir("$dir/.git") && !is_link("$dir/.git")) break;  # stop here
			if (dirname($dir) === '.') break;                         # and here
			$dir = dirname($dir);
		}
		return $files;
	}

	function find_langignore_files($dir) {
		$files = array();
		while (true) {
			$file = "$dir/.langignore";
			if (is_file($file)) $files[] = $file;
			if (is_dir("$dir/.git") && !is_link("$dir/.git")) break;  # stop here
			if (dirname($dir) === '.') break;                         # and here
			$dir = dirname($dir);
		}
		return $files;
	}

	function parse_git_ignore_file($file) { // $file = '/absolute/path/to/.gitignore'
		$dir = dirname($file);
		$matches = array();
		$lines = file($file);
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '') continue;                 // empty line
			if (substr($line, 0, 1) == '#') continue;   // a comment
			if (substr($line, 0, 1) == '!') {           // negated glob
				$line = substr($line, 1);
				$matches = array_diff($matches, array("$dir/$line"));
				continue;
			} elseif(preg_match('/\*/i',$line)) {
				$files = glob("$dir/$line");
			} else {
				$files = array("$line");
			}
			$matches = array_merge($matches, $files);
		}
		return $matches;
	}

	function parseGitIgnoreFile($directory) {
		$gitIgnoreFiles = $this->find_gitignore_files($this->cwd);
		$langIgnoreFiles = $this->find_langignore_files($this->cwd);
		$ignoreFiles = array_merge($gitIgnoreFiles, $langIgnoreFiles);
		if(empty($ignoreFiles)) {
			return array();
		}
		$ignores = array();
		foreach($ignoreFiles as $file) {
			$i = $this->parse_git_ignore_file($file);
			if(!empty($i) && is_array($i)) {
				$ignores = array_merge($i, $ignores);
			}
		}
		return $ignores;
	}

	function isIgnored($file) {
		if(empty($this->ignores)) {
			$this->ignores = $this->ignores = $this->parseGitIgnoreFile($this->cwd);
		}
		foreach($this->ignores as $ignore) {
			if(strrpos($file, $ignore) !== false) {
				return true;
			}
		}
		return false;
	}

	/* OK, now we have come to a place where framework and core need to be treated totally differently from other modules
	Rather that go to the trouble of trying to special case everything within the same function, which is going to lead to
	code that is more complex than necessary, what I have done is make two functions - one is update_i18n() which is used
	for all regular modules. The other is update_i18n_amp(), which is used for framework and core.

	If you try to use update_i18n() on framework or core by mistake, it's going to fail with an error message and exit.
	The reverse is also true - you can't use update_i18n_amp() on anything except the specialModules defined above
	*/

	/**
	 * Update i18n files for normal modules
	 * @param  {string} $module Module rawname
	 */
	function update_i18n() {

		if (in_array($this->xml['rawname'], $this->specialModules)) {
			echo "ERROR: " . $this->xml['rawname'] . " is treated differently than regular modules. Please use update_i18n_amp() instead.\n";
			exit();
		}

		$i18n = $this->cwd . '/i18n';
		if (!is_dir($i18n)) {
			echo "The i18n folder doesnt exist! Have you run makeLanguage yet?\n";
			exit();
		}

		$i18n_php = $this->cwd . '/' . $this->xml['rawname'] . '.i18n.php';
		//Running module_admin should probably be avoided because it requires the PBX system to be installed and the DB
		//to be in tact with the latest information to work. It would be better to have this pick up the necessary strings
		//from the latest source code alone if possible.

		//Prepare the temporary PHP file where we will store strings
		file_put_contents($i18n_php, "<?php \nif(false) {");
		$xmlData=simplexml_load_file($this->cwd . '/module.xml'); //or die("Failed to load the module.xml file!")
		//From module.xml - name, category, description (we used to get this from module_admin)
		file_put_contents($i18n_php, '_("' . trim($xmlData->name) . '");' . "\n", FILE_APPEND);
		file_put_contents($i18n_php, '_("' . trim($xmlData->category) . '");' . "\n", FILE_APPEND);
		file_put_contents($i18n_php, '_("' . trim($xmlData->description) . '");' . "\n", FILE_APPEND);
		//Logic for if there are <menuitems> - there can often be several of these so we need to loop over the code
		if (!empty($xmlData->menuitems)) {
			foreach ($xmlData->menuitems->children() AS $child) {
				file_put_contents($i18n_php, '_("' . trim($child) . '");' . "\n", FILE_APPEND);
			}
		}
		//Go through the module's install.php file and get the strings that need to be translated
		if (file_exists($this->cwd . '/install.php')) {
			//The 3 patterns we need to grep from install.php
			$pattern_cat=':^[\s\S]*?\$set.*category:';
			$pattern_name=':^[\s\S]*?\$set.*name:';
			$pattern_desc=':^[\s\S]*?\$set.*description:';
			//Grep the needed lines and format them to be put in the temp file
			$lines=array();
			array_push($lines, preg_grep($pattern_cat, file($this->cwd . '/install.php')));
			array_push($lines, preg_grep($pattern_name, file($this->cwd . '/install.php')));
			array_push($lines, preg_grep($pattern_desc, file($this->cwd . '/install.php')));
			//Perform textual healing
			$process = new RecursiveIteratorIterator(new RecursiveArrayIterator($lines));
			foreach($process as $trans_str) {
				$pat=':^[\s\S]*?\$set\[\S{1}\S+?\S{1}\]\s*=\s*\S{1}([\s\S]*)\S{2}\s*$:';
				$replacement='$1';
				$output=preg_replace($pat, $replacement, $trans_str);
				file_put_contents($i18n_php, '_("' . $output . '");' . "\n", FILE_APPEND);
			}
		}
		//Running module_admin should probably be avoided because it requires the PBX system to be installed and the DB
		//to be in tact with the latest information to work. It would be better to have this pick up the necessary strings
		//from the latest source code alone if possible.

		$i18n_dir = $this->cwd . '/i18n';
		if (is_dir($i18n_dir)) {
			//Finish off the $i18n_php temp file with the required code
			file_put_contents($i18n_php,"}\n?>\n", FILE_APPEND);

			$phps = $this->rglob($this->cwd, "/(.*\.php)/");
			$jss = $this->rglob($this->cwd, "/(.*\.js)/");
			asort($phps);
			asort($jss);
			//What we do next is a little weird, but we need to take the first file and run over it with gettext
			//separate from the other ones in the loop below. The reason is that we only want to replace the
			//CHARSET with utf-8 once, and that's annoying to try and do with a loop
			$file = array_shift($phps);
			//print($file . "<<<<<<<<<<<this is the file");
			$tmpFile = $i18n_dir . '/' . $this->xml['rawname'] . '.tmp';
			//We add the --force-po flag here to ensure that a pot file gets written out, even if the first file we scan
			//doesn't yield any messages
			exec("xgettext " . $file . " --from-code=UTF-8 -L PHP -o " . $tmpFile . " --add-location --sort-output --keyword=_ --force-po");
			file_put_contents($tmpFile, str_replace("CHARSET", "utf-8", file_get_contents($tmpFile)));
			//Continue and go ahead and scan the rest of the files in the $files array
			foreach($phps as $f) {
				if($this->isIgnored($f)) {
					continue;
				}
				exec("xgettext " . $f . " -j --from-code=UTF-8 -L PHP -o " . $tmpFile . " --add-location --sort-output --keyword=_");
			}

			foreach($jss as $f) {
				if($this->isIgnored($f)) {
					continue;
				}
				exec("xgettext " . $f . " -j --from-code=UTF-8 -L Perl -o " . $tmpFile . " --add-location --sort-output --keyword=_");
			}

			//get our file header
			$string = $this->createHeader();

			$potFile = $i18n_dir . '/' . $this->xml['rawname'] . '.pot';
			file_put_contents($potFile, $string);

			//Remove the first six lines of the .tmp file and tack it onto the .pot file
			exec("/bin/sed '1,6d' $tmpFile >> $potFile");

			//Remove the .tmp file created above
			unlink($tmpFile);
		}
	}

	/**
	 * Update i18n files for special modules to make amp.pot
	 * @param  {string} $module Module rawname
	 */
	function update_i18n_amp() {
		// Give an error and exit if this is not a special module
		if (!in_array($this->xml['rawname'], $this->specialModules)) {
			echo "ERROR: " . $this->xml['rawname'] . " is a regular module. Please use the normal update_i18n() function instead.\n";
			exit();
		}
		// Give an error if someone tries to run this on core
		if ($this->xml['rawname'] == 'core') {
			echo "ERROR: update_i18n_amp should only be run on framework, not on core\n";
			exit();
		}
		// Give and error and exit if core and framework are not in the same parent directory
		// This script should only be run for framework
		if (!is_dir($this->cwd . '/../core')) {
			echo "ERROR: you must have core and framework in the same parent directory to use update_i18n_amp()\n";
			exit();
		}
		//Code for processing amp stuff comes here
		//Make rawname into amp since we passed the above test and it's OK to proceed
		$this->xml['rawname'] = 'amp';
		//This will only get run on framework, so let's make a handy variable for the core directory since we
		//passed the above test
		$core_dir = $this->cwd . '/../core';
		$i18n_php = $this->cwd . '/' . $this->xml['rawname'] . '.i18n.php';

		//Start the temporary PHP file where we will store strings
		file_put_contents($i18n_php, "<?php \nif(false) {", FILE_APPEND);
		//We need to handle two module.xml files - framework and core
		// FRAMEWORK - module.xml
		$xmlData=simplexml_load_file($this->cwd . '/module.xml'); //or die("Failed to load the module.xml file!")
		//From module.xml - name, category, description
		file_put_contents($i18n_php, '_("' . trim($xmlData->name) . '")' . "\n", FILE_APPEND);
		file_put_contents($i18n_php, '_("' . trim($xmlData->category) . '")' . "\n", FILE_APPEND);
		file_put_contents($i18n_php, '_("' . trim($xmlData->description) . '")' . "\n", FILE_APPEND);
		//Logic for if there are <menuitems> - there can often be several of these so we need to loop over the code
		if ($xmlData->menuitems->children()) {
			foreach ($xmlData->menuitems->children() AS $child) {
				file_put_contents($i18n_php, '_("' . trim($child) . '")' . "\n", FILE_APPEND);
			}
		}
		// CORE - module.xml
		$xmlData=simplexml_load_file($core_dir . '/module.xml'); //or die("Failed to load the module.xml file!")
		//From module.xml - name, category, description
		file_put_contents($i18n_php, '_("' . trim($xmlData->name) . '")' . "\n", FILE_APPEND);
		file_put_contents($i18n_php, '_("' . trim($xmlData->category) . '")' . "\n", FILE_APPEND);
		file_put_contents($i18n_php, '_("' . trim($xmlData->description) . '")' . "\n", FILE_APPEND);
		//Logic for if there are <menuitems> - there can often be several of these so we need to loop over the code
		if ($xmlData->menuitems->children()) {
			foreach ($xmlData->menuitems->children() AS $child) {
				file_put_contents($i18n_php, '_("' . trim($child) . '")' . "\n", FILE_APPEND);
			}
		}
		// FRAMEWORK - libfreepbx.install.php
		if (file_exists($this->cwd . '/libfreepbx.install.php')) {
			//The 3 patterns we need to grep from install.php
			$pattern_cat=':^[\s\S]*?\$set.*category:';
			$pattern_name=':^[\s\S]*?\$set.*name:';
			$pattern_desc=':^[\s\S]*?\$set.*description:';
			//Grep the needed lines and format them to be put in the temp file
			$lines=array();
			array_push($lines, preg_grep($pattern_cat, file($this->cwd . '/libfreepbx.install.php')));
			array_push($lines, preg_grep($pattern_name, file($this->cwd . '/libfreepbx.install.php')));
			array_push($lines, preg_grep($pattern_desc, file($this->cwd . '/libfreepbx.install.php')));
			//Perform textual healing
			$process = new RecursiveIteratorIterator(new RecursiveArrayIterator($lines));
			foreach($process as $trans_str) {
				$pat=':^[\s\S]*?\$set\[\S{1}\S+?\S{1}\]\s*=\s*\S{1}([\s\S]*)\S{2}\s*$:';
				$replacement='$1';
				$output=preg_replace($pat, $replacement, $trans_str);
				//print($output);
				file_put_contents($i18n_php, '_("' . $output . '")' . "\n", FILE_APPEND);
			}
		}
		// CORE - install.php
		if (file_exists($core_dir . '/install.php')) {
			//The 3 patterns we need to grep from install.php
			$pattern_cat=':^[\s\S]*?\$set.*category:';
			$pattern_name=':^[\s\S]*?\$set.*name:';
			$pattern_desc=':^[\s\S]*?\$set.*description:';
			//Grep the needed lines and format them to be put in the temp file
			unset($lines);
			unset($process);
			$lines=array();
			array_push($lines, preg_grep($pattern_cat, file($core_dir . '/install.php')));
			array_push($lines, preg_grep($pattern_name, file($core_dir . '/install.php')));
			array_push($lines, preg_grep($pattern_desc, file($core_dir . '/install.php')));
			//Perform textual healing
			$process = new RecursiveIteratorIterator(new RecursiveArrayIterator($lines));
			foreach($process as $trans_str) {
				$pat=':^[\s\S]*?\$set\[\S{1}\S+?\S{1}\]\s*=\s*\S{1}([\s\S]*)\S{2}\s*$:';
				$replacement='$1';
				$output=preg_replace($pat, $replacement, $trans_str);
				file_put_contents($i18n_php, '_("' . $output . '")' . "\n", FILE_APPEND);
			}
		}
		// This is the i18n dir path for framework
		$i18n_dir = $this->cwd . '/amp_conf/htdocs/admin/i18n';
		if (is_dir($i18n_dir)) {
			//Finish off the $i18n_php temp file with the required code
			file_put_contents($i18n_php, "}\n?>\n", FILE_APPEND);

			//We will now scan all the PHP (and js) files in core and framework directories to put the resulting
			//entries in amp.pot. This should include the temporary files created above.

			//FRAMEWORK - all php/js files including those in subdirs
			$php = $this->rglob($this->cwd, "/(.*\.php)/");
			$js = $this->rglob($this->cwd, "/(.*\.js)/");
			$files = !empty($js) ? array_merge($php, $js) : $php;
			asort($files);
			$file = array_shift($files);
			$tmpFile = $i18n_dir . '/' . $this->xml['rawname'] . '.tmp';
			//We add --force-po flag here to make sure that a pot file gets written even if the first php file doesn't contain
			//any translatable messages
			exec("xgettext " . $file . " --from-code=UTF-8 -L PHP -o " . $tmpFile . " --add-location --sort-output --keyword=_ --force-po");
			file_put_contents($tmpFile, str_replace("CHARSET", "utf-8", file_get_contents($tmpFile)));
			foreach($files as $f) {
				exec("xgettext " . $f . " -j --from-code=UTF-8 -L PHP -o " . $tmpFile . " --add-location --sort-output --keyword=_");
			}
			//CORE - all php/js files including those in subdirs
			$php = $this->rglob($core_dir, "/(.*\.php)/");
			$js = $this->rglob($core_dir, "/(.*\.js)/");
			$files = !empty($js) ? array_merge($php, $js) : $php;
			asort($files);
			foreach($files as $f) {
				exec("xgettext " . $f . " -j --from-code=UTF-8 -L PHP -o " . $tmpFile . " --add-location --sort-output --keyword=_");
			}

			//get our file header
			$string = $this->createHeader();

			$potFile = $i18n_dir . '/' . $this->xml['rawname'] . '.pot';
			file_put_contents($potFile, $string);

			//Remove the first six lines of the .tmp file and tack it onto the .pot file
			exec("/bin/sed '1,6d' $tmpFile >> $potFile");

			//Remove the .tmp file created above
			unlink($tmpFile);
		}
	}


	function rglob($folder, $pattern) {
		$dir = new RecursiveDirectoryIterator($folder);
		$ite = new RecursiveIteratorIterator($dir);
		$files = new RegexIterator($ite, $pattern, RegexIterator::GET_MATCH);
		$fileList = array();
		foreach($files as $file) {
			$fileList = array_merge($fileList, $file);
		}
		return $fileList;
	}
}
