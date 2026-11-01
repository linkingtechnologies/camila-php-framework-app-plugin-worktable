# Endpoints {#top}

Gli indirizzi che servono ad altri programmi per parlare con questa istanza, e i passi per collegarle due assistenti di intelligenza artificiale. La pagina mostra solo informazioni: non cambia nulla.

## I due indirizzi {#indirizzi}

In alto ci sono due campi di sola lettura:

- **Endpoint MCP di questa app**: l'indirizzo del server MCP di questa istanza. È quello da dare a un assistente;
- **URL base delle API REST di questa app**: l'indirizzo di base delle API REST, cioè il `cf_api.php` di questa applicazione. Serve agli script della pagina [Tools](guide:tools).

Un clic in un campo ne seleziona tutto il testo. **Copia** lo mette negli appunti e la pagina conferma con **Copiato!**. Se il browser rifiuta, la pagina dice di selezionare il testo e copiarlo a mano.

## Collegare Claude Desktop {#claude}

Il riquadro **Collega Claude Desktop** dà i due passi:

1. generare un token API per il proprio utente (nell'area di amministrazione: *Utenti*, modifica del proprio utente, *Set API token*);
2. in Claude Desktop aggiungere un connettore personalizzato con l'endpoint MCP qui sopra, oppure aggiungere a `claude_desktop_config.json` la voce mostrata sotto **Esempio di voce in claude_desktop_config.json**, sostituendo `<token>` con il token del passo 1.

L'esempio contiene già l'indirizzo di questa istanza. **Copia** lo copia.

## Collegare ChatGPT {#chatgpt}

Il riquadro **Collega ChatGPT (app Windows / web)** elenca i tre passi in ChatGPT e, sotto, due avvisi gialli:

- se l'endpoint MCP non è un indirizzo HTTPS pubblico (per esempio quando si lavora su `localhost`) ChatGPT non può raggiungerlo: va pubblicato dietro HTTPS o esposto con un tunnel sicuro;
- questa istanza chiede un header personalizzato `X-API-Key`, e non è confermato che ChatGPT permetta di aggiungerne uno: verificalo prima di contare sul collegamento.

A differenza di Claude Desktop, ChatGPT si collega solo a server remoti: non avvia programmi locali.

La pagina [MCP Checker](guide:mcp-checker) permette di provare l'endpoint prima di collegarci un assistente.
