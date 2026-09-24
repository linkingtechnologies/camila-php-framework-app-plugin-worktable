# CAMILA WorkTable Plugin — SPA Development Guide

## Purpose

This directory is the **worktable** plugin — a scaffold for building lightweight Single Page Applications on the CAMILA WorkTable platform.

The plugin is part of the **CAMILA WorkTable** ecosystem:

- **Framework**: [camila-php-framework](https://github.com/linkingtechnologies/camila-php-framework) — PHP backend providing the WorkTable REST API, authentication, and table management.
- **Plugin**: this directory, containing SPAs and their PHP mount files.

SPAs run entirely in the browser and communicate with the backend exclusively via `WorkTableClient`, which wraps the CAMILA WorkTable REST API.

AI agents working in this repository must generate and modify SPA modules that are deterministic, state-safe, backward-compatible, and easy to review.

The primary goal is to build administrative tools without introducing unnecessary framework complexity or speculative architecture.

---

## What is in this plugin today

```
AGENTS.md
api/handlers.inc.php            custom REST endpoints (GET /status, POST /mcp-proxy, GET /tools*)
app.css                         shared SPA styles (.spa-title-box, kiosk table styles)
conf/menu.xml                   tab bar — declares the dashboards and their order
conf/repo.json                  GitHub repo metadata (upstream: camila-php-framework-app-plugin-worktable)
dashboards.inc.php              tab dispatch — delegates to camila core
dashboard-<id>.inc.php          one manual mount per SPA
app-<name>.js                   one entry point per SPA
views/<spa>/index.js            optional view module for a larger SPA
lang/{en,it}.lang.php           plugin i18n keys
specs/<spa>/{use-case,design}.md
tools/                          Python helper scripts, downloadable from the Tools tab
```

| Tab (menu.xml) | Dashboard id | Mount | Entry point | Spec |
|---|---|---|---|---|
| Home | `home` | `dashboard-home.inc.php` | `app-home.js` | `specs/home/` |
| Worktable Explorer | `worktable-explorer` | `dashboard-worktable-explorer.inc.php` | `app-worktable-explorer.js` + `views/worktable-explorer/index.js` | `specs/worktable-explorer/` |
| Endpoints | `endpoints` | `dashboard-endpoints.inc.php` | `app-endpoints.js` | `specs/endpoints/` |
| MCP Checker | `mcp-checker` | `dashboard-mcp-checker.inc.php` | `app-mcp-checker.js` | `specs/mcp-checker/` |
| Tools | `tools` | `dashboard-tools.inc.php` | `app-tools.js` | `specs/tools/` |

`home` is the plugin's default tab because it is the first `<tab>` in `conf/menu.xml`.

Known divergences already recorded in the specs — do not "fix" them silently:

- `views/worktable-explorer/index.js` is a verbatim port from the sibling `segreteria-campo` plugin: all its UI strings are hardcoded Italian and it does not use `window.I18N` / the lang files.

---

## The plugin directory is inside the web root

Everything under `plugins/worktable/` is served statically by the web server for any path that is not `.php` (nginx here: `location /` with `root html`, no dotfile deny). A `.py`, `.md`, `.json`, `.example` or dotfile placed here is fetchable over HTTP, by anyone, without a session.

Consequences:

- **Never put a secret in this directory.** `tools/.env.sync` and `tools/.env.locale`, created by an operator from the shipped `.example` templates, hold plaintext usernames and passwords for the source *and* destination instances. They belong outside the document root; nothing in the repo's `.gitignore` covers `.env*` either.
- Files that ship with the plugin are public by nature — the plugin's own repository is public — so linking them at their static path is fine and is what the Tools SPA does (`plugins/worktable/tools/<name>`, relative, with the `download` attribute). Keep such links relative so they survive a URL prefix.
- Route a download through an authenticated endpoint only when the file is *not* public. Nothing in this plugin currently needs that.
- Server-side listings must still whitelist what they advertise, so an operator-created `.env` never appears in a file list.

---

## SPA Specifications

Each SPA has a dedicated specification directory under `specs/`. Read the relevant spec before modifying a SPA.

Each spec directory contains:
- `use-case.md` — expected behavior (the "what"): goal, actors, main scenario, alternative flows in Cockburn style
- `design.md` — technical decisions (the "how"): state shape, tables involved, merge logic, payload

If implementation and specification disagree, report the discrepancy. Do not silently change behavior.

---

## Specification Style

### use-case.md — the "what"

Cockburn-style use case with the following sections, in order:

1. **ID** — short code (UC-XXX) and one-line description
2. **System context** — where this SPA fits in the workflow (skip if standalone)
3. **Goal** — one paragraph, user-facing outcome
4. **Primary Actor** — who operates it
5. **Stakeholders and interests** — table: stakeholder | interest
6. **Preconditions** — bullet list
7. **Postconditions — Success** — what is true after success
8. **Postconditions — Error / Partial failure** — what happens on failure
9. **Status classification** *(if applicable)* — table mapping data conditions to UI labels
10. **Main Success Scenario** — one sub-section per wizard step or view, with numbered steps
11. **Extensions** — coded as `Na.` (step N, alternative a): empty states, API errors, validation failures, partial failures

Rules:
- Steps describe observable behavior, not implementation details
- Do not mention lit-html, state variables, or JS internals
- Do mention WorkTable table names, field names, and business rules
- Keep each step to one sentence where possible
- Keep the sections in this order, but omit the ones that do not apply — a static page has no stakeholders table and no extensions (see `specs/home/use-case.md`)
- A placeholder or unfinished SPA ends with a short **Status** section saying so

### design.md — the "how"

Technical reference for implementors:

1. **Structure** — wizard steps or views with ASCII flow diagram
2. **State shape** — full JS object with all keys and types/defaults
3. **Tables involved** — table: operation | WorkTable table name
4. **Merge logic** *(if applicable)* — merge key, priority rules, field resolution strategy
5. **Payload** — exact field names and values written to each table
6. **Classification logic** *(if applicable)* — code-level rules for categorizing records
7. **Other technical notes** — loading strategy, draft editing pattern, sequence API, non-obvious guards

Rules:
- Use code blocks for state shape, payload examples, and classification logic
- Use tables for tables involved
- Note divergences from the patterns documented in this AGENTS.md
- A ported or copied SPA opens with a **Provenance** paragraph naming the source and every intentional edit (see `specs/worktable-explorer/design.md`)
- Client methods a spec relies on are verified against `camila/js/worktable-client.js` and the spec says so

---

## Core Principles

### 1. Deterministic behavior

Generated code must behave predictably across reloads, repeated operations, pagination changes, filtering, sorting, and editing flows.

Avoid hidden state transitions and implicit side effects.

### 2. State safety

State must be explicit and reset when the active context changes.

Examples of context changes:
- selected table / tab / record / organization / event
- editor mode
- wizard step

Do not reuse stale drafts or stale API responses across contexts.

### 3. Backward compatibility

Prefer additive changes.

Do not rename existing state keys, API fields, table names, or DOM assumptions unless explicitly instructed.

Do not remove existing behavior without a matching specification change.

### 4. No speculative refactors

Do not rewrite working code for style, abstraction, or framework preference.

Only refactor when required by the requested behavior or when explicitly instructed.

### 5. Explicit over generic

Prefer explicit mappings and configuration objects over generic magic:
- explicit table names
- explicit field lists
- explicit label overrides
- explicit filterable columns
- explicit derived-field rules

---

# Frontend Runtime

## Rendering

Use `lit-html` templates.

There is no bundler and no import map: `lit-html` is imported from the framework copy served at the web root, by relative path, exactly as the existing entry points do.

```js
// from plugins/worktable/app-<name>.js  (4 levels up = web root)
import { html, render } from "../../../../camila/js/lit-html/lit-html.js";
```

A view module under `views/<spa>/` does not import `lit-html` at all — the entry point passes `html` and `render` in (see "View modules").

Do not introduce React, Vue, Angular, Svelte, Solid, Alpine, JSX build steps, or virtual DOM frameworks.

Templates should be pure functions of state wherever possible.

## lit-html — `<select>` with dynamic value

The `.value` binding on a `<select>` **is unreliable** in lit-html when options are dynamic or loaded asynchronously. The browser applies `.value` only if the matching option already exists in the DOM; if options arrive later (e.g. after an API call), the select silently falls back to the first element.

**Rule:** always use `?selected` on every `<option>`.

```js
// WRONG
html`<select .value=${current}>
  ${opts.map(o => html`<option value=${o}>${o}</option>`)}
</select>`

// CORRECT
html`<select @change=${e => onChange(e.target.value)}>
  ${opts.map(o => html`<option value=${o} ?selected=${current === o}>${o}</option>`)}
</select>`
```

This rule applies to all `<select>` elements whose value or options depend on async state.

`.value` on `<input>` and `<textarea>` is fine and is used throughout — the rule is specific to `<select>`.

## Styling

Use:
- Bulma CSS
- Remix Icons (`ri-*` classes)

Do not introduce additional UI frameworks unless explicitly requested.

Plugin-specific rules live in `app.css`, which every mount links with
`camila_add_js('<link href="plugins/worktable/app.css" rel="stylesheet">')`:

| Selector | Purpose |
|---|---|
| `#app .spa-title-box` | squared top corners + 3px blue top border, so the first box touches the tab bar above it |
| `.table tr.row-selected td` | soft-green selected row with a left accent bar (kiosk-friendly, avoids Bulma blue) |
| `.table.is-hoverable tbody tr:hover td` | softened hover so it does not clash with the selected row |
| `.table td`, `.table th` | `user-select: none` — touch screens |
| `tr.is-readonly` | dimmed row, `not-allowed` cursor on its inputs |
| `.table.is-vcentered` | middle-aligned cells, for roomy list tables (not a Bulma class) |
| `.tools-icon-cell`, `.tools-size`, `.tools-action` | shrink-to-fit columns in the Tools list |
| `body` | `overscroll-behavior-y: none` — kills Android pull-to-refresh on the totem |

Add new shared rules here rather than inlining them in a SPA. Note that the explorer view still uses inline `style="..."` heavily (ported code) — new code should prefer Bulma classes and `app.css`.

## Admin page layout pattern

Admin dashboard SPAs must follow the visual pattern of `/cf_app.php?admin&dashboard=users`, adapted for this plugin as follows: **do not render a separate page-title box.** The tab bar above the SPA already names the page (e.g. "HOME", "ITINERARY MAP"); repeating that text in an `<h3>` right below it is redundant, and looks especially odd once the first box is visually attached to the tab bar (see `.spa-title-box` below). Go straight into the toolbar/first content box instead:

```
<div class="container pt-0 pb-4">                        ← no side padding: content touches
                                                             the tab bar's left/right edges
  <div class="box spa-title-box"> ... first content ... </div>   ← spa-title-box: squared top
                                                                     corners + blue top border,
                                                                     touches the tab bar above

<div class="level mb-3">                                 ← toolbar row
  <div class="level-left"> ... </div>                    ← search / info (optional)
  <div class="level-right">
    <button class="button is-primary is-small">          ← primary action
      <span class="icon"><i class="ri-*-line"></i></span>
      <span>Label</span>
    </button>
  </div>
</div>

<!-- inline error (no section/container wrapper) -->
<article class="message is-danger">
  <div class="message-body">...</div>
</article>

<!-- inline non-blocking warning -->
<article class="message is-warning">
  <div class="message-body">...</div>
</article>

<!-- loading bar (initial load only) -->
<progress class="progress is-small is-primary"></progress>

<table class="table is-fullwidth is-striped is-hoverable">
  ...
</table>
```

Rules:
- No separate page-title box (see above) — the toolbar (or first content box) is always rendered (button is disabled, not hidden, when inactive)
- Errors and warnings appear inline between the toolbar and the table — no `section`/`container` wrappers
- Progress bar appears only during the initial data load, not during per-row operations
- Per-row progress: `<progress class="progress is-small" style="max-width:160px">` inside the cell
- Result tags: `<span class="tag is-light is-success">` / `<span class="tag is-light is-danger">`
- Warning tags (non-blocking data alerts): `<span class="tag is-warning is-light">`

## Layout

Use responsive, non-fragile layouts.

Prefer:
- `flex-wrap`
- `min-width: 0`
- Bulma utility classes
- compact forms and tables for administrative screens

Avoid fixed widths unless needed for compact action columns.

## Module loading and cache busting

There are two independent cache-busting layers. Preserve both.

**1. Boot script (PHP side).** Each mount appends the entry point's own `filemtime()` to its `<script type="module">` tag, so a deployed change is picked up but an unchanged file still caches:

```php
$ver    = @filemtime(__DIR__ . '/app-<name>.js');
$suffix = $ver ? ('?v=' . $ver) : '';
$_CAMILA['page']->camila_add_js('<script type="module" src="./plugins/worktable/app-<name>.js' . $suffix . '"></script>');
```

**2. Dynamically imported view modules (JS side).** SPA view modules may be dynamically imported, with the version appended to the URL:

```js
import(`./views/my-module/index.js?v=${VERSION}`)
```

`VERSION` must come from `window.APP_CONFIG?.version` with `Date.now()` as fallback:

```js
const VERSION = window.APP_CONFIG?.version || Date.now();
```

No mount in this plugin sets `APP_CONFIG.version`, so `VERSION` is always `Date.now()` today: dynamically imported view modules are never served from cache. That is intentional and cheap (one extra request per page load); do not "optimize" it without a spec change.

Define `VERSION` only in entry points that actually perform a dynamic import. `app-home.js`, `app-endpoints.js` and `app-mcp-checker.js` are single-file SPAs and correctly have no `VERSION`.

Do not introduce a bundler requirement unless explicitly requested.

---

# SPA Entry Points

Each SPA has one `app-<name>.js` file at the plugin root, loaded as `<script type="module">` by its mount.

An entry point must:

1. Import `html` and `render` by relative path (see "Rendering")
2. Obtain `root` via `document.getElementById("app")`
3. Guard on `WorkTableClient` being present before using it (see below)
4. Initialize `client` via `WorkTableClient(window.APP_CONFIG || {})` — skip this only if the SPA makes no API calls at all, as `app-home.js` does
5. Define `VERSION` only if it dynamically imports a view module
6. Define only the state properties the SPA actually uses
7. Call `mount()` (or `render(App(), root)`) to start rendering

## `WorkTableClient` is a global, not a module

`WorkTableClient` is a classic script (`camila/js/worktable-client.js`) that the mount injects *before* the module tag; it is not importable. Because a module runs after the document is parsed, the global is normally there — but every entry point still fails loudly rather than silently rendering a dead page:

```js
if (typeof WorkTableClient !== "function") {
  render(html`<div class="notification is-danger">WorkTableClient not available</div>`, root);
  throw Error("WorkTableClient not available");
}
```

Keep this guard in every new entry point, including SPAs that do not call the API yet.

## View modules

A SPA whose view does not comfortably fit in the entry point puts it in `views/<spa>/index.js` and dynamically imports it. The contract, as implemented by `worktable-explorer`:

```js
// entry point
const { WorktableExplorer } = await import(`./views/worktable-explorer/index.js?v=${VERSION}`);
render(await WorktableExplorer({ state, client, html, render, root }), root);

// views/worktable-explorer/index.js
export async function WorktableExplorer({ state, client, html, render, root }) {
  let records = [];                       // view-local state: plain closure variables
  function rerender() { render(view(), root); }
  function view() { return html`...`; }
  await loadTables();                     // initial load before first paint
  return view();                          // entry point renders this; the view owns every later render
}
```

Rules:
- The view module imports nothing — `html`, `render`, `client` and `root` all arrive as arguments, which keeps the relative-path problem in one place.
- It returns the *first* template and owns all subsequent renders through its own `rerender()`.
- It must render into the `root` it was given, never re-query the DOM for it.
- Entry-point-level `state` is for what crosses module boundaries. State private to one view may live as closure variables inside the module.
- Wrap the dynamic import in try/catch and render the failure — a missing or broken view module must not leave a blank page.

Single-view SPAs must not carry unused `step`, `org`, or wizard state.

Wizard SPAs must guard each step transition: if required preceding state is missing, redirect back to step 1.

---

# PHP Mount Files

Each SPA is mounted by exactly one file: `dashboard-<id>.inc.php` at the plugin root. There is no separate `<name>.inc.php` in this plugin — the whole manual mount lives in the dashboard file.

Adding a SPA therefore means four files: a `<tab>` in `conf/menu.xml`, `dashboard-<id>.inc.php`, `app-<name>.js`, and `specs/<spa>/`.

## Dashboard registration and routing

`dashboards.inc.php` owns no logic of its own — it delegates to the shared camila-core dispatcher:

```php
<?php
$_camilaPluginDir = __DIR__;
require_once(CAMILA_DIR . '/views/plugin_dashboards.inc.php');
```

Keep it that way: routing, path-safety and cross-plugin linking are the framework's job, and every plugin in the app must behave identically. What that dispatcher does:

- Prints the tab bar from this plugin's own `conf/menu.xml`, with the active tab highlighted, and suppresses it entirely for kiosk users (username starting with `totem`). Export is disabled for all dashboards.
- Resolves `?dashboard=<id>` and `require()`s the matching `dashboard-<id>.inc.php`. The underscore spelling `dashboard_<id>.inc.php` is accepted as a fallback; prefer the dash form.
- With no `dashboard` param, falls back to the id of the **first `<tab>` in `conf/menu.xml`** (`home` here), not to a hardcoded `m0`. Reordering `menu.xml` therefore changes the landing tab.
- Accepts a namespaced `?dashboard=<plugin>--<dashboard>` to link into another plugin's dashboard, and `<plugin>--` for that plugin's own default tab. The separator is `--` because ids themselves contain single dashes (`worktable-explorer`). When a namespaced id resolves to a foreign plugin, that plugin's tab bar is shown with every link re-namespaced.
- Whitelists both segments to `[A-Za-z0-9_-]+` and checks `is_file()` before `require()`, so an unknown id renders an inline "Dashboard not found" message instead of a fatal error.

Consequences for SPA code:

- Dashboard ids must match `[A-Za-z0-9_-]+`.
- Cross-plugin links must be written `?dashboard=<plugin>--<id>`; a bare id always resolves inside the current plugin.
- Do not print your own tab bar or page title — the dispatcher already printed the tab bar (see "Admin page layout pattern").

`conf/menu.xml` is the tab list. One `<tab>` per dashboard, in display order:

```xml
<menu>
 <tab>
  <id>home</id>
  <title>Home</title>
  <url>?dashboard=home</url>
 </tab>
</menu>
```

`<id>` must equal the `dashboard-<id>.inc.php` suffix, and `<url>` must be the bare `?dashboard=<id>` form — the dispatcher rewrites it when another plugin borrows this menu.

## Manual mount pattern

`CamilaUserInterface::mountMiniApp($pluginName, $bootScript, $cssFilePath)` is the framework shortcut: it injects `worktable-client.js`, a fixed `APP_CONFIG` (`baseUrl` / `apiKeyHeaderName` / `apiKeyHeaderValue`), the `<div id="app">`, the `nomodule` guard-rail, the stylesheet link and the module tag.

Every SPA in *this* plugin uses the manual pattern instead, because `mountMiniApp` cannot inject `window.I18N`, cannot add extra `APP_CONFIG` keys such as `mcpDefaultUrl`, and does not cache-bust the boot script. Use the manual pattern for anything new here; it is also the only way to keep the four mounts consistent.

**Naming warning:** never name the local translations array `$i18n`. Dashboard mount files are `require()`'d at global scope (`cf_app.php` → `header.php`/`views/cf_app.inc.php` → `dashboards.inc.php` → `dashboard-<id>.inc.php`), so a top-level `$i18n = [...]` here becomes a real PHP global and overwrites camila's own `global $i18n` (a `CamilaTranslator` instance set in `camila_hawhaw.php`, used by TinyButStrong's `onshow` auto-merge to render the header's logout/preferences links). That collision breaks the top menu with a TBS error ("item 'getTranslation(...)' is not an existing key in the array") because TBS then finds a plain array instead of the expected object. Use `$pluginI18n` (or any non-colliding name) instead.

```php
<?php
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

$pluginI18n = [/* ... keys loaded from plugin lang file ... */];

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

// Boot script cache-busting — see "Module loading and cache busting"
$scriptVersion = @filemtime(__DIR__ . '/app-<name>.js');
$verSuffix     = $scriptVersion ? ('?v=' . $scriptVersion) : '';
$_CAMILA['page']->camila_add_js('<script type="module" src="./plugins/worktable/app-<name>.js' . $verSuffix . '"></script>');
```

Notes on the template:

- Order matters: `worktable-client.js`, then `APP_CONFIG`, then `I18N`, then the `<div id="app">`, then the module tag last. The module must never run before the global client and the config exist.
- Omit the closing `?>` — none of the existing mounts have one, and a stray newline after it would be emitted into the page.
- Omit the `I18N` line for a SPA that has no translatable strings (`dashboard-worktable-explorer.inc.php` does).
- Keep the `<script nomodule>` guard-rail: it is the only message an old browser gets.
- Never rebuild the API URL as `'/app/' . CAMILA_APP_DIR . '/cf_api.php'`. Derive it from `dirname($_SERVER['SCRIPT_NAME'])` as above, and build every other endpoint (`mcpDefaultUrl`, custom routes) by concatenating onto `$apiUrl` — one derivation per mount, never two. The two forms are identical on a root-mounted app, so a regression here is invisible until the app moves behind a prefix.
- Asset URLs stay relative (`../../camila/js/...` in `add_raw`, `./plugins/worktable/...` in `camila_add_js`): they resolve against the document URL and are already prefix-safe. Do not turn them into absolute paths.

`APP_CONFIG` keys used in this plugin:

| Key | Value | Used by |
|---|---|---|
| `baseUrl` | `<scheme>://<host><app base path>/cf_api.php`, the app base path taken from `dirname($_SERVER['SCRIPT_NAME'])` | every SPA (`WorkTableClient`) |
| `apiKeyHeaderName` | `Authorization` | every SPA |
| `apiKeyHeaderValue` | `PHPSESSID` — the session cookie is the credential | every SPA |
| `mcpDefaultUrl` | `<baseUrl>?mcp=1` — built from the same `$apiUrl` | `endpoints`, `mcp-checker` |

`WorkTableClient` also accepts `recordsPath`, `columnsPath`, `permissionsPath`, `sequencePath`, `tablesPath`, `attachmentsPath` and `timeoutMs` (default 20000 ms); none are overridden here, so all default paths apply.

## Plugin lang loading

Plugin-specific i18n keys live in `lang/{lang}.lang.php`. Load them with a helper function — do not use `camila_get_translation()` for plugin keys.

Wrap the helper in `if (!function_exists('ai_load_lang'))`: dashboard files are `require()`d at global scope, and a bare `function` declaration would fatal the moment two of them end up in one request.

```php
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

$lang       = ai_load_lang(__DIR__ . '/lang', $_CAMILA['lang'] ?? 'en');
$pluginI18n = [
    'key' => $lang['plugin.key'] ?? '',
    // ...
];
```

Do not name this array `$i18n` — see the naming warning in "Manual mount pattern" above.

Lang file format (`lang/en.lang.php`):

```
// English — worktable plugin
plugin.page.title = My Page Title
plugin.btn.save   = Save
plugin.error      = Error
```

Rules:
- Line comments start with `//`
- Key/value separator is ` = ` (space-equals-space)
- Fallback is always `en.lang.php`
- Pass `%s` placeholders in values; replace with positional args in JS via `t(key, value)`
- `en.lang.php` and `it.lang.php` must carry the same key set; both are maintained here
- Keys are namespaced by SPA (`home.*`, `endpoints.*`, `mcpChecker.*`) in one shared file per language
- The mount lists every key it passes explicitly, each with an `?? ''` fallback — no wildcard/prefix export. A key added to the lang file but not to the mount never reaches the browser.

## JS i18n helper

```js
const t = (key, ...args) => {
  let s = window.I18N?.[key] ?? key;
  args.forEach(a => { s = s.replace('%s', a); });
  return s;
};
```

---

# Navigation

Navigation is manual and state-driven.

Allowed patterns:
- wizard-style `state.step`
- tab-based `state.activeTab`
- explicit `goTo(step)`
- full reload when browser history consistency is at risk

Avoid introducing a router framework.

Prefer full reload over complex history manipulation when consistency matters.

---

# Data Access

All data access must use `WorkTableClient`.

The authoritative surface is `camila/js/worktable-client.js` (served at the web root). Its header comment lists every method and endpoint — read it before assuming a method exists, and record in the spec that you did.

## Table-bound operations

```js
client.table(tableName).list(query)
client.table(tableName).read(id)
client.table(tableName).create(payload)
client.table(tableName).update(id, payload)
client.table(tableName).remove(id)
client.table(tableName).describe(query)       // GET /columns/<table>
client.table(tableName).permissions(query)    // GET /permissions/<table>
client.table(tableName).sequence(query)       // GET /sequence/<table> — next id
client.table(tableName).distinct(column, query)

// attachments (see below)
client.table(tableName).uploadAttachment(id, file)
client.table(tableName).fetchAttachment(id)
client.table(tableName).hasAttachment(id)
client.table(tableName).listAttachments()
client.table(tableName).deleteAttachment(id)
client.table(tableName).attachmentUrl(id)
```

## Public operations

```js
client.list(tableName, query)
client.read(tableName, id)
client.create(tableName, payload)
client.update(tableName, id, payload)
client.remove(tableName, id)
client.describe(tableName, query)
client.permissions(tableName, query)
client.sequence(tableName, query)
client.distinct(tableName, column, query)
client.filter(column, operator, ...values)
client.negate(operator)

// attachments, table passed as first argument
client.uploadAttachment(tableName, id, file)
client.fetchAttachment(tableName, id)
client.hasAttachment(tableName, id)
client.listAttachments(tableName)
client.deleteAttachment(tableName, id)
client.attachmentUrl(tableName, id)
```

The table-bound form is preferred inside a SPA that works on one table at a time.

## Tables enumeration

```js
// List all visible tables (plain list)
client.tables()

// With metadata (id + short_title per table)
client.tables({ metadata: "1" })

// With metadata and record count
client.tables({ metadata: "1", count: "1" })
// Response: { tables: [{ name, id, short_title, count }] }
```

## Attachments

Each record may carry at most one attachment, stored server-side as `<attachments>/<table>/<id>.bin` plus an `.meta` file holding the MIME type.

```js
await client.table(t).uploadAttachment(id, file)   // multipart, field "file" → { url }
await client.table(t).fetchAttachment(id)          // → { blob, mime, ext }
await client.table(t).hasAttachment(id)            // HEAD → false | { mime, ext }
await client.table(t).listAttachments()            // → { ids: [{ id, mime, ext }] }
await client.table(t).deleteAttachment(id)         // → void (204 tolerated)
client.table(t).attachmentUrl(id)                  // plain URL string, no auth header
```

Rules:

- **Never point an `<img src>` (or an `<a href>`) at `attachmentUrl()`.** That URL carries no auth header, and the served response sets `Content-Disposition: attachment`, so the browser downloads instead of displaying. To show an image, `fetchAttachment()` → `URL.createObjectURL(blob)` → use that object URL.
- Pair every `createObjectURL` with a `revokeObjectURL`: on overlay close, on attachment delete, after a download click, and right after `img.onload` when pre-loading.
- `listAttachments()` is the cheap way to badge a list: call it once after loading records and keep a `Map` keyed on `String(id)`. Failure is non-blocking — leave the map empty and show no badges rather than an error.
- `hasAttachment()` returns `false`, not a rejected promise, when there is nothing there.
- **Only images can be uploaded.** The server sniffs the content with `finfo` and rejects anything whose MIME does not start with `image/` with `400 Only image/* files are allowed` — the declared `Content-Type` is ignored. An upload UI that offers a generic file picker must handle that 400; it cannot be prevented client-side by the `accept` attribute alone.
- Record ids are whitelisted server-side: a UUID when `CAMILA_APPLICATION_UUID_ENABLED`, otherwise `[a-zA-Z0-9_-]+`. Anything else is a `400 Invalid record id`.
- A successful upload answers `201 { url }`, where `url` is the same path `attachmentUrl()` builds.

Known discrepancy (reported, not fixed): `views/worktable-explorer/index.js` has a "non-image file → upload without crop" path, and `specs/worktable-explorer/design.md` documents it, but this backend rejects every non-image upload with the 400 above. That path can therefore only ever surface an error banner. Do not implement new non-image upload flows against this API.

## Import

```js
// Import a server-side file into a worktable
client.importTable(name, filepath, sheet)
// name:     mapped API table name as returned by tables() (e.g. "materiali")
// filepath: path relative to CAMILA_APP_PATH
// sheet:    optional zero-based sheet index (default 0)
// Response: { status, imported, failed, total }

// Import a browser File (xlsx/xls) via multipart upload
client.importTableUpload(name, file, sheet)
```

## Custom plugin endpoints

```js
// Call any plugin-specific endpoint with auth applied
client.call(method, path, body, query)
// method: "GET"|"POST"|"PUT"|"DELETE"|"PATCH"
// path:   e.g. "/worktable/my-endpoint"
// body:   optional JSON object
// query:  optional plain object → query string
```

`call()` targets `baseUrl` directly rather than `/records`, so `path` is the full route including the `/worktable` prefix.

This plugin's own endpoints live in `api/handlers.inc.php`:

| Route | Purpose |
|---|---|
| `GET /worktable/status` | liveness check → `{ status: "ok" }` |
| `GET /worktable/tools` | lists the downloadable files in `tools/` (`*.py` + `*.example`, via `scandir()` so dotfiles are included) → `{ files: [{ name, size, lang }] }`. The Tools SPA links the files themselves as static assets. |
| `POST /worktable/mcp-proxy` | forwards one JSON-RPC 2.0 message to an arbitrary MCP Streamable HTTP endpoint, sidestepping browser CORS. Body `{ url, authHeader?, sessionId?, payload }` → `{ httpStatus, sessionId, body, raw }`. Parses both `application/json` and `text/event-stream` (`data:` lines) responses, and surfaces `Mcp-Session-Id` from the response headers. Used by the MCP Checker SPA. |

Do not introduce alternative API clients or abstraction layers unless explicitly requested.

### Implementing a custom plugin endpoint (PHP side)

`client.call()` reaches `api/handlers.inc.php`, which is `require`-d by `Tqdev\PhpCrudApi\CamilaPluginController` (in `camila/api.include.php`) on every API request. That file must **`return` an array** mapping `'METHOD /path'` (relative — no `/worktable` prefix) to a callable:

```php
<?php
return [
    'GET /my-endpoint' => function ($params, $body, $segments) {
        // $params: query string params, $body: decoded JSON body (array|null), $segments: URL path segments
        return ['ok' => true];               // 200, JSON-encoded
    },
    'POST /my-endpoint' => function ($params, $body, $segments) {
        return ['__status' => 400, 'error' => 'bad_request']; // '__status' sets the HTTP status and is stripped from the payload
    },
];
```

A file that does not `return` an array (e.g. a bare procedural script with `if ($method === ...) { echo ...; exit; }`) is silently ignored — its routes are never registered, and any request to them fails with `Route '...' not found`. There is no `$method`/`$path` global available to this file; use the route array keys and the callable arguments instead.

**How the file is found.** `camila/api/cf_api_controller.inc.php` walks `CamilaPlugins::getList()` and, for each plugin with an `api/handlers.inc.php`, registers it with `prefix = '/' . <plugin dir id>`. That is where the `/worktable` prefix comes from — it is the directory name, so routes inside the file stay relative. A new plugin directory needs no API wiring beyond creating the file.

**Authentication.** Routes are registered behind the API's normal auth middleware, exactly like `/records`. A route that must be reachable without a session declares itself public:

```php
'GET /ping' => ['public' => true, 'handler' => function ($params, $body, $segments) {
    return ['pong' => true];
}],
```

Use this sparingly and never for anything that reads or writes table data.

**Diagnostics.** `GET /status/plugins` reports every plugin file the API tried to load, whether it loaded, each route it registered, and the reason any of them did not — `file not found`, `file did not return an array`, `handler not callable`, `invalid route format`. Check it first when a `client.call()` 404s; it distinguishes "my file was ignored" from "my path is wrong".

---

# WorkTable Query Rules

## Listing records

```js
client.table(tableName).list({
  include,    // string | string[]  — fields to return
  exclude,    // string | string[]  — fields to exclude
  filters,    // Array              — AND filters (see §Filtering)
  orFilters,  // Object             — OR filters (see §Filtering)
  order,      // Array              — sorting (see §Sorting)
  size,       // number             — max rows (no pagination)
  page,       // number | number[]  — pagination (see §Pagination)
})
```

### include / exclude

```js
client.table("items").list({ include: ["name", "status", "created_at"] })
client.table("items").list({ exclude: ["large_blob_field"] })
```

### size

Use `size` only when pagination is not needed:

```js
client.table("catalogs").list({ size: 9999 })  // all records
client.table("settings").list({ size: 1 })     // just 1 record
```

## Sorting

Server-side sorting via `order` parameter. Format: array of `[field, direction]`:

```js
order: [["created_at", "desc"]]
order: [["last_name", "asc"], ["first_name", "asc"]]
```

Directions: `"asc"` | `"desc"`

If server-side sorting is unavailable, sort client-side with a stable sort using the original index as tiebreaker.

## Filtering

Build filters with `client.filter(column, operator, value)` → `[column, operator, value]`.

Pass filters as array to the `filters` parameter (logical AND):

```js
client.table("items").list({
  filters: [client.filter("status", "eq", "active")]
})
```

**Important:** always use `filters: [...]` — the `filter: { field: value }` syntax is silently ignored.

### Confirmed operators

`client.filter()` only builds the tuple `[column, operator, ...values]` — the operator set is the server's, not the client's. These are the ones in use in this plugin and verified against the backend:

| Operator | Meaning | Takes a value |
|---|---|---|
| `eq` | equals | yes |
| `neq` | not equals (`negate("eq")`) | yes |
| `cs` | contains string (case-sensitive) | yes |
| `sw` | starts with | yes |
| `ew` | ends with | yes |
| `gt` | greater than | yes |
| `lt` | less than | yes |
| `is` | is null / empty | **no** |
| `nis` | is not null / empty (`negate("is")`) | **no** |

Value-less operators must be built without a value — `client.filter(col, "is")`, not `client.filter(col, "is", "")`.

To negate: `client.negate("eq")` → `"neq"` (it simply prefixes `n`).

Anything not in this table is unverified: check it against the backend before relying on it, and add it here when confirmed.

### OR filters

```js
orFilters: {
  filter1: ["status", "eq", "active"],
  filter2: ["status", "eq", "pending"],
}
```

## Pagination

```js
page: [1, 50]   // page 1, 50 records per page → ?page=1,50
page: 2         // page number only
```

Expected paginated response:

```json
{ "records": [...], "results": 150 }
```

Normalize always:

```js
function getRecords(res) {
  if (Array.isArray(res)) return res;
  if (res && Array.isArray(res.records)) return res.records;
  return [];
}
```

## Distinct values

```js
client.table("items").distinct("category", { include: "category,label" })
```

Use `distinct()` for deduplicated single-column values. Use `list()` with `include` and `size` when you need to join multiple fields or sources.

When merging from multiple sources, deduplicate with a `Map` keyed on a stable composite key:

```js
const map = new Map();
for (const row of rows) {
  const key = [norm(row.a), norm(row.b)].join("|");
  if (!map.has(key)) map.set(key, row);
}
```

## Parallel loading with partial fallback

```js
const results = await Promise.allSettled([
  withRetry(() => loadFromTableA(client)),
  withRetry(() => loadFromTableB(client))
]);

const dataA = results[0].status === "fulfilled" ? results[0].value : [];
const dataB = results[1].status === "fulfilled" ? results[1].value : [];

const failures = results.filter(r => r.status === "rejected");
if (failures.length) error = normalizeApiError(failures[0].reason);
```

## Permissions

```json
{ "table": "items", "id": "1", "can": { "create": true, "read": true, "update": true, "delete": true } }
```

If the permissions request fails, enter read-only mode. Create, edit, and delete actions must be disabled or hidden in read-only mode.

---

# Error Handling

Every API operation must handle failure: show an error message, avoid corrupting state, keep the UI usable.

## What the client actually rejects with

`WorkTableClient` never resolves a non-2xx response. It rejects with a plain `Error`:

```js
err.message  // "HTTP 404"
err.status   // 404
err.payload  // parsed JSON when the response was application/json, otherwise the raw text
```

Two cases do not follow that shape:

- **Timeout.** Every request is aborted by an `AbortController` after `timeoutMs` (20 s by default). The rejection is a `DOMException` with `name === "AbortError"` and no `status` — the `normalizeApiError` below classifies it as `unknown`, so check `err?.name === "AbortError"` explicitly if the SPA needs to say "timed out".
- **`hasAttachment()`** resolves to `false` instead of rejecting when there is no attachment.

Shortcut used by the explorer when a full normalization is overkill:

```js
const msg = String(e?.payload?.message || e?.message || e);
```

## Error normalization

```js
function normalizeApiError(err) {
  const raw = err?.payload ?? err?.response ?? err;
  const status  = raw?.status ?? raw?.statusCode ?? err?.status ?? err?.statusCode;
  const code    = raw?.code ?? err?.code ?? raw?.error?.code;
  const message = raw?.message ?? err?.message ?? raw?.error?.message
    ?? (typeof raw === "string" ? raw : "Unknown error");

  let kind = "unknown";
  if (status === 401 || status === 403) kind = "auth";
  else if (status === 404)              kind = "not_found";
  else if (status === 429)              kind = "rate_limit";
  else if (status >= 500)               kind = "server";
  else if (code === "ETIMEDOUT" || code === "ECONNABORTED") kind = "timeout";
  else if (code === "ENETUNREACH" || code === "ECONNRESET") kind = "network";

  return { status, code, message, kind, raw };
}
```

```js
function userFriendlyErrorText(e) {
  switch (e.kind) {
    case "auth":       return "Session expired or insufficient permissions.";
    case "rate_limit": return "Too many requests. Please wait and try again.";
    case "timeout":
    case "network":    return "Connection problem. Check your network.";
    case "server":     return "Server error. Please try again later.";
    case "not_found":  return "Resource not found.";
    default:           return "An error occurred.";
  }
}
```

Always show a retry button alongside error messages in load contexts.

## Retry with exponential backoff

```js
const sleep = ms => new Promise(r => setTimeout(r, ms));

function shouldRetry(e) {
  return ["network", "timeout", "server", "rate_limit"].includes(e.kind);
}

async function withRetry(fn, { retries = 2, baseDelay = 400 } = {}) {
  let last;
  for (let attempt = 0; attempt <= retries; attempt++) {
    try { return await fn(); }
    catch (err) {
      last = normalizeApiError(err);
      if (attempt === retries || !shouldRetry(last)) throw last;
      await sleep(baseDelay * Math.pow(2, attempt) + Math.floor(Math.random() * 150));
    }
  }
  throw last;
}
```

Do not retry `auth` or `not_found` errors.

---

# Async Render Safety

When an async load is in-flight and the user navigates away or changes context, avoid re-rendering into a stale root.

Use a `cancelled` flag:

```js
let cancelled = false;

async function load() {
  // ...
  if (!cancelled) rerender();
}

// on context change or cleanup:
cancelled = true;
```

For a multi-step sequence, re-check the flag after *every* `await`, and set it whenever an input that feeds the sequence changes — `app-mcp-checker.js` does exactly this, so editing the URL mid-handshake cannot let a late response overwrite the freshly reset results:

```js
function onFieldInput(key, value) {
  state[key] = value;
  cancelled = true;      // invalidate anything in flight for the previous inputs
  resetResults();
  mount();
}

async function connect() {
  cancelled = false;
  const stale = () => cancelled;
  const a = await step1(); if (stale()) return;
  const b = await step2(); if (stale()) return;
  // ...
}
```

The `catch` block must check the flag too, or an abort surfaces as a user-visible error after the user already moved on.

---

# State Management

Use plain JavaScript state objects. Do not introduce Redux, Zustand, MobX, Pinia, or external state machines.

Two shapes are in use, both acceptable:

- **Module-level `state` object** (`app-endpoints.js`, `app-mcp-checker.js`) — a single object, mutated in place, followed by `mount()`.
- **Closure variables inside a view module** (`views/worktable-explorer/index.js`) — plain `let` bindings owned by the exported function, followed by its own `rerender()`.

Do not convert one into the other as a refactor. Whichever a SPA uses, every mutation must be followed by exactly one re-render call, and re-render must always go through the SPA's single `mount()` / `rerender()` function rather than an ad-hoc `render()`.

## State shape

Define only the state properties the SPA actually uses. Preserve existing shape across modifications. Prefer additive properties:

```js
state.step1 ||= {};
state.step2 ||= {};
```

## Context reset

Reset state when context changes:
- changing tab resets pagination page
- changing filter resets pagination page
- changing editor record resets draft
- saving a record invalidates list cache

## Draft editing

Editors must use a draft object separate from the persisted base row:

```js
state.editor.baseRow
state.editor.draftRow
```

Do not mutate persisted rows directly while editing.

---

# UI Behavior

## Lists

List pages must support: loading state, empty state, error state, pagination, sorting, filtering, permission-aware actions.

Filters and pagination controls must remain visible even when no records are returned.

## Tables

Dense administrative tables — many rows, many columns, data the user scans — should be compact:

```html
<table class="table is-small is-narrow is-fullwidth is-hoverable is-striped">
```

A short list whose rows *are* the page's main action (a handful of items, each with
a button) is not that case: `is-small is-narrow` there buys no density and only
shrinks the text. Drop both modifiers and let Bulma's default sizing apply, as the
Tools tab does:

