<?php

chdir(__DIR__ . '/../../data/');

$hosts = glob('*', GLOB_ONLYDIR);
natsort($hosts);

foreach ($hosts as $host) {
    echo "Host: $host\n\n";
    $services = glob("$host/*.path", GLOB_ONLYDIR);
    natsort($services);
    foreach ($services as $servicePathFile) {
        $service = basename(dirname(trim(file_get_contents($servicePathFile))));
        echo " - $service\n";
    }
    echo "\n\n";
}
