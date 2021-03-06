<?php

/**
 * "Wayback" Cache
 *
 * Caches data while intelligently managing a history of previous states.
 *
 * @link https://github.com/chrisullyott/wayback-cache
 * @author Chris Ullyott <contact@chrisullyott.com>
 */

class Cache
{
    /**
     * The key (essentially, the ID) of this cache.
     *
     * @var string
     */
    private $key;

    /**
     * The expiration frequency of this cache.
     *
     * @var string
     */
    private $expire = 'nightly';

    /**
     * A number of seconds to add to the expiration.
     *
     * @var integer
     */
    private $offset;

    /**
     * For fetching by URL - a regular expression newly requested data must match.
     *
     * @var string
     */
    private $mustMatch;

    /**
     * For fetching by URL - a regular expression newly requested data cannot match.
     *
     * @var string
     */
    private $mustNotMatch;

    /**
     * The number of history states (files) to keep in this cache.
     *
     * @var integer
     */
    private $historyLimit = 10;

    /**
     * The time this class began to run.
     *
     * @var integer
     */
    private $runTime;

    /**
     * The time this cache was created.
     *
     * @var integer
     */
    private $createdTime;

    /**
     * The time this cache expires.
     *
     * @var integer
     */
    private $expireTime;

    /**
     * The path for all caches.
     *
     * @var string
     */
    private $container = 'cache';

    /**
     * The path for this cache directory.
     *
     * @var string
     */
    private $cachePath;

    /**
     * This cache's catalog.
     *
     * @var Catalog
     */
    private $catalog;

    /**
     * If these object properties aren't equal to those stored in the catalog, the
     * cache is cleared and then rebuilt to keep current.
     *
     * @var array
     */
    private static $matchedProps = array(
        'key',
        'expire',
        'offset',
        'mustMatch',
        'mustNotMatch'
    );

    /**
     * Constructor.
     */
    public function __construct()
    {
        $a = func_get_args();

        // Set properties.
        if (is_array($a[0])) {
            $this->setProperties($a[0]);
        } else {
            if (isset($a[0])) {
                $this->key = $a[0];
            }

            if (isset($a[1])) {
                $this->expire = $a[1];
            }
        }

        // Initialize if invalid.
        if (!$this->isValid()) {
            $this->init();
        }
    }

    /**
     * Set multiple properties of this object via an associative array.
     *
     * @param array $properties An associative array of property names and values
     * @return self
     */
    private function setProperties(array $properties)
    {
        foreach ($properties as $name => $value) {
            if (property_exists($this, $name)) {
                $this->{$name} = $value;
            } else {
                throw new Exception("{$name} is not a valid property");
            }
        }

        return $this;
    }

    /**
     * What time is it?  |(• ◡•)|/ \(❍ᴥ❍ʋ)
     *
     * @return integer
     */
    private function getRunTime()
    {
        if (!$this->runTime) {
            $this->runTime = time();
        }

        return $this->runTime;
    }

    /**
     * Get this cache's key.
     *
     * @return string
     */
    private function getKey()
    {
        if (!$this->key) {
            throw new Exception('Cache key is missing');
        }

        return $this->key;
    }

    /**
     * Get the path to this cache.
     *
     * @return string
     */
    private function getCachePath()
    {
        if (!$this->cachePath) {
            $this->cachePath = File::path($this->container, $this->getKey());
        }

        return $this->cachePath;
    }

    /**
     * Get the catalog path.
     *
     * @return string
     */
    private function getCatalogPath()
    {
        return File::path($this->getCachePath(), '.catalog');
    }

    /**
     * Get the Catalog object belonging to this cache.
     *
     * @return Catalog
     */
    private function getCatalog()
    {
        if (!$this->catalog) {
            $this->catalog = new Catalog($this->getCatalogPath());
        }

        return $this->catalog;
    }

