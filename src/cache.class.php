<?php

/*
* "Wayback" Cache
*
* Caches data while intelligently managing a history of previous states.
*
* @author Chris Ullyott
* @created November 2014
* @version 2.1
*
*/

class Cache
{

    /* !TO-DO */
    // 1. Build a CSV, graph, or any other kind of human-readable report for what is happening in a cache.
    // 2. Add timezone support.
    // 3. Catch errors in a handy log.

    /* !PROPERTIES */

    private $key = 'default';
    private $expire = 'nightly';
    private $mustMatch = null;
    private $mustNotMatch = null;
    private $offset = 0;
    private $retry = false;
    private $history_limit = 10;
    private $container = '/_cache';

    private $container_path;
    private $cache_path;
    private $catalog_path;

    private $current_time;


    /* !CONSTRUCT OBJECT */

    public function __construct($args)
    {

        // What time is it?  |(• ◡•)|/ \(❍ᴥ❍ʋ)
        $this->current_time = strtotime('now');

        // Set all properties
        if (is_array($args)) {
            foreach($args as $key => $val) {
                if(isset($this->{$key})) {
                    $this->{$key} = $val;
                }
            }
        }

        // Congruency points
        $this->congruentParams = array(
          'container',
          'key',
          'expire',
          'mustMatch',
          'mustNotMatch'
        );

        // Set up paths
        if (!$this->container_path) {
            $this->container_path = $this->path($_SERVER['DOCUMENT_ROOT'], $this->container);
        }
        $this->cache_path = $this->path($this->container_path, $this->key);
        $this->catalog_path = $this->path($this->cache_path, '.catalog');

        // Clear the cache
        if (isset($_GET['clearCache'])) {
            self::deleteDir($this->cache_path);
        }

        // Initialize cache
        $this->init_cache();
    }


    /* !GET METHOD */

    // Read the latest entry, or make attempts to retrieve the data.

    public function get($url)
    {
        $data = '';
        $last_data = '';

        $catalog = $this->get_catalog();
        $history_states = $catalog['history'];

        // data is not expired
        if ($history_states) {
            $last_data = $this->read_history($history_states);
            if (($this->current_time < $catalog['expire_time'])) {
                $data = $last_data;
            }
        }

        // data has expired or cache config is different
        if (!$data) {

            // make new request
            if ($this->retry) {

                // multiple fetch attempts
                $attempt = 0;
                $attempt_max = 2;
                while (($data == '' || $data == $last_data) && ($attempt < $attempt_max)) {
                    $data = $this->fetch_data($url);
                    ++$attempt;
                }
            } else {

                // single fetch attempt
                $data = $this->fetch_data($url);
            }


            // if new data is NULL, return last and skip writing history
            if (!$data) {
                return $last_data;
            }

            // if new data doesn't match expected pattern, return last and skip writing history
            if ($this->mustMatch && preg_match($this->mustMatch, $data) == 0) {
                return $last_data;
            }

            // if new data doesn't match expected pattern, return last and skip writing history
            if ($this->mustNotMatch && preg_match($this->mustNotMatch, $data) == 1) {
                return $last_data;
            }

            // store a history state
            $history_file = $this->availableFilename($this->cache_path, date('Ymd'));
            $this->create_file($this->path($this->cache_path, $history_file), $data);

            // log a history state
            $history_states = $this->update_history($history_states, array(
                    'file' => $history_file,
                    'date' => date('r', $this->current_time),
                    'time' => $this->current_time,
                    'size' => strlen($data),
                ));

            // delete old states
            $history_preserved = $this->curate_history($history_states);

            // update the catalog
            $catalog_updates = array(
                    'expire_freq' => $this->expire,
                    'expire_offset' => $this->offset,
                    'expire_date' => $this->expire_time($this->expire, $this->offset),
                    'expire_time' => strtotime($this->expire_time($this->expire, $this->offset)),
                    'last_date' => date('r', $this->current_time),
                    'last_time' => $this->current_time,
                    'history' => $history_preserved
              );

            // make cache congruent, while still using existing history states
            if (!$this->is_congruent_cache($catalog)) {
                $obj_vars = get_object_vars($this);
                foreach ($this->congruentParams as $var) {
                    $catalog_updates[$var] = $obj_vars[$var];
                }
            }

            $this->update_catalog($catalog_updates);
        }

        return $data;
    }