```html
<table class="table is-fullwidth is-hoverable is-striped is-vcentered">
```

`.table.is-vcentered` is defined in `app.css`, not by Bulma.

Use icons consistently:
- edit: `ri-pencil-line` (toggling an inline edit mode: `ri-edit-line`)
- delete: `ri-delete-bin-6-line`
- add: `ri-add-line`
- save: `ri-save-line`
- back: `ri-arrow-left-line`
- upload/import: `ri-upload-2-line`
- read-only: `ri-lock-line`
- refresh: `ri-refresh-line`
- close / cancel: `ri-close-line`
- filter: `ri-filter-3-line`, apply filter: `ri-search-line`
- copy to clipboard: `ri-file-copy-line`
- attachment badge: `ri-attachment-2`, camera: `ri-camera-line`
- connect / test endpoint: `ri-plug-line`

## Transient notifications

Per-operation results (row saved, record deleted) are transient banners, not persistent error state. One helper, one timer per kind, auto-dismiss:

```js
function flash(msg, isError = false) { /* success 3 s, error 5 s */ }
```

Clear the previous timer before setting a new one, and make success and error mutually exclusive — never leave both banners on screen. A failed *load* is different: that stays until retried (see "Error Handling").

## Overlays and modals

Stacked layers use explicit, documented z-indexes so a new layer cannot land underneath an existing one. The explorer's stack:

