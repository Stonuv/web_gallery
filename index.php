<?php
// Путь к папке с миниатюрами
$thumbDir = __DIR__ . '/thumbnails/';

// Получаем список файлов
$files = array_diff(scandir($thumbDir), ['.', '..']);

// Фильтруем только файлы изображений (по расширению)
$images = [];
foreach ($files as $file) {
    $path = $thumbDir . $file;
    if (is_file($path)) {
        // допустимые расширения
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $images[] = $file;
        }
    }
}
?>