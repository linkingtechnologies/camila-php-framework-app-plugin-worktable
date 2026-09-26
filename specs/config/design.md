# Configuration — design

## Structure

Single view, no wizard steps. The confirmation is an inline panel, not a modal.

```
┌──────────────────────────────────────────────────────────────┐
│  intro                                    [ Rotate the name ]│  ← spa-title-box
│  (error / failure / success message)                         │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ Current directory   var                                │  │
│  │ Name rotated        No, still the default name         │  │
│  │ Database over HTTP  downloadable without a session     │  │
│  │ Compatibility stub  None                               │  │
│  └────────────────────────────────────────────────────────┘  │
│  [ warning: sqlite inside / windows open handle ]            │
│  [ confirm panel — only after the first click ]              │
│  Preflight checks ✓✓✓✓                                       │
└──────────────────────────────────────────────────────────────┘
[ box: the real fix — nginx deny rule, with copy button        ]
```

## State shape

```js
const state = {
  loading:    true,      // GET /vardir in flight
  error:      null,      // { status, message, kind, payload } | null — loading the state
  info:       null,      // payload of GET /vardir
  confirming: false,     // plan shown, waiting for the explicit second click
  running:    false,     // POST /vardir/rotate in flight
  result:     null,      // { ok, from, to, stub } after success
  failure:    null,      // { error, message } after a failed rotation
  exposure:   "unknown", // "unknown" | "checking" | "exposed" | "protected"
  copied:     false,     // clipboard feedback for the nginx snippet
};
```

Module-level, mutated in place, every mutation followed by `mount()`. A `cancelled`
flag guards both async paths. `error` and `failure` are separate on purpose: one is
"I could not read the state", the other is "the rotation itself went wrong", and they
carry different recovery text.

## Tables involved

None. This SPA calls only the plugin's own endpoints.

| Operation | Call |
|---|---|
| Read state + preflight | `client.call("GET", "/worktable/vardir")` |
| Perform the rotation | `client.call("POST", "/worktable/vardir/rotate", { targetName, token })` |
| Probe HTTP exposure | plain `fetch("<currentName>/<probeFile>", { method: "HEAD" })` — no API, no session |

## Payload

`GET /worktable/vardir`

```json
{
  "currentName": "var",
  "base": "var",
  "rotated": false,
  "targetName": "var-3f9a1c72b8d4e05617ac92be4d10f883",
  "stubFile": "<app>/var/config.php",
  "stubPresent": false,
  "dbInside": true,
  "checks": { "parentWritable": true, "currentExists": true, "targetFree": true, "stubWritable": true },
  "warnings": ["sqlite_inside", "windows_open_handle"],
  "canRotate": true,
  "token": "<sha256 of currentName|targetName>"
}
```

`POST /worktable/vardir/rotate` → `{ ok: true, from, to, stub, reloadHint: true }`, or
with `defer: true` → `{ scheduled: true, to, waitFor, php }`, or one of
`403 admin_required`, `400 invalid_target`, `409 stale_plan`, `409 preflight_failed`,
`500 rename_failed` (with `hint: "locked"`), `500 stub_dir_failed`, `500 stub_write_failed`,
`500 verify_failed`, `500 no_cli_binary`, `500 spawn_failed`, `500 script_missing`.

## Why a stub instead of patching the framework

`camila/config.inc.php` hardcodes `var/config.php` twice, and
`cf_login_openid.php` once. Patching them would work, but the first is a **framework**
file shared by every app on the install: a framework update would silently revert the
rotation and leave the app unable to find its configuration.

The stub inverts the problem. `<base>/config.php` becomes one line:

```php
require(__DIR__ . '/../<base>-<guid>/config.php');
```

Nothing outside the data directory changes. Everything downstream follows
automatically, because the real config defines
`CAMILA_VAR_ROOTDIR` as `dirname(__FILE__)` and every other path constant
(`CAMILA_LOG_DIR`, `CAMILA_TMP_DIR`, `CAMILA_FM_ROOTDIR`, `CAMILA_WORKTABLES_DIR`,
`CAMILA_DB_DSN`, …) derives from it. `CAMILA_APP_PATH`, `CAMILA_APP_DIR` and
`CAMILA_HOMEDIR` derive from its *parent*, so they are unaffected.

Verified in a sandbox: after a rotation, loading the stub in a clean PHP process
resolves `CAMILA_VAR_ROOTDIR` to the rotated directory.

## Base name and repeatability

"Rotating" is repeatable, so the target cannot be derived from the current name
verbatim. The base is the current name with any previous suffix stripped:

```php
$base = preg_replace('/-[0-9a-f]{32}$/i', '', basename(CAMILA_VAR_ROOTDIR));
```

`var` → `var`, and `var-<guid1>` → `var`. The target is always `<base>-<fresh guid>`,
so the stub path is stable across rotations: the first rotation creates
`<base>/config.php`, later ones only rewrite its single line.

## Safety

- **Admin only.** Both endpoints check `$_CAMILA['adm_user_group'] === CAMILA_ADM_USER_GROUP`,
  the same condition as `camila/auth.class.inc.php`. Note the framework treats an empty
  group as admin.
