<?php

/**
 * Catalog
 *
 * Wrap the Log class to allow data storage in memory.
 *
 * @author Chris Ullyott <chris@monkdevelopment.com>
 */

class Catalog extends Log
{
    /**
     * The log data in memory.
     *
     * @var array
     */
    private $data;

    /**
     * Get a stored value by key.
     *
     * @param  string|integer $key The item key
     * @param  boolean $fromFile Whether to read from the physical file
     * @return mixed
     */
    public function get($key, $fromFile = false)
    {
        $array = $this->getAll($fromFile);

        return isset($array[$key]) ? $array[$key] : null;
    }

    /**
     * Get the log data.
     *
     * @param  boolean $fromFile Whether to read from the physical file
     * @return array
     */
    public function getAll($fromFile = false)
    {
        if (!$this->data || $fromFile) {
            $this->data = parent::getAll();
        }

        return $this->data;
    }
}
