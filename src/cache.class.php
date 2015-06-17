<?php

/*
 * "Wayback" Cache
 *
 * Caches data while intelligently managing a history of previous states.
 *
 * @author Chris Ullyott
 * @created November 2014
 * @version 1.8
 *
 */

class Cache {


	/* !TO-DO */
	// 1. Define the list of catalog data points as a global variable for other methods to use (make checking for a congruent cache more straightforward).


	/* !WISH-LIST */
	// 1. Build a CSV, graph, or any other kind of human-readable report for what is happening in a cache.
	// 2. Add timezone support.



	/* !GLOBAL DEFAULTS */


	private $data_prefix = '.data';



	/* !CONSTRUCT OBJECT */


	public function __construct($options){

		// What time is it?  |(• ◡•)|/ \(❍ᴥ❍ʋ)
		$this->current_time = strtotime('now');

		// Vars as array
		if(is_array($options)){
			$this->key = 'default';
			if(isset($options['key'])){
				$this->key = $options['key'];
			}
			$this->expire = 'nightly';
			if(isset($options['expire'])){
				$this->expire = $options['expire'];
			}
			$this->offset = 0;
			if(isset($options['offset'])){
				$this->offset = $options['offset'];
			}
			$this->retry = false;
			if(isset($options['retry'])){
				$this->retry = $options['retry'];
			}
			$this->history_limit = 100;
			if(isset($options['limit'])){
				$this->history_limit = $options['limit'];
			}
			$this->container = $_SERVER['DOCUMENT_ROOT'] . '/_cache/';
			if(isset($options['container'])){
				$this->container = $options['container'];
			}
		}

		// URL option
		if($this->key=='url'){
			$this->key = self::url_dirname();
		}

		// Set paths
		$this->container = $this->path($this->container);
		$this->cache_path = $this->path($this->container, $this->key);
		$this->catalog_path = $this->path($this->cache_path, '.catalog');

		// Forcibly invalidate the cache
		$this->userExpired = false;
		if(isset($_GET['expireCache'])){
			$this->userExpired = true;
		}

		// Clear the cache
		if(isset($_GET['clearCache'])){
			self::deleteDir($this->cache_path);
		}

		// Initialize container directory
		$this->create_dir($this->container);

		// Initialize cache directory
		$this->init_cache();

	}



	/* !READ METHOD */

	// Read the latest entry if valid or return false.


	public function read(){

		$catalog = $this->get_catalog();

		if(!$this->is_congruent_cache($catalog)){
			return false;
		}

		$data = false;
		if(($this->current_time < $catalog['expire_time']) && !$this->userExpired){
			$data = $this->read_file($this->path($this->cache_path, $catalog['history'][0]['file']));
		}

		return $data;

	}



	/* !WRITE METHOD */

	// Write $data to the cache and create a new history state.


	public function write($data = ''){

		if($data!=''){

			$catalog = $this->get_catalog();
			$history_states = $catalog['history'];

			// store a history state
			$history_file = $this->available_filename($this->cache_path, $this->data_prefix . '_' . date('Ymd'));
			$this->create_file($this->path($this->cache_path, $history_file), $data);

			// log a history state
			$history_states = $this->update_history($history_states, array(
				'file' => $history_file,
				'date' => date('r', $this->current_time),
				'time' => $this->current_time,
				'size' => strlen($data)
			));

			// delete old states
			$history_preserved = $this->curate_history($history_states);

			// update the catalog
			$this->update_catalog(array(
				'expire_freq'   => $this->expire,
				'expire_offset' => $this->offset,
				'expire_date'   => $this->expire_time($this->expire, $this->offset),
				'expire_time'   => strtotime($this->expire_time($this->expire, $this->offset)),
				'last_date'     => date('r', $this->current_time),
				'last_time'     => $this->current_time,
				'history'       => $history_preserved
			));

			// return the data
			return $data;

		}

	}



	/* !GET METHOD */

	// Read the latest entry, or make attempts to retrieve the data from the $url.


