<?php
/**
 * rotate_vardir.php — rename the instance data directory from the command line.
 *
 * Same operation as the plugin's Configuration tab, but without a running web
 * server: the tab has to do the rename from inside a request that is itself
 * holding the SQLite database open, which on Windows makes rename() fail. Stop
 * the web server and run this instead.
 *
 *   php rotate_vardir.php --dry-run     show what would happen, change nothing
 *   php rotate_vardir.php               rename, after an explicit confirmation
 *   php rotate_vardir.php --yes         rename without asking (for scripting)
 *   php rotate_vardir.php --wait=30     keep retrying for 30s, then give up
 *   php rotate_vardir.php --log=FILE    write the outcome to FILE as JSON
 *   php rotate_vardir.php --lang=it     message language (default: en)
 *   php rotate_vardir.php --target=NAME use this exact new name instead of a fresh
 *                                       GUID (the Configuration tab passes the name
 *                                       it showed the operator, so the plan it
 *                                       confirmed is the plan that runs)
 *
 * --wait exists so the Configuration tab can avoid a restart altogether: it spawns
 * this script detached, the request that spawned it ends and releases the database,
 * and the retry loop wins the moment the file is free. --wait implies --yes.
 *
 * It renames <app>/var (or <app>/var-<old guid>) to <app>/var-<new guid> and
 * leaves a one-line stub at <app>/var/config.php pointing at the new directory,
 * so no framework file needs patching. Every CAMILA_* path constant follows,
 * because they all derive from the config file's own location.
 *
 * On any failure after the rename, the directory is put back.
 *
 * Messages come from the plugin's own lang/<lang>.lang.php files under the "cli."
 * prefix, so they are translated in the same place as the web UI. Those keys are
 * never listed in a dashboard mount, so they never reach the browser.
 *
 * See specs/config/ for the full design.
 */

// This file sits inside the document root, where the web server executes .php.
// It must never do anything over HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "This script only runs from the command line.\n";
    exit(1);
}

$argvFlags  = array_slice($argv, 1);
$dryRun     = in_array('--dry-run', $argvFlags, true);
$assumeYes  = in_array('--yes', $argvFlags, true) || in_array('-y', $argvFlags, true);

$waitFor    = 0.0;
$logFile    = '';
$wantedName = '';
$lang       = 'en';
foreach ($argvFlags as $flag) {
    if (str_starts_with($flag, '--wait='))   $waitFor    = max(0.0, (float)substr($flag, 7));
    if (str_starts_with($flag, '--log='))    $logFile    = substr($flag, 6);
    if (str_starts_with($flag, '--target=')) $wantedName = trim(substr($flag, 9));
    if (str_starts_with($flag, '--lang='))   $lang       = trim(substr($flag, 7));
}
if ($waitFor > 0) $assumeYes = true;   // unattended by definition

/* ---------------------------------------------------------------------------
 * i18n — same file format as the rest of the plugin (see AGENTS.md): one
 * "key = value" per line, "//" comments, en as the fallback. The only addition
 * is that a literal \n inside a value becomes a real newline, because these
 * messages go to a terminal rather than being laid out by a browser.
 * ------------------------------------------------------------------------- */
function load_lang(string $langDir, string $lang): array {
    $safe = preg_match('/^[a-z]{2}$/', $lang) ? $lang : 'en';
    $file = $langDir . '/' . $safe . '.lang.php';
    if (!is_file($file)) $file = $langDir . '/en.lang.php';
    if (!is_file($file)) return [];

    $map = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (ltrim($line) === '' || ltrim($line)[0] === '/') continue;
        $parts = explode(' = ', $line, 2);
        if (count($parts) === 2) {
            $map[trim($parts[0])] = str_replace('\n', "\n", trim($parts[1]));
        }
    }
    return $map;
}

$I18N = load_lang(__DIR__ . '/../lang', $lang);

/** Same semantics as the plugin's JS helper: an unknown key renders as the key. */
function t(string $key, ...$args): string {
    global $I18N;
    $s = $I18N[$key] ?? $key;
    foreach ($args as $a) {
        $pos = strpos($s, '%s');
        if ($pos === false) break;
        $s = substr_replace($s, (string)$a, $pos, 2);
    }
    return $s;
}

/** Records the outcome where the web UI can read it back. */
$writeLog = function (string $status, array $extra = []) use ($logFile) {
    if ($logFile === '') return;
    @file_put_contents($logFile, json_encode(array_merge([
        'status' => $status,
        'at'     => date('c'),
    ], $extra), JSON_UNESCAPED_SLASHES));
};

// plugins/worktable/tools/ -> plugins/worktable -> plugins -> <app>
$appDir = dirname(__DIR__, 3);

function out(string $s = ''): void { fwrite(STDOUT, $s . PHP_EOL); }
function fail(string $s, int $code = 1): void { fwrite(STDERR, $s . PHP_EOL); exit($code); }

/**
 * The data directory is the one matching var / var-<32 hex> that actually holds
 * instance data — not the compatibility stub, which contains only config.php.
 */
function find_data_dir(string $appDir): ?array {
    $markers = ['db', 'log', 'tmp', 'templates', 'worktables', 'files'];
    $found   = [];

    foreach (scandir($appDir) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        $path = $appDir . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($path)) continue;
        if (!preg_match('/^(.+?)(-[0-9a-f]{32})?$/', $name, $m)) continue;
        if (!is_file($path . DIRECTORY_SEPARATOR . 'config.php')) continue;

        $hasData = false;
        foreach ($markers as $marker) {
            if (is_dir($path . DIRECTORY_SEPARATOR . $marker)) { $hasData = true; break; }
        }
        if ($hasData) {
            $found[] = ['name' => $name, 'path' => $path, 'base' => $m[1]];
        }
    }

    if (count($found) !== 1) return null;
    return $found[0];
}

