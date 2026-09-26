<?php
// Configuration dashboard — worktable plugin
// Rotates CAMILA_VAR_ROOTDIR: renames it to "<base>-<guid>" and leaves a one-line
// stub at the old path. Admin only — the endpoints enforce it server-side.
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
    'config.intro'             => $lang['config.intro'] ?? '',
    'config.state.current'     => $lang['config.state.current'] ?? '',
    'config.state.rotated'     => $lang['config.state.rotated'] ?? '',
    'config.state.rotated.yes' => $lang['config.state.rotated.yes'] ?? '',
    'config.state.rotated.no'  => $lang['config.state.rotated.no'] ?? '',
    'config.state.exposure'    => $lang['config.state.exposure'] ?? '',
    'config.state.stub'        => $lang['config.state.stub'] ?? '',
    'config.state.stub.none'   => $lang['config.state.stub.none'] ?? '',
    'config.exposure.checking' => $lang['config.exposure.checking'] ?? '',
    'config.exposure.exposed'  => $lang['config.exposure.exposed'] ?? '',
    'config.exposure.protected' => $lang['config.exposure.protected'] ?? '',
    'config.state.driver'      => $lang['config.state.driver'] ?? '',
    'config.exposure.noprobe'  => $lang['config.exposure.noprobe'] ?? '',
    'config.exposure.probed'   => $lang['config.exposure.probed'] ?? '',
    'config.nginx.status.exposed'   => $lang['config.nginx.status.exposed'] ?? '',
    'config.nginx.status.protected' => $lang['config.nginx.status.protected'] ?? '',
    'config.nginx.status.unknown'   => $lang['config.nginx.status.unknown'] ?? '',
    'config.checks.title'      => $lang['config.checks.title'] ?? '',
    'config.check.currentExists'  => $lang['config.check.currentExists'] ?? '',
    'config.check.parentWritable' => $lang['config.check.parentWritable'] ?? '',
    'config.check.targetFree'     => $lang['config.check.targetFree'] ?? '',
    'config.check.stubWritable'   => $lang['config.check.stubWritable'] ?? '',
    'config.warn.sqlite'       => $lang['config.warn.sqlite'] ?? '',
    'config.warn.windows'      => $lang['config.warn.windows'] ?? '',
    'config.confirm.title'     => $lang['config.confirm.title'] ?? '',
    'config.confirm.body'      => $lang['config.confirm.body'] ?? '',
    'config.btn.rotate'        => $lang['config.btn.rotate'] ?? '',
    'config.btn.rotateAgain'   => $lang['config.btn.rotateAgain'] ?? '',
    'config.btn.confirm'       => $lang['config.btn.confirm'] ?? '',
    'config.btn.cancel'        => $lang['config.btn.cancel'] ?? '',
    'config.btn.retry'         => $lang['config.btn.retry'] ?? '',
    'config.btn.reload'        => $lang['config.btn.reload'] ?? '',
    'config.btn.deferred'      => $lang['config.btn.deferred'] ?? '',
    'config.deferred.offer.title' => $lang['config.deferred.offer.title'] ?? '',
    'config.deferred.offer.body'  => $lang['config.deferred.offer.body'] ?? '',
    'config.deferred.running.title' => $lang['config.deferred.running.title'] ?? '',
    'config.deferred.running.body'  => $lang['config.deferred.running.body'] ?? '',
    'config.deferred.ok'       => $lang['config.deferred.ok'] ?? '',
    'config.deferred.ok.note'  => $lang['config.deferred.ok.note'] ?? '',
    'config.deferred.timeout'  => $lang['config.deferred.timeout'] ?? '',
    'config.state.lastRun'     => $lang['config.state.lastRun'] ?? '',
    'config.lastRun.ok'        => $lang['config.lastRun.ok'] ?? '',
    'config.lastRun.failed'    => $lang['config.lastRun.failed'] ?? '',
    'config.btn.copy'          => $lang['config.btn.copy'] ?? '',
    'config.copied'            => $lang['config.copied'] ?? '',
    'config.result.ok'         => $lang['config.result.ok'] ?? '',
    'config.result.reload'     => $lang['config.result.reload'] ?? '',
    'config.error.admin'       => $lang['config.error.admin'] ?? '',
    'config.error.locked'      => $lang['config.error.locked'] ?? '',
    'config.locked.howto.title' => $lang['config.locked.howto.title'] ?? '',
    'config.locked.step1'      => $lang['config.locked.step1'] ?? '',
    'config.locked.step2'      => $lang['config.locked.step2'] ?? '',
    'config.locked.step3'      => $lang['config.locked.step3'] ?? '',
    'config.error.stale'       => $lang['config.error.stale'] ?? '',
    'config.error.preflight'   => $lang['config.error.preflight'] ?? '',
    'config.error.rolledBack'  => $lang['config.error.rolledBack'] ?? '',
    'config.error.network'     => $lang['config.error.network'] ?? '',
    'config.error.server'      => $lang['config.error.server'] ?? '',
    'config.error.generic'     => $lang['config.error.generic'] ?? '',
    'config.error.noCliBinary' => $lang['config.error.noCliBinary'] ?? '',
    'config.error.spawnFailed' => $lang['config.error.spawnFailed'] ?? '',
    'config.error.scriptMissing' => $lang['config.error.scriptMissing'] ?? '',
    'config.nginx.title'       => $lang['config.nginx.title'] ?? '',
    'config.nginx.body'        => $lang['config.nginx.body'] ?? '',
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
// app.css is cache-busted the same way as the boot script: without it a
// stylesheet change never reaches a browser that already cached it.
$cssVersion = @filemtime(__DIR__ . '/app.css');
$cssSuffix  = $cssVersion ? ('?v=' . $cssVersion) : '';
$_CAMILA['page']->camila_add_js("<link href=\"plugins/worktable/app.css" . $cssSuffix . "\" rel=\"stylesheet\">\n");
$configScriptVersion = @filemtime(__DIR__ . '/app-config.js');
$configVerSuffix     = $configScriptVersion ? ('?v=' . $configScriptVersion) : '';
$_CAMILA['page']->camila_add_js('<script type="module" src="./plugins/worktable/app-config.js' . $configVerSuffix . '"></script>');
