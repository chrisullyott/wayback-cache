<?php require_once('vendor/autoload.php'); ?>
<?php

    error_reporting(E_ERROR | E_PARSE);

    $cache = new Cache('test-instance', 'hourly');

    $url = 'http://ip-api.com/json/wired.com';
    $data = $cache->getByUrl($url);

    echo $data . "\n";