	public function get($url){

		$data = '';
		$last_data = '';

		$catalog = $this->get_catalog();
		$history_states = $catalog['history'];

		// data is not expired
		if($history_states){
			$last_data = $this->read_history($history_states);
			if(($this->current_time < $catalog['expire_time']) && !$this->userExpired){
				return $last_data;
			}
		}

		// data has expired
		if(!$data || !$this->is_congruent_cache($catalog)){

			// use multiple attempts
			if($this->retry){
				$attempt = 0;
				$attempt_max = 2;
				while(($data=='' || $data==$last_data) && ($attempt < $attempt_max)){
					$data = $this->get_data($url);
					$attempt++;
				}
			} else {
				$data = $this->get_data($url);
			}

			// store a history state
			$history_file = $this->available_filename($this->cache_path, $this->data_prefix . '_' . date('Ymd'));
			$this->create_file($this->path($this->cache_path, $history_file), $data);

			// log a history state
			$history_states = $this->update_history($history_states, array(
				'file' => $history_file,
				'date' => date('r', $this->current_time),
				'time' => $this->current_time,
				'size' => strlen($data)
			));

			// delete old states
			$history_preserved = $this->curate_history($history_states);

			// update the catalog
			$this->update_catalog(array(
				'expire_freq'   => $this->expire,
				'expire_offset' => $this->offset,
				'expire_date'   => $this->expire_time($this->expire, $this->offset),
				'expire_time'   => strtotime($this->expire_time($this->expire, $this->offset)),
				'last_date'     => date('r', $this->current_time),
				'last_time'     => $this->current_time,
				'history'       => $history_preserved
			));

		}

		return $data;

	}



	/* !HIGH-LEVEL FUNCTIONS */


	// initialize the cache directory and catalog
	public function init_cache(){
		$this->create_dir($this->cache_path);
		if(!$this->is_valid_cache()){
			$this->create_file($this->catalog_path, $this->init_catalog());
		}
		return true;
	}


	// create a catalog
	public function init_catalog(){
		$catalog = array(
			'key'           => $this->key,
			'expire_freq'   => $this->expire,
			'expire_offset' => $this->offset,
			'expire_date'   => $this->expire_time($this->expire, $this->offset),
			'expire_time'   => strtotime($this->expire_time($this->expire, $this->offset)),
			'last_date'     => date('r', $this->current_time),
			'last_time'     => $this->current_time,
			'created_date'  => date('r', $this->current_time),
			'created_time'  => $this->current_time,
			'history_limit' => $this->history_limit,
			'history_count' => 0,
			'history'       => array()
		);
		return json_encode($catalog);
	}


	// update the catalog
	public function update_catalog($data = array()){
		$catalog = $this->get_catalog();
		foreach($data as $key => $data_point){
			if($data_point){
				$catalog[$key] = $data[$key];
			}
		}
		unset($catalog['history']);
		$catalog['history'] = $data['history'];
		$catalog['history_count'] = count($data['history']);
		$this->write_file($this->catalog_path, json_encode($catalog));
		return true;
	}


	// update a history list
	public function update_history($history_states = array(), $data = array()){
		$history_item = array(
			'file' => $data['file'],
			'date' => $data['date'],
			'time' => $data['time'],
			'size' => $data['size']
		);
		array_unshift($history_states, $history_item);
		return $history_states;
	}


	// read + parse the catalog into an array
	public function get_catalog(){
		$catalog = $this->read_file($this->catalog_path);
		$catalog = json_decode($catalog, true);
		return $catalog;
	}


	// read a given history state, $index = 0 defaults to latest
	public function read_history($history_states, $index = 0){
		if(isset($history_states[$index]['file'])){
			$file_path = $this->path($this->cache_path, $history_states[$index]['file']);
			return $this->read_file($file_path);
		}
	}


	// preserve + discard history items
	public function curate_history($history_states){
		$history_files = $this->list_files($this->cache_path, $this->data_prefix . '*');
		$history_preserved = $history_states;
		if($this->history_limit){
			$history_preserved = array_slice($history_states, 0, $this->history_limit);
		}
		$history_files_preserved = $this->get_key_values($history_preserved,'file');
		$history_discard = array_diff($history_files, $history_files_preserved);
		foreach($history_discard as $history_state_discard){
			unlink($this->path($this->cache_path, $history_state_discard));
		}
		return $history_preserved;
	}


	// check if is a valid cache dir
	public function is_valid_cache(){
		$is_valid = false;
		if(file_exists($this->catalog_path)){
			$catalog = $this->get_catalog();
			if($catalog['key']!=''){
				$is_valid = true;
			}
		}
		return $is_valid;
	}


	// check if cache is congruent with this object
	public function is_congruent_cache($catalog){
		$is_congruent = false;
		if($catalog){
			if(	($catalog['key']==$this->key) &&
					($catalog['expire_offset']==$this->offset) &&
					($catalog['expire_freq']==$this->expire) &&
					($catalog['history_limit']==$this->history_limit)
			){
				$is_congruent = true;
			}
		}
		return $is_congruent;
	}


