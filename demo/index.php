<?php require_once('../src/Cache.class.php'); ?>
<?php

	ini_set('display_errors', 1);
    header('Content-Type: application/json');

    $requestUrl = 'http://ip-api.com/json/wired.com';

	$cacheInstance = new Cache(array(
        'url'    => $requestUrl,
      	'key'    => 'ip_lookup',
        'expire' => 'hourly'
	));

	$ipData = $cacheInstance->get();

	echo $ipData;

?>