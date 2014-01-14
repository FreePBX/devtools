<?php
if(!class_exists('PestJSON')) {
	require_once('pest/PestJSON.php');
}
class Stash {
	public $project_key = 'freepbx';
	private $api_url = 'http://git.freepbx.org/rest/api/1.0';
	private $pest;
	
	/**
	 * Constructor for Stash
	 *
	 * Also checks for valid credentials before construct can finish
	 *
	 * @param   string $username Stash Username 
	 * @param   string $password Stash password
	 * @return  string
	 */
	function __construct($username,$password) {
		$this->pest = new PestJSON('http://git.freepbx.org');
		$this->pest->setupAuth($username,$password);
		if(!$this->getUser($username)) {
			throw new Exception('Username/Password Not Valid');
		}
	}
	
	/**
	 * Gets all information about said user from Stash
	 *
	 * @param   string $username Stash Username 
	 * @return  array
	 */
	function getUser($username) {
		try {
			$o = $this->pest->get('/rest/api/1.0/users/'.$username);
		} catch (Exception $e) {
			return false;
		}
		return $o;
	}
	
	/**
	 * Gets all repos for said project
	 *
	 * @return  string
	 */
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
	
	function getRepo($repoName) {
		try {
			$o = $this->pest->get('/projects/'.$this->project_key.'/repos/'.$repoName);
		} catch (Exception $e) {
			return false;
		}
		if (is_array($o)) {
			$o['cloneSSH'] = "ssh://git@git.freepbx.org/".$this->project_key."/".$o['name'].".git";
		}
		return $o;
	}
}
