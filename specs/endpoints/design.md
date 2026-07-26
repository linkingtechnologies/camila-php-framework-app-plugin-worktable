# Endpoints — design

## Structure

Single view, no wizard steps.

```
┌──────────────────────────────────────────┐
│  MCP endpoint box                        │
│  [ https://host/app/<app>/cf_api.php?mcp=1 ] [ Copy ]  │
│  (copied! / copy failed, transient)      │
│                                           │
│  REST API base URL box                   │
│  [ https://host/app/<app>/cf_api.php ] [ Copy ]        │
│  (copied! / copy failed, transient)      │
└──────────────────────────────────────────┘
┌──────────────────────────────────────────┐
│  Connect to Claude Desktop                │
│  step 1 (generate API token) / step 2     │
│  claude_desktop_config.json snippet [Copy]│
└──────────────────────────────────────────┘
┌──────────────────────────────────────────┐
│  Connect to ChatGPT (Windows app / web)   │
│  step 1..3 / limits note                  │
│  [warning: not a public HTTPS URL] (cond.)│
│  [warning: auth mechanism unconfirmed]    │
└──────────────────────────────────────────┘
```

## State shape

```js
const state = {
  mcpUrl:     "",   // from window.APP_CONFIG.mcpDefaultUrl, read-only
  restApiUrl: "",   // from window.APP_CONFIG.baseUrl, read-only
  copy: {
    mcp:          null,  // null | 'copied' | 'error'
    restApi:      null,  // null | 'copied' | 'error'
    claudeConfig: null,  // null | 'copied' | 'error'
  },
};
```

Each `copy.<field>` is reset to `null` after a short timeout (2s), independently per field (separate `setTimeout` handles keyed by field name) — copying one box does not affect the confirmation state of the others.

## Tables involved

None.

## Payload

No network calls. `state.mcpUrl` / `state.restApiUrl` come entirely from `window.APP_CONFIG.mcpDefaultUrl` / `window.APP_CONFIG.baseUrl`, injected server-side by `dashboard-endpoints.inc.php`. `mcpDefaultUrl` uses the same computation as the MCP Tester's default (`$scheme://$host/app/{CAMILA_APP_DIR}/cf_api.php?mcp=1`); `baseUrl` is the same value without `?mcp=1`.

The Claude Desktop config snippet is generated client-side (not injected by PHP):

```js
JSON.stringify({
  mcpServers: {
    camila: { type: "http", url: state.mcpUrl, headers: { "X-API-Key": "<token>" } },
  },
}, null, 2)
```

`<token>` is always a literal placeholder — this page never has access to any specific user's real API token (see "Other technical notes").

## Classification logic

Not applicable.

## Other technical notes

- Copy actions use `navigator.clipboard.writeText(value)`; on rejection (promise rejects), set `copy.<field> = 'error'` rather than throwing. Same pattern for all three copyable fields (MCP URL, REST API URL, Claude config snippet), via one shared `copyField(field, value)` helper.
- Both URL fields are read-only `<input>` (not buttons) so the user can also select-and-copy manually if the Clipboard API is unavailable.
- **Auth model, verified against `camila/api/cf_mcp_handler.inc.php`**: this app's MCP endpoint authenticates callers via an `X-API-Key: <token>` header, where `<token>` matches the `token` column of the CAMILA users table (`apiKeyDbAuth`) — **not** the `Authorization` header/cookie-session scheme the app's own SPAs use (`apiKeyHeaderName: "Authorization"` in `APP_CONFIG` is for `WorkTableClient`'s own calls, unrelated to the MCP endpoint's auth). A token is generated per-user in CAMILA admin (Users → edit user → "Set API token"); it is shown once at generation time and not otherwise recoverable, so this page cannot look it up or pre-fill it — hence the `<token>` placeholder in the config snippet.
- **Divergence from MCP Tester's own header field**: the MCP Tester SPA (`specs/mcp-tester/`) lets the operator supply an arbitrary `Authorization` header value when testing *any* MCP endpoint. Testing *this app's own* MCP endpoint through that tool therefore will not authenticate correctly as-is, since the server expects `X-API-Key`, not `Authorization` — known gap, not fixed as part of this page.
- No WorkTableClient table/call operations: this view is pure display of server-injected config plus static instructional content.
- **ChatGPT (Windows app / web)**: steps are Settings → Security & access → enable Developer mode → Settings → Plugins → "+" → paste the MCP URL → invoke via the "+" menu or "@" in a conversation. Per information supplied by the plugin maintainer (not independently verified against OpenAI's own docs, which were not accessible at the time this was written): ChatGPT only connects to *remote* MCP servers (Streamable HTTP or SSE, not local `stdio` like Claude Desktop) and its connector setup is reported to support OAuth or no authentication.
- **`isPublicHttpsUrl(url)`**: a client-side check (`https:` protocol, hostname not `localhost`/`127.*`/`::1`/`0.0.0.0`) gates a conditional warning (`endpoints.openai.warnNotPublic`) — shown only when `state.mcpUrl` doesn't look like a publicly reachable HTTPS address, since ChatGPT (per the note above) needs the endpoint to be public HTTPS, while this same URL is perfectly fine as-is for the MCP Tester/Claude Desktop use cases on this page.
- **Auth compatibility warning (`endpoints.openai.warnAuth`), always shown**: this app's MCP endpoint requires a custom `X-API-Key` header (see above), while ChatGPT's connector setup is reported to support only OAuth or no authentication — it's unconfirmed whether ChatGPT's UI lets you attach a custom header the way Claude Desktop's JSON config does. Flagged rather than asserted either way, since this wasn't verified against OpenAI's own documentation.
