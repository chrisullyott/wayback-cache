<?php

	ini_set('display_errors', 1);
	header('Content-Type: text/plain');

	// Free Geolocation API
	// https://freegeoip.net/
	$endpoint = 'http://chrisullyott.com/blog/2014-12-29-valley-of-fire/';

	// Instantiate class
	include('../src/cache.class.php');
	$geoIP = new Cache(array(
		'container' => 'cache',
		'key' => 'geoip',
		'expire' => 'hourly',
		'limit' => 10
	));

	// Make request
	$geoIp = $geoIP->get($endpoint);
	$geoIp = json_decode($geoIp, true);

	// Output
	print_r($geoIp);

?>
