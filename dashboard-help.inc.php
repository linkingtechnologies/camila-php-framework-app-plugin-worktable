<?php
// Help dashboard — worktable plugin
// A PHP page (no SPA): it only points at the manual, which is guide.php. The manual needs no
// session, so the link opens it in a new tab.
// NOTE: local translations array must NOT be named $i18n (see AGENTS.md "Naming warning" —
// this file is require()'d at global scope and would overwrite camila's own global $i18n).

global $_CAMILA;

if (!function_exists('ai_load_lang')) {
    function ai_load_lang(string $langDir, string $lang): array {
        $file = $langDir . '/' . $lang . '.lang.php';
        if (!is_file($file)) $file = $langDir . '/en.lang.php';
        if (!is_file($file)) return [];
        $map = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (ltrim($line)[0] === '/') continue;
            $parts = explode(' = ', $line, 2);
            if (count($parts) === 2) $map[trim($parts[0])] = trim($parts[1]);
        }
        return $map;
    }
}

$camilaUI = new CamilaUserInterface();
$pageLang = $_CAMILA['lang'] ?? 'en';
$lang     = ai_load_lang(__DIR__ . '/lang', $pageLang);

// first box of the page: welded to the tab bar (see .spa-title-box in app.css)
$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="box spa-title-box">'));
$camilaUI->insertTitle($lang['help.title'] ?? 'Help', 'question-sign');
$camilaUI->insertText($lang['help.intro'] ?? '');
$camilaUI->insertLineBreak();

$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="row columns">'));
$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '<div class="col-xs-12 col-md-4 column is-12-mobile is-4-desktop">'));
$camilaUI->insertButton(
    'plugins/' . basename(__DIR__) . '/guide.php?lang=' . rawurlencode($pageLang),
    $lang['help.btn.manual'] ?? 'Manual', 'globe', true, '', '_blank'
);
$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div>'));
$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, '</div><br/>'));

$camilaUI->insertText($lang['help.note'] ?? '');
$camilaUI->closeBox();

// app.css (the .spa-title-box rule) is cache-busted like the scripts of the SPA pages
$cssVersion = @filemtime(__DIR__ . '/app.css');
$_CAMILA['page']->camila_add_js("<link href=\"plugins/worktable/app.css" . ($cssVersion ? ('?v=' . $cssVersion) : '') . "\" rel=\"stylesheet\">\n");
