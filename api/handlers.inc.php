<?php
/**
 * worktable — Plugin API handlers
 * Base path: /app/<app-name>/cf_api.php/worktable
 *
 * ENDPOINTS
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * GET /status                                                        [PRIVATE]
 *   Simple liveness check. Returns: {status: "ok"}
 *
 * POST /mcp-proxy                                                    [PRIVATE]
 *   Forwards a single JSON-RPC 2.0 message to an arbitrary MCP Streamable HTTP
 *   endpoint, avoiding browser CORS restrictions. Used by the MCP Checker SPA.
 *   Body: { url, authHeader?, sessionId?, payload }
 *   Returns: { httpStatus, sessionId, body, raw }
 *
 * GET /vardir                                                  [PRIVATE/ADMIN]
 *   Reports the state of CAMILA_VAR_ROOTDIR and the preflight checks for
 *   rotating it. Returns: { current, rotated, target, token, checks, warnings,
 *   dbDriver, probeFile, ... }. probeFile is a static file inside the data
 *   directory for the browser to probe; the database is only a good probe on a
 *   bundled SQLite install, so it is not assumed.
 *
 * POST /vardir/rotate                                           [PRIVATE/ADMIN]
 *   Renames CAMILA_VAR_ROOTDIR to "<base>-<guid>" and leaves a one-line stub at
 *   the old path so nothing else needs patching. Body: { targetName, token }
 *   Returns: { ok, from, to, stub } | 4xx/5xx with { error, hint }
 *   When the data directory holds an open file (the SQLite database on a running
 *   instance) the rename cannot succeed from inside a request, because that very
 *   request is holding the file. Two ways out, both offered by the failure:
 *     - body { defer: true } spawns tools/rotate_vardir.php detached with --wait,
 *       so it retries until this request ends and releases the database. No restart.
 *     - cliScript is that script's path, to run by hand with the server stopped.
 *
 * GET /tools                                                         [PRIVATE]
 *   Lists the downloadable helper scripts in the plugin's tools/ directory.
 *   Returns: { files: [{ name, size, lang }] }
 *
 * ─────────────────────────────────────────────────────────────────────────────
 */

/**
 * Files listed by GET /tools.
 *
 * Only *.py and *.example are listed: those ship with the plugin and are public
 * (the SPA links them directly as static assets under plugins/worktable/tools/).
 * A real .env, which an operator creates locally from a .example template and
 * which holds plaintext credentials, matches neither pattern and is therefore
 * never advertised here.
 */
if (!function_exists('wt_tools_files')) {
    function wt_tools_files(): array {
        $real = realpath(__DIR__ . '/../tools');
        if ($real === false) return [];

        // scandir(), not glob(): the .env.*.example files are dotfiles and glob()
        // skips names starting with a dot.
        $out = [];
        foreach (scandir($real) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            if (!str_ends_with($name, '.py') && !str_ends_with($name, '.example')) continue;
            $path = $real . DIRECTORY_SEPARATOR . $name;
            if (!is_file($path)) continue;
            $out[$name] = $path;
        }
        ksort($out);
        return $out;
    }
}

/**
 * True when the caller is an administrator.
 *
 * Mirrors camila/auth.class.inc.php: $_CAMILA['adm_user_group'] is set by the API
 * auth middleware and equals CAMILA_ADM_USER_GROUP both for users in the admin
 * group and for users with an empty group (the framework's "no restriction"
 * convention).
 */
if (!function_exists('wt_is_admin')) {
    function wt_is_admin(): bool {
        global $_CAMILA;
        return isset($_CAMILA['adm_user_group'])
            && defined('CAMILA_ADM_USER_GROUP')
            && $_CAMILA['adm_user_group'] === CAMILA_ADM_USER_GROUP;
    }
}

/**
 * Everything the vardir endpoints need to know about the current layout.
 *
 * "Rotating" means renaming CAMILA_VAR_ROOTDIR to "<base>-<32 hex>", where <base>
 * is the directory name with any previous "-<32 hex>" suffix stripped — so the
 * operation is repeatable and each run produces a fresh, unguessable name.
 */
