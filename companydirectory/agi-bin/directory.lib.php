<?php

class company_dir{
	//agi class handler
	var $agi;
	//inital agi pased variables
	var $agivar;	
	//asterisk manager class handler
	var $ami;
	//pear::db database object handel
	var $db;
	//options of the directory that we are currently working with
	var $dir;
	//the current directory that we are working with
	var $directory;
	//string we are searching for
	var $searchstring;
	
	//this function is run by php automaticly when the class is initalized
	function __construct(){
		$this->agi=$this->__construct_agi();
		//$this->ami=$this->__construct_ami();
		$this->db=$this->__construct_db();
		//$this->agivars=$this->__construct_inital_vars();
		$this->directory=$this->agivar['dir'];
		$this->dir=$this->__construct_dir_opts();
	}
	
	//get agi handel/inital agi vars, called by __construct()
	function __construct_agi(){
		require_once('/var/lib/asterisk/agi-bin/phpagi.php');//todo: remove hardcoded path
		$agi=new AGI();
		foreach($agi->request as $key => $value){//strip agi_ prefix from keys
			if(substr($key,0,4)=='agi_'){
				$opts[str_replace('agi_','',$key)]=$value;
			}
		}

		foreach($opts as $key => $value){//get passed in vars
			if(substr($key,0,4)=='arg_'){
				$expld=explode('=',$value);
				$opts[$expld[0]]=$expld[1];
				unset($opts[$key]);
			}
		}
		
		array_shift($_SERVER['argv']);
		foreach($_SERVER['argv'] as $arg){
			$arg=explode('=',$arg);
			//remove leading '--'
			if(substr($arg['0'],0,2) == '--'){$arg['0']=substr($arg['0'],2);}
			$opts[$arg['0']]=isset($arg['1'])?$arg['1']:null;
		}
		$this->agivar=$opts;
		return $agi;
	}
	
	//get ami handel, called by __construct()
	function __construct_ami(){
		require_once($this->agi_get_var('ASTAGIDIR').'/phpagi-asmanager.php');//todo: remove hardcoded path
		$ami=new AGI_AsteriskManager();
		return $ami;
	}	
	
	//get database handel, called by __construct()
	function __construct_db(){
		require_once("DB.php");
		$dbhost=$this->agi_get_var('AMPDBHOST');
		$dbname=$this->agi_get_var('AMPDBNAME');
		$dbuser=$this->agi_get_var('AMPDBUSER');
		$dbpass=$this->agi_get_var('AMPDBPASS');
		$db=DB::connect('mysql://'.$dbuser.':'.$dbpass.'@'.$dbhost.'/'.$dbname);
		return $db;
	}
	
	//get options associated with the current dir
	function __construct_dir_opts(){
		$sql='SELECT * FROM directory_details WHERE ID = ?';
		$row=$this->db->getRow($sql,array($this->directory),DB_FETCHMODE_ASSOC);
		
		//set defualt if keys are blank
		if(!$row['announcement']){$row['announcement']='first-three-letters-entry';}
		if(!$row['valid_recording']){$row['valid_recording']='first-three-letters-entry';}
		if(!$row['repeat_recording']){$row['repeat_recording']='demo-nomatch';}
		if(!$row['invalid_recording']){$row['invalid_recording']='goodbye';}
		
		return $row;
	}

	//get a channel varibale	
	function agi_get_var($var){
		$ret=$this->agi->get_variable($var);
		if($ret['result']==1){
			$result=$ret['data'];
			return $result;
		}else{
			return '';
		}
	}

	function getKeypress($filename, $pressables='', $timeout=2000){
	  $ret=$this->agi->stream_file($filename, $pressables);
	  if(empty($ret['result'])){
	  	$ret=$this->agi->wait_for_digit($timeout);
	  }
	  return chr($ret['result']);
  }
	
