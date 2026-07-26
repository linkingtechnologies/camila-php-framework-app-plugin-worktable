<?php
// MCP Checker dashboard — worktable plugin
// Manual mount pattern (see AGENTS.md): APP_CONFIG / I18N must be injected before the module loads.

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
$config   = [
    'baseUrl'           => $scheme . '://' . $host . '/app/' . CAMILA_APP_DIR . '/cf_api.php',
    'apiKeyHeaderName'  => 'Authorization',
    'apiKeyHeaderValue' => 'PHPSESSID',
    'mcpDefaultUrl'     => $scheme . '://' . $host . '/app/' . CAMILA_APP_DIR . '/cf_api.php?mcp=1',
];

$lang = ai_load_lang(__DIR__ . '/lang', $_CAMILA['lang'] ?? 'en');
// NOTE: must NOT be named $i18n — camila_hawhaw.php sets a global $i18n (CamilaTranslator
// instance) used by TinyButStrong's onshow auto-merge for the header menu; since this file
// is require()'d at global scope, a local $i18n here would overwrite that global and break
// the header's logout/prefs links.
$mcpCheckerI18n = [
    'mcpChecker.field.url'             => $lang['mcpChecker.field.url'] ?? '',
    'mcpChecker.field.url.placeholder' => $lang['mcpChecker.field.url.placeholder'] ?? '',
    'mcpChecker.field.authHeader'      => $lang['mcpChecker.field.authHeader'] ?? '',
    'mcpChecker.field.authHeader.placeholder' => $lang['mcpChecker.field.authHeader.placeholder'] ?? '',
    'mcpChecker.btn.connect'           => $lang['mcpChecker.btn.connect'] ?? '',
    'mcpChecker.btn.connecting'        => $lang['mcpChecker.btn.connecting'] ?? '',
    'mcpChecker.error.invalidUrl'      => $lang['mcpChecker.error.invalidUrl'] ?? '',
    'mcpChecker.error.step.initialize' => $lang['mcpChecker.error.step.initialize'] ?? '',
    'mcpChecker.error.step.tools'      => $lang['mcpChecker.error.step.tools'] ?? '',
    'mcpChecker.serverInfo.title'      => $lang['mcpChecker.serverInfo.title'] ?? '',
    'mcpChecker.serverInfo.name'       => $lang['mcpChecker.serverInfo.name'] ?? '',
    'mcpChecker.serverInfo.version'    => $lang['mcpChecker.serverInfo.version'] ?? '',
    'mcpChecker.serverInfo.protocol'   => $lang['mcpChecker.serverInfo.protocol'] ?? '',
    'mcpChecker.tools.title'           => $lang['mcpChecker.tools.title'] ?? '',
    'mcpChecker.tools.empty'           => $lang['mcpChecker.tools.empty'] ?? '',
    'mcpChecker.tools.inputSchema'     => $lang['mcpChecker.tools.inputSchema'] ?? '',
];

$refrCode  = "<script src='../../camila/js/worktable-client.js'></script>";
$refrCode .= "<script>window.APP_CONFIG = " . json_encode($config, JSON_UNESCAPED_SLASHES) . "</script>";
$refrCode .= "<script>window.I18N = "       . json_encode($mcpCheckerI18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>";
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
$mcpScriptVersion = @filemtime(__DIR__ . '/app-mcp-checker.js');
$mcpVerSuffix     = $mcpScriptVersion ? ('?v=' . $mcpScriptVersion) : '';
$_CAMILA['page']->camila_add_js('<script type="module" src="./plugins/worktable/app-mcp-checker.js' . $mcpVerSuffix . '"></script>');