if (!function_exists('wt_vardir_state')) {
    function wt_vardir_state(): array {
        $current  = CAMILA_VAR_ROOTDIR;                 // absolute, self-derived
        $parent   = dirname($current);
        $currName = basename($current);

        // strip a previous rotation suffix to get the stable base name
        $base     = preg_replace('/-[0-9a-f]{32}$/i', '', $currName);
        $rotated  = ($base !== $currName);

        $stubDir  = $parent . DIRECTORY_SEPARATOR . $base;
        $stubFile = $stubDir . DIRECTORY_SEPARATOR . 'config.php';

        return [
            'currentPath' => $current,
            'currentName' => $currName,
            'parent'      => $parent,
            'base'        => $base,
            'rotated'     => $rotated,
            'stubDir'     => $stubDir,
            'stubFile'    => $stubFile,
        ];
    }
}

/**
 * Non-destructive preflight. Nothing here writes anything.
 */
if (!function_exists('wt_vardir_preflight')) {
    function wt_vardir_preflight(array $st, string $targetName): array {
        $target = $st['parent'] . DIRECTORY_SEPARATOR . $targetName;

        $checks = [
            'parentWritable' => is_writable($st['parent']),
            'currentExists'  => is_dir($st['currentPath']),
            'targetFree'     => !file_exists($target),
            'stubWritable'   => is_dir($st['stubDir'])
                                    ? is_writable($st['stubDir'])
                                    : is_writable($st['parent']),
        ];

        $warnings = [];

        // SQLite living inside the directory we are about to rename: on Windows an
        // open handle without FILE_SHARE_DELETE makes rename() fail outright.
        $dsn = defined('CAMILA_DB_DSN') ? CAMILA_DB_DSN : '';
        $sqliteInside = false;
        if (stripos($dsn, 'sqlite') === 0 || stripos($dsn, 'sqlite3://') === 0) {
            $decoded = urldecode($dsn);
            $needle  = str_replace('\\', '/', $st['currentPath']);
            $sqliteInside = stripos(str_replace('\\', '/', $decoded), $needle) !== false;
        }
        if ($sqliteInside) {
            $warnings[] = 'sqlite_inside';
        }
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false && $sqliteInside) {
            $warnings[] = 'windows_open_handle';
        }

        return ['target' => $target, 'checks' => $checks, 'warnings' => $warnings];
    }
}

/**
 * Path of the PHP **CLI** binary, or '' if it cannot be found.
 *
 * PHP_BINARY is whatever SAPI is serving this request. Under FastCGI — which is how
 * nginx runs this app, four php-cgi.exe workers behind an upstream — that is
 * php-cgi.exe, not php.exe. Spawning it would run the background job under the cgi
 * SAPI, where rotate_vardir.php's own "CLI only" guard refuses to do anything and
 * exits without even writing its log. That is exactly how a deferred rotation used to
 * fail silently and time out. The CLI binary normally sits next to it.
 */
if (!function_exists('wt_cli_php')) {
    function wt_cli_php(?string $current = null): string {
        $current = $current ?? PHP_BINARY;
        if ($current === '') return '';

        $windows = stripos(PHP_OS_FAMILY, 'Windows') !== false;
        $exe     = $windows ? 'php.exe' : 'php';

        // Already the CLI binary?
        if (basename($current) === $exe && PHP_SAPI === 'cli') {
            return $current;
        }

        $candidate = dirname($current) . DIRECTORY_SEPARATOR . $exe;
        if (is_file($candidate)) return $candidate;

        // Last resort: let the OS resolve it from PATH.
        return $exe;
    }
}

/**
 * Database driver name only — never the DSN, which carries credentials.
 *
 * The framework can be pointed at MySQL or PostgreSQL as well as the bundled
 * SQLite, and that changes what is worth checking: with an external database
 * there is no camila.db inside the data directory at all.
 */