- **The target is never caller-chosen.** `targetName` must match
  `^<base>-[0-9a-f]{32}$` exactly, which rejects traversal and any path separator
  before the value is used.
- **The plan is bound to the state it was computed from.** `token` is
  `sha256(currentName|targetName)`, compared with `hash_equals()`. A tab left open
  across a rotation gets `409 stale_plan` instead of renaming twice.
- **`rename()` on a directory is atomic.** A failure moves nothing, which is why the
  preflight does not need a destructive probe.
- **Rollback.** After a successful rename, any failure renames the directory back and
  removes a stub directory this attempt created. Verified in a sandbox by replacing the
  stub path with a regular file so `mkdir()` fails: the rotated directory reappeared
  under its old name with the database intact and no residue under the new name.

## Known limitation — this is obscurity, not access control

The directory stays inside the document root and the web server keeps serving it;
only its name becomes unguessable. The `.htaccess` already in the directory denies
access but works only on Apache — nginx ignores it, which is why the database is
downloadable today. The SPA therefore shows the nginx rule as *the* fix:

```
location ~ "/app/[^/]+/var(-[0-9a-f]{32})?/" {
    deny all;
}
```

The pattern covers both the default name and any rotated one. **The quotes are not
optional**: unquoted, nginx reads the `{` of `{32}` as the start of a block, truncates
the expression and refuses to start with `pcre2_compile() failed: missing closing
parenthesis`. The rule is shipped in the bundle templates
(`templates/{win,linux}/nginx/nginx/conf/nginx*.conf`), placed before the `\.php$`
location so that `var/config.php` is denied rather than executed, next to a companion
rule that denies dotfiles (`.env` and friends) while still allowing `*.example` and
`/.well-known/`.

## Three ways to run it, and why

Measured on this instance (PHP 8.3.31, Windows), with a real PDO SQLite handle:

| Condition | `rename()` of the parent directory |
|---|---|
| connection open in the same process | fails — *Access is denied (code: 5)* |
| **connection closed** (`$pdo = null`) | **succeeds** |
| held open by another process | fails |

So it is not that Windows forbids the rename: it is that *somebody* must be holding
the database, and during the request that presses the button, somebody always is.
That gives three modes, in the order the tab offers them:

1. **Immediate** (`POST /vardir/rotate`). Works wherever nothing holds the data
   directory open — a MySQL-backed instance, or any platform that allows the rename.
   On this instance it fails, atomically, moving nothing.
2. **Deferred** (`POST /vardir/rotate` with `defer: true`) — *no restart needed*.
   The endpoint spawns `tools/rotate_vardir.php` detached with `--wait=30` and
   returns immediately; the script retries every 250 ms and wins as soon as the
   spawning request ends and PHP releases the database. Measured end to end: the
   spawn returns in ~60 ms and the rename succeeds on the 9th attempt, about two
   seconds after the request finishes.
3. **By hand**, with the web server stopped. Always available, and the answer when
   something else is holding the database open for longer than the deadline.

Why two handles cannot simply be closed instead: an API request holds
`$_CAMILA['db']` (ADOdb, from `camila/database.inc.php`) *and* php-crud-api's own
PDO. A route handler receives only `$params, $body, $segments`, so it cannot reach
the second one, and closing the first alone changes nothing.

### Deferred mode details

- The endpoint passes `--target=<the confirmed name>`, so the background run produces
  the name the operator was shown. Without it the script would mint its own GUID and
  the response's `to` would be a lie — caught in testing, fixed.
- The script validates `--target` against `^<base>-[0-9a-f]{32}$` itself: it is a
  value that ends up in a filesystem path, and the CLI entry point cannot assume the
  endpoint sanitised it.
- Detachment is `cmd /c start /B ""` on Windows and a backgrounded command elsewhere.
- **The spawned binary must be the CLI one, not `PHP_BINARY`.** nginx serves this app
  through four `php-cgi.exe` workers (`upstream php_farm`, ports 9000-9003), so inside
  a request `PHP_BINARY` is `php-cgi.exe` and `PHP_SAPI` is `cgi-fcgi`. Spawning that
  runs `rotate_vardir.php` under the cgi SAPI, where its own "CLI only" guard prints a
  403 line and exits — doing nothing and writing no log, which is indistinguishable
  from "still retrying" and ends as a timeout. `wt_cli_php()` therefore looks for
  `php.exe` (or `php`) next to `PHP_BINARY` and falls back to the PATH, and the
  endpoint returns `no_cli_binary` rather than spawning something that cannot work.
  Verified on this bundle: `php-cgi.exe rotate_vardir.php` prints the refusal, the
  `php.exe` beside it runs the script normally.
- The outcome is written as JSON to `sys_get_temp_dir()/wt_rotate_vardir.json`, which
  `GET /vardir` returns as `lastRun`. That is what makes the result visible after a
  reload, and what the SPA polls for.
