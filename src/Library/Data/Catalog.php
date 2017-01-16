<?php

/**
 * Catalog
 *
 * Extends AbstractData to provide CRUD operations necessary for caching.
 */

namespace Cache\Library\Data;

class Catalog extends AbstractData
{
    /**
     * Constructor.
     */
    public function __construct($filePath)
    {
        parent::__construct($filePath);
    }

    /**
     * Create a new catalog, overwriting an existing one.
     *
     * @param  array $data Initial data to store
     * @return bool        Whether the file was successfully written
     */
    public function create(array $data)
    {
        $this->setAll($data);

        return $this->save();
    }

    /**
     * Read data from the object. Return either a specific key, or all keys.
     *
     * @param  string $key A specific data key
     * @return string|array
     */
    public function read($key = null)
    {
        if ($key) {
            return $this->get($key);
        } else {
            return $this->getAll();
        }
    }

    /**
     * Update the catalog with new data and save the file.
     * Accepts an array as a single argument, or a key + value pair as two arguments.
     *
     * @return bool Whether the file was successfully written
     */
    public function update()
    {
        $a = func_get_args();

        if (is_array($a[0])) {
            $this->mergeAll($a[0]);
        } elseif (isset($a[0]) && isset($a[1])) {
            $this->set($a[0], $a[1]);
        }

        return $this->save();
    }

    /**
     * Delete the object and its data.
     *
     * @return bool Whether the file was successfully deleted
     */
    public function delete()
    {
        return parent::clear() && parent::delete();
    }

}
