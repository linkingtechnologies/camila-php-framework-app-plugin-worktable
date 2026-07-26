# UC-ENDPOINTS1 — Show this app's API endpoints and how to connect a client

## System context

SPA of the **worktable** plugin (tab "Endpoints", dashboard id `endpoints`). Formerly the plugin's default landing tab (`m0` / "AI SANDBOX"); the default landing tab is now the separate "home" dashboard (`specs/home/`). Does not read or write any WorkTable table.

## Goal

Let an operator immediately see the two addresses *this* CAMILA WorkTable instance exposes — the MCP (Model Context Protocol) endpoint and the plain REST API base URL — copy either with one click, and follow instructions to connect the MCP endpoint to an external AI client (Claude Desktop and ChatGPT today; other clients as they get documented).

## Primary Actor

CAMILA WorkTable administrator / developer.

## Stakeholders and interests

| Stakeholder | Interest |
|---|---|
| Administrator / developer | Wants the exact MCP and REST API URLs for this environment without reconstructing them by hand, and a working recipe to wire the MCP endpoint into an AI client |
| Plugin maintainer | Wants a single obvious, read-only place that cannot be mistaken for a form that mutates data |

## Preconditions

- User is logged into CAMILA WorkTable with access to the worktable plugin.
- To actually connect an external client (not just view the URLs): an admin has generated an API token for the CAMILA user the client should authenticate as (Admin → Users → "Set API token").

## Postconditions — Success

- The MCP endpoint URL and the REST API base URL for the current environment are each displayed in full and can be copied with one click.
- A ready-to-adapt `claude_desktop_config.json` snippet is shown, using the real MCP URL for this environment and a `<token>` placeholder for the API key.

## Postconditions — Error / Partial failure

- If a copy action fails (e.g. clipboard permission denied by the browser), the corresponding field is still visible and selectable/copyable by hand; the other fields on the page are unaffected.

## Main Success Scenario

### Step 1 — Open the Endpoints tab

1. User opens the "Endpoints" tab.
2. The page shows the MCP endpoint URL and the REST API base URL, each in its own read-only field.

### Step 2 — Copy an endpoint

1. User clicks "Copy" next to either URL.
2. That URL is copied to the clipboard and a brief confirmation is shown next to that field only.

### Step 3 — Connect Claude Desktop

1. User generates an API token for their CAMILA user in Admin → Users (outside this page).
2. User either adds a custom connector in Claude Desktop using the MCP URL from Step 1, or copies the provided `claude_desktop_config.json` snippet and replaces `<token>` with the token from step 3.1.

### Step 4 — Connect ChatGPT (Windows app / web)

1. User enables Developer mode in ChatGPT (Settings → Security & access).
2. User goes to Settings → Plugins, presses "+" and pastes the MCP endpoint URL from Step 1.
3. User invokes the plugin in a conversation via the "+" menu or "@".
4. If the MCP URL for this environment isn't a public HTTPS address, an inline warning explains ChatGPT won't be able to reach it and suggests publishing it behind HTTPS or a secure tunnel.
5. A second, always-shown warning notes that ChatGPT's connector auth (OAuth or none, per available information) may not support this app's `X-API-Key` header requirement — unconfirmed either way.

## Extensions

- **2a.** The browser denies clipboard access (e.g. insecure context, permission denied) → an inline error replaces the confirmation for that field; the URL remains visible and selectable for manual copy.
- **3a.** User has no API token yet → the "Connect to Claude Desktop" section's step 1 tells them where to generate one; this page cannot generate or display an existing token itself (tokens are shown once, at generation time, in the Admin Users screen).

## Known gaps

- The "MCP Checker" tab (spec folder: `specs/mcp-checker/`) lets an operator supply an arbitrary `Authorization` header when testing any MCP endpoint. This app's own MCP endpoint actually authenticates via `X-API-Key`, not `Authorization` — so testing this app's own endpoint through the MCP Checker as-is will not authenticate correctly. Not addressed here; flagged for a future fix.
- ChatGPT's connector setup is reported to support OAuth or no authentication; whether it also lets you attach a custom `X-API-Key` header (needed for this app's endpoint) is unconfirmed — the page warns about this rather than asserting either way. The ChatGPT steps themselves are based on information supplied by the plugin maintainer, not independently verified against OpenAI's own documentation.
