Wayback Cache
=============

A simple filesystem cache [made with care](http://chrisullyott.com/blog/2014-11-24-wayback-cache/). Cache responses from third-party APIs easily and responsibly.

```
<?php

    $cache = new Cache(array(
        'key'    => 'test-instance',
        'expire' => 'hourly'
    ));

    $url = 'http://ip-api.com/json/wired.com';
    $data = $cache->getByUrl($url);

    echo $data . "\n";

```

Features
--------

- **Fallbacks.** For most websites, old content is better than no content. If any API request fails, the cache returns the most recent content until the next request can be made.
- **Easy rate-limiting.** When the host reports only a few requests are remaining, the cache returns the most recent content until the host's rate limit has reset.
- **History states.** A number of previous requests are stored in the cache for future reference.
- **Small footprint.** Old or irrelevant cache files are removed automatically on a nightly basis.

Methods
-------

### get()

Reads the latest content from the cache, or returns `FALSE` if expired.

### set()

Updates the cache with new content and increments the expiration.

### getByUrl()

A combination of `get()` and `set()` where the latest content is either returned from the cache, or requested and re-cached from the URL when expired.

To avoid running into problems with the host's rate limit, pass in the rate limit headers (count remaining and reset time) and this method will stop requesting before you're blocked.

For example, from the [Vimeo API](https://developer.vimeo.com/guidelines/rate-limiting):

```
$data = $cache->getByUrl($url, 'X-RateLimit-Remaining', 'X-RateLimit-Reset');
```

Options
-------

Cache options are set as an associative array passed to the object being instantiated (see above).

### container _(string)_

Sets the path to the parent cache directory, where this cache instance will be stored. If the path does not exist, it is created. The default is `cache`. For websites, you could use:

```
$_SERVER['DOCUMENT_ROOT'] . '/_cache'
```

### key _(string)_

The identifier of this specific cache instance (i.e., `instagram_feed` or `weather_data`). This will be used for the name of a subdirectory inside the cache container directory.

### expire _(integer or string)_

The interval of time after which the cache will expire. Accepts either an integer (number of seconds) or a friendly keyword from the list below:

Value              | Cache expiration set
:----------        | :-----------
second             | Every second
minute             | Every minute
hourly             | Every hour
workday            | Every eight hours
halfday            | Every twelve hours
nightly (default)  | Every night at midnight
weekly             | Every Sunday night at midnight
monthly            | Every first of the month at midnight

### offset _(integer)_

Pushes back the expiration time by a number of seconds. For example, to make the cache expire at 2:00 am, use `nightly` and the value of `2 * 60 * 60`. The default is `0`.

### historyLimit _(integer)_

Sets the maximum number of history states (cache files) that are allowed to remain in the filesystem. Once the cache has stored this many files, the oldest ones will soon be deleted (on the first request after midnight). The default is 25 history states.

### mustMatch _(string)_

A regular expression which incoming content must match in order for the cache to be updated. Example: `/user_images/`

### mustNotMatch _(string)_

A regular expression which incoming content _must not match_ in order for the cache to be updated. Example: `/error/`
