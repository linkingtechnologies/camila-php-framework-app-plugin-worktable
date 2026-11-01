# Strumenti {#top}

Gli script Python di supporto che parlano con questa istanza, pronti da scaricare, con le istruzioni per usarli. Non serve avere accesso ai file del server. La pagina elenca e distribuisce solo file: non cambia nessun dato.

## L'elenco {#elenco}

Per ogni file la pagina mostra il nome, una riga che dice a cosa serve e la dimensione. Ci sono gli script (`.py`) e i modelli di configurazione (`.env…`):

- `worktable_client.py` è la libreria client: incapsula le API REST di questa istanza. Gli altri script la importano; non si esegue da sola;
- `worktable_sync.py` è uno script interattivo che copia i dati da un'istanza WorkTable a un'altra: una tabella, tutte le tabelle visibili, oppure i template. Richiede `worktable_client.py` nella stessa cartella;
- `.env.locale.example` è il modello di configurazione per una sola istanza, `.env.sync.example` quello per `worktable_sync.py`.

Un file senza descrizione non mostra la riga: è uno script aggiunto alla cartella prima della sua descrizione.

## Scaricare {#scarica}

**Scarica** su una riga salva quel file con il suo nome. **Scarica tutti** salva ogni file uno dopo l'altro: il browser può chiedere di consentire più download da questo sito; i pulsanti sulle righe restano disponibili per i file che ha saltato.

## Come si usano {#uso}

Il riquadro **Come si usano** dà i tre passi:

1. installare le dipendenze: `pip install requests python-dotenv`;
2. copiare l'esempio `.env` accanto agli script, rinominarlo (`.env.locale` oppure `.env.sync`) e compilare URL e credenziali. `WORKTABLE_BASE_URL` deve puntare al `cf_api.php` di questa applicazione: l'indirizzo è nella pagina [Endpoints](guide:endpoints);
3. eseguire lo script da quella cartella: `python worktable_sync.py`.

## Se qualcosa non va {#problemi}

- *Nessuno script scaricabile trovato*: la cartella `tools/` del plugin è vuota o manca. **Scarica tutti** resta visibile ma disattivato;
- se l'elenco non si carica, un messaggio rosso dice perché (sessione scaduta o permessi insufficienti, problema di connessione, errore del server) e **Riprova** ci riprova;
- un file tolto dal server dopo il caricamento dell'elenco dà un «non trovato» dal browser: ricarica la pagina per avere l'elenco aggiornato.
