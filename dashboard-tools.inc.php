<?php
// Tools dashboard — worktable plugin
// Lets an operator download the plugin's Python helper scripts (tools/).
// Manual mount pattern (see AGENTS.md): APP_CONFIG / I18N must be injected before the module loads.
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
$scheme   = $camilaUI->isHttps() ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
// Base path derived from the currently-executing script (cf_app.php lives in the app
// directory, alongside cf_api.php) instead of a hardcoded '/app/<CAMILA_APP_DIR>/' —
// so the API URL stays correct when the app is served under a URL prefix, e.g. behind
// a reverse proxy in a subfolder. Same approach as CamilaUserInterface::mountMiniApp().
$appBasePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$apiUrl      = $scheme . '://' . $host . $appBasePath . '/cf_api.php';
$config   = [
    'baseUrl'           => $apiUrl,
    'apiKeyHeaderName'  => 'Authorization',
    'apiKeyHeaderValue' => 'PHPSESSID',
];

$lang       = ai_load_lang(__DIR__ . '/lang', $_CAMILA['lang'] ?? 'en');
$pluginI18n = [
    'tools.intro'            => $lang['tools.intro'] ?? '',
    'tools.empty'            => $lang['tools.empty'] ?? '',
    'tools.desc.client'      => $lang['tools.desc.client'] ?? '',
    'tools.desc.sync'        => $lang['tools.desc.sync'] ?? '',
    'tools.desc.envLocale'   => $lang['tools.desc.envLocale'] ?? '',
    'tools.desc.envSync'     => $lang['tools.desc.envSync'] ?? '',
    'tools.btn.download'     => $lang['tools.btn.download'] ?? '',
    'tools.btn.downloadAll'  => $lang['tools.btn.downloadAll'] ?? '',
    'tools.btn.retry'        => $lang['tools.btn.retry'] ?? '',
    'tools.error.auth'       => $lang['tools.error.auth'] ?? '',
    'tools.error.network'    => $lang['tools.error.network'] ?? '',
    'tools.error.server'     => $lang['tools.error.server'] ?? '',
    'tools.error.notFound'   => $lang['tools.error.notFound'] ?? '',
    'tools.error.generic'    => $lang['tools.error.generic'] ?? '',
    'tools.usage.title'      => $lang['tools.usage.title'] ?? '',
    'tools.usage.step1'      => $lang['tools.usage.step1'] ?? '',
    'tools.usage.step2'      => $lang['tools.usage.step2'] ?? '',
    'tools.usage.step3'      => $lang['tools.usage.step3'] ?? '',
];

$refrCode  = "<script src='../../camila/js/worktable-client.js'></script>";
$refrCode .= "<script>window.APP_CONFIG = " . json_encode($config, JSON_UNESCAPED_SLASHES) . "</script>";
$refrCode .= "<script>window.I18N = "       . json_encode($pluginI18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>";
$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $refrCode));

$html = <<<HTML
<div id="app"></div>
<script nomodule>
  document.body.innerHTML = `<section class="section"><div class="container">
    <article class="message is-danger">
      <div class="message-header"><p>Browser not supported</p></div>
      <div class="message-body">This application requires a modern browser (Chrome or Edge).</div>
    </article></div></section>`;
</script>
HTML;

$_CAMILA['page']->add_raw(new HAW_raw(HAW_HTML, $html));
$_CAMILA['page']->camila_add_js("<link href=\"plugins/worktable/app.css\" rel=\"stylesheet\">\n");
$toolsScriptVersion = @filemtime(__DIR__ . '/app-tools.js');
$toolsVerSuffix     = $toolsScriptVersion ? ('?v=' . $toolsScriptVersion) : '';
$_CAMILA['page']->camila_add_js('<script type="module" src="./plugins/worktable/app-tools.js' . $toolsVerSuffix . '"></script>');
