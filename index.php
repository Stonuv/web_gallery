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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<main class="mx-auto flex min-h-screen max-w-6xl flex-col gap-8 px-4 py-10 lg:px-6">
    <header class="space-y-4 text-center">
        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-indigo-500">Фото-галерея</p>
        <h1 class="text-3xl font-semibold text-slate-900 sm:text-4xl">Типа Пинтерест</h1>
        <p class="text-base text-slate-500">Минималистичная витрина с пагинацией, предпросмотром и быстрым переходом к загрузкам.</p>
    </header>

    <div class="flex flex-wrap items-center justify-center gap-3 text-sm">
        <span class="stat-pill">Всего изображений: <?= $totalImages ?></span>
        <span class="stat-pill">Страниц: <?= $totalPages ?></span>
        <span class="stat-pill">Текущая: <?= $page ?></span>
        <span class="stat-pill">На странице: <?= count($imagesOnPage) ?></span>
    </div>

    <div class="flex justify-center">
        <a href="upload.php" class="btn-primary">Загрузить изображение</a>
    </div>

    <section class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" aria-live="polite">
        <?php if (empty($images)): ?>
            <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white/60 p-10 text-center text-slate-500">
                Нет изображений. Загрузите первое!
            </div>
        <?php else: ?>
            <?php foreach ($imagesOnPage as $img): ?>
                <article class="card flex flex-col gap-4">
                    <button type="button"
                            class="group relative block overflow-hidden rounded-2xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-500"
                            onclick="openModal('<?= htmlspecialchars($img['full']) ?>')">
                        <img src="<?= htmlspecialchars($img['thumb']) ?>"
                             alt="<?= $img['desc'] ?: 'Превью кадра' ?>"
                             class="h-64 w-full object-cover transition duration-300 group-hover:scale-105">
                        <span class="pointer-events-none absolute inset-0 rounded-2xl border-2 border-white opacity-0 transition group-hover:opacity-100"></span>
                    </button>
                    <p class="text-sm text-slate-600"><?= $img['desc'] ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?php if ($totalPages > 1): ?>
        <nav class="flex flex-wrap items-center justify-center gap-2 text-sm font-medium text-slate-600" aria-label="Пагинация">
            <?php if ($page > 1): ?>
                <a class="rounded-full border border-slate-200 px-3 py-1 transition hover:border-indigo-400 hover:text-indigo-600"
                   href="?page=1">&laquo; Первая</a>
                <a class="rounded-full border border-slate-200 px-3 py-1 transition hover:border-indigo-400 hover:text-indigo-600"
                   href="?page=<?= $page - 1 ?>">« Предыдущая</a>
            <?php endif; ?>

            <?php
            $range = 2;
            $start = max(1, $page - $range);
            $end   = min($totalPages, $page + $range);

            if ($start > 1) {
                echo '<a class="rounded-full border border-slate-200 px-3 py-1 hover:border-indigo-400 hover:text-indigo-600" href="?page=1">1</a>';
                if ($start > 2) {
                    echo '<span class="px-2">…</span>';
                }
            }

            for ($i = $start; $i <= $end; $i++) {
                if ($i == $page) {
                    echo '<span class="rounded-full bg-indigo-600 px-3 py-1 text-white">' . $i . '</span>';
                } else {
                    echo '<a class="rounded-full border border-slate-200 px-3 py-1 hover:border-indigo-400 hover:text-indigo-600" href="?page=' . $i . '">' . $i . '</a>';
                }
            }

            if ($end < $totalPages) {
                if ($end < $totalPages - 1) {
                    echo '<span class="px-2">…</span>';
                }
                echo '<a class="rounded-full border border-slate-200 px-3 py-1 hover:border-indigo-400 hover:text-indigo-600" href="?page=' . $totalPages . '">' . $totalPages . '</a>';
            }
            ?>

            <?php if ($page < $totalPages): ?>
                <a class="rounded-full border border-slate-200 px-3 py-1 transition hover:border-indigo-400 hover:text-indigo-600"
                   href="?page=<?= $page + 1 ?>">Следующая »</a>
                <a class="rounded-full border border-slate-200 px-3 py-1 transition hover:border-indigo-400 hover:text-indigo-600"
                   href="?page=<?= $totalPages ?>">Последняя &raquo;</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</main>

<div id="imageModal"
     class="fixed inset-0 z-50 hidden bg-slate-950/90 p-4"
     role="dialog"
     aria-modal="true"
     onclick="closeModal()">
    <div class="mx-auto flex h-full max-w-5xl items-center justify-center" onclick="event.stopPropagation()">
        <div class="relative">
            <img id="modalImage"
                 src=""
                 alt=""
                 class="max-h-[80vh] max-w-full rounded-2xl border-4 border-white shadow-2xl" />
            <button type="button"
                    class="absolute -right-4 -top-4 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/80 text-xl font-semibold text-slate-700 shadow-lg transition hover:bg-white"
                    aria-label="Закрыть"
                    onclick="closeModal()">&times;</button>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');

    function openModal(src) {
        modalImage.src = src;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modalImage.removeAttribute('src');
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
</script>
</body>
</html>
