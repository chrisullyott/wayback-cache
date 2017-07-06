<?php

/**
 * AbstractData
 *
 * Interacts with the filesystem to store and retrieve data.
 */

abstract class AbstractData
{
    /**
     * The absolute path to the file.
     *
     * @var string
     */
    protected $path;

    /**
     * The object's data.
     *
     * @var array
     */
    protected $data;

    /**
     * Constructor.
     *
     * @param string $filePath The absolute path to the file.
     */
    protected function __construct($filePath)
    {
        $this->path = File::path($filePath);
        $this->data = $this->getAll();
    }

    /**
     * Set a key with a given value.
     *
     * @param  string $key   The key of the data item
     * @param  string $value The value of the data item
     * @return array         The current data
     */
    protected function set($key, $value)
    {
        $this->data[$key] = $value;

        return $this->data;
    }

    /**
     * Overwrite the object with new data.
     *
     * @param  array $data An array of values to store
     * @return array       The current data
     */
    protected function setAll(array $data)
    {
        $this->data = $data;

        return $this->data;
    }

    /**
     * Merge an array of values into the existing data.
     *
     * @param  array $data An array of values to store
     * @return array       The current data
     */
    protected function mergeAll(array $data)
    {
        $this->data = array_merge($this->data, $data);

        return $this->data;
    }

    /**
     * Get a value.
     *
     * @param  boolean $fromFile Whether to read from the filesystem
     * @return string
     */
    protected function get($key, $fromFile = false)
    {
        $data = $this->getAll($fromFile);

        return isset($data[$key]) ? $data[$key] : null;
    }

    /**
     * Get all values.
     *
     * @param  boolean $fromFile Whether to read from the filesystem
     * @return array
     */
    protected function getAll($fromFile = false)
    {
        if (!$this->data || $fromFile) {
            $contents = File::read($this->path);
            $data = json_decode($contents, true);
            $this->data = is_array($data) ? $data : array();
        }

        return $this->data;
    }

    /**
     * Save this object to the filesystem as JSON.
     *
     * @return boolean Whether the file was successfully written
     */
    protected function save()
    {
        $json = json_encode($this->data);

        return File::write($this->path, $json);
    }

    /**
     * Empty the object's data.
     */
    protected function clear()
    {
        return $this->data = array();
    }

    /**
     * Delete the physical file and clear the data.
     *
     * @return bool Whether the file was successfully deleted
     */
    protected function delete()
    {
        return unlink($this->path);
    }

}
