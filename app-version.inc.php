<?php
declare(strict_types=1);

/*
 * Cache-busting version of the SPAs: the newest mtime among the entry points, the views and
 * the shared scripts. The dashboards pass it to the page as APP_CONFIG.version, and app-boot.js
 * appends it to the imports of the views, so a changed file is fetched again and an unchanged
 * one comes from the cache.
 */
function wt_app_version(): string
{
    $files = array_merge(
        glob(__DIR__ . '/*.js') ?: [],
        glob(__DIR__ . '/views/*/*.js') ?: []
    );
    return (string)max(array_map(fn($f) => is_file($f) ? filemtime($f) : 0, $files));
}
