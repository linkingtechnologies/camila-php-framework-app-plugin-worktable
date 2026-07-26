# MCP Checker — design

## Structure

Single view, no wizard steps.

```
┌──────────────────────────────────────────┐
│  Connection form                         │
│  [ URL ............................... ]  │
│  [ Authorization header (optional) ... ]  │
│  [ Connect ]                              │
└──────────────────────────────────────────┘
              │ click Connect
              ▼
   initialize  →  notifications/initialized  →  tools/list
     (proxy)         (proxy, fire-and-forget)      (proxy)
              │
   ┌──────────┴──────────┐
   ▼                      ▼
 error                  success
 (inline message,       ┌────────────────────┐
  step-tagged)          │ Server info box     │
                         │ Tools list / empty  │
                         └────────────────────┘
```

## State shape

```js
const state = {
  url: "",            // raw input value, pre-filled with window.APP_CONFIG.mcpDefaultUrl (this app's own MCP endpoint) so the operator can test it with one click
  authHeader: "",      // raw input value, sent verbatim as Authorization header
  connecting: false,   // true while the initialize→list sequence is in flight
  connected: false,    // true once tools/list has succeeded at least once for the current inputs
  error: null,         // { step: 'validate'|'initialize'|'tools', message } | null
  sessionId: null,     // Mcp-Session-Id returned by initialize, if any
  serverInfo: null,    // { name, version } | null
  protocolVersion: null, // string | null
  tools: null,         // array of { name, description, inputSchema } | null
};
```

## Tables involved

None. This SPA does not read or write any WorkTable table.

## Payload

### Frontend → backend proxy

`client.call("POST", "/worktable/mcp-proxy", body)` where `body`:

```js
{
  url: state.url,               // target MCP endpoint, http(s) only
  authHeader: state.authHeader,  // optional, sent as-is in the Authorization header
  sessionId: state.sessionId,    // optional, sent as Mcp-Session-Id header on requests after initialize
  payload: { /* JSON-RPC 2.0 message, see below */ },
}
```

JSON-RPC messages sent, in order:

```js
// 1) initialize
{ jsonrpc: "2.0", id: 1, method: "initialize", params: {
  protocolVersion: "2024-11-05",
  capabilities: {},
  clientInfo: { name: "worktable-mcp-checker", version: "1.0.0" },
}}

// 2) notification — no id, no response body expected
{ jsonrpc: "2.0", method: "notifications/initialized" }

// 3) tools/list
{ jsonrpc: "2.0", id: 2, method: "tools/list", params: {} }
```

### Backend proxy → frontend

The proxy (`POST /mcp-proxy` in `api/handlers.inc.php`, mounted at `/worktable/mcp-proxy`) forwards `payload` as the JSON body of a POST request to `url`, with headers:

```
Content-Type: application/json
Accept: application/json, text/event-stream
Authorization: <authHeader>        (only if non-empty)
Mcp-Session-Id: <sessionId>        (only if non-empty)
```

It replies with:

```js
{
  httpStatus: 200,          // upstream HTTP status
  sessionId: "…" | "",       // Mcp-Session-Id response header, if present
  body: { /* parsed JSON-RPC response */ } | null,
  raw: "…" | null,           // raw response body, only set when body could not be parsed as JSON
}
```

If the proxy itself cannot reach the target (curl failure), it responds with HTTP 502 and `{ error: "proxy_request_failed", message }`. If `url` is missing/invalid or `payload` is missing, it responds with HTTP 400 and `{ error: "invalid_url" | "missing_payload" }`.

The endpoint's `Streamable HTTP` responses may be `text/event-stream` instead of `application/json`; the proxy extracts the last `data:` line and parses it as JSON in that case.

## Classification logic

Not applicable.

## Other technical notes

- **Sequencing**: the three JSON-RPC calls are sent as three separate proxy calls (initialize → notifications/initialized → tools/list), sequentially, awaiting each response before sending the next. The `notifications/initialized` proxy call's response body is ignored (MCP servers reply 202 with no body for notifications).
- **Session id propagation**: if the `initialize` proxy response includes a `sessionId`, it is stored in `state.sessionId` and sent as `Mcp-Session-Id` on the following two proxy calls. Some servers do not use sessions; an empty `sessionId` is valid and simply omits the header.
- **Error normalization**: uses a local, simplified `normalizeApiError(err)` (returns `{ status, message }` only — no `kind` classification, no `userFriendlyErrorText`) for proxy/network-level failures (HTTP-level); `norm.message` is shown as-is. For JSON-RPC-level errors (the proxy call succeeds but `body.error` is present), surface `body.error.message` (and `body.error.code` if present) directly — these are the server's own diagnostic text and more useful to the operator than a generic mapping.
- **Async render safety**: use the `cancelled` flag pattern. If the user edits `url` or `authHeader` while a connect sequence is in flight, set `cancelled = true` for that sequence so its late responses do not overwrite the newly-reset state.
- **Context reset**: editing `url` or `authHeader` resets `connected`, `error`, `sessionId`, `serverInfo`, `protocolVersion`, `tools` to their initial values (UC-MCPCHECKER1 extension 3a). It does not reset the field being edited.
- **No WorkTableClient table operations**: this SPA only uses `client.call()` (custom plugin endpoint), never `client.table(...)`.
- **Security**: the backend proxy performs a plain outbound HTTP(S) request with `CURLOPT_SSL_VERIFYPEER` enabled; it does not log the `authHeader` value. The frontend never persists `url`/`authHeader` (no localStorage), consistent with "do not expose secrets in generated code".
