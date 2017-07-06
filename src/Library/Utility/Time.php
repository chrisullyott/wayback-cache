<?php

/**
 * Methods for time.
 */

class Time
{
    /**
     * Get the timestamp of the next expiration based on a friendly keyword.
     *
     * @param  string  $expire The keyword denoting an expiration frequency
     * @param  integer $offset Extra seconds to add to the expiration time
     * @return integer
     */
    public static function nextExpire($expire, $offset = 0)
    {
        switch ($expire) {
            case is_numeric($expire):
                $time = time() + $expire;
                break;
            case 'minute':
                $time = strtotime('+1 minute', strtotime(date('Y-m-d H:i:00')));
                break;
            case 'hourly':
                $time = strtotime('+1 hour', strtotime(date('Y-m-d H:00:00')));
                break;
            case 'workday':
                $time = strtotime('+8 hours', strtotime(date('Y-m-d H:00:00')));
                break;
            case 'halfday':
                $time = strtotime('+12 hours', strtotime(date('Y-m-d H:00:00')));
                break;
            case 'nightly':
                $time = strtotime('+1 day', strtotime(date('Y-m-d')));
                break;
            case 'weekly':
                $time = strtotime('+1 week', (strtotime('this week', strtotime(date('Y-m-d')))));
                break;
            case 'monthly':
                $time = strtotime('+1 month', (strtotime('this month', strtotime(date('Y-m')))));
                break;
            default:
                // ... is "nightly"
                $time = strtotime('+1 day', strtotime(date('Y-m-d')));
        }

        return $time + $offset;
    }

    /**
     * Get the timestamp of the next cleanup time (midnight), optionally offset by
     * an additional number of hours.
     *
     * @param  integer $hoursPastMidnight The number of hours past midnight
     * @return integer
     */
    public static function nextCleanup($hoursPastMidnight = 0)
    {
        return strtotime('tomorrow midnight') + ($hoursPastMidnight * 60 * 60);
    }

    /**
     * Takes either a timestamp or a date string, and always returns a timestamp.
     *
     * @param  string|integer $dateOrTime Either a timestamp or date string
     * @return integer
     */
    public static function getFromDateOrTime($dateOrTime)
    {
        return self::isValid($dateOrTime) ? $dateOrTime : strtotime($dateOrTime);
    }

    /**
     * Verify that a given timestamp is valid.
     *
     * @param  integer $time A Unix timestamp
     * @return boolean
     */
    public static function isValid($time)
    {
        return is_numeric($time) && date('Y', $time) > '1970';
    }

}
