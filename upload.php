<?php
// === КОНФИГУРАЦИЯ ===
$fullDir        = 'full/';
$thumbnailsDir  = 'thumbnails/';
$metadataFile   = 'data/metadata.json';
$allowedExts    = ['jpg', 'jpeg', 'png'];
$maxFileSize    = 5 * 1024 * 1024; // 5 МБ
$fontFile       = __DIR__ . '/fonts/ARIAL.TTF';
$watermarkFile  = __DIR__ . '/watermark/watermark.png'; // путь к файлу водяного знака

if (!file_exists($fontFile)) {
    die('Ошибка: файл шрифта не найден: ' . $fontFile);
}
if (!file_exists($watermarkFile)) {
    die('Ошибка: файл водяного знака не найден: ' . $watermarkFile);
}

/**
 * Санитизация имени файла (удаляем нежелательные символы)
 */
function sanitizeFileName($name)
{
    $clean = preg_replace('/[^A-Za-z0-9_\-]/', '_', $name);
    $clean = trim($clean, '_');
    return $clean === '' ? 'file' : $clean;
}

/**
 * Создаёт миниатюру изображения и накладывает текст-дату
 */
function createThumbnailWithDateText($srcPath, $destPath, $thumbWidth, $text, $fontFile)
{
    $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $srcImg = imagecreatefromjpeg($srcPath);
            break;
        case 'png':
            $srcImg = imagecreatefrompng($srcPath);
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
    if ($ext === 'png') {
        imagecolortransparent($thumbImg, imagecolorallocatealpha($thumbImg, 0, 0, 0, 127));
        imagealphablending($thumbImg, false);
        imagesavealpha($thumbImg, true);
    }

    imagecopyresampled(
        $thumbImg,
        $srcImg,
        0,
        0,
        0,
        0,
        $thumbW,
        $thumbH,
        $origW,
        $origH
    );

    // наложение текста
    $fontSize   = 14;
    $angle      = 0;
    $padding    = 5;
    $textColor   = imagecolorallocate($thumbImg,   255, 255, 255);
    $shadowColor = imagecolorallocate($thumbImg,     0,  0,  0);

    $box   = imagettfbbox($fontSize, $angle, $fontFile, $text);
    $textW = abs($box[4] - $box[0]);
    $textH = abs($box[5] - $box[1]);

    $x = $thumbW - $textW - $padding;
    $y = $thumbH - $padding;

    imagettftext($thumbImg, $fontSize, $angle, $x + 1, $y + 1, $shadowColor, $fontFile, $text);
    imagettftext($thumbImg, $fontSize, $angle, $x,   $y,   $textColor,   $fontFile, $text);

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($thumbImg, $destPath, 90);
            break;
        case 'png':
            imagepng($thumbImg, $destPath);
            break;
    }

    imagedestroy($srcImg);
    imagedestroy($thumbImg);

    return true;
}

/**
 * Накладывает водяной знак-PNG на изображение
 */
