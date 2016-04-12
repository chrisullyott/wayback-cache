<?php

/**
 * "Wayback" Cache
 *
 * Caches data while intelligently managing a history of previous states.
 * Created November 2014.
 *
 * @author Chris Ullyott
 * @version 2.2
 */

class Cache
{
    /* !PROPERTIES */

    // Cache
    private $url;
    private $key = 'default';
    private $expire = 'nightly';
    private $mustMatch = null;
    private $mustNotMatch = null;
    private $offset = 0;
    private $retry = false;
    private $limit = 10;
    private $currentTime;

    // Authentication
    private $username;
    private $password;

    // Paths
    private $container = '/_cache';
    private $containerPath;
    private $cachePath;
    private $catalogPath;


    /* !CONSTRUCT OBJECT */

    public function __construct($args)
    {
        // What time is it?  |(• ◡•)|/ \(❍ᴥ❍ʋ)
        $this->currentTime = strtotime('now');

        // Set all properties
        if (is_array($args)) {
            foreach($args as $key => $val) {
                $this->{$key} = $val;
            }
        }

        // Congruency points
        $this->congruentParams = array(
            'container',
            'key',
            'expire',
            'mustMatch',
            'mustNotMatch',
        );

        // Set up paths
        if (!$this->containerPath) {
            $this->containerPath = $this->path($_SERVER['DOCUMENT_ROOT'], $this->container);
        }

        $this->cachePath = $this->path($this->containerPath, $this->key);
        $this->catalogPath = $this->path($this->cachePath, '.catalog');

        // Clear the cache
        if (isset($_GET['clearCache'])) {
            self::deleteDir($this->cachePath);
        }

        // Initialize cache
        $this->initCache();
    }


    /* !GET METHOD */

    // Read the latest entry, or make attempts to retrieve the data.
    public function get()
    {
        $data = '';
        $lastData = '';

        $catalog = $this->getCatalog();
        $historyStates = $catalog['history'];

        // data is not expired
        if ($historyStates) {
            $lastData = $this->readHistory($historyStates);
            if (($this->currentTime < $catalog['expire_time'])) {
                $data = $lastData;
            }
        }

        // data has expired or cache config is different
        if (!$data) {

            // make a new request
            if ($this->retry) {

                // multiple fetch attempts
                $attempt = 0;
                $attemptMax = 2;

                while (($data == '' || $data == $lastData) && ($attempt < $attemptMax)) {
                    $data = $this->fetchData($this->url);
                    ++$attempt;
                }

            } else {

                // single fetch attempt
                $data = $this->fetchData($this->url);
            }


            // if new data is NULL, return last and skip writing history
            if (!$data) {
                return $lastData;
            }

            // if new data doesn't match expected pattern, return last and skip writing history
            if ($this->mustMatch && preg_match($this->mustMatch, $data) == 0) {
                return $lastData;
            }

            // if new data doesn't match expected pattern, return last and skip writing history
            if ($this->mustNotMatch && preg_match($this->mustNotMatch, $data) == 1) {
                return $lastData;
            }

            // store a history state
            $historyFile = $this->availableFilename($this->cachePath, date('Ymd'));
            $this->createFile($this->path($this->cachePath, $historyFile), $data);

            // log a history state
            $historyStates = $this->updateHistory(
                $historyStates, array(
                    'file' => $historyFile,
                    'date' => date('r', $this->currentTime),
                    'time' => $this->currentTime,
                    'size' => strlen($data),
                )
            );

            // delete old states
            $historyPreserved = $this->curateHistory($historyStates);

            // update the catalog
            $catalogUpdates = array(
                'expire_freq' => $this->expire,
                'expire_offset' => $this->offset,
                'expire_date' => $this->expireTime($this->expire, $this->offset),
                'expire_time' => strtotime($this->expireTime($this->expire, $this->offset)),
                'last_date' => date('r', $this->currentTime),
                'last_time' => $this->currentTime,
                'history' => $historyPreserved,
            );

            // make cache congruent, while still using existing history states
            if (!$this->isCongruentCache($catalog)) {
                $objectVars = get_object_vars($this);
                foreach ($this->congruentParams as $var) {
                    $catalogUpdates[$var] = $objectVars[$var];
                }
            }

            $this->updateCatalog($catalogUpdates);
        }

        return $data;
    }


    /* !HIGH-LEVEL FUNCTIONS */

    // initialize the cache directory and catalog
    public function initCache()
    {
        $this->createDir($this->cachePath);
        if (!$this->isValidCache()) {
            $this->createFile($this->catalogPath, $this->initCatalog());
        }

        return true;
    }

    // create a catalog
    public function initCatalog()
    {
        $catalog = array();

        $catalog = array_merge(
            get_object_vars($this),
            array(
                'expire_date' => $this->expireTime($this->expire, $this->offset),
                'expire_time' => strtotime($this->expireTime($this->expire, $this->offset)),
                'last_date' => date('r', $this->currentTime),
                'last_time' => $this->currentTime,
                'created_date' => date('r', $this->currentTime),
                'created_time' => $this->currentTime,
            )
        );

        // Remove username and password
        unset($catalog['username']);
        unset($catalog['password']);

        $catalog['history'] = array();

        return json_encode($catalog);
    }

