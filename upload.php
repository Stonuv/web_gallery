<?php
// === КОНФИГУРАЦИЯ ===
$fullDir       = 'full/';
$thumbnailsDir = 'thumbnails/';
$metadataFile  = 'data/metadata.json';
$allowedExts   = ['jpg','jpeg','png','gif'];
$maxFileSize   = 5 * 1024 * 1024; // 5 МБ
$fontFile      = __DIR__ . '/fonts/ARIAL.TTF';

if (!file_exists($fontFile)) {
    die('Ошибка: файл шрифта не найден: ' . $fontFile);
}

/**
 * Санитизация имени файла: оставляем только буквы, цифры, подчёркивания и дефисы.
 */
function sanitizeFileName($name) {
    $clean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $name);
    $clean = trim($clean, '_');
    return $clean === '' ? 'file' : $clean;
}

/**
 * Создаёт миниатюру изображения нужной ширины и накладывает текст (например, дата/время).
 *
 * @param string $srcPath     путь к исходному изображению
 * @param string $destPath    путь, куда сохранить миниатюру
 * @param int    $thumbWidth  желаемая ширина миниатюры (px)
 * @param string $text        текст для наложения
 * @param string $fontFile    путь к шрифту .ttf
 * @return bool              true при успехе, false при ошибке
 */
function createThumbnailWithDateText($srcPath, $destPath, $thumbWidth, $text, $fontFile) {
    $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $srcImg = imagecreatefromjpeg($srcPath);
            break;
        case 'png':
            $srcImg = imagecreatefrompng($srcPath);
            break;
        case 'gif':
            $srcImg = imagecreatefromgif($srcPath);
            break;
        default:
            return false;
    }
    if (!$srcImg) return false;

    $origW = imagesx($srcImg);
    $origH = imagesy($srcImg);

    $thumbW = $thumbWidth;
    $thumbH = (int) round($origH * ($thumbWidth / $origW));

    $thumbImg = imagecreatetruecolor($thumbW, $thumbH);

    if (in_array($ext, ['png','gif'])) {
        imagecolortransparent($thumbImg, imagecolorallocatealpha($thumbImg, 0, 0, 0, 127));
        imagealphablending($thumbImg, false);
        imagesavealpha($thumbImg, true);
    }

    imagecopyresampled(
        $thumbImg,
        $srcImg,
        0, 0, 0, 0,
        $thumbW, $thumbH,
        $origW, $origH
    );

    // Наложение текста
    $fontSize   = 14;
    $angle      = 0;
    $padding    = 5;
    $textColor   = imagecolorallocate($thumbImg,   255, 255, 255); // белый
    $shadowColor = imagecolorallocate($thumbImg,     0,   0,   0);   // чёрная тень

    $box = imagettfbbox($fontSize, $angle, $fontFile, $text);
    $textW = abs($box[4] - $box[0]);
    $textH = abs($box[5] - $box[1]);

    $x = $thumbW - $textW - $padding;
    $y = $thumbH - $padding;

    imagettftext($thumbImg, $fontSize, $angle, $x+1, $y+1, $shadowColor, $fontFile, $text);
    imagettftext($thumbImg, $fontSize, $angle, $x,   $y,   $textColor,   $fontFile, $text);

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($thumbImg, $destPath, 90);
            break;
        case 'png':
            imagepng($thumbImg, $destPath);
            break;
        case 'gif':
            imagegif($thumbImg, $destPath);
            break;
    }

    imagedestroy($srcImg);
    imagedestroy($thumbImg);

    return true;
}

// Обработка загрузки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        die('Ошибка: файл не загружен правильно.');
    }

    $fileTmp  = $_FILES['image']['tmp_name'];
    $origName = basename($_FILES['image']['name']);
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts)) {
        die('Ошибка: допустимые типы файлов: ' . implode(', ', $allowedExts));
    }
    $imgInfo = getimagesize($fileTmp);
    if ($imgInfo === false) {
        die('Ошибка: файл не является изображением.');
    }
    if ($_FILES['image']['size'] > $maxFileSize) {
        die('Ошибка: файл слишком большой.');
    }

    // Обработка пользовательского имени файла
    $userName = trim($_POST['custom_name'] ?? '');
    if ($userName !== '') {
        $baseName = sanitizeFileName($userName);
    } else {
        $baseName = pathinfo($origName, PATHINFO_FILENAME);
        $baseName = sanitizeFileName($baseName);
    }
    $fileName   = $baseName . '.' . $ext;
    $targetPath = $fullDir . $fileName;

    // Проверка на дубликат
    if (file_exists($targetPath)) {
        $i = 1;
        do {
            $fileName = $baseName . '_' . $i . '.' . $ext;
            $targetPath = $fullDir . $fileName;
            $i++;
        } while (file_exists($targetPath));
    }

    if (!move_uploaded_file($fileTmp, $targetPath)) {
        die('Ошибка: не удалось сохранить загруженный файл.');
    }

    // Создаём миниатюру и записываем метаданные
    $thumbFilePath = $thumbnailsDir . $fileName;
    $dateTimeText  = date('Y-m-d H:i:s');
    if (! createThumbnailWithDateText($targetPath, $thumbFilePath, 300, $dateTimeText, $fontFile) ) {
        error_log("Ошибка: не удалось создать миниатюру для {$fileName}");
    }

    // Запись метаданных
    $record = [
        'filename' => $fileName,
        'thumb'    => $thumbFilePath,
        'full'     => $targetPath,
        'desc'     => htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'uploaded' => $dateTimeText,
        'tags'     => []
    ];

    if (!file_exists($metadataFile)) {
        if (!is_dir(dirname($metadataFile))) {
            mkdir(dirname($metadataFile), 0755, true);
        }
        file_put_contents($metadataFile, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    $json = file_get_contents($metadataFile);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        $data = [];
    }
    $data[] = $record;
    file_put_contents($metadataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo 'Файл успешно загружен: ' . htmlspecialchars($fileName) . '<br>';
    echo '<a href="index.php">Вернуться к галерее</a>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Загрузить изображение</title>
</head>
<body>
<h1>Загрузить изображение</h1>
<form action="" method="post" enctype="multipart/form-data">
    <div>
        <label>Выберите файл изображения:<br>
            <input type="file" name="image" accept="image/*" required>
        </label>
    </div>
    <div>
        <label>Желаемое имя файла (без расширения):<br>
            <input type="text" name="custom_name" maxlength="64">
        </label>
    </div>
    <div>
        <label>Описание (необязательно):<br>
            <textarea name="description" rows="4" cols="50"></textarea>
        </label>
    </div>
    <div>
        <button type="submit">Загрузить</button>
    </div>
</form>
</body>
</html>
