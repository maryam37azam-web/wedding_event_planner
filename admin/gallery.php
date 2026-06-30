<?php

declare(strict_types=1);

$galleryPageRole = 'admin';

ob_start();

require __DIR__ . '/../includes/gallery_page.php';

$pageHtml = (string) ob_get_clean();

$oldSummaryUrl = e(
    url('/admin/gallery.php?filter=all')
    . '#galleryList'
);

$newSummaryUrl = e(
    url('/gallery/all_gallery.php')
);

$pageHtml = str_replace(
    'href="' . $oldSummaryUrl . '"',
    'href="' . $newSummaryUrl . '"',
    $pageHtml
);

echo $pageHtml;