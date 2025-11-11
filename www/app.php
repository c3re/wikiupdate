<?php

$parts = explode('/', $_SERVER['REQUEST_URI']);
array_shift($parts);
$host = basename(array_shift($parts));
$file = implode('/', $parts);
$content = file_get_contents('php://input');

$hostDir = __DIR__ . "/data/$host";
if (!is_dir($hostDir)) {
    mkdir($hostDir, 0777, true);
}
$fileStoragePath = $hostDir . '/' . md5($file);

file_put_contents($fileStoragePath . '.data', $content);
file_put_contents($fileStoragePath . '.path', $file);
