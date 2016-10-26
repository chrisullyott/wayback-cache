Wayback Cache
===============

A PHP class for intelligently requesting and cataloging a history of data. Helpful when needing to cache requests from third-party API's and fall back to previous data if a new request is unsuccessful.

```
<?php require_once('Cache.class.php'); ?>
<?php

    $requestUrl = 'http://ip-api.com/json/wired.com';

    $cacheInstance = new Cache(array(
        'url'    => $requestUrl,
        'key'    => 'ip_lookup',
        'expire' => 'hourly'
    ));

    $ipData = $cacheInstance->get();

    echo $ipData;

?>
```

See also: http://chrisullyott.com/blog/2014-11-24-wayback-cache/

## Initialization

### container _(string)_

Sets the path to the parent cache directory, where this cache instance will be stored. If the path does not exist, it is created. The default is `_cache`. For websites, it's recommended to use:

```
$_SERVER['DOCUMENT_ROOT'] . '/_cache'
```

### container_path _(string)_

Similar to `container` but accepts a full path.

### key _(string)_

The identifier of this specific cache instance (i.e., `instagram_feed` or `weather_data`). This will be used for the name of a subdirectory inside the cache container directory.

### url _(string)_

The API's endpoint from which we will request a response.

### expiration _(string)_

Value                   | Cache expiration set
:----------             | :-----------
second                  | Every second
minute                  | Every minute
hourly                  | Every hour
nightly (default)       | Every night at midnight
weekly                  | Every Sunday night at midnight
monthly                 | Every first of the month at midnight

### offset _(integer)_

Pushes back the expiration time by a number of seconds. For example, to make the cache expire at 2:00 am, use `nightly` and the value of `2 * 60 * 60`. The default is `0`.

### retry _(boolean)_

With `retry` set to `TRUE`, _multiple fetch attempts_ are made if the data received from `$url` is either:

1. NULL
2. Equal to the data from the previous history state

If after all attempts either of these are still true, the previous history state is returned.

### requestLimit _(integer)_

Sets the maximum number of requests that can be made with the `url` in a day. Requests are tallied against the domain of the `url` to help avoid reaching rate limits. The default is 100 requests.

### historyLimit _(integer)_

Sets the number of history states (cache files) that are saved. Once the cache has stored this many files, the oldest ones will soon be deleted (on the first page load after midnight). Default is 100 history states.

### mustMatch _(string)_

A regular expression pattern which incoming data must match in order for the cache to be updated. Example: `/<img/`

### mustNotMatch _(string)_

A regular expression pattern which incoming data _must not match_ in order for the cache to be updated. Example: `/error/`

## Methods

### get()

Reads the latest data from the cache. If the cache is expired, data is fetched and a new history state is created with the response.

## Query strings

### ?clearCache

The cache is first erased and then set up again with one new history state.
