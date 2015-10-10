<?php


	ini_set( "display_errors", 1);


	// Breezometer API
	// http://breezometer.com/api/
	include('api_keys.php');
	$lat = '33.966793';
	$lon = '-118.018911';
	$endpoint = "http://api-beta.breezometer.com/baqi/?key=$api_key&lat=$lat&lon=$lon";


	// Cache
	include("../src/cache.class.php");
	$breeze_data = new Cache(array(
		"container_path" => "cache",
		"key" => "breezeometer",
		"expire" => "minute",
		"mustMatch" => "/country_name/",
		"limit" => 5
	));
	$breeze = $breeze_data->get($endpoint);
	$breeze = json_decode($breeze, true);


	// Output
	echo "<h3>Air quality data for Whittier, CA:</h3>";
	echo "<div id='meter' style='width:100px;height:100px;background:".$breeze['breezometer_color']."'>";
	echo "<div id='aqi' style='color:#fff;font-weight:bold;text-align:center;line-height:100px;font-size:40px;letter-spacing:4px;font-family:Georgia,serif;'>".$breeze['breezometer_aqi']."</div>";
	echo "</div>";
	echo "<h4>".$breeze['breezometer_description']."</h4>";


?>
