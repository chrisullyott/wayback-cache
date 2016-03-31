<?php

	ini_set('display_errors', 1);

	// Free Geolocation API
	// https://freegeoip.net/
	$endpoint = 'https://freegeoip.net/json/85.90.227.224';

	// Instantiate class
	include('../src/cache.class.php');
	$geoIP = new Cache(array(
		'container_path' => 'cache',
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
