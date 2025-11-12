<?php

chdir(__DIR__ . '/../../data/');
header('Content-Type: text/plain');

$hosts = glob('*', GLOB_ONLYDIR);
// todo: filter old entries
natsort($hosts);

foreach ($hosts as $host) {
    if (filemtime($host) < time() - 7 * 24 * 60 * 60) {
        // skip hosts not modified in the last 7 days
        continue;
    }
    echo "# Host: $host\n\n";
    $services = glob("$host/*.path");

    usort($services, function ($a, $b) {
        return strnatcasecmp(file_get_contents($a), file_get_contents($b));
    });

    foreach ($services as $servicePathFile) {
        $service = basename(dirname(trim(file_get_contents($servicePathFile))));
        echo " - [$service](./Dienste-Intern/$service)\n";
    }
    echo "\n\n";
}
