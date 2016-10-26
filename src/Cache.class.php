<?php

/**
 * "Wayback" Cache
 *
 * Caches data while intelligently managing a history of previous states.
 *
 * @author Chris Ullyott
 * @version 2.5
 */

class Cache
{
    /* !PROPERTIES */

    // Time
    private $currentTime;

    // Cache
    private $url          = null;
    private $key          = null;
    private $expire       = 'nightly';
    private $offset       = 0;
    private $mustMatch    = null;
    private $mustNotMatch = null;
    private $requestLimit = 100;
    private $historyLimit = 100;
    private $cacheEmpty   = false;
    private $retry        = false;

    // Content
    private $curlInfo;
    private $headerInfo;

    // Authentication
    private $username;
    private $password;

    // Paths
    private $container = '_cache';
    private $containerPath;
    private $cachePath;
    private $catalogPath;
    private $requestLogPath;


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

        // Set default key
        if (!$this->key) {
            $this->key = md5($this->url);
        }

        // Congruency points
        $this->congruentParams = array(
            'container',
            'key',
            'expire',
            'mustMatch',
            'mustNotMatch',
            'requestLimit',
            'historyLimit'
        );

        // Set up paths
        $this->containerPath = $this->path($this->container);
        $this->cachePath = $this->path($this->containerPath, $this->key);
        $this->catalogPath = $this->path($this->cachePath, '.catalog');
        $this->requestLogPath = $this->path($this->containerPath, '.requestLog');

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
        $data          = '';
        $lastData      = '';
        $fetchNew      = false;

        $catalog       = $this->getCatalog();
        $historyStates = $catalog['history'];
        $isExpired     = $catalog['expire_time'] <= $this->currentTime;

        if ($historyStates) {
            $lastData = $this->readHistory($historyStates);
        }

        if ($this->cacheEmpty) {
            if (!$isExpired && $historyStates) {
                $data = $lastData;
            } else {
                $fetchNew = true;
            }
        } else {
            if (!$isExpired && $lastData) {
                $data = $lastData;
            } else {
                $fetchNew = true;
            }
        }

        // data has expired or cache config is different
        if ($fetchNew) {

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

            // return last and skip writing history
            if (!$data && !$this->cacheEmpty) {
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
                    'file'    => $historyFile,
                    'time'    => $this->currentTime,
                    'info'    => $this->curlInfo,
                    'headers' => $this->headerInfo,
                )
            );

            // create new array of updates
            $catalogUpdates = array();

            // delete old history states if it's cleanup time
            if ($this->currentTime >= $catalog['cleanup_time']) {
                $historyStates = $this->cleanupHistory($historyStates);
                $catalogUpdates['cleanup_time'] = self::getNextCleanupTime();
            }

            // update the catalog
            $catalogUpdates['expire_freq'] = $this->expire;
            $catalogUpdates['expire_offset'] = $this->offset;
            $catalogUpdates['expire_time'] = strtotime($this->expireTime($this->expire, $this->offset));
            $catalogUpdates['last_time'] = $this->currentTime;
            $catalogUpdates['history'] = $historyStates;

            // make cache congruent in case it isn't,
            // while still using existing history states
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
            $this->createFile($this->requestLogPath);
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
                'expire_time' => strtotime($this->expireTime($this->expire, $this->offset)),
                'last_time' => $this->currentTime,
                'created_time' => $this->currentTime,
                'cleanup_time' => self::getNextCleanupTime()
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
            'file'    => $data['file'],
            'time'    => $data['time'],
            'info'    => $data['info'],
            'headers' => $data['headers'],
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
    public function cleanupHistory($historyStates)
    {
        // enforce a limit of 1000 cache files
        if (!$this->historyLimit || $this->historyLimit > 1000) {
            $this->historyLimit = 1000;
        }

        // get the names of all files to discard
        $historyStates = array_slice($historyStates, 0, $this->historyLimit);
        $filesInCache = $this->listFiles($this->cachePath, '*');
        $filesToPreserve = $this->getKeyValues($historyStates, 'file');
        $filesToDiscard = array_diff($filesInCache, $filesToPreserve);

        foreach ($filesToDiscard as $fileName) {
            unlink($this->path($this->cachePath, $fileName));
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

    // Increment the request count in the API log
    // Return false when the rate limit has been reached
    private function checkRequestLog($url)
    {
        $log = file_get_contents($this->requestLogPath);
        $domain = parse_url($url, PHP_URL_HOST);
        $today = date('Y-m-d');

        // Get json
        if ($log) {
            $log = json_decode($log, true);
        } else {
            $log = array();
        }

        // Check for today's date (start fresh each day)
        if (!isset($log[$today])) {
            $log = array(
                $today => array()
            );
        }

        // Get current count for this domain
        if (isset($log[$today][$domain])) {
            $count = $log[$today][$domain];
        } else {
            $count = 0;
        }

        // Throw exception if limit has been reached, else increment the count
        if (is_int($this->requestLimit) && ($count >= $this->requestLimit)) {
            $exceptionMessage  = 'The API request limit of ';
            $exceptionMessage .= $this->requestLimit . ' has been reached.';

            throw new Exception('API request limit has been reached.');
        } else {
            $count = $count + 1;
            $log[$today][$domain] = $count;

            return file_put_contents($this->requestLogPath, json_encode($log));
        }
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

    /**
     * Get the timestamp of the next nightly cleanup.
     */
    public static function getNextCleanupTime($hoursAfterMidnight = 2)
    {
        return strtotime(date('Y-m-d')) + (60 * 60 * (24 + $hoursAfterMidnight));
    }


    /* !LOW-LEVEL FUNCTIONS */

    /**
     * Fetch data with cURL
     */
    public function fetchData($url)
    {
        // Before fetching, make sure we're not running into the request limit.
        try {
            $this->checkRequestLog($url);
        } catch (Exception $e) {
            return null;
        }

        $ch = curl_init();

        // Set the request URL
        curl_setopt($ch, CURLOPT_URL, $url);

        // Authenticate
        if ($this->username) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }

        // For logging headers
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);

        // Follow a redirect
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        // Return the contents
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Limit the entire transfer
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // Execute
        $response = curl_exec($ch);

        // Get CURL info
        $this->curlInfo = curl_getinfo($ch);

        // Parse headers
        $headers = array();
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headerLines = explode("\n", trim(substr($response, 0, $headerSize)));

        foreach ($headerLines as $headerLine) {
            $headerLineArray = explode(':', $headerLine, 2);

            if (stripos($headerLineArray[0], 'HTTP/') !== false) {
                $headerLineArray[1] = $headerLineArray[0];
                $headerLineArray[0] = 'Response';
            }

            $headerLineKey = trim($headerLineArray[0]);
            $headerLineValue = trim($headerLineArray[1]);
            $headers[$headerLineKey] = $headerLineValue;
        }

        $this->headerInfo = $headers;

        // Get data
        $data = trim(substr($response, $headerSize));

        // Close connection
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
