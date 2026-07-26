// Italiano — plugin worktable
mcpChecker.field.url = URL endpoint MCP
mcpChecker.field.url.placeholder = https://example.com/mcp
mcpChecker.field.authHeader = Header Authorization (opzionale)
mcpChecker.field.authHeader.placeholder = Bearer <token>
mcpChecker.btn.connect = Connetti
mcpChecker.btn.connecting = Connessione…
mcpChecker.error.invalidUrl = Inserisci un URL http:// o https:// valido.
mcpChecker.error.step.initialize = Handshake (initialize) fallito: %s
mcpChecker.error.step.tools = Recupero strumenti fallito: %s
mcpChecker.serverInfo.title = Server
mcpChecker.serverInfo.name = Nome
mcpChecker.serverInfo.version = Versione
mcpChecker.serverInfo.protocol = Versione protocollo
mcpChecker.tools.title = Strumenti (%s)
mcpChecker.tools.empty = Questo server non espone alcuno strumento.
mcpChecker.tools.inputSchema = Schema input

endpoints.mcpEndpoint.label = Endpoint MCP di questa app
endpoints.restApi.label = URL base delle API REST di questa app
endpoints.btn.copy = Copia
endpoints.copied = Copiato!
endpoints.copyError = Copia fallita — seleziona il testo e copia manualmente.

endpoints.claudeDesktop.title = Collega Claude Desktop
endpoints.claudeDesktop.step1 = 1. Genera un token API per il tuo utente CAMILA: Admin → Utenti → modifica il tuo utente → "Set API token".
endpoints.claudeDesktop.step2 = 2. In Claude Desktop, aggiungi un connettore personalizzato usando l'URL dell'endpoint MCP qui sopra (Impostazioni → Connettori), oppure aggiungi la voce qui sotto al tuo claude_desktop_config.json, sostituendo <token> con quello ottenuto al passo 1.
endpoints.claudeDesktop.configLabel = Esempio di voce in claude_desktop_config.json

endpoints.openai.title = Collega ChatGPT (app Windows / web)
endpoints.openai.step1 = 1. In ChatGPT: Impostazioni → Sicurezza e accesso → attiva Modalità sviluppatore.
endpoints.openai.step2 = 2. Vai in Impostazioni → Plugin, premi "+" e incolla l'URL dell'endpoint MCP qui sopra.
endpoints.openai.step3 = 3. Nella conversazione, seleziona il plugin dal menu "+" oppure richiamalo con "@".
endpoints.openai.limits = Limiti: ChatGPT si collega solo a server MCP remoti raggiungibili in rete (Streamable HTTP o SSE), con autenticazione OAuth o nessuna autenticazione — a differenza di Claude Desktop, non avvia server locali stdio. L'endpoint deve normalmente essere pubblico e raggiungibile in HTTPS.
endpoints.openai.warnNotPublic = L'endpoint MCP di questo ambiente non è un indirizzo HTTPS pubblico, quindi ChatGPT non potrà raggiungerlo così com'è — pubblicalo dietro HTTPS, oppure esponilo tramite un tunnel sicuro, prima di procedere.
endpoints.openai.warnAuth = Da verificare: la configurazione dei connettori di ChatGPT supporterebbe OAuth o nessuna autenticazione. L'endpoint MCP di questa app richiede invece un header personalizzato "X-API-Key" — verifica se ChatGPT permette di aggiungere quell'header prima di affidarti a questa connessione.

home.welcome.title = Benvenuto
home.welcome.message = Questa è la pagina iniziale del plugin worktable.
