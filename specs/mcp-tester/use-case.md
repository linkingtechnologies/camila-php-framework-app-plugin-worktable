# UC-MCP1 — Test an MCP endpoint's tool catalog

## System context

Standalone diagnostic tool inside the **worktable** plugin. Not part of a larger workflow; does not read or write any WorkTable table.

## Goal

Let an operator paste the URL of any Model Context Protocol (MCP) Streamable HTTP endpoint, optionally supply an `Authorization` header value, and verify that the server initializes correctly and see which tools it exposes — before wiring that endpoint into an agent.

## Primary Actor

CAMILA WorkTable administrator / developer.

## Stakeholders and interests

| Stakeholder | Interest |
|---|---|
| Administrator / developer | Wants to confirm an MCP endpoint is reachable and correctly exposes tools without leaving WorkTable or using an external tool |
| Plugin maintainer | Wants a safe, read-only diagnostic that cannot mutate data and does not leak credentials |

## Preconditions

- User is logged into CAMILA WorkTable with access to the worktable plugin
- User has the URL of an MCP endpoint to test (their own server, or a third party one)

## Postconditions — Success

- Server info (name, version) and protocol version returned by `initialize` are shown
- The list of tools returned by `tools/list` (name, description, input schema) is shown

## Postconditions — Error / Partial failure

- An inline error identifies which step failed (`initialize` or `tools/list`) and the underlying message
- No partial or stale tool list is shown after a failed attempt

## Main Success Scenario

### Step 1 — Enter endpoint

1. User opens the "MCP Checker" tab (the tab's display label; the spec folder and internal naming remain "mcp-tester"); the URL field is pre-filled with this app's own MCP endpoint (same value shown on the Endpoints tab), so testing it requires no typing.
2. User types a different MCP endpoint URL into the URL field, or leaves the pre-filled one.
3. User optionally types a value for the `Authorization` header (e.g. `Bearer <token>`).

### Step 2 — Connect

1. User clicks "Connect".
2. The app performs the MCP `initialize` handshake against the endpoint (via the plugin's backend proxy), sends the `notifications/initialized` notification, then calls `tools/list`.
3. While the sequence runs, the Connect button shows a busy state and is disabled.

### Step 3 — Review results

1. On success, the app shows the server name, version, and negotiated protocol version.
2. The app shows the list of tools: name, description, and input schema (rendered as formatted JSON).

## Extensions

- **2a.** URL field is empty or not a valid `http(s)://` URL → inline validation error, Connect does nothing (no network call made).
- **2b.** The backend proxy cannot reach the target endpoint (network error, timeout, DNS failure, TLS error) → inline error, previous results (if any) are cleared.
- **2c.** The endpoint returns a JSON-RPC error for `initialize` → inline error showing the JSON-RPC code/message, no server info or tools are shown.
- **2d.** The endpoint returns a JSON-RPC error for `tools/list` → inline error showing the JSON-RPC code/message; server info from the successful `initialize` step is still shown.
- **2e.** The endpoint requires authentication and none was supplied, or the supplied value is rejected → surfaces as a normal JSON-RPC / HTTP error from step 2c or 2d (the tool does not special-case auth failures beyond showing the returned message).
- **2f.** The endpoint's `tools/list` returns zero tools → server info is shown, tool list area shows an explicit empty state (not blank).
- **3a.** User edits the URL or Authorization header after a successful or failed connect → all prior results (server info, tools, error) are cleared immediately; nothing is shown until the next "Connect" click.
