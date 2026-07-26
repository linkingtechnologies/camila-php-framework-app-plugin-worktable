// English — worktable plugin
mcp.field.url = MCP endpoint URL
mcp.field.url.placeholder = https://example.com/mcp
mcp.field.authHeader = Authorization header (optional)
mcp.field.authHeader.placeholder = Bearer <token>
mcp.btn.connect = Connect
mcp.btn.connecting = Connecting…
mcp.error.invalidUrl = Enter a valid http:// or https:// URL.
mcp.error.step.initialize = Handshake (initialize) failed: %s
mcp.error.step.tools = Listing tools failed: %s
mcp.serverInfo.title = Server
mcp.serverInfo.name = Name
mcp.serverInfo.version = Version
mcp.serverInfo.protocol = Protocol version
mcp.tools.title = Tools (%s)
mcp.tools.empty = This server does not expose any tools.
mcp.tools.inputSchema = Input schema

endpoints.mcpEndpoint.label = This app's MCP endpoint
endpoints.restApi.label = This app's REST API base URL
endpoints.btn.copy = Copy
endpoints.copied = Copied!
endpoints.copyError = Copy failed — select the text and copy manually.

endpoints.claudeDesktop.title = Connect to Claude Desktop
endpoints.claudeDesktop.step1 = 1. Generate an API token for your CAMILA user: Admin → Users → edit your user → "Set API token".
endpoints.claudeDesktop.step2 = 2. In Claude Desktop, add a custom connector using the MCP endpoint URL above (Settings → Connectors), or add the entry below to your claude_desktop_config.json, replacing <token> with the token from step 1.
endpoints.claudeDesktop.configLabel = Example claude_desktop_config.json entry

endpoints.openai.title = Connect to ChatGPT (Windows app / web)
endpoints.openai.step1 = 1. In ChatGPT: Settings → Security & access → enable Developer mode.
endpoints.openai.step2 = 2. Go to Settings → Plugins, press "+" and paste the MCP endpoint URL above.
endpoints.openai.step3 = 3. In a conversation, select the plugin from the "+" menu, or invoke it with "@".
endpoints.openai.limits = Limits: ChatGPT only connects to remote MCP servers reachable over the network (Streamable HTTP or SSE), using OAuth or no authentication — unlike Claude Desktop, it does not launch local stdio-based servers. The endpoint normally needs to be public and reachable over HTTPS.
endpoints.openai.warnNotPublic = This environment's MCP endpoint is not a public HTTPS address, so ChatGPT will not be able to reach it as-is — publish it behind HTTPS, or expose it through a secure tunnel, first.
endpoints.openai.warnAuth = Unconfirmed: ChatGPT's connector setup is reported to support OAuth or no authentication. This app's MCP endpoint instead requires a custom "X-API-Key" header — verify whether ChatGPT lets you attach that header before relying on this connection.

home.welcome.title = Welcome
home.welcome.message = This is the worktable plugin's landing page.