| Layer | z-index |
|---|---|
| record overlay | 1000 |
| image lightbox | 1100 |
| camera modal | 1200 |
| crop modal | 1300 |

Closing an overlay must release everything it acquired: stop `MediaStream` tracks, revoke object URLs, drop any canvas/crop state, then null the overlay and re-render once.

## Forms

Forms must use controlled values. Each visible field must read from state and write back to state. Do not rely on uncontrolled DOM state.

## Derived fields

Derived fields must be explicit and kept synchronized before save. If a selected value is no longer in the catalog, render it as out-of-list rather than dropping it silently.

---

# CRUD Rules

## Create

1. Initialize a clean draft
2. Populate required derived fields
3. Call `create(payload)`
4. Invalidate affected list cache
5. Navigate back or refresh deterministically

## Update

1. Load the record by id
2. Create a draft copy
3. Save only after explicit user action
4. Call `update(id, payload)`
5. Invalidate affected list cache

## Delete

1. Require explicit confirmation — inline two-button confirm (✓ / ✕) on the row is the pattern here, not `window.confirm()`
2. Call `remove(id)`
3. Refresh or invalidate the list
4. Handle empty page after deletion

Do not perform optimistic deletion: remove the row from the list only *after* the server confirms. Splicing the record out in place (and decrementing the total) once `remove()` resolves, instead of reloading the page, is fine — that is what the explorer does.

