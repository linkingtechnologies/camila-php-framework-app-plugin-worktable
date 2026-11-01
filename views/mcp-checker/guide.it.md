# MCP Checker {#top}

Uno strumento di diagnosi: gli dai l'indirizzo di un server MCP (Model Context Protocol, il modo in cui gli assistenti di intelligenza artificiale raggiungono strumenti esterni) e ti dice se il server risponde e quali strumenti offre. È di sola lettura: non può cambiare nessun dato. Usalo prima di collegare un assistente a un endpoint.

## Inserire l'endpoint {#endpoint}

La pagina si apre con il primo campo già compilato con l'endpoint MCP di questa istanza (lo stesso mostrato in [Endpoints](guide:endpoints)), quindi provare il proprio server non richiede di scrivere nulla.

- **URL endpoint MCP**: l'indirizzo da provare, che inizia con `http://` o `https://`. Sostituiscilo per provare un altro server;
- **Header Authorization (opzionale)**: il valore dell'header `Authorization`, per esempio `Bearer <token>`. Lascialo vuoto se il server non ne chiede uno.

Se cambi uno dei due campi dopo una prova, i risultati precedenti spariscono subito: non si vede nulla finché non premi di nuovo il pulsante.

## Connettersi {#connetti}

**Connetti** esegue la prova: la stretta di mano con il server, poi la richiesta dei suoi strumenti. Finché lavora il pulsante dice **Connessione…** ed è disattivato. Le richieste passano dal server del plugin, quindi non serve che il browser raggiunga l'endpoint.

Se l'indirizzo è vuoto o non è un indirizzo `http://` o `https://`, la pagina dice **Inserisci un URL http:// o https:// valido.** e non invia nulla.

## Leggere il risultato {#risultato}

Se va bene il riquadro **Server** mostra il **Nome**, la **Versione** e la **Versione protocollo** dichiarati dal server. Sotto, **Strumenti (N)** elenca gli strumenti uno per uno con la descrizione e, sotto **Schema input**, i parametri che ciascuno accetta.

Se il server non ha strumenti la pagina dice **Questo server non espone alcuno strumento.**

## Quando non funziona {#errori}

Un messaggio rosso dice quale passo è fallito e perché:

- *Handshake (initialize) fallito*: il server non è stato raggiunto, oppure ha rifiutato la stretta di mano. Non si vedono né le informazioni del server né gli strumenti;
- *Recupero strumenti fallito*: la stretta di mano è riuscita ma l'elenco no. Le informazioni del server restano a schermo.

Un server che chiede autenticazione e non la riceve, o riceve un valore sbagliato, si presenta come uno di questi due errori, con il messaggio che il server ha restituito.
