<?php

$imagesPerPage = 6;

$imagesDir = 'full/';
$thumbnailsDir = 'thumbnails/';

$images = [];
$files = scandir($thumbnailsDir);
foreach ($files as $file) {
    $images[] = [
        'thumbnail' => $thumbnailsDir . $file,
        'full' => $imagesDir . $file
    ];
}

$totalImages = count($images);
$totalPages = ceil($totalImages / $imagesPerPage);

$page = 1;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

var_dump($imagesDir, $thumbnailsDir, $totalImages, $totalPages, $page);