if (!function_exists('wt_db_driver')) {
    function wt_db_driver(): string {
        $dsn = defined('CAMILA_DB_DSN') ? CAMILA_DB_DSN : '';
        if (!preg_match('~^([a-z0-9]+)://~i', $dsn, $m)) return 'unknown';
        $scheme = strtolower($m[1]);
        if (str_starts_with($scheme, 'sqlite'))   return 'sqlite';
        if (str_starts_with($scheme, 'mysql'))    return 'mysql';
        if (str_starts_with($scheme, 'postgres')) return 'postgres';
        return $scheme;
    }
}

/**
 * A file inside the data directory that the web server would serve verbatim, as
 * a relative URL path — what the browser-side probe should ask for.
 *
 * It must be a real, static file: a .php would be executed (200 with no body,
 * which proves nothing) and a dotfile may be denied by a rule that still leaves
 * everything else readable. camila.db is the obvious candidate on a bundled
 * SQLite install and simply does not exist on MySQL or PostgreSQL, which is why
 * this looks for any static file rather than assuming one.
 *
 * Returns '' when there is nothing suitable, so the UI can say "cannot tell"
 * instead of reporting a reassuring result it has not earned.
 */
if (!function_exists('wt_vardir_probe_file')) {
    function wt_vardir_probe_file(string $root): string {
        // Cheap, always-present candidates first.
        foreach (['plugins/index.txt', 'db/camila.db'] as $rel) {
            if (is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel))) {
                return $rel;
            }
        }

        // Otherwise the first static file, breadth-first and depth-limited so a
        // large files/ tree cannot turn this into a full filesystem walk.
        $queue = [['', 0]];
        while ($queue) {
            [$sub, $depth] = array_shift($queue);
            if ($depth > 2) continue;
            $dir = $sub === '' ? $root : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub);
            foreach (scandir($dir) ?: [] as $name) {
                if ($name === '.' || $name === '..' || $name[0] === '.') continue;
                $rel  = $sub === '' ? $name : $sub . '/' . $name;
                $path = $dir . DIRECTORY_SEPARATOR . $name;
                if (is_dir($path)) { $queue[] = [$rel, $depth + 1]; continue; }
                if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'php') continue;
                return $rel;
            }
        }
        return '';
    }
}

if (!function_exists('wt_vardir_logfile')) {
    function wt_vardir_logfile(): string {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wt_rotate_vardir.json';
    }
}

