<?php
declare(strict_types=1);

/*
 * Cache-busting version of everything the guides are made of: the newest mtime
 * among the Markdown files, the index and the scripts. A changed guide is fetched
 * again, an unchanged one is not. Used by guide.php.
 */
function wt_guide_version(): string
{
    $files = array_merge(
        glob(__DIR__ . '/views/*/guide.*.md') ?: [],
        glob(__DIR__ . '/docs/guide/*.json') ?: [],
        glob(__DIR__ . '/docs/guide/*/*.md') ?: [],
        [__DIR__ . '/views/shared/guide.js']
    );
    return (string)max(array_map(fn($f) => is_file($f) ? filemtime($f) : 0, $files));
}
