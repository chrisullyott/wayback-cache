<?php

	ini_set('display_errors', 1);
	header('Content-Type: text/plain');

	$page = 'http://chrisullyott.com/blog/2014-12-29-valley-of-fire';

	// Instantiate class
	include('../src/cache.class.php');
	$pageCache = new Cache(array(
		'container' => 'cache',
		'key' => 'blog'
	));

	// Make request
	$pageCache = $pageCache->get($page);

	// Output
	print_r($pageCache);

?>
