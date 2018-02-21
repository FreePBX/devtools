#!/usr/bin/env php
<?php

require_once('libraries/freepbx.php');

//get cli opts
$longopts = array(
	'directory::',
	'help::'
);
$vars = getopt('d::h::', $longopts);

$helpArray = array(
	array('--help', 'Show this menu and exit'),
	array('--directory', 'Directory Location of framework root, always assumed to be ../freepbx from this location')
);

//if help was requested, show help and exit
if (isset($vars['h'])) {
	echo freepbx::showHelp(basename(__FILE__),$helpArray,true);
	exit(0);
}

if(isset($vars['help'])) {
        echo freepbx::showHelp(basename(__FILE__),$helpArray);
        exit(0);
}

$vars['directory'] = !empty($vars['directory']) ? $vars['directory'] : dirname(dirname(__FILE__)) . '/freepbx/framework';
$libfreepbx = $vars['directory'] . '/amp_conf/htdocs/admin/assets/js/pbxlib.js';
$dir = $vars['directory'] . '/amp_conf/htdocs/admin/assets/js';
$output=array();

exec("ls $dir/*.js",$output,$ret);
if(file_exists("$dir/bootstrap-table-extensions-1.11.0/")) {
	exec("ls $dir/bootstrap-table-extensions-1.11.0/*.js",$output2,$ret);
	$output = array_merge($output,$output2);
}
$final=$finalB=array();
/*
 * to order js files: files will be appened to the array in the order they appear
 * in the switch statmenet below. To give a file priority, create a case for it and
 * add it to the $finalB array. All other files will be appended to the $final array.
 * $finalB is then merged with $final, with $finalB being put first
 */
/*
Array
(
[0] => /usr/src/freepbx/framework/amp_conf/htdocs/admin/assets/js/jquery.cookie.js
[1] => /usr/src/freepbx/framework/amp_conf/htdocs/admin/assets/js/script.legacy.js
[2] => /usr/src/freepbx/framework/amp_conf/htdocs/admin/assets/js/XMLHttpRequest.js
[3] => /usr/src/freepbx/framework/amp_conf/htdocs/admin/assets/js/class.js
[4] => /usr/src/freepbx/framework/amp_conf/htdocs/admin/assets/js/jquery.autosize.min.js
[5] => /usr/src/freepbx/framework/amp_conf/htdocs/admin/assets/js/jquery.hotkeys.js
[6] => /usr/src/freepbx/framework/amp_conf/htdocs/admin/assets/js/tabber-minimized.js
)
 */
$xml = simplexml_load_file($vars['directory']."/module.xml");
if(version_compare_freepbx((string)$xml->version,"14.0","<")) {
	$skip = array(
		"|$dir/progress-polyfill.min.js|",
		"|$dir/jquery-.*\.js|",
		"|$dir/jquery-ui-.*\.js$|",
		"|$dir/jquery.selector-set-.*\.js|",
		"|$dir/selector-set-.*\.js|",
		"|$dir/less-.*\.js|",
		"|$dir/toastr-.*\.js|",
		"|$libfreepbx|",
		"|$dir/bootstrap-.*\.js|",
		"|$dir/html5shiv\.js|",
		"|$dir/module_admin\.js|",
		"|$dir/modernizr\.js|",
		"|$dir/browser-support\.js|",
		"|$dir/outdatedbrowser\.min\.js|",
		"|$dir/selectivizr\.js|",
		"|$dir/typeahead\.bundle\.js|",
		"|$dir/typeahead\.bundle\.min\.js|",
		"|$dir/search\.js|",
		"|$dir/respond\.min\.js|",
		"|$dir/jed\.js|",
		"|$dir/zxcvbn\.js|",
		"|$dir/bootstrap-table-locale|",
		"|$dir/bootstrap-multiselect\.js|",
		"|$dir/chosen\.jquery\.min\.js|",
		"|$dir/kclc\.js|",
		"|$dir/eventsource\.min\.js|",
		"|$dir/jquery\.fileupload.*\.js|",
		"|$dir/jquery\.iframe-transport\.js|",
		"|$dir/load-image\.all\.min\.js|",
		"|$dir/jquery\.smartWizard\.js|",
		"|$dir/modgettext\.js|",
		"|$dir/Sortable\.min\.js|",
		"|$dir/toastr-.*\.js|",
		"|$dir/class\.js|",
		"|$dir/jquery\.jplayer\.min\.js|",
		"|$dir/XMLHttpRequest\.js|",
		"|$dir/jquery\.form\.min\.js|",
		"|$dir/selectize\.min\.js|",
		"|$dir/recorder\.js|",
		"|$dir/recorderWorker\.js|",
		"|$dir/moment-with-locales\.min\.js|",
		"|$dir/moment-timezone\.min\.js|",
		"|$dir/browser-locale\.min\.js|"
	);
} else {
	$skip = array(
		"|$libfreepbx|",
		"|$dir/jquery-\d.*\.js|",
		"|$dir/zxcvbn-.*\.min\.js|",
		"|$dir/outdatedbrowser-.*\.min\.js|",
		"|$dir/selector-set-.*\.js|",
		"|$dir/jquery.selector-set-.*\.js|",
		"|$dir/class\.js|",
		"|$dir/jed-.*\.js|",
		"|$dir/modgettext\.js|",
		"|$dir/kclc\.js|",
		"|$dir/module_admin\.js|",
		"|$dir/eventsource-.*\.min\.js|",
	);
	$finalB = array();
}

