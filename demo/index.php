<?php require_once('../src/Cache.class.php'); ?>
<?php

    // Error reporting (exclude notices)
    error_reporting(E_ERROR | E_PARSE);

    header('Content-Type: application/json');

    $requestUrl = 'http://ip-api.com/json/wired.com';

    $cacheInstance = new Cache(array(
        'url'    => $requestUrl,
        'expire' => 'hourly'
    ));

    $ipData = $cacheInstance->get();

    echo "data:\n";
    echo $ipData . "\n";