out(t('cli.title'));
out(str_repeat('-', 56));
out(t('cli.app', $appDir));

$dir = find_data_dir($appDir);
if ($dir === null) {
    fail(t('cli.notFound', $appDir));
}

$base    = $dir['base'];
$current = $dir['path'];
$rotated = $dir['name'] !== $base;

// A caller-supplied name must still be "<base>-<32 hex>" and nothing else: it ends up
// in a filesystem path, so no separators and no traversal.
if ($wantedName !== '') {
    if (!preg_match('/^' . preg_quote($base, '/') . '-[0-9a-f]{32}$/', $wantedName)) {
        fail(t('cli.badTarget', $base, $wantedName));
    }
    $targetName = $wantedName;
} else {
    $targetName = $base . '-' . bin2hex(random_bytes(16));
}
$target  = $appDir . DIRECTORY_SEPARATOR . $targetName;
$stubDir = $appDir . DIRECTORY_SEPARATOR . $base;
$stubFil = $stubDir . DIRECTORY_SEPARATOR . 'config.php';

out(t('cli.current', $dir['name'], $rotated ? t('cli.current.rotated') : t('cli.current.default')));
out(t('cli.newName', basename($target)));
out(t('cli.stub', $stubFil));
out('');

// ---- preflight -----------------------------------------------------------
$checks = [
    'cli.check.exists' => is_dir($current),
    'cli.check.parent' => is_writable($appDir),
    'cli.check.free'   => !file_exists($target),
    'cli.check.stub'   => is_dir($stubDir) ? is_writable($stubDir) : is_writable($appDir),
];
foreach ($checks as $key => $ok) {
    out(sprintf('  [%s] %s', $ok ? 'ok' : 'NO', t($key)));
}
if (in_array(false, $checks, true)) {
    $writeLog('preflight_failed', ['checks' => $checks]);
    fail(t('cli.checkFailed'));
}

// Is anything still holding the database open? Not conclusive on every platform,
// but it catches the common case of a web server that is still running.
$db = $current . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'camila.db';
if (is_file($db)) {
    $h = @fopen($db, 'r+');
    if ($h === false) {
        out('  [!!] ' . t('cli.db.unwritable'));
    } else {
        if (!@flock($h, LOCK_EX | LOCK_NB)) {
            out('  [!!] ' . t('cli.db.locked'));
        } else {
            flock($h, LOCK_UN);
        }
        fclose($h);
    }
}

if ($dryRun) {
    out(t('cli.dryRun'));
    exit(0);
}

if (!$assumeYes) {
    out('');
    out(t('cli.confirm'));
    $answer = strtolower(trim((string)fgets(STDIN)));
    // Accept the affirmatives of every shipped language, whichever prompt was shown.
    if (!in_array($answer, ['y', 'yes', 's', 'si', 'sì'], true)) {
        fail(t('cli.aborted'), 0);
    }
}

// ---- execute -------------------------------------------------------------
// rename() on a directory is atomic: if it fails, nothing moved. With --wait we
// simply keep asking until the database handle is released by whoever holds it.
$deadline = microtime(true) + $waitFor;
$attempts = 0;
$renamed  = false;

do {
    $attempts++;
    $renamed = @rename($current, $target);
    if ($renamed) break;
    if (microtime(true) >= $deadline) break;
    usleep(250000);
} while (true);

if (!$renamed) {
    $e   = error_get_last();
    $msg = $e['message'] ?? '';
    $writeLog('rename_failed', ['attempts' => $attempts, 'message' => $msg]);
    fail(t('cli.renameFailed', $attempts, $msg !== '' ? ': ' . $msg : ''));
}
out(t('cli.renamed', basename($current), basename($target))
    . ($attempts > 1 ? '  ' . t('cli.renamed.attempt', $attempts) : ''));

$createdStubDir = false;
$rollback = function (string $why) use ($target, $current, $stubDir, $stubFil, &$createdStubDir, $writeLog) {
    if ($createdStubDir && is_dir($stubDir)) { @unlink($stubFil); @rmdir($stubDir); }
    @rename($target, $current);
    $writeLog('rolled_back', ['why' => $why]);
    fail(t('cli.rolledBack', $why, basename($current)));
};

if (!is_dir($stubDir)) {
    if (!@mkdir($stubDir, 0755, true)) {
        $rollback(t('cli.stubDirFailed'));
    }
    $createdStubDir = true;
}

$stub = "<?php\n"
      . "// Compatibility stub — the real configuration lives in the rotated\n"
      . "// directory next to this one. Written by tools/rotate_vardir.php;\n"
      . "// see specs/config/.\n"
      . "require(__DIR__ . '/../" . basename($target) . "/config.php');\n";

if (@file_put_contents($stubFil, $stub) === false) {
    $rollback(t('cli.stubWriteFailed'));
}
out(t('cli.stubWritten', $stubFil));

if (!is_file($target . DIRECTORY_SEPARATOR . 'config.php') || !is_file($stubFil)) {
    $rollback(t('cli.verifyFailed'));
}

$writeLog('ok', ['from' => basename($current), 'to' => basename($target), 'attempts' => $attempts]);

out('');
out($waitFor > 0 ? t('cli.done.noRestart') : t('cli.done.restart'));
out(t('cli.done.dbGone'));
out('');
out(t('cli.reminder'));
out('');
// The quoted form is the one that actually works: unquoted, nginx reads the "{"
// of "{32}" as a block opener and refuses to start.
out('    location ~ "/app/[^/]+/var(-[0-9a-f]{32})?/" {');
out('        deny all;');
out('    }');
