# Tools — design

## Structure

Single view, no wizard steps.

```
┌──────────────────────────────────────────────────────────────┐
│  intro text                              [ Download all ]    │  ← spa-title-box
│  (error message + retry, when the listing failed)            │
│  (progress bar, initial load only)                           │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ [i]  .env.locale.example           131 B  [ Download ] │  │
│  │      one-line description                              │  │
│  │ [i]  worktable_sync.py            10.9 kB [ Download ] │  │
│  │      one-line description                              │  │
│  └────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────┘
[ box: how to use them — pip install / .env / run              ]
```

Four columns: icon, name + description, size, action.

## Sizing — deliberate divergence

AGENTS.md recommends `is-small is-narrow` for administrative tables. This one drops
both and uses the default Bulma sizing instead. Those modifiers exist for dense data
grids; this is a four-row list whose rows are the page's primary action, and at
`is-small` the filenames rendered around 11 px, which is what prompted the change.

Measured on the rendered page: filename and description 16 px, intro 20 px, icon
24 px, row height 66 px, buttons 40 px tall.

Three helper classes in `app.css` support the layout — `.table.is-vcentered` for
middle-aligned cells, `.tools-icon-cell` / `.tools-size` / `.tools-action` for the
shrink-to-fit columns, `.tools-name code` for the 1 rem filename.

## State shape

```js
const state = {
  loading: true,   // initial listing in flight
  error:   null,   // { status, message, kind } | null — normalized
  files:   [],     // [{ name: String, size: Number, lang: "python"|"env" }]
};
```

Module-level, mutated in place, every mutation followed by `mount()`. A `cancelled`
flag guards the listing's async callbacks. There is no per-row state: downloading
is the browser's job, not the SPA's.

## Tables involved

None. This SPA calls only the plugin's own endpoints.

| Operation | Call |
|---|---|
| List downloadable files | `client.call("GET", "/worktable/tools")` |
| Download a file | plain `<a href="plugins/worktable/tools/<name>" download>` — no API call |

## Payload

`GET /worktable/tools`

```json
{ "files": [ { "name": "worktable_sync.py", "size": 11183, "lang": "python" } ] }
```

## File descriptions

The one-line description under each filename comes from an explicit map in the SPA,
not from anything read out of the file:

```js
const DESCRIPTIONS = {
  "worktable_client.py": "tools.desc.client",
  "worktable_sync.py":   "tools.desc.sync",
  ".env.locale.example": "tools.desc.envLocale",
  ".env.sync.example":   "tools.desc.envSync",
};
```

The map is keyed by filename and its values are i18n keys, so the text is translated
like everything else. A file with no entry renders without a description rather than
with a placeholder: dropping a new script into `tools/` shows it immediately, and
adding its description is a separate, optional step.

Descriptions state what the file is and, where it matters, how it is used —
`worktable_client.py` is a library that the other scripts import, `worktable_sync.py`
is the runnable one and needs the client beside it.

## Server-side listing

`wt_tools_files()` in `api/handlers.inc.php` decides what the tab advertises:

```php
$real = realpath(__DIR__ . '/../tools');           // resolved once
foreach (scandir($real) as $name) {                 // NOT glob(): dotfiles
    if (!str_ends_with($name, '.py') && !str_ends_with($name, '.example')) continue;
    ...
}
```

- `scandir()` rather than `glob()` because `.env.locale.example` and
  `.env.sync.example` are dotfiles and `glob()` skips names starting with a dot.
- The extension whitelist keeps an operator-created `.env` (no `.example` suffix)
  out of the listing: it matches neither pattern. Note this governs only what the
  tab *advertises* — the web server still serves everything in that directory, so
  a real `.env` must not be kept there. See AGENTS.md, "The plugin directory is
  inside the web root".

## Download mechanism

Each row is a plain anchor at the file's static path:

```js
const TOOLS_PATH = "plugins/worktable/tools/";     // relative to the page
html`<a class="button" href=${TOOLS_PATH + encodeURIComponent(name)} download=${name}>`
```

These files ship with the plugin and its repository is public, so there is nothing
to protect: no API round-trip, no blob, no object URL to revoke. The path is
relative to the document (`cf_app.php` sits in the app directory), which keeps it
correct under a URL prefix — same reasoning as the stylesheet and module tags in
the mount.

This server sends every file in `tools/` as `application/octet-stream` (verified for
all four), so a download is prompted even without the attribute. `download` is kept
anyway: it pins the saved filename, and it makes the behaviour independent of how a
given web server decides to type these extensions.

"Download all" clicks each anchor in turn. The browser may ask the user to allow
multiple downloads for the site; the per-row buttons remain the fallback.

## Other technical notes

- **The listing needs a session, the download does not.** `GET /worktable/tools` is
  a private endpoint, so the tab only renders for a logged-in user; the files
  themselves are public static assets and can be fetched by anyone who knows the
  path. That is deliberate — they are published source. It does mean the listing
  must never advertise anything that is not public.
- **No permissions call.** There is nothing to create, update or delete, so the
  read-only fallback in AGENTS.md does not apply; the endpoints are private, so
  an unauthenticated caller gets a 401/403 which surfaces as `kind: "auth"`.
- **Sizes** are formatted client-side (`B` under 1024, otherwise one decimal `kB`).
- The list is fetched once on mount. A file added to `tools/` on the server shows
  up after a page reload, not automatically.