foreach ($output as $file) {

	//skip the files in the skip array
	foreach ($skip as $s) {
		if (preg_match($s, $file)) {
			echo "skipping $file\n";
			continue 2;
		}
	}

	if(version_compare_freepbx((string)$xml->version,"14.0","<")) {
		//add files
		switch(true){
			case preg_match("|$dir/jquery\.cookie\.js|",$file):
				$finalB[] = $file;
			break;
			case $file==$dir.'/script.legacy.js'://legacy script
				$finalB[] = $file;
			break;
			case $file != $dir.'/script.legacy.js'://default
				$final[] = $file;
			break;
		}
	} else {
		//add files
		switch(true){
			case preg_match("|$dir/moment-with-locales-.*\.min\.js|",$file):
				$finalB[] = $file;
			break;
			case $file==$dir.'/script.legacy.js'://legacy script
				$finalB[] = $file;
			break;
			case $file != $dir.'/script.legacy.js'://default
				$final[] = $file;
			break;
		}
	}
}

sort($finalB);
sort($final);

$final=array_merge($finalB,$final);

echo "\narray(\n";
foreach($final as $f) {
	echo '"'."assets/js".preg_replace("/^".preg_quote($dir,"/")."/","",$f).'",'."\n";
}
echo ")\n";

echo "creating $libfreepbx with:\n\n";
print_r($final);

foreach ($final as $f) {
	echo "\npacking " . $f ."...";
	$data = file_get_contents($f);
	$name = basename($f);
	if(preg_match("/\.min/",$name)) {
		$js[] = $data;
		echo 'already packed!';
	} else {
		// Second param is purely for debugging
		$js[] = JSMin::minify($data, $f);
		echo 'done!';
	}

}
echo "\n\n\n";

file_put_contents($libfreepbx, $js);

/**
 * jsmin.php - PHP implementation of Douglas Crockford's JSMin.
 *
 * This is pretty much a direct port of jsmin.c to PHP with just a few
 * PHP-specific performance tweaks. Also, whereas jsmin.c reads from stdin and
 * outputs to stdout, this library accepts a string as input and returns another
 * string as output.
 *
 * PHP 5 or higher is required.
 *
 * Permission is hereby granted to use this version of the library under the
 * same terms as jsmin.c, which has the following license:
 *
 * --
 * Copyright (c) 2002 Douglas Crockford  (www.crockford.com)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * The Software shall be used for Good, not Evil.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * --
 *
 * @package JSMin
 * @author Ryan Grove <ryan@wonko.com>
 * @copyright 2002 Douglas Crockford <douglas@crockford.com> (jsmin.c)
 * @copyright 2008 Ryan Grove <ryan@wonko.com> (PHP port)
 * @copyright 2012 Adam Goforth <aag@adamgoforth.com> (Updates)
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @version 1.1.2 (2012-05-01)
 * @link https://github.com/rgrove/jsmin-php
 */

class JSMin {
  const ORD_LF            = 10;
  const ORD_SPACE         = 32;
  const ACTION_KEEP_A     = 1;
  const ACTION_DELETE_A   = 2;
  const ACTION_DELETE_A_B = 3;

