<?php
// WorkTable Explorer dashboard — worktable plugin
// Manual mount pattern (see AGENTS.md). No custom I18N needed for this SPA (all
// strings live in views/worktable-explorer/index.js, copied verbatim from
// segreteria-campo's plugin — see specs/worktable-explorer/).

global $_CAMILA;

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

$refrCode  = "<script src='../../camila/js/worktable-client.js'></script>";
$refrCode .= "<script>window.APP_CONFIG = " . json_encode($config, JSON_UNESCAPED_SLASHES) . "</script>";
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
// app.css is cache-busted the same way as the boot script: without it a
// stylesheet change never reaches a browser that already cached it.
$cssVersion = @filemtime(__DIR__ . '/app.css');
$cssSuffix  = $cssVersion ? ('?v=' . $cssVersion) : '';
$_CAMILA['page']->camila_add_js("<link href=\"plugins/worktable/app.css" . $cssSuffix . "\" rel=\"stylesheet\">\n");
$wtExplorerScriptVersion = @filemtime(__DIR__ . '/app-worktable-explorer.js');
$wtExplorerVerSuffix     = $wtExplorerScriptVersion ? ('?v=' . $wtExplorerScriptVersion) : '';
$_CAMILA['page']->camila_add_js('<script type="module" src="./plugins/worktable/app-worktable-explorer.js' . $wtExplorerVerSuffix . '"></script>');