	function readContact($con,$keys=''){
		switch($con['audio']){
			case 'vm':
				//check to see if we have a greet.* and play it. otherwise, fallback to spelling the name
				$dir=scandir($this->agi_get_var('ASTSPOOLDIR').'/voicemail/default/'.$con['dial']);
				foreach($dir as $file){
					if(strstr($file,'greet')){
						$file=pathinfo($file);	
						$ret=$this->agi->stream_file($this->agi_get_var('ASTSPOOLDIR').'/voicemail/default/'.$con['dial'].'/'.$file['filename'],$keys);
						break 2;	
					}	
				}
			case 'spell':
				foreach(str_split($con['name'],1) as $char){
					switch(true){
						case ctype_alpha($char):
							$ret=$this->agi->evaluate('SAY ALPHA '.$char.' '.$keys);
						break;
						case ctype_digit($char):
							$ret=$this->agi->say_digits($char, $keys);
						break;
						case ctype_space($char)://pause
							$ret=$this->agi->wait_for_digit(750);
						break;					
					}
					if(trim($ret['result'])){break;}
				}
			break;
			case 'tts':
				$ret=$this->agi->exec('Flite '.$con['name'].'|'.$keys);
			break;
			default:
				if(is_numeric($con['audio'])){
					$sql='SELECT filename from recordings where id = ?';
					$rec=$this->db->getOne($sql, array($con['audio']));
					$rec=explode('&',$rec);
					while(!$ret){
						foreach($rec as $r){
							$ret=$this->agi->stream_file($r,$keys);
						}
						if(trim($ret['result'])){break;}
					}
				}
			break; 
		}
		return $ret;
	}
	
	function search($key,$count=0){
		if(empty($key)){return false;}//requre search term
		//the regex in the query will match the searchstring at the beging of the string or after a space
		$num=array(1,2,3,4,5,6,7,8,9,0,'#');
		$alph=array('','[abc]','[def]','[ghi]','[jkl]','[mno]','[pqrs]','[tuv]','[wxyz]','( )','');
		
		if(strlen($key)>1){
			$keys=array();
			foreach((array)$key as $index => $digit){
				$keys[]=str_replace($num,$alph,$digit);
			}
			$this->searchstring=implode($keys);
		}else{
			$this->searchstring=str_replace($num,$alph,$key);
		}
		
		if($count==1){
			$sql='SELECT COUNT(*) FROM directory_entries WHERE id = ? AND name REGEXP ?';
			$res=$this->db->getOne($sql,array($this->directory,'(^| )'.$this->searchstring));
		}else{
			$sql='SELECT * FROM directory_entries WHERE id = ? AND name REGEXP ?';
			$res=$this->db->getAll($sql,array($this->directory,'(^| )'.$this->searchstring),DB_FETCHMODE_ASSOC);
		}
		//$this->dbug($this->db->last_query,$res);
		//$this->dbug('search results',$res);
		return $res;
	}

  
 /* 
  * FreePBX Debuging function
  * This function can be called as follows:
  * dbug() - will just print a time stamp to the debug log file ($amp_conf['FPBXDBUGFILE'])
  * dbug('string') - same as above + will print the string
  * dbug('string',$array) - same as above + will print_r the array after the message
  * dbug($array) - will print_r the array with no message (just a time stamp)  
  * dbug('string',$array,1) - same as above + will var_dump the array
  * dbug($array,1) - will var_dump the array with no message  (just a time stamp)
  * 	 
 	*/  
	function dbug(){
		$opts=func_get_args();
		//call_user_func_array('freepbx_debug',$opts);
		$disc=$msg=$dump='';
		//sort arguments
		switch(count($opts)){
			case 1:
				$msg=$opts[0];
			break;
			case 2:
				if(is_array($opts[0])||is_object($opts[0])){
					$msg=$opts[0];
					$dump=$opts[1];
				}else{
					$disc=$opts[0];
					$msg=$opts[1];
				}
			break;
			case 3:
				$disc=$opts[0];
				$msg=$opts[1];
				$dump=$opts[2];
			break;	
		}
		if($disc){$disc=' \''.$disc.'\':';}
		$txt=date("Y-M-d H:i:s").$disc."\n"; //add timestamp
		$this->dbug_write($txt,1);
		if($dump==1){//force output via var_dump
			ob_start();
			var_dump($msg);
			$msg=ob_get_contents();
			ob_end_clean();
			$this->dbug_write($msg."\n");
		}elseif(is_array($msg)||is_object($msg)){
			$this->dbug_write(print_r($msg,true)."\n");
		}else{
			$this->dbug_write($msg."\n");
		}
	}
	function dbug_write($txt,$check=''){
		$append=FILE_APPEND;
		//optionaly ensure that dbug file is smaller than $max_size
		if($check){
			$max_size=52428800;//hardcoded to 50MB. is that bad? not enough?
			$size=filesize('/tmp/freepbx_debug.log');
			$append=(($size > $max_size)?'':FILE_APPEND);
		}
		file_put_contents('/tmp/freepbx_debug.log',$txt, $append);
	}
}
?>