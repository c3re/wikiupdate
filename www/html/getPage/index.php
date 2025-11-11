<?php

chdir(__DIR__ . '/../../data/');
header('Content-Type: text/plain');

$hosts = glob('*', GLOB_ONLYDIR);
natsort($hosts);

foreach ($hosts as $host) {
    echo "# Host: $host\n\n";
    $services = glob("$host/*.path");
    natsort($services);
    foreach ($services as $servicePathFile) {
        $service = basename(dirname(trim(file_get_contents($servicePathFile))));
        echo " - $service\n";
    }
    echo "\n\n";
}
