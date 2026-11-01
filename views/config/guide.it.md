# Configurazione {#top}

La pagina per la *cartella dati dell'istanza*: la cartella che contiene il database, i file caricati, i log e la configurazione generata. Di base sta in un percorso che chiunque può indovinare (`var`). La pagina la rinomina con un nome non indovinabile (`var-` seguito da 32 lettere e cifre) e lascia al vecchio percorso uno *stub* di una riga, così non serve modificare nessun altro file.

È **riservata agli amministratori**: per chiunque altro ogni richiesta viene rifiutata e la pagina lo dice.

## Cosa mostra la pagina {#stato}

La tabella in alto descrive la situazione di adesso:

- **Cartella attuale**: il nome della cartella adesso;
- **Nome ruotato**: se porta già la parte casuale (*Sì, contiene un GUID*) o ha ancora il nome predefinito;
- **Cartella dati via HTTP**: la pagina chiede al web server, senza sessione, un file della cartella dati e riporta *servita senza autenticazione* (male: chi ne conosce il percorso può scaricarla), *non raggiungibile* (bene) oppure *non determinabile* (non c'è un file statico da provare, cosa normale quando i dati stanno in un server di database);
- **Database in uso**: il tipo di database;
- **Stub di compatibilità**: il file lasciato al vecchio percorso dopo una rotazione, oppure *Nessuno* se la cartella è ancora dov'era;
- **Ultima esecuzione in background**: l'esito dell'ultima rotazione fatta in background, se c'è stata.

Sotto, **Verifiche preventive** elenca quattro condizioni con una spunta o una croce: la cartella dati esiste, la cartella superiore è scrivibile, il nuovo nome è libero, lo stub si può scrivere. Se una non passa, non viene modificato nulla.

Compare un avviso giallo quando il database SQLite sta dentro la cartella da rinominare: il web server tiene aperto quel file e su Windows di norma questo impedisce il rename, quindi il pulsante con ogni probabilità fallirà senza spostare niente.

## La soluzione vera: la regola nginx {#nginx}

Il riquadro **La soluzione vera: negare la cartella in nginx** conta più della rotazione. Un nome casuale nasconde solo il percorso, non controlla l'accesso; e il file `.htaccess` dentro la cartella vale solo per Apache. Il riquadro mostra la regola da aggiungere al server block di nginx perché i dati non vengano mai serviti, comunque si chiami la cartella. **Copia** la copia. La sua prima riga dice cosa ha trovato la verifica qui sopra.

## Ruotare il nome {#ruotare}

1. Premi **Ruota il nome**. La pagina mostra **Conferma la rotazione**: il nome vecchio e quello nuovo, e il promemoria che resta uno stub e che, se un passaggio successivo fallisce, tutto viene rimesso a posto da solo;
2. premi **Sì, rinomina**, oppure **Annulla** per rinunciare;
3. se va bene la pagina dice *Fatto: la cartella dati ora è …* e chiede di premere **Ricarica la pagina**: la pagina aperta ha ancora in memoria i vecchi percorsi e non va usata prima di ricaricarla.

Quando il nome è già ruotato il pulsante dice **Ruota di nuovo** e propone un nuovo nome casuale.

## Se il rename è bloccato {#bloccato}

Quando un file dentro la cartella è aperto, il rename non può riuscire dalla pagina. Non viene spostato nulla, e la pagina offre due strade.

*Senza fermare il web server*: **Esegui in background** affida il lavoro a un processo separato che continua a riprovare e ce la fa appena termina la richiesta di questa pagina, di norma in un paio di secondi. Il riquadro **Rotazione in corso in background** nomina la cartella che sta aspettando e la pagina controlla da sola: lasciala stare per qualche secondo. Alla fine mostra il nuovo nome e chiede di ricaricare. Se dopo circa quaranta secondi non ha risposto, la pagina lo dice: premi **Riprova** per rileggere lo stato, oppure usa la riga di comando.

*Da riga di comando*: il riquadro **Come completarla da riga di comando** dà i tre passi: fermare il web server; aprire un terminale sul server ed eseguire il comando mostrato (**Copia** lo copia); riavviare il web server e ricaricare la pagina. Aggiungi `--dry-run` al comando per vedere il piano senza modificare nulla.

## Quando va storto altro {#problemi}

- *Il piano non è più valido*: la cartella è cambiata nel frattempo. La pagina ha già riletto lo stato: controllalo e riprova;
- *Una verifica preventiva non passa più*: non è stato modificato nulla;
- *Il rename è riuscito ma un passaggio successivo è fallito*: è stato rimesso tutto a posto e non si è perso niente;
- un errore di connessione o del server arriva con **Riprova**.

La rotazione si può annullare rinominando la cartella con il suo nome predefinito.