    /* !HIGH-LEVEL FUNCTIONS */

    // initialize the cache directory and catalog
    public function init_cache()
    {
        $this->create_dir($this->cache_path);
        if (!$this->is_valid_cache()) {
            $this->create_file($this->catalog_path, $this->init_catalog());
        }

        return true;
    }

    // create a catalog
    public function init_catalog()
    {
        $catalog = array();
        $catalog = array_merge(
          get_object_vars($this),
          array(
            'expire_date' => $this->expire_time($this->expire, $this->offset),
            'expire_time' => strtotime($this->expire_time($this->expire, $this->offset)),
            'last_date' => date('r', $this->current_time),
            'last_time' => $this->current_time,
            'created_date' => date('r', $this->current_time),
            'created_time' => $this->current_time,
          )
        );
        $catalog['history'] = array();

        return json_encode($catalog);
    }

    // update the catalog
    public function update_catalog($data = array())
    {
        $catalog = $this->get_catalog();
        foreach ($data as $key => $data_point) {
            if ($data_point) {
                $catalog[$key] = $data[$key];
            }
        }
        unset($catalog['history']);
        $catalog['history'] = $data['history'];
        $this->write_file($this->catalog_path, json_encode($catalog));

        return true;
    }

    // update a history list
    public function update_history($history_states = array(), $data = array())
    {
        $history_item = array(
            'file' => $data['file'],
            'date' => $data['date'],
            'time' => $data['time'],
            'size' => $data['size'],
        );
        array_unshift($history_states, $history_item);

        return $history_states;
    }

    // read + parse the catalog into an array
    public function get_catalog()
    {
        $catalog = $this->read_file($this->catalog_path);
        $catalog = json_decode($catalog, true);

        return $catalog;
    }

    // read a given history state, $index = 0 defaults to latest
    public function read_history($history_states, $index = 0)
    {
        if (isset($history_states[$index]['file'])) {
            $file_path = $this->path($this->cache_path, $history_states[$index]['file']);

            return $this->read_file($file_path);
        }
    }

    // preserve + discard history items
    public function curate_history($history_states)
    {
        $history_files = $this->list_files($this->cache_path, '*');
        $history_preserved = $history_states;
        if ($this->history_limit) {
            $history_preserved = array_slice($history_states, 0, $this->history_limit);
        }
        $history_files_preserved = $this->get_key_values($history_preserved, 'file');
        $history_discard = array_diff($history_files, $history_files_preserved);
        foreach ($history_discard as $history_state_discard) {
            unlink($this->path($this->cache_path, $history_state_discard));
        }

        return $history_preserved;
    }

    // check if is a valid cache dir
    public function is_valid_cache()
    {
        $is_valid = false;
        if (file_exists($this->catalog_path)) {
            $catalog = $this->get_catalog();
            if ($catalog['key'] != '') {
                $is_valid = true;
            }
        }

        return $is_valid;
    }

    // check if cache configuration is congruent with this object
    public function is_congruent_cache($catalog)
    {
        $is_congruent = true;
        foreach ($this->congruentParams as $c) {
            if (!isset($catalog[$c]) || ($catalog[$c] !== $this->$c)) {
                return false;
            }
        }

        return $is_congruent;

    }

    // generate expiration time
    public function expire_time($expire, $offset = 0)
    {
        $time = null;
        $format = 'r';
        if ($expire == 'second') {
            $time = date($format, strtotime('+1 second', strtotime(date('Y-m-d H:i:s'))));
        } elseif ($expire == '30-second') {
            $time = date($format, strtotime('+30 seconds', strtotime(date('Y-m-d H:i:s'))));
        } elseif ($expire == 'minute') {
            $time = date($format, strtotime('+1 minute', strtotime(date('Y-m-d H:i:00'))));
        } elseif ($expire == 'hourly') {
            $time = date($format, strtotime('+1 hour', strtotime(date('Y-m-d H:00:00'))));
        } elseif ($expire == 'workday') {
            $time = date($format, strtotime('+8 hours', strtotime(date('Y-m-d H:00:00'))));
        } elseif ($expire == 'halfday') {
            $time = date($format, strtotime('+12 hours', strtotime(date('Y-m-d H:00:00'))));
        } elseif ($expire == 'nightly') {
            $time = date($format, strtotime('+1 day', strtotime(date('Y-m-d'))));
        } elseif ($expire == 'weekly') {
            $time = date($format, strtotime('+1 week', (strtotime('this week', strtotime(date('Y-m-d'))))));
        } elseif ($expire == 'monthly') {
            $time = date($format, strtotime('+1 month', (strtotime('this month', strtotime(date('Y-m'))))));
        } else {
            // default is "nightly"
            $time = date($format, strtotime('+1 day', strtotime(date('Y-m-d'))));
        }
        if ($offset) {
            // add seconds offset
            $time = date($format, (strtotime($time) + $offset));
        }

        return $time;
    }