    // update the catalog
    public function updateCatalog($data = array())
    {
        $catalog = $this->getCatalog();

        foreach ($data as $key => $dataPoint) {
            if ($dataPoint) {
                $catalog[$key] = $data[$key];
            }
        }

        unset($catalog['history']);
        $catalog['history'] = $data['history'];
        $this->writeFile($this->catalogPath, json_encode($catalog));

        return true;
    }

    // update a history list
    public function updateHistory($historyStates = array(), $data = array())
    {
        $historyItem = array(
            'file' => $data['file'],
            'date' => $data['date'],
            'time' => $data['time'],
            'size' => $data['size'],
        );
        array_unshift($historyStates, $historyItem);

        return $historyStates;
    }

    // read + parse the catalog into an array
    public function getCatalog()
    {
        $catalog = file_get_contents($this->catalogPath);
        $catalog = json_decode($catalog, true);

        return $catalog;
    }

    // read a given history state, $index = 0 defaults to latest
    public function readHistory($historyStates, $index = 0)
    {
        if (isset($historyStates[$index]['file'])) {
            $filePath = $this->path($this->cachePath, $historyStates[$index]['file']);

            return file_get_contents($filePath);
        }
    }

    // delete all files in this cache directory, except the allowed number
    // of history states
    public function curateHistory($historyStates)
    {
        if ($this->limit) {
            $historyStates = array_slice($historyStates, 0, $this->limit);
            $filesInCache = $this->listFiles($this->cachePath, '*');
            $filesToPreserve = $this->getKeyValues($historyStates, 'file');
            $filesToDiscard = array_diff($filesInCache, $filesToPreserve);

            foreach ($filesToDiscard as $fileName) {
                unlink($this->path($this->cachePath, $fileName));
            }
        }

        return $historyStates;
    }

    // check if is a valid cache dir
    public function isValidCache()
    {
        $isValid = false;

        if (file_exists($this->catalogPath)) {
            $catalog = $this->getCatalog();

            if ($catalog['key'] != '') {
                $isValid = true;
            }
        }

        return $isValid;
    }

    // Check if cache configuration is congruent with this object
    public function isCongruentCache($catalog)
    {
        $isCongruent = true;

        foreach ($this->congruentParams as $c) {
            if (!isset($catalog[$c]) || ($catalog[$c] !== $this->$c)) {
                return false;
            }
        }

        return $isCongruent;

    }

    // generate expiration time
    public function expireTime($expire, $offset = 0)
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

    /**
     * Fetch data with cURL
     */
    public function fetchData($url)
    {
        $ch = curl_init();

        if ($this->username) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }

        // Set the request URL
        curl_setopt($ch, CURLOPT_URL, $url);

        // Follow a redirect
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        // Return the contents
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Limit the entire transfer
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // Stop if error occurred
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);

        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

    /**
     * Get a full path from a list of parts
     */
    public static function path()
    {
        $s = DIRECTORY_SEPARATOR;
        $path = '';

        foreach (func_get_args() as $key => $p) {
            if ($key == 0) {
                $path .= rtrim($p, $s) . $s;
            } else {
                $path .= trim($p, $s) . $s;
            }
        }

        return rtrim($path, '/');
    }

    /**
     * List the files in a directory
     */
    public function listFiles($dir, $pattern)
    {
        $files = array();
        $globPath = rtrim($dir, '/') . '/' . $pattern;
        $filesGlob = glob($globPath);

        foreach ($filesGlob as $file) {
            if (is_file($file)) {
                $files[] = basename($file);
            }
        }

        return $files;
    }

    /**
     * Create a directory
     */
    public function createDir($path, $perms = 0775)
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

    /**
     * Create a new file with said contents
     */
    public function createFile($filePath, $contents = '', $perms = 0775)
    {
        if (!file_exists($filePath)) {
            if (file_put_contents($filePath, $contents) !== false) {
                chmod($filePath, $perms);

                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * Create a new file or overwrite an existing file with said contents
     */
    public function writeFile($filePath, $contents = '', $perms = 0775)
    {
        if (!file_exists($filePath)) {
            if (self::createFile($filePath, $contents, $perms)) {
                return true;
            }
        } else {
            if (!is_writable($filePath)) {
                chmod($filePath, $perms);
            }

            if (file_put_contents($filePath, $contents) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return all values of multidimensional array from a given key
     */
    public function getKeyValues($array, $key)
    {
        $values = array();
        
        if (is_array($array)) {
            foreach ($array as $item) {
                if ($item[$key]) {
                    $values[] = $item[$key];
                }
            }
        }

        return $values;
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
    public static function randomString($length = 20, $numeric = false)
    {
        $str = '';

        if ($numeric) {
            $characters = array_merge(range('0', '9'));
        } else {
            $characters = array_merge(range('A', 'Z'), range('a', 'z'), range('0', '9'));
        }

        $max = count($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $rand = mt_rand(0, $max);
            $str .= $characters[$rand];
        }

        return $str;
    }

    /**
     * Delete an entire directory
     */
    public function deleteDir($dirPath)
    {
        if (file_exists($dirPath)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $path) {
                $path->isFile() ? unlink($path->getPathname()) : rmdir($path->getPathname());
            }
        }

        rmdir($dirPath);
    }
}
