<?php
// WorkTable Explorer dashboard — ai-toolbox plugin
// Manual mount pattern (see AGENTS.md). No custom I18N needed for this SPA (all
// strings live in views/worktable-explorer/index.js, copied verbatim from
// segreteria-campo's plugin — see specs/worktable-explorer/).

global $_CAMILA;

$camilaUI = new CamilaUserInterface();
$scheme   = $camilaUI->isHttps() ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$config   = [
    'baseUrl'           => $scheme . '://' . $host . '/app/' . CAMILA_APP_DIR . '/cf_api.php',
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
$_CAMILA['page']->camila_add_js("<link href=\"plugins/ai-toolbox/app.css\" rel=\"stylesheet\">\n");
$wtExplorerScriptVersion = @filemtime(__DIR__ . '/app-worktable-explorer.js');
$wtExplorerVerSuffix     = $wtExplorerScriptVersion ? ('?v=' . $wtExplorerScriptVersion) : '';
$_CAMILA['page']->camila_add_js('<script type="module" src="./plugins/ai-toolbox/app-worktable-explorer.js' . $wtExplorerVerSuffix . '"></script>');