    /**
     * Initialize a new cache by clearing its directory and building a new catalog.
     *
     * @return boolean Whether the cache was set up
     */
    private function init()
    {
        // Create the directory if it doesn't exist
        File::createDir($this->getCachePath());

        // Build and save a new catalog file
        $props = array(
            'key'          => $this->getKey(),
            'expire'       => $this->expire,
            'offset'       => $this->offset,
            'mustMatch'    => $this->mustMatch,
            'mustNotMatch' => $this->mustNotMatch,
            'createdTime'  => $this->getRunTime(),
            'expireTime'   => Time::nextExpire($this->expire, $this->offset),
            'cleanupTime'  => Time::nextCleanup(),
            'historyLimit' => $this->historyLimit,
            'history'      => array()
        );

        return $this->getCatalog()->setAll($props);
    }

    /**
     * Determine whether this cache is valid by checking whether the catalog exists,
     * and whether the most relevant properties match those in the instantiated
     * Catalog object.
     *
     * @return boolean Whether the cache is valid
     */
    private function isValid()
    {
        $props = $this->getCatalog()->getAll();

        foreach (self::$matchedProps as $p) {
            if (!array_key_exists($p, $props) || ($props[$p] !== $this->{$p})) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find whether this cache's content is expired.
     *
     * @return boolean Whether expired
     */
    private function isExpired()
    {
        return $this->getCatalog()->get('expireTime') <= $this->getRunTime();
    }

    /**
     * Store a value in the cache, and catalog the new history state. Also performs
     * cleanup if it's cleanup time.
     *
     * @param  string $contents    The contents to store
     * @param  array  $historyData Extra information about this history state
     * @return boolean             Whether the cache was updated
     */
    public function set($contents, array $historyData = array())
    {
        $file = File::availablePath($this->getCachePath());

        // Stop if regexes do not allow this content.
        if ($this->passesRegex($contents) && File::write($file, $contents)) {
            return $this->addToHistory($file, $historyData) && $this->cleanup();
        }

        return false;
    }

    /**
     * Get the latest data from the cache. Return false if expired or unreadable.
     *
     * @return string
     */
    public function get()
    {
        if (!$this->isExpired()) {
            return $this->readFromHistory();
        }

        return false;
    }

    /**
     * Get the latest data from the cache, or fetch from URL if expired. If both Curl
     * and cache update were successful, return the new content. If not, return the
     * old content but increment the expiration time, so that we do not continually
     * make bad requests.
     *
     * @param  string $url The URL to fetch from
     * @return array
     */
    public function getByUrl($url, $remainingHeader = null, $resetTimeHeader = null)
    {
        // Get the last history state.
        $contents = $this->readFromHistory();

        // Empty or expired: make a new request.
        if (is_null($contents) || $this->isExpired()) {

            // Before requesting, first check if we've been rate-limited.
            if (!$this->isRateLimited($remainingHeader, $resetTimeHeader)) {

                // Request now with Curl.
                $curl = new Curl($url);
                $body = $curl->getBody();

                if ($curl->isSuccessful() && $this->set($body, $curl->getInfo())) {
                    $contents = $body;
                } else {
                    $this->increment();
                }
            }
        }

        return $contents;
    }

    /**
     * Determine whether a string passes defined regex tests.
     *
     * @param  string $string The string to operate on
     * @return boolean
     */
    private function passesRegex($string)
    {
        if (($this->mustMatch && preg_match($this->mustMatch, $string) === 0)
            ||
            ($this->mustNotMatch && preg_match($this->mustNotMatch, $string) === 1)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the cache has been rate limited.
     *
     * @param  string $remainingHeader The API's header for requests remaining
     * @param  string $resetTimeHeader The API's header for rate limit reset time
     * @return boolean
     */
    private function isRateLimited($remainingHeader = null, $resetTimeHeader = null)
    {
        $history = $this->getCatalog()->get('history');

        if (!empty($history[0]) && $remainingHeader && $resetTimeHeader) {
            $last = $history[0];

            if (!empty($last['headers'][$remainingHeader])) {
                $remaining = $last['headers'][$remainingHeader];
            } else {
                throw new Exception("Header {$remainingHeader} not found");
            }

            if (!empty($last['headers'][$resetTimeHeader])) {
                $resetTime = Time::getFromDateOrTime($last['headers'][$resetTimeHeader]);
            } else {
                throw new Exception("Header {$resetTimeHeader} not found");
            }

            // Stop at 10 requests left to provide a little buffer.
            return $remaining <= 10 && $this->getRunTime() < $resetTime;
        }

        return false;
    }

    /**
     * Read the contents of the cache in a given history state.
     *
     * @param  integer $index Which history state to read (defaults to latest)
     * @return string|boolean The contents of the file in storage
     */
    private function readFromHistory($index = 0)
    {
        $history = $this->getCatalog()->get('history');

        if (isset($history[$index]['file'])) {
            $file = File::path($this->getCachePath(), $history[$index]['file']);
            return File::read($file);
        }

        return null;
    }

    /**
     * Log a newly created cache file as a history state.
     *
     * @param string $file      The path to this history state
     * @param array  $extraData Extra data to save along with this history state
     * @return boolean Whether the catalog was updated
     */
    private function addToHistory($file, array $extraData = array())
    {
        $history = $this->getCatalog()->get('history');

        $historyState = array_merge($extraData, array(
            'file' => basename($file),
            'time' => $this->getRunTime()
        ));

        array_unshift($history, $historyState);
        $history = array_slice($history, 0, $this->historyLimit);

        return $this->getCatalog()->merge(array(
            'history'    => $history,
            'expireTime' => Time::nextExpire($this->expire, $this->offset)
        ));
    }

    /**
     * Delete all non-hidden files in the cache directory, except the allowed number
     * of history states.
     *
     * @return boolean Whether the catalog was updated
     */
    private function cleanupHistory()
    {
        $history = $this->getCatalog()->get('history', true);
        $history = array_slice($history, 0, $this->historyLimit);

        $filesInCache = File::listDir($this->getCachePath());
        $filesInCache = array_map('basename', $filesInCache);

        $filesToKeep = array_map(
            function ($arr) {
                return $arr['file'];
            }, $history
        );
        $filesToDiscard = array_diff($filesInCache, $filesToKeep);

        foreach ($filesToDiscard as $file) {
            unlink(File::path($this->getCachePath(), $file));
        }

        return $this->getCatalog()->merge(array(
            'cleanupTime' => Time::nextCleanup(),
            'historyLimit' => $this->historyLimit,
            'history' => $history
        ));
    }

    /**
     * Find whether it's cleanup time.
     *
     * @return boolean Whether time for cleanup
     */
    private function isCleanupTime()
    {
        return $this->getCatalog()->get('cleanupTime') <= $this->getRunTime();
    }

    /**
     * Delete old history states if it's cleanup time.
     *
     * @return boolean Whether we're all cleaned up
     */
    private function cleanup()
    {
        if ($this->isCleanupTime()) {
            return $this->cleanupHistory();
        }

        return true;
    }

    /**
     * Move the cache's expiration up to the next time.
     *
     * @return boolean Whether the catalog was updated
     */
    private function increment()
    {
        return $this->getCatalog()->merge(array(
            'expireTime' => Time::nextExpire($this->expire, $this->offset)
        ));
    }

    /**
     * Invalidate this cache.
     *
     * @return boolean Whether the cache was invalidated
     */
    private function invalidate()
    {
        return $this->getCatalog()->set('expireTime', 0);
    }

    /**
     * Clear the cache. If a key is not specified, the container is cleared.
     *
     * @return boolean Whether the cache was cleared
     */
    private function clear()
    {
        return File::deleteDir($this->getCachePath());
    }
}
