<?php
if(!class_exists('PestJSON')) {
	require_once('pest/PestJSON.php');
}
class Stash {
	private $api_url = 'https://api.github.com';
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
		$this->pest = new PestJSON($this->api_url);
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
			$o = $this->pest->get('/users/' . $username, [], ['User-Agent' => 'FreePBX']);
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
	function getAllRepos($project_key='freepbx') {
		try {
			$o = $this->pest->get('/orgs/'.$project_key.'/repos', ['per_page' => 200], ['User-Agent' => 'FreePBX']);
		} catch (Exception $e) {
			return false;
		}
		$repos = [];
		foreach($o as $repo) {
			$name = $repo['name'];
			$repos[] = [
				'name' => $name,
				'cloneSSH' => "git@github.com:".$project_key."/".$name.".git",
				'cloneUrl' => "https://github.com/".$project_key."/".$name.".git"
			];
		}
		return $repos;
	}

	function getRepo($repoName,$project_key='freepbx') {
		try {
			$o = $this->pest->get('/repos/'.$project_key.'/'.$repoName, [], ['User-Agent' => 'FreePBX']);
		} catch (Exception $e) {
			return false;
		}
		$repo = [];
		if (is_array($o)) {
			$name = $o['name'];
			$repo = [
				'name' => $name,
				'cloneSSH' => "git@github.com:".$project_key."/".$name.".git",
				'cloneUrl' => "https://github.com/".$project_key."/".$name.".git"
			];
		}
		return $repo;
	}
}
