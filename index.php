<?php



// === КОНФИГУРАЦИЯ ===
$imagesPerPage = 8;

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Фото-галерея</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="home-page">
<main class="site-shell">
    <header class="site-header">
        <div class="header-text">
            <span class="header-tag">Галерея</span>
            <h1>Типа Пинтерест</h1>
            <p>Собираем изображения с описаниями, показываем компактной сеткой и открываем по клику.</p>
        </div>
        <a class="link-inline" href="upload.php">Загрузить новое изображение →</a>
    </header>

    <section class="stat-panel">
        <div>
            <p class="stat-label">Всего</p>
            <p class="stat-value"><?= $totalImages ?></p>
        </div>
        <div>
            <p class="stat-label">Страниц</p>
            <p class="stat-value"><?= $totalPages ?></p>
        </div>
        <div>
            <p class="stat-label">Текущая</p>
            <p class="stat-value"><?= $page ?></p>
        </div>
        <div>
            <p class="stat-label">На странице</p>
            <p class="stat-value"><?= count($imagesOnPage) ?></p>
        </div>
    </section>

    <section class="gallery-grid">
        <?php if (empty($images)): ?>
            <div class="gallery-empty">
                Нет изображений. Загрузите первое!
            </div>
        <?php else: ?>
            <?php foreach ($imagesOnPage as $img): ?>
                <article class="gallery-card">
                    <button type="button" class="card-thumb" onclick="openModal('<?= htmlspecialchars($img['full']) ?>')">
                        <img src="<?= htmlspecialchars($img['thumb']) ?>" alt="Превью" loading="lazy">
                    </button>
                    <p class="card-desc"><?= $img['desc'] ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?php if ($totalPages > 1): ?>
        <nav class="pager" aria-label="Пагинация">
            <?php if ($page > 1): ?>
                <a href="?page=1">&laquo; Первая</a>
                <a href="?page=<?= $page - 1 ?>">« Предыдущая</a>
            <?php endif; ?>

            <?php
            $range = 2;
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
        </nav>
    <?php endif; ?>
</main>

<div id="imageModal" class="modal" role="dialog" aria-modal="true" onclick="closeModal()">
    <div class="modal-inner" onclick="event.stopPropagation()">
        <button type="button" class="modal-close" aria-label="Закрыть" onclick="closeModal()">×</button>
        <img id="modalImage" src="" alt="Просмотр">
    </div>
</div>

<script>
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');

    function openModal(src) {
        modalImage.src = src;
        modal.classList.add('visible');
    }

    function closeModal() {
        modal.classList.remove('visible');
        modalImage.removeAttribute('src');
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('visible')) {
            closeModal();
        }
    });
</script>
</body>
</html>
