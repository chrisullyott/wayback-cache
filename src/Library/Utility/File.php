<?php

/**
 * Methods for the filesystem.
 */

namespace Cache\Library\Utility;

class File
{
    /**
     * Build a full path from parts passed as arguments.
     *
     * @return string
     */
    public static function path()
    {
        $parts = func_get_args();
        $sep = DIRECTORY_SEPARATOR;

        $path = rtrim(array_shift($parts), $sep);

        foreach ($parts as $p) {
            $path .= $sep . trim($p, $sep);
        }

        return $path;
    }

    /**
     * Read a file's contents.
     *
     * @param  string $path The path to the file
     * @return string
     */
    public static function read($path)
    {
        if (is_readable($path)) {
            return file_get_contents($path);
        }

        return false;
    }

    /**
     * Write a file and use locking.
     * http://php.net/manual/en/function.fopen.php
     *
     * @param  string $path     The path to the file
     * @param  string $contents The contents for the file
     * @param  string $mode     The mode required for the handle
     * @return booealn          Whether the handle was closed
     */
    public static function write($path, $contents, $mode = 'w')
    {
        $handle = fopen($path, $mode);

        if (flock($handle, LOCK_EX)) {
            fwrite($handle, $contents);
            fflush($handle);
            flock($handle, LOCK_UN);
        }

        return fclose($handle);
    }

    /**
     * Create a directory (recursively) and set its permissions.
     * http://permissions-calculator.org/decode/0775/
     *
     * @param  string  $path  A path for the directory
     * @param  integer $perms The permissions octal
     * @return boolean        Whether the directory exists
     */
    public static function createDir($path, $perms = 0775)
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
     * List the files in a directory (does not include hidden files).
     *
     * @param  string  $directory The directory path
     * @param  string  $pattern   A regex pattern to customize the selection
     * @param  boolean $basenames Whether to output only the filenames
     * @return array
     */
    public static function listDir($directory, $pattern = '*', $basenames = false)
    {
        $files = array();
        $path = rtrim($directory, '/') . '/' . $pattern;
        $glob = glob($path);

        $items = array_values(array_filter($glob, 'is_file'));

        if ($basenames) {
            foreach ($items as $k => $v) {
                $items[$k] = basename($v);
            }
        }

        return $items;
    }

    /**
     * Delete an entire directory including all of its contents.
     *
     * @param  string  $directory  The directory path
     * @param  boolean $keepParent Whether to avoid deleting the parent folder itself
     * @return boolean             Whether the directory was deleted
     */
    public static function deleteDir($directory, $keepParent = false)
    {
        if (is_dir($directory)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST) as $path) {
                $path->isFile() ? unlink($path->getPathname()) : rmdir($path->getPathname());
            }

            if (!$keepParent) {
                return rmdir($directory);
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Look into a directory and generate a path for a file that doesn't exist there.
     *
     * @param  string $directory The directory path
     * @param  string $extension The expected file extension, if any
     * @param  string $prefix    A prefix for the file, if any
     * @return string
     */
    public static function availablePath($directory, $extension = '', $prefix = '')
    {
        $extension = $extension ? '.' . trim($extension, '.') : '';

        do {
            $name = $prefix . String::random() . $extension;
            $path = self::path($directory, $name);
        } while (file_exists($path));

        return $path;
    }

}
