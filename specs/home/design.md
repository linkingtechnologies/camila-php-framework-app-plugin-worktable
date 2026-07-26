# Home — design

## Structure

Single view, no wizard steps.

```
┌──────────────────────────────────────────┐
│  Welcome title                           │
│  Welcome message                         │
└──────────────────────────────────────────┘
```

## State shape

None. Static content, no state object.

## Tables involved

None.

## Payload

No network calls. `window.APP_CONFIG` is injected (baseUrl / apiKeyHeader*) following this plugin's manual mount pattern, but unused by this view — kept for consistency with the other dashboards and in case this page grows beyond a static welcome message.

## Classification logic

Not applicable.

## Other technical notes

- Placeholder implementation: `app-home.js` renders `home.welcome.title` / `home.welcome.message` from the lang file and nothing else.
- No WorkTableClient calls (no `client.call()` / `client.table()`).
- Formerly, this dashboard id (`m0`/"home") hosted the MCP-endpoint display feature; that moved to the `endpoints` dashboard (see `specs/endpoints/`) when this plugin's real home page is implemented.