  protected $a           = '';
  protected $b           = '';
  protected $input       = '';
  protected $inputIndex  = 0;
  protected $inputLength = 0;
  protected $lookAhead   = null;
  protected $output      = '';

  public $lineno = 0;
  private $filename;

  // -- Public Static Methods --------------------------------------------------

  /**
   * Minify Javascript
   *
   * @uses __construct()
   * @uses min()
   * @param string $js Javascript to be minified
   * @param string $filename Filename to be displayed on error
   * @return string
   */
  public static function minify($js, $filename = "unknown") {
    $jsmin = new JSMin($js, $filename);
    return $jsmin->min();
  }

  // -- Public Instance Methods ------------------------------------------------

  /**
   * Constructor
   *
   * @param string $input Javascript to be minified
   */
  public function __construct($input, $filename = "unknown") {
    $this->filename    = $filename;
    $this->input       = str_replace("\r\n", "\n", $input);
    $this->inputLength = strlen($this->input);
  }

  // -- Protected Instance Methods ---------------------------------------------

  /**
   * Action -- do something! What to do is determined by the $command argument.
   *
   * action treats a string as a single character. Wow!
   * action recognizes a regular expression if it is preceded by ( or , or =.
   *
   * @uses next()
   * @uses get()
   * @throws JSMinException If parser errors are found:
   *         - Unterminated string literal
   *         - Unterminated regular expression set in regex literal
   *         - Unterminated regular expression literal
   * @param int $command One of class constants:
   *      ACTION_KEEP_A      Output A. Copy B to A. Get the next B.
   *      ACTION_DELETE_A    Copy B to A. Get the next B. (Delete A).
   *      ACTION_DELETE_A_B  Get the next B. (Delete B).
  */
  protected function action($command) {
    switch($command) {
      case self::ACTION_KEEP_A:
        $this->output .= $this->a;

      case self::ACTION_DELETE_A:
        $this->a = $this->b;

        if ($this->a === "'" || $this->a === '"') {
          for (;;) {
            $this->output .= $this->a;
            $this->a       = $this->get();

            if ($this->a === $this->b) {
              break;
            }

            if (ord($this->a) <= self::ORD_LF) {
              throw new JSMinException('Unterminated string literal in file '.$this->filename.' on line '.$this->lineno);
            }

            if ($this->a === '\\') {
              $this->output .= $this->a;
              $this->a       = $this->get();
            }
          }
        }

      case self::ACTION_DELETE_A_B:
        $this->b = $this->next();

        if ($this->b === '/' && (
            $this->a === '(' || $this->a === ',' || $this->a === '=' ||
            $this->a === ':' || $this->a === '[' || $this->a === '!' ||
            $this->a === '&' || $this->a === '|' || $this->a === '?' ||
            $this->a === '{' || $this->a === '}' || $this->a === ';' ||
            $this->a === "\n" )) {

          $this->output .= $this->a . $this->b;

          for (;;) {
            $this->a = $this->get();

            if ($this->a === '[') {
              /*
                inside a regex [...] set, which MAY contain a '/' itself. Example: mootools Form.Validator near line 460:
                  return Form.Validator.getValidator('IsEmpty').test(element) || (/^(?:[a-z0-9!#$%&'*+/=?^_`{|}~-]\.?){0,63}[a-z0-9!#$%&'*+/=?^_`{|}~-]@(?:(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\])$/i).test(element.get('value'));
              */
              for (;;) {
                $this->output .= $this->a;
                $this->a = $this->get();

                if ($this->a === ']') {
                    break;
                } elseif ($this->a === '\\') {
                  $this->output .= $this->a;
                  $this->a       = $this->get();
                } elseif (ord($this->a) <= self::ORD_LF) {
                  throw new JSMinException('Unterminated regular expression set in regex literal in file '.$this->filename.' on line '.$this->lineno);
                }
              }
            } elseif ($this->a === '/') {
              break;
            } elseif ($this->a === '\\') {
              $this->output .= $this->a;
              $this->a       = $this->get();
            } elseif (ord($this->a) <= self::ORD_LF) {
               throw new JSMinException('Unterminated regular expression literal in file '.$this->filename.' on line '.$this->lineno);
            }

            $this->output .= $this->a;
          }

          $this->b = $this->next();
        }
    }
  }