function applyWatermarkWithScale($srcPath, $watermarkPath, $destPath, $options = [])
{
    // Настройки по умолчанию
    $defaults = [
        'scale'    => 0.2,
        'maxWidth' => null,
        'position' => 'bottom-right',
        'margin'   => 10
    ];
    $opts = array_merge($defaults, $options);

    if (!file_exists($srcPath) || !file_exists($watermarkPath)) {
        return false;
    }

    $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $img = imagecreatefromjpeg($srcPath);
            break;
        case 'png':
            $img = imagecreatefrompng($srcPath);
            break;
        default:
            return false;
    }
    if (!$img) return false;

    $wm = imagecreatefrompng($watermarkPath);
    if (!$wm) {
        imagedestroy($img);
        return false;
    }

    $imgW = imagesx($img);
    $imgH = imagesy($img);
    $wmOrigW = imagesx($wm);
    $wmOrigH = imagesy($wm);

    // Определяем размер знака
    $targetW = (int)round($imgW * $opts['scale']);
    if ($opts['maxWidth'] !== null) {
        $targetW = min($targetW, $opts['maxWidth']);
    }
    // вычисляем высоту пропорционально
    $targetH = (int)round($wmOrigH * ($targetW / $wmOrigW));

    // Создаём промежуточный ресайз водяного знака
    $wmResized = imagecreatetruecolor($targetW, $targetH);
    // Сохраняем альфа-канал
    imagealphablending($wmResized, false);
    imagesavealpha($wmResized, true);
    // Копируем с ресемплингом
    imagecopyresampled(
        $wmResized,
        $wm,
        0,
        0,
        0,
        0,
        $targetW,
        $targetH,
        $wmOrigW,
        $wmOrigH
    );

    // Позиционирование
    switch ($opts['position']) {
        case 'top-left':
            $x = $opts['margin'];
            $y = $opts['margin'];
            break;
        case 'top-right':
            $x = $imgW - $targetW - $opts['margin'];
            $y = $opts['margin'];
            break;
        case 'center':
            $x = (int)(($imgW - $targetW) / 2);
            $y = (int)(($imgH - $targetH) / 2);
            break;
        case 'bottom-left':
            $x = $opts['margin'];
            $y = $imgH - $targetH - $opts['margin'];
            break;
        case 'bottom-right':
        default:
            $x = $imgW - $targetW - $opts['margin'];
            $y = $imgH - $targetH - $opts['margin'];
            break;
    }

    // Накладываем знак
    imagealphablending($img, true);
    imagesavealpha($img, true);
    imagecopy($img, $wmResized, $x, $y, 0, 0, $targetW, $targetH);

    // Сохраняем результат
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($img, $destPath, 90);
            break;
        case 'png':
            imagepng($img, $destPath);
            break;
    }

    imagedestroy($img);
    imagedestroy($wm);
    imagedestroy($wmResized);

    return true;
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        die("Ошибка: файл не загружен правильно.\n${var_dump($_FILES['image'])}");
    } // php.ini - upload max file size

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

    // Формирование имени
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
            $fileName   = $baseName . '_' . $i . '.' . $ext;
            $targetPath = $fullDir . $fileName;
            $i++;
        } while (file_exists($targetPath));
    }

    if (! move_uploaded_file($fileTmp, $targetPath)) {
        die('Ошибка: не удалось сохранить файл.');
    }


    // Создаём миниатюру с датой
    $thumbFilePath = $thumbnailsDir . $fileName;
    $dateTimeText  = date('Y-m-d H:i:s');
    if (! createThumbnailWithDateText($targetPath, $thumbFilePath, 300, $dateTimeText, $fontFile)) {
        error_log("Ошибка: миниатюра не создана для {$fileName}");
    }

    // Применяем водяной знак к оригиналу
    applyWatermarkWithScale(
        $targetPath,
        $watermarkFile,
        $targetPath,
        [
            'scale'    => 0.2,            // знак будет 20% от ширины изображения
            'maxWidth' => 300,             // и не шире 300px
            'position' => 'bottom-right',  // позиция знака
            'margin'   => 10               // отступ от краёв
        ]
    );

    // Запись метаданных
    $record = [
        'filename' => $fileName,
        'thumb'    => $thumbFilePath,
        'full'     => $targetPath,
        'desc'     => htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'uploaded' => $dateTimeText,
        'tags'     => []
    ];

    if (! file_exists($metadataFile)) {
        if (! is_dir(dirname($metadataFile))) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Загрузить изображение</title>
    <link rel="stylesheet" href="style.css">
    <script src="modal.js" defer></script>
</head>

<body class="upload-page">
    <main class="upload-container">
        <header class="upload-header">
            <h1>Добавить новое изображение</h1>
            <p>Выберите файл, при необходимости задайте имя и задайте описание</p>
            <a href="index.php" class="upload-back-link">← Вернуться в галерею</a>
        </header>

        <form action="" method="post" enctype="multipart/form-data" class="upload-form">
            <div class="upload-field">
                <label class="upload-label" for="image">Выберите файл изображения</label>
                <input type="file"
                       id="image"
                       name="image"
                       accept="image/*"
                       required
                       class="upload-input upload-input-file">
                <p class="upload-helper">Поддерживаются JPG и PNG размером до 5 МБ.</p>
            </div>

            <div class="upload-field">
                <label class="upload-label" for="custom_name">Желаемое имя файла (без расширения)</label>
                <input type="text"
                       id="custom_name"
                       name="custom_name"
                       maxlength="64"
                       class="upload-input">
            </div>

            <div class="upload-field">
                <label class="upload-label" for="description">Описание (необязательно)</label>
                <textarea id="description"
                          name="description"
                          rows="4"
                          class="upload-input upload-textarea"></textarea>
            </div>

            <div class="upload-actions">
                <button type="submit" class="upload-submit">Загрузить</button>
            </div>
        </form>
    </main>
</body>

</html>