return [

    // GET /worktable/status
    'GET /status' => function ($params, $body, $segments) {
        return ['status' => 'ok'];
    },

    // POST /worktable/mcp-proxy
    'POST /mcp-proxy' => function ($params, $body, $segments) {
        $input      = is_array($body) ? $body : [];
        $targetUrl  = is_string($input['url'] ?? null) ? trim($input['url']) : '';
        $authHeader = is_string($input['authHeader'] ?? null) ? trim($input['authHeader']) : '';
        $sessionId  = is_string($input['sessionId'] ?? null) ? trim($input['sessionId']) : '';
        $payload    = $input['payload'] ?? null;

        if ($targetUrl === '' || !preg_match('#^https?://#i', $targetUrl)) {
            return ['__status' => 400, 'error' => 'invalid_url'];
        }
        if ($payload === null) {
            return ['__status' => 400, 'error' => 'missing_payload'];
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream',
        ];
        if ($authHeader !== '') $headers[] = 'Authorization: ' . $authHeader;
        if ($sessionId  !== '') $headers[] = 'Mcp-Session-Id: ' . $sessionId;

        $ch = curl_init($targetUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $raw = curl_exec($ch);

        if ($raw === false) {
            $curlError = curl_error($ch);
            curl_close($ch);
            return ['__status' => 502, 'error' => 'proxy_request_failed', 'message' => $curlError];
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $rawHeaders = substr($raw, 0, $headerSize);
        $rawBody    = substr($raw, $headerSize);

        $respSessionId = '';
        if (preg_match('/^Mcp-Session-Id:\s*(.+)$/mi', $rawHeaders, $m)) {
            $respSessionId = trim($m[1]);
        }

        $bodyJson = null;
        $trimmedBody = trim($rawBody);
        if ($trimmedBody !== '') {
            if (stripos($rawHeaders, 'text/event-stream') !== false) {
                foreach (explode("\n", $rawBody) as $line) {
                    $line = trim($line);
                    if (stripos($line, 'data:') === 0) {
                        $decoded = json_decode(trim(substr($line, 5)), true);
                        if ($decoded !== null) $bodyJson = $decoded;
                    }
                }
            } else {
                $bodyJson = json_decode($rawBody, true);
            }
        }

        return [
            'httpStatus' => $httpStatus,
            'sessionId'  => $respSessionId,
            'body'       => $bodyJson,
            'raw'        => $bodyJson === null ? $rawBody : null,
        ];
    },

    // GET /worktable/vardir
    'GET /vardir' => function ($params, $body, $segments) {
        if (!wt_is_admin()) {
            return ['__status' => 403, 'error' => 'admin_required'];
        }

        $st = wt_vardir_state();

        // The proposed name is recomputed on every call; the token binds a later
        // POST to exactly this plan, so a stale tab cannot rotate twice.
        $targetName = $st['base'] . '-' . bin2hex(random_bytes(16));
        $pre        = wt_vardir_preflight($st, $targetName);

        return [
            'currentName' => $st['currentName'],
            'base'        => $st['base'],
            'rotated'     => $st['rotated'],
            'targetName'  => $targetName,
            'stubFile'    => $st['stubFile'],
            'stubPresent' => is_file($st['stubFile']),
            'dbInside'    => in_array('sqlite_inside', $pre['warnings'], true),
            'dbDriver'    => wt_db_driver(),
            // Relative path the browser should probe to see whether the data
            // directory is served over HTTP; '' when nothing suitable exists.
            'probeFile'   => wt_vardir_probe_file($st['currentPath']),
            'checks'      => $pre['checks'],
            'warnings'    => $pre['warnings'],
            'canRotate'   => !in_array(false, $pre['checks'], true),
            'token'       => hash('sha256', $st['currentName'] . '|' . $targetName),
            // Offered as the way out when the rename cannot work from inside a
            // request: the tab cannot tell the operator to stop the web server and
            // then press a button on a page that server has to serve.
            'cliScript'   => realpath(__DIR__ . '/../tools/rotate_vardir.php') ?: '',
            'cliPhp'      => wt_cli_php(),
            // Outcome of the most recent deferred run, so a reload after one shows
            // what happened instead of leaving the operator guessing.
            'lastRun'     => (function () {
                $f = wt_vardir_logfile();
                if (!is_file($f)) return null;
                $j = json_decode((string)file_get_contents($f), true);
                return is_array($j) ? $j : null;
            })(),
        ];
    },

    // POST /worktable/vardir/rotate
    'POST /vardir/rotate' => function ($params, $body, $segments) {
        if (!wt_is_admin()) {
            return ['__status' => 403, 'error' => 'admin_required'];
        }

        $input      = is_array($body) ? $body : [];
        $targetName = is_string($input['targetName'] ?? null) ? trim($input['targetName']) : '';
        $token      = is_string($input['token'] ?? null) ? trim($input['token']) : '';

        $st = wt_vardir_state();

        // The target must be exactly "<base>-<32 hex>" — never a caller-chosen path.
        if (!preg_match('/^' . preg_quote($st['base'], '/') . '-[0-9a-f]{32}$/', $targetName)) {
            return ['__status' => 400, 'error' => 'invalid_target'];
        }
        if (!hash_equals(hash('sha256', $st['currentName'] . '|' . $targetName), $token)) {
            return ['__status' => 409, 'error' => 'stale_plan'];
        }

        $pre = wt_vardir_preflight($st, $targetName);
        if (in_array(false, $pre['checks'], true)) {
            return ['__status' => 409, 'error' => 'preflight_failed', 'checks' => $pre['checks']];
        }

        $from = $st['currentPath'];
        $to   = $pre['target'];

        // Deferred mode: hand the job to a detached CLI process that retries until
        // this request ends and the database handle is released. Measured: the
        // spawn returns in well under a second and the renamer wins a couple of
        // seconds later, with no restart. See specs/config/.
        if (!empty($input['defer'])) {
            $script = realpath(__DIR__ . '/../tools/rotate_vardir.php');
            if ($script === false) {
                return ['__status' => 500, 'error' => 'script_missing'];
            }
            $log = wt_vardir_logfile();
            @unlink($log);

            $php = wt_cli_php();
            if ($php === '') {
                return ['__status' => 500, 'error' => 'no_cli_binary', 'cliScript' => $script];
            }
            // Pass the confirmed target through, so the background run produces the
            // name the operator was shown rather than inventing a second one.
            $args = escapeshellarg($script)
                  . ' --yes --wait=30'
                  . ' --target=' . escapeshellarg($targetName)
                  . ' --log=' . escapeshellarg($log);

            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                // "start /B" detaches the child so it outlives this request.
                $cmd = 'cmd /c start /B "" ' . escapeshellarg($php) . ' ' . $args;
            } else {
                $cmd = escapeshellarg($php) . ' ' . $args . ' > /dev/null 2>&1 &';
            }

            $h = @popen($cmd, 'r');
            if ($h === false) {
                return ['__status' => 500, 'error' => 'spawn_failed'];
            }
            pclose($h);

            return [
                'scheduled' => true,
                'to'        => $targetName,
                'waitFor'   => 30,
                'php'       => $php,      // which binary actually got spawned
            ];
        }

        // rename() on a directory is atomic: on failure nothing moved.
        if (!@rename($from, $to)) {
            $err = error_get_last();
            return [
                '__status'  => 500,
                'error'     => 'rename_failed',
                'hint'      => 'locked',
                'message'   => $err['message'] ?? '',
                'cliScript' => realpath(__DIR__ . '/../tools/rotate_vardir.php') ?: '',
            ];
        }

        // From here on a failure must put the directory back.
        $createdStubDir = false;
        $rollback = function () use ($to, $from, &$createdStubDir, $st) {
            if ($createdStubDir && is_dir($st['stubDir'])) {
                @unlink($st['stubFile']);
                @rmdir($st['stubDir']);
            }
            @rename($to, $from);
        };

        if (!is_dir($st['stubDir'])) {
            if (!@mkdir($st['stubDir'], 0755, true)) {
                $rollback();
                return ['__status' => 500, 'error' => 'stub_dir_failed'];
            }
            $createdStubDir = true;
        }

        $stub = "<?php
"
              . "// Compatibility stub — the real configuration lives in the rotated
"
              . "// directory next to this one. Written by the worktable plugin's
"
              . "// Configuration tab; see specs/config/.
"
              . "require(__DIR__ . '/../" . $targetName . "/config.php');
";

        if (@file_put_contents($st['stubFile'], $stub) === false) {
            $rollback();
            return ['__status' => 500, 'error' => 'stub_write_failed'];
        }

        // Verify the new layout before declaring success.
        $realConfig = $to . DIRECTORY_SEPARATOR . 'config.php';
        if (!is_file($realConfig) || !is_file($st['stubFile'])) {
            $rollback();
            return ['__status' => 500, 'error' => 'verify_failed'];
        }

        return [
            'ok'         => true,
            'from'       => basename($from),
            'to'         => $targetName,
            'stub'       => $st['stubFile'],
            'reloadHint' => true,
        ];
    },

    // GET /worktable/tools
    'GET /tools' => function ($params, $body, $segments) {
        $files = [];
        foreach (wt_tools_files() as $name => $path) {
            $files[] = [
                'name' => $name,
                'size' => filesize($path) ?: 0,
                'lang' => str_ends_with($name, '.py') ? 'python' : 'env',
            ];
        }
        return ['files' => $files];
    },


];