  /**
   * Get next char. Convert ctrl char to space.
   *
   * @return string|null
   */
  protected function get() {

    $c = $this->lookAhead;
    $this->lookAhead = null;

    if ($c === null) {
      if ($this->inputIndex < $this->inputLength) {
        $c = substr($this->input, $this->inputIndex, 1);
        $this->inputIndex += 1;
      } else {
        $c = null;
      }
    }
    if ($c === "\r" || $c === "\n") {
	    $this->lineno++;
    }

    if ($c === "\r") {
      return "\n";
    }

    if ($c === null || $c === "\n" || ord($c) >= self::ORD_SPACE) {
      return $c;
    }

    return ' ';
  }

  /**
   * Is $c a letter, digit, underscore, dollar sign, or non-ASCII character.
   *
   * @return bool
   */
  protected function isAlphaNum($c) {
    return ord($c) > 126 || $c === '\\' || preg_match('/^[\w\$]$/', $c) === 1;
  }

  /**
   * Perform minification, return result
   *
   * @uses action()
   * @uses isAlphaNum()
   * @uses get()
   * @uses peek()
   * @return string
   */
  protected function min() {
    if (0 == strncmp($this->peek(), "\xef", 1)) {
        $this->get();
        $this->get();
        $this->get();
    }

    $this->a = "\n";
    $this->action(self::ACTION_DELETE_A_B);

    while ($this->a !== null) {
      switch ($this->a) {
        case ' ':
          if ($this->isAlphaNum($this->b)) {
            $this->action(self::ACTION_KEEP_A);
          } else {
            $this->action(self::ACTION_DELETE_A);
          }
          break;

        case "\n":
          switch ($this->b) {
            case '{':
            case '[':
            case '(':
            case '+':
            case '-':
            case '!':
            case '~':
              $this->action(self::ACTION_KEEP_A);
              break;

            case ' ':
              $this->action(self::ACTION_DELETE_A_B);
              break;

            default:
              if ($this->isAlphaNum($this->b)) {
                $this->action(self::ACTION_KEEP_A);
              }
              else {
                $this->action(self::ACTION_DELETE_A);
              }
          }
          break;

        default:
          switch ($this->b) {
            case ' ':
              if ($this->isAlphaNum($this->a)) {
                $this->action(self::ACTION_KEEP_A);
                break;
              }

              $this->action(self::ACTION_DELETE_A_B);
              break;

            case "\n":
              switch ($this->a) {
                case '}':
                case ']':
                case ')':
                case '+':
                case '-':
                case '"':
                case "'":
                  $this->action(self::ACTION_KEEP_A);
                  break;

                default:
                  if ($this->isAlphaNum($this->a)) {
                    $this->action(self::ACTION_KEEP_A);
                  }
                  else {
                    $this->action(self::ACTION_DELETE_A_B);
                  }
              }
              break;

            default:
              $this->action(self::ACTION_KEEP_A);
              break;
          }
      }
    }

    return $this->output;
  }

  /**
   * Get the next character, skipping over comments. peek() is used to see
   *  if a '/' is followed by a '/' or '*'.
   *
   * @uses get()
   * @uses peek()
   * @throws JSMinException On unterminated comment.
   * @return string
   */
  protected function next() {
    $c = $this->get();

    if ($c === '/') {
      switch($this->peek()) {
        case '/':
          for (;;) {
            $c = $this->get();

            if (ord($c) <= self::ORD_LF) {
              return $c;
            }
          }

        case '*':
		$this->get();

		for (;;) {
			switch($this->get()) {
			case '*':
				if ($this->peek() === '/') {
					$this->get();
					return ' ';
				}
				break;

			case null:
				throw new JSMinException('Unterminated comment  in file '.$this->filename.' on line '.$this->lineno);
			}
		}

	default:
		return $c;
      }
    }

    return $c;
  }

  /**
   * Get next char. If is ctrl character, translate to a space or newline.
   *
   * @uses get()
   * @return string|null
   */
  protected function peek() {
    $this->lookAhead = $this->get();
    return $this->lookAhead;
  }
}

// -- Exceptions ---------------------------------------------------------------
class JSMinException extends Exception {}


function version_compare_freepbx($version1, $version2, $op = null) {
	$version1 = str_replace("rc","RC", strtolower($version1));
	$version2 = str_replace("rc","RC", strtolower($version2));
	if (!is_null($op)) {
		return version_compare($version1, $version2, $op);
	} else {
		return version_compare($version1, $version2);
	}
}
?>