    /* !LOW-LEVEL FUNCTIONS */

    // write a full path from a list of parts
    public static function path()
    {
        $path = '';

        $s = DIRECTORY_SEPARATOR;

        foreach (func_get_args() as $key => $p) {
            if ($key == 0) {
                $path .= rtrim($p, $s) . $s;
            } else {
                $path .= trim($p, $s) . $s;
            }
        }

        return rtrim($path, '/');
    }

    // list the files in a directory
    public function list_files($dir, $pattern)
    {
        $files = array();
        $glob_path = rtrim($dir, '/').'/'.$pattern;
        $files_glob = glob($glob_path);
        foreach ($files_glob as $file) {
            if (is_file($file)) {
                $files[] = basename($file);
            }
        }

        return $files;
    }

    // read a file
    public function read_file($filepath)
    {
        return file_get_contents($filepath);
    }

    // create a directory
    public function create_dir($path, $perms = 0775)
    {
        if (!file_exists($path)) {
            if (mkdir($path, $perms, true)) {
                chmod($path, $perms);

                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    // create a new file with said contents
    public function create_file($filepath, $contents = '', $perms = 0775)
    {
        if (!file_exists($filepath)) {
            if (file_put_contents($filepath, $contents) !== false) {
                chmod($filepath, $perms);

                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    // create a new file or overwrite an existing file with said contents
    public function write_file($filepath, $contents = '', $perms = 0775)
    {
        if (!file_exists($filepath)) {
            if (self::create_file($filepath, $contents, $perms)) {
                return true;
            }
        } else {
            if (!is_writable($filepath)) {
                chmod($filepath, $perms);
            }
            if (file_put_contents($filepath, $contents) !== false) {
                return true;
            }
        }

        return false;
    }

    // return all values of multidimensional array from a given key
    public function get_key_values($array, $key)
    {
        $new_array = array();
        foreach ($array as $item) {
            if ($item[$key]) {
                $new_array[] = $item[$key];
            }
        }

        return $new_array;
    }

    /**
    * Generate a filename available within a directory
    */
    public static function availableFilename($directory, $prefix = '', $extension = '')
    {
    if ($prefix) {
      $prefix = $prefix . '_';
    }
    if ($extension) {
      $extension = '.' . trim($extension, '.');
    }
    $name = $prefix . self::randomString();
    $path = self::path($directory, $name);
    while (file_exists($path)) {
      $name = $prefix . self::randomString();
      $path = self::path($directory, $name);
    }
    return $name;
    }

    /**
    * Generate a random string
    */
    public static function randomString($length = 15, $numeric = false)
    {
        $str = '';
        if ($numeric) {
          $sessionaracters = array_merge(range('0', '9'));
        } else {
          $sessionaracters = array_merge(range('A', 'Z'), range('a', 'z'), range('0', '9'));
        }
        $max = count($sessionaracters) - 1;
        for ($i = 0; $i < $length; $i++) {
          $rand = mt_rand(0, $max);
          $str .= $sessionaracters[$rand];
        }
        return $str;
    }

    // fetch data with cURL
    public function fetch_data($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

    // delete an entire directory
    public function deleteDir($dirPath)
    {
        if (file_exists($dirPath)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $path) {
                $path->isFile() ? unlink($path->getPathname()) : rmdir($path->getPathname());
            }
        }
        rmdir($dirPath);
    }

    // get current microtime
    public function getTime()
    {
        $time = microtime();
        $time = explode(' ', $time);
        $time = $time[1] + $time[0];

        return $time;
    }

    // get microtime elapsed
    public function getTimeElapsed($time_start, $time_end)
    {
        $time_elapsed = round(($time_end - $time_start), 5);

        return $time_elapsed;
    }
}