---

# Security and Safety

Do not bypass permission checks in the frontend.

Frontend permissions are a UX guard only. Backend authorization remains authoritative.

Do not expose secrets or API keys in generated code.

---

# Specification-Driven Development

Read the relevant spec before modifying a SPA.

```text
AGENTS.md
specs/
  home/                  use-case.md  design.md
  worktable-explorer/    use-case.md  design.md
  endpoints/             use-case.md  design.md
  mcp-checker/           use-case.md  design.md
```

If implementation and specification disagree, report the discrepancy. Do not silently change behavior.

A behavior change lands as a spec change plus a code change in the same step. Adding a SPA means adding `specs/<spa>/` at the same time, not afterwards.

---

# Testing Expectations

For each generated or modified SPA page, verify:

- initial load
- empty list
- paginated list
- sorting / filtering
- create / update / delete permission
- read-only fallback
- create / edit / delete flow
- API failure handling
- retry behavior
- derived field synchronization
- async render safety (no stale re-render after navigation)

Plugin-specific checks:

- the tab appears in `conf/menu.xml`, opens from the tab bar, and `?dashboard=<id>` loads it directly
- landing with no `dashboard` param still lands on `home` (first tab in `menu.xml`)
- every `t()` key the SPA uses is present in both `lang/en.lang.php` and `lang/it.lang.php` **and** in the mount's `$pluginI18n` array — an untranslated key renders as the raw key string
- the page renders without the client: with `WorkTableClient` missing, the guard message shows instead of a blank page
- attachments, when used: badge from `listAttachments()`, thumbnail via blob URL (not `attachmentUrl`), object URLs revoked on close
- the mount emits no output before `add_raw` (no stray whitespace, no closing `?>`), and the top menu still renders — a `$i18n` collision breaks it (see "Naming warning")

---

# Code Style

Prefer readable, explicit code. Avoid excessive abstraction.

Use small helper functions for:
- response normalization (`getRecords`)
- error normalization (`normalizeApiError`)
- user-facing error text (`userFriendlyErrorText`)
- retry logic (`withRetry`)
- i18n (`t`)
- pagination state
- stable sorting
- field derivation

Keep helper behavior deterministic.

---

# Unknowns

Do not invent missing behavior.

If something is unknown:
- mark it as unknown in the specification
- ask for clarification when necessary
- otherwise implement the safest conservative behavior

Safe conservative defaults:
- read-only if permissions are unknown
- empty catalog if distinct lookup fails
- no mutation if record id is missing
- no destructive action without confirmation

---

# Non Goals

Do not introduce without explicit instruction:
- frontend framework migrations
- global state managers
- optimistic sync
- speculative server validation
- hidden API conventions
- unrelated UI redesigns
- large-scale rewrites
