<?php



// === КОНФИГУРАЦИЯ ===
$imagesPerPage = 6;

// === ПАПКИ ===
$thumbnailsDir = 'thumbnails/';
$fullDir = 'full/';
$metadataFile = 'data/metadata.json';

// === УБЕДИМСЯ, ЧТО ПАПКИ СУЩЕСТВУЮТ ===
if (!is_dir($thumbnailsDir) || !is_dir($fullDir)) {
    die('Ошибка: отсутствуют папки thumbnails/ или full/');
}

if (!file_exists($metadataFile)) {
    $images = [];
} else {
    $json   = file_get_contents($metadataFile);
    $images = json_decode($json, true);
    if (!is_array($images)) {
        $images = [];
    }
}

// === СБОР СПИСКА ИЗОБРАЖЕНИЙ ===
$images = [];

if (file_exists($metadataFile)) {
    $json = file_get_contents($metadataFile);
    $imagesData = json_decode($json, true);
    if (!is_array($imagesData)) {
        $imagesData = [];
    }
} else {
    $imagesData = [];
}

// Если хочешь — можно дополнительно отфильтровать те элементы, у которых существуют миниатюры
foreach ($imagesData as $item) {
    // Можно проверить: файл thumb существует, full существует, расширение корректное
    if (isset($item['thumb'], $item['full'], $item['desc'])) {
        $images[] = [
            'thumb' => $item['thumb'],
            'full'  => $item['full'],
            'desc'  => htmlspecialchars($item['desc'], ENT_QUOTES, 'UTF-8')
        ];
    }
}

// Сортировка: новые сверху
usort($images, fn($a,$b) => strtotime($b['uploaded']) <=> strtotime($a['uploaded']));

$totalImages = count($images);
$totalPages  = ($imagesPerPage > 0) ? (int)ceil($totalImages / $imagesPerPage) : 1;

$page = 1;
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $page = (int)$_GET['page'];
}
$page = max(1, min($page, $totalPages));

$offset       = ($page - 1) * $imagesPerPage;
$imagesOnPage = array_slice($images, $offset, $imagesPerPage);

// Дополнительно: если нет изображений или смещение превышает массив, можно сбросить
if (empty($imagesOnPage) && $totalImages > 0) {
    // Например, перенаправить на последнюю страницу
    header("Location: ?page=" . $totalPages);
    exit;
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Фото-галерея</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Типа Пинтерест</h1>

<div style="background:#ffebee; padding:10px; text-align:center; margin-bottom:20px; color:#c62828; font-weight:bold;">
    Всего изображений: <?= $totalImages ?> | 
    Страниц: <?= $totalPages ?> | 
    Текущая: <?= $page ?> | 
    Показано на странице: <?= count($imagesOnPage) ?>
</div>

<div class="upload">
    <a href="upload.php">Страница загрузки изображений</a>
</div>

<div class="gallery">
    <?php if (empty($images)): ?>
        <p>Нет изображений. Загрузите первое!</p>
    <?php else: ?>
        <?php foreach ($imagesOnPage as $img): ?>
            <div class="item">
                <img src="<?= htmlspecialchars($img['thumb']) ?>" 
                     alt="Превью" 
                     onclick="openModal('<?= htmlspecialchars($img['full']) ?>')">
                <p><?= $img['desc'] ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Пагинация -->
<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1">&laquo; Первая</a>
            <a href="?page=<?= $page - 1 ?>">« Предыдущая</a>
        <?php endif; ?>

        <?php
        $range = 2; // сколько страниц слева и справа показывать
        $start = max(1, $page - $range);
        $end   = min($totalPages, $page + $range);

        if ($start > 1) {
            echo '<a href="?page=1">1</a>';
            if ($start > 2) {
                echo '<span>…</span>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            if ($i == $page) {
                echo '<span class="current">' . $i . '</span>';
            } else {
                echo '<a href="?page=' . $i . '">' . $i . '</a>';
            }
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                echo '<span>…</span>';
            }
            echo '<a href="?page=' . $totalPages . '">' . $totalPages . '</a>';
        }
        ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>">Следующая »</a>
            <a href="?page=<?= $totalPages ?>">Последняя &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Модальное окно -->
<div id="imageModal" class="modal" onclick="closeModal()">
    <span class="close" onclick="closeModal()">&times;</span>
    <img id="modalImage" src="">
</div>

<script>
    function openModal(src) {
        document.getElementById('modalImage').src = src;
        document.getElementById('imageModal').style.display = 'flex';
    }
    function closeModal() {
        document.getElementById('imageModal').style.display = 'none';
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });
</script>

</body>
</html>