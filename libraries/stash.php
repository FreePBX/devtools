<?php
require_once('pest/PestJSON.php');
class Stash {
	public $project_key = 'freep12';
	private $api_url = 'http://git.freepbx.org/rest/api/1.0';
	private $pest;
	
	function __construct($username,$password) {
		$this->pest = new PestJSON('http://git.freepbx.org');
		$this->pest->setupAuth($username,$password);
		if(!$this->getUser($username)) {
			throw new Exception('Username/Password Not Valid');
		}
	}
	
	function getUser($username) {
		try {
			$o = $this->pest->get('/rest/api/1.0/users/'.$username);
		} catch (Exception $e) {
			return false;
		}
		return $o;
	}
	
	function getAllRepos() {
		try {
			$o = $this->pest->get('/projects/'.$this->project_key.'/repos?limit=200');
		} catch (Exception $e) {
			return false;
		}
		foreach($o['values'] as $key => $repo) {
			$o['values'][$key]['cloneSSH'] = "ssh://git@git.freepbx.org/".$this->project_key."/".$repo['name'].".git";
		}
		return $o;
	}
}

/*
$username = $argv[1];
$password = $argv[2];
$repo = $argv[3];
$description = '';





$pest->setupAuth($username,$password);

$o = $pest->get('/rest/api/1.0/projects/FREEP12/repos');

foreach($o['values'] as $repos) {
	if($repos['name'] == $repo) {
		$pest->delete('/rest/api/1.0/projects/FREEP12/repos/'.$repo);
	}
}

$data = array(
	'name' => $repo,
	'scmId' => 'git'
);
$pest->post('/rest/api/1.0/projects/FREEP12/repos',$data);
*/