	// generate expiration time
	public function expire_time($expire, $offset = 0){
		$time = NULL;
		$format = 'r';
		if($expire=='second'){
			$time = date($format,strtotime('+1 second',strtotime(date('Y-m-d H:i:s'))));
		}
		elseif($expire=='minute'){
			$time = date($format,strtotime('+1 minute',strtotime(date('Y-m-d H:i:00'))));
		}
		elseif($expire=='hourly'){
			$time = date($format,strtotime('+1 hour',strtotime(date('Y-m-d H:00:00'))));
		}
		elseif($expire=='workday'){
			$time = date($format,strtotime('+8 hours',strtotime(date('Y-m-d H:00:00'))));
		}
		elseif($expire=='halfday'){
			$time = date($format,strtotime('+12 hours',strtotime(date('Y-m-d H:00:00'))));
		}
		elseif($expire=='nightly'){
			$time = date($format,strtotime('+1 day',strtotime(date('Y-m-d'))));
		}
		elseif($expire=='weekly'){
			$time = date($format,strtotime('+1 week',(strtotime('this week',strtotime(date('Y-m-d'))))));
		}
		elseif($expire=='monthly'){
			$time = date($format,strtotime('+1 month',(strtotime('this month',strtotime(date('Y-m'))))));
		}
		else{
			// default is "nightly"
			$time = date($format,strtotime('+1 day',strtotime(date('Y-m-d'))));
		}
		if($offset){
			// add seconds offset
			$time = date($format,(strtotime($time)+$offset));
		}
		return $time;
	}



	/* !LOW-LEVEL FUNCTIONS */


	// write a full path from a list of parts
	public function path(){
		$path = '';
		foreach(func_get_args() as $key => $p){
			if($key==0){
				$path .= rtrim($p,'/') . '/';
			} else {
				$path .= trim($p,'/') . '/';
			}
		}
		return rtrim($path,'/');
	}


	// generate a directory name from the current URL
	// http://www.cyberciti.biz/faq/linuxunix-rules-for-naming-file-and-directory-names/
	function url_dirname(){
		$url_dirname = 'url_' . substr(urlencode($_SERVER['REQUEST_URI']), 0, 250);
		return $url_dirname;
	}


	// list the files in a directory
	public function list_files($dir, $pattern){
		$files = array();
		$glob_path = rtrim($dir,'/') . '/' . $pattern;
		$files_glob = glob($glob_path);
		foreach($files_glob as $file){
			if(is_file($file)){
				$files[] = basename($file);
			}
		}
		return $files;
	}


	// read a file
	public function read_file($filepath, $perms = 0775){
		if(file_exists($filepath)){
			if(!is_readable($filepath)){
				chmod($filepath, $perms);
			}
			return file_get_contents($filepath);
		} else {
			return false;
		}
	}


	// create a directory
	public function create_dir($path, $perms = 0775){
		if(!is_dir($path)){
			mkdir($path, $perms, true);
			chmod($path, $perms);
		}
		return true;
	}


	// create a file
	public function create_file($filepath, $contents = '', $perms = 0775){
		if(!file_exists($filepath)){
			file_put_contents($filepath, $contents);
			chmod($filepath, $perms);
		}
		return true;
	}


	// overwrite a file
	public function write_file($filepath, $contents = '', $perms = 0775){
		if(file_exists($filepath)){
			if(!is_writeable($filepath)){
				chmod($filepath, $perms);
			}
			file_put_contents($filepath, $contents);
		} else {
			file_put_contents($filepath, $contents);
			chmod($filepath, $perms);
		}
		return true;
	}


	// return all values of multidimensional array from a given key
	public function get_key_values($array, $key){
		$new_array = array();
		foreach($array as $item){
			if($item[$key]){
				$new_array[] = $item[$key];
			}
		}
		return $new_array;
	}


	// generate a filename available within a directory
	public function available_filename($dir_path, $prefix = '', $extension = ''){
		if($prefix){ $prefix = $prefix . '_'; }
		if($extension){ $extension = '.' . trim($extension,'.'); }
		$name = $prefix . rand(100000, 999999);
		$path = $this->path($dir_path, $name);
		while(file_exists($path)){
			$name = $prefix . rand(100000, 999999);
			$path = $this->path($dir_path, $name);
		}
		return $name;
	}


	// get_data() via David Walsh (http://davidwalsh.name/curl-download)
	public function get_data($url) {
		$ch = curl_init();
		$timeout = 5;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$data = curl_exec($ch);
		curl_close($ch);
		if(!$data){
			$data = file_get_contents($url); // fallback
		}
		return $data;
	}


	// delete an entire directory
	public function deleteDir($dirPath) {
		if(file_exists($dirPath)){
			foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $path){
				$path->isFile() ? unlink($path->getPathname()) : rmdir($path->getPathname());
			}
		}
		rmdir($dirPath);
	}


	// get current microtime
	public function getTime(){
		$time = microtime();
		$time = explode(' ', $time);
		$time = $time[1] + $time[0];
		return $time;
	}


	// get microtime elapsed
	public function getTimeElapsed($time_start, $time_end){
		$time_elapsed = round(($time_end - $time_start), 5);
		return $time_elapsed;
	}


}


?>