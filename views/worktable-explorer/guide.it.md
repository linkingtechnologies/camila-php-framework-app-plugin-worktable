# Worktable Explorer {#top}

Uno strumento *tecnico* per guardare e correggere i dati grezzi di qualunque tabella
del sistema, riga per riga: per le correzioni che le altre pagine non prevedono. Si
apre dal tab *Worktable Explorer* di questo plugin.

Chi lavora ogni giorno con i dati di un'applicazione non ne ha bisogno: ogni pagina
ha già i suoi strumenti. Qui non c'è nessun controllo di coerenza, perciò ciò che
scrivi finisce nella tabella così com'è.

*Nota*: l'interfaccia di questa pagina è per ora solo in italiano.

*Cautela*: modificare o eliminare una riga qui non aggiorna le altre tabelle che la
referenziano per nome (per esempio il nome di un servizio in un'applicazione che lo
usa come chiave).

## Scegliere la tabella {#tabella}

In alto, la tendina **— Seleziona tabella —** elenca tutte le tabelle. Finché non ne
scegli una vedi **Seleziona una tabella per esplorarne i dati.** Accanto, *10 / pag.*
(e 20, 50, 100) decide quante righe vedere per pagina.

**Aggiorna** rilegge i dati della tabella scelta.

Sotto la barra compare l'indicazione *Pagina N di N*, con il numero dei record; la
casella e le frecce cambiano pagina. Lo stesso comando è ripetuto in fondo.

Una tabella vuota o senza colonne mostra **Nessun record trovato.**

## Ordinare e filtrare {#filtri}

- *Ordinare*: un clic sull'intestazione di una colonna ordina in modo crescente, un
  secondo clic in modo decrescente, un terzo toglie l'ordine.
- *Filtrare*: la barra gialla sotto la barra principale ha tre campi: la colonna,
  l'operatore (**contiene**, **uguale a**, **inizia con**, **finisce con**, **è
  vuoto**, **non è vuoto**) e il valore (*valore…*). **Applica** (o *Invio*) filtra la
  tabella; **Rimuovi filtro** torna all'elenco completo. Il filtro attivo è ricordato
  accanto ai pulsanti.

Filtro e ordine riportano alla prima pagina.

## Guardare un record {#record}

Con un *doppio clic* su una riga si apre la scheda del record, con il nome della
tabella e l'**id** in alto. Mostra tutti i campi; **id** e **uuid** sono in **Sola
lettura**. Dalla scheda:

- **Modifica** rende i campi modificabili; **Salva modifiche** li scrive;
- il cestino elimina il record: la scheda chiede **Eliminare questo record?
  L'operazione è irreversibile.** e conferma con **Elimina**;
- la ✕ chiude la scheda.

## Modificare più righe {#modifica}

**Modifica** nella barra principale (diventa **Annulla modifica**) trasforma le righe
in campi di testo. Cambia i valori che servono: per ogni riga modificata si attiva il
pulsante con il dischetto (**Salva**), che scrive solo quella riga (**Record
aggiornato.**). **Annulla modifica** scarta tutto quello che non hai salvato.

Con la modifica attiva compare anche **Nuovo**, che aggiunge in testa una riga verde da
compilare: la spunta la crea (**Record creato.**), la ✕ annulla. L'**id** lo assegna il
sistema.

Per eliminare una riga premi il cestino (**Elimina**) e poi la spunta per
confermare, oppure la ✕ per rinunciare (**Record eliminato.**).

I messaggi di esito compaiono in alto; gli errori restano a schermo per qualche
secondo, tranne l'avviso di sessione scaduta.

## Allegati e fototessere {#allegati}

Ogni record può avere **un allegato**, tipicamente una foto. Le righe che ne hanno uno
mostrano una piccola graffetta. Nella scheda del record vedi l'anteprima (un clic la
ingrandisce) oppure **Nessun allegato**. I pulsanti sono:

- *Anteprima* e *Scarica allegato*;
- *Elimina allegato*, con la domanda **Eliminare l'allegato?**;
- *Carica allegato* (o *Sostituisci allegato* se già c'è): sceglie un file;
- *Scatta foto con fotocamera*: apre la fotocamera del dispositivo; **Scatta** fa la foto,
  **Annulla** chiude. Se la fotocamera non è disponibile vedi **Fotocamera non
  disponibile:** con il motivo.

Quando carichi o scatti una **immagine** si apre il ritaglio **fototessera 35×45 mm**:
trascina il riquadro nel punto giusto e scegli **Ritaglia e carica**; **Carica originale**
manda la foto intera, **Annulla** rinuncia (**Fototessera caricata.** a operazione
riuscita). Un file che non è un'immagine viene caricato così com'è (**Allegato
caricato.**).

## Se qualcosa non va {#problemi}

- Se non riesce l'elenco delle tabelle, la tendina dice **Errore caricamento tabelle**.
- Se non riesce una lettura o una scrittura vedi un riquadro **Errore** con il motivo.
- Se la sessione di lavoro non è più valida (per esempio perché lo stesso utente è entrato da
  un altro browser), letture e scritture falliscono con lo stesso riquadro **Errore**: rifai
  l'accesso.
