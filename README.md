Wayback Cache
===============

A PHP class for intelligently caching and cataloging a history of data. Helpful when needing to cache content from third-party API's and fall back to previous versions if necessary.

```
<?php require_once("cache.class.php"); ?>
<?php

	$object = new Cache(array(
  	'container' => 'webcache',
  	'key' => 'instagram_feed',
  	'expire' => 'hourly',
  	'limit' => 10
	));
	
	$data = $object->get($url);
	
	echo $data;

?>
```

See also: http://chrisullyott.com/blog/2014-11-24-wayback-cache/

## Initialization

### container _(string)_

Sets the path to the parent cache directory, where this cache instance will be stored. If the path does not exist, it is created. This path is relative to the document root. The default is `_cache`.

### container_path _(string)_

Similar to `container` but accepts a full path.

### key _(string)_

The identifier (or purpose) of this specific cache instance (ie, `instagram_feed` or `weather_data`).

Set to `url` to use a key generated from the page's current URL.

### expiration _(string)_

Value						| Cache expiration set
:----------				| :-----------
second					| Every second
minute					| Every minute
hourly					| Every hour
nightly (default)		| Every night at midnight
weekly					| Every Sunday night at midnight
monthly					| Every first of the month at midnight

### offset _(integer)_

Pushes back the expiration time by a number of seconds. For example, to make the cache expire at 2:00 am, use `nightly` and the value of `2 * 60 * 60`. The default is `0`.

### retry _(boolean)_

With `retry` set to `TRUE`, _multiple fetch attempts_ are made if the data received from `$url` is either:

1. NULL
2. Equal to the data from the previous history state

If after all attempts either of these are still true, the previous history state is returned.

### limit _(integer)_

Sets the number of history states that are saved. Once the cache has stored this many states, the oldest one is deleted to make way for new data. Default is `100` history states.

### mustMatch _(string)_

A regular expression pattern which incoming data must match in order for the cache to be updated. Example: `/<img/`

### mustNotMatch _(string)_

A regular expression pattern which incoming data _must not match_ in order for the cache to be updated. Example: `/error/`

## Methods

### get()

Reads the latest data from the cache. If the cache is expired, data from `$url` is fetched and a new history state is created with the result. The new data is returned.

## Query strings

### ?clearCache

The cache is first erased and then set up again with one new history state.

## Examples

```
<?php require_once("cache.class.php"); ?>
<?php

	$article_cache = new Cache(array(
		'key' => 'cnbc_article',
		'expire' => 'hourly'
	));

	$article = $article_cache->get('http://www.cnbc.com/id/101618128');

	echo $article;

?>
```