- The SPA polls `GET /vardir` every 2 s, up to 20 times. Each poll briefly reopens the
  database, which is why the interval is generous rather than tight: against a 250 ms
  retry loop the contention is negligible, but polling hard would be self-defeating.
- There is a window of a few milliseconds between the rename and the stub being
  written, during which a concurrent request would not find `var/config.php`. It is
  inherent to doing this live; a request landing exactly there fails once.

## The command-line fallback

`tools/rotate_vardir.php` performs the identical operation with the web server
stopped. It exists because the tab cannot honestly tell an operator to "stop the web
server and press retry" — the retry button lives on a page that same server has to
serve. The endpoint therefore returns `cliScript` (the script's absolute path) both in
the state payload and in a `rename_failed` response, and the SPA turns it into a
three-step procedure with the exact command to copy:

1. stop the web server
2. run `php "<path>/tools/rotate_vardir.php"`
3. start the web server and reload the tab to check the result

The script:

- refuses to run under any SAPI other than CLI. It sits inside the document root,
  where the web server executes `.php`, so this guard is its first statement and is
  what keeps a rotation endpoint from existing at a public URL. Verified: an HTTP
  request to it answers `403` and does nothing.
- locates the data directory itself rather than loading the framework config, by
  looking under the app directory for the single `var` / `var-<32 hex>` directory that
  contains `config.php` *and* at least one of `db/ log/ tmp/ templates/ worktables/
  files/`. That deliberately skips the compatibility stub, which holds only
  `config.php`. If the match is not unique it refuses rather than guessing.
- supports `--dry-run` (prints the plan and the preflight, changes nothing) and
  `--yes` (skips the confirmation prompt); without either it asks before acting.
- takes its messages from `lang/<lang>.lang.php` under the `cli.` prefix, with
  `--lang=xx` selecting the language and `en` as the fallback — the same files and the
  same format the web UI uses, so nothing operator-facing is hardcoded. The `cli.*`
  keys are never listed in a dashboard mount, so they stay out of the browser payload.
  The confirmation accepts the affirmatives of every shipped language regardless of
  which prompt was displayed.
- warns when the database is still locked by another process, using a non-blocking
  `flock` probe — advisory, not conclusive, so the atomic `rename()` remains the real
  test.
- applies the same rename, stub and rollback logic as the endpoint, and prints the
  nginx deny rule at the end — in its quoted form, which is the one that actually
  loads (see "Known limitation" below).

Verified in a sandbox: rotation, stub chain resolving to the rotated directory in a
clean PHP process, database following the move, and a second rotation correctly
recovering the base name and repointing the stub.

## Other technical notes

- **SQLite lives inside the directory being renamed** (`CAMILA_DB_DSN` points at
  `CAMILA_VAR_ROOTDIR/db/camila.db`), so the web server holds it open while serving the
  page that triggers the rotation. On Windows that normally makes `rename()` fail with
  "Access is denied", and the button cannot work at all. Two handles are open during an
  API request — `$_CAMILA['db']` (ADOdb, from `camila/database.inc.php`) and
  php-crud-api's own PDO — and a route handler receives only `$params, $body, $segments`,
  so it cannot reach the second one to close it. Closing only the first would not help.
  This is why the deferred mode and the command-line fallback exist rather than some
  in-request workaround: the preflight warns about it up front, and the failure offers
  both ways out.
- **The request that performs the rotation keeps the old constants**, since they were
  defined at bootstrap. Anything it writes afterwards to `CAMILA_LOG_DIR` or
  `CAMILA_TMP_DIR` would land on a path that no longer exists, so the success message
  asks for a reload rather than continuing.
- **The exposure probe is a client-side `fetch`**, not a server check: the server cannot
  easily know what the web server will serve. `HEAD` keeps the body off the wire, and
  after a rotation the probe re-runs against the new name.

  **What it asks for comes from the server** (`probeFile`), because no single filename
  is always there. The framework can be pointed at MySQL or PostgreSQL instead of the
  bundled SQLite, and then `db/camila.db` does not exist at all — probing it would 404
  and the tab would report a reassuring "not reachable" while everything else in the
  directory stayed downloadable. `wt_vardir_probe_file()` therefore returns a real
  static file: `plugins/index.txt` when present (the framework writes it on every
  install), otherwise the first non-`.php`, non-dotfile it finds breadth-first within
  three levels. `.php` is excluded because it would be executed — a 200 with an empty
  body proves nothing — and dotfiles because a rule may deny them while leaving the
  rest readable.

  When there is nothing suitable it returns `''` and the UI says "cannot tell" rather
  than claiming safety it has not verified.

- **The verdict is about reachability, not about the rule.** `GET /vardir` also returns
  `dbDriver` (scheme only — never the DSN, which carries credentials) so the tab can
  show which database is in use. The nginx box repeats the probe's verdict as its own
  heading: red when the directory answers, green when it does not, amber when it could
  not be checked. The wording stays "the data directory is being served" rather than
  "the rule is missing", because the probe cannot distinguish a deny rule from any
  other reason a path does not answer.
- The stub directory holds only `config.php`; the `.htaccess` and all data move with the
  rotated directory.
