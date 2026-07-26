<?php
// MCP Tester dashboard — ai-toolbox plugin
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
$mcpI18n = [
    'mcp.field.url'             => $lang['mcp.field.url'] ?? '',
    'mcp.field.url.placeholder' => $lang['mcp.field.url.placeholder'] ?? '',
    'mcp.field.authHeader'      => $lang['mcp.field.authHeader'] ?? '',
    'mcp.field.authHeader.placeholder' => $lang['mcp.field.authHeader.placeholder'] ?? '',
    'mcp.btn.connect'           => $lang['mcp.btn.connect'] ?? '',
    'mcp.btn.connecting'        => $lang['mcp.btn.connecting'] ?? '',
    'mcp.error.invalidUrl'      => $lang['mcp.error.invalidUrl'] ?? '',
    'mcp.error.step.initialize' => $lang['mcp.error.step.initialize'] ?? '',
    'mcp.error.step.tools'      => $lang['mcp.error.step.tools'] ?? '',
    'mcp.serverInfo.title'      => $lang['mcp.serverInfo.title'] ?? '',
    'mcp.serverInfo.name'       => $lang['mcp.serverInfo.name'] ?? '',
    'mcp.serverInfo.version'    => $lang['mcp.serverInfo.version'] ?? '',
    'mcp.serverInfo.protocol'   => $lang['mcp.serverInfo.protocol'] ?? '',
    'mcp.tools.title'           => $lang['mcp.tools.title'] ?? '',
    'mcp.tools.empty'           => $lang['mcp.tools.empty'] ?? '',
    'mcp.tools.inputSchema'     => $lang['mcp.tools.inputSchema'] ?? '',
];

$refrCode  = "<script src='../../camila/js/worktable-client.js'></script>";
$refrCode .= "<script>window.APP_CONFIG = " . json_encode($config, JSON_UNESCAPED_SLASHES) . "</script>";
$refrCode .= "<script>window.I18N = "       . json_encode($mcpI18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>";
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
$_CAMILA['page']->camila_add_js("<link href=\"plugins/ai-toolbox/app.css\" rel=\"stylesheet\">\n");
$mcpScriptVersion = @filemtime(__DIR__ . '/app-mcp-tester.js');
$mcpVerSuffix     = $mcpScriptVersion ? ('?v=' . $mcpScriptVersion) : '';
$_CAMILA['page']->camila_add_js('<script type="module" src="./plugins/ai-toolbox/app-mcp-tester.js' . $mcpVerSuffix . '"></script>');
