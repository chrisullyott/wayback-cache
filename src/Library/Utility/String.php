<?php

/**
 * Methods for strings.
 */

namespace Cache\Library\Utility;

class String
{
    /**
     * Generate a random string.
     *
     * @param  integer $length  The desired length of the resulting string
     * @param  boolean $numeric Whether to make a numeric random string
     * @return string
     */
    public static function random($length = 32, $numeric = false)
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

}
