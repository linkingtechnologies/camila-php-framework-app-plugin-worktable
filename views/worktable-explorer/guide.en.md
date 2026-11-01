# Worktable Explorer {#top}

A *technical* tool for looking at and correcting the raw data of any table of the system, row by row: for the corrections the other pages do not offer. It opens from the *Worktable Explorer* tab of this plugin.

Whoever works with the data of an application every day does not need it: every page already has its own tools. Here there is no consistency check, so what you write goes into the table as it is.

*Note*: the interface of this page is currently in Italian only. The labels in bold below are written exactly as the screen shows them, with their meaning next to them.

*Caution*: changing or deleting a row here does not update the other tables that refer to it by name (for example the name of a service in an application that uses it as a key).

## Choose the table {#tabella}

At the top, the drop-down **— Seleziona tabella —** (*Select a table*) lists every table. Until you pick one the page says **Seleziona una tabella per esplorarne i dati.** (*Select a table to explore its data*). Next to it, *10 / pag.* (and 20, 50, 100) sets how many rows you see per page.

**Aggiorna** (*Refresh*) reads the chosen table again.

Under the bar the page shows *Pagina N di N* (*Page N of N*) with the number of records; the box and the arrows change page. The same control is repeated at the bottom.

An empty table, or one without columns, shows **Nessun record trovato.** (*No records found*).

## Sort and filter {#filtri}

- *Sort*: a click on a column header sorts ascending, a second click descending, a third removes the sort;
- *Filter*: the yellow bar under the main bar has three fields: the column, the operator (**contiene** *contains*, **uguale a** *equals*, **inizia con** *starts with*, **finisce con** *ends with*, **è vuoto** *is empty*, **non è vuoto** *is not empty*) and the value (*valore…*). **Applica** (*Apply*, or *Enter*) filters the table; **Rimuovi filtro** (*Remove filter*) goes back to the full list. The active filter is shown next to the buttons.

A filter or a sort brings you back to the first page.

## Look at a record {#record}

A *double click* on a row opens the record card, with the name of the table and the **id** at the top. It shows every field; **id** and **uuid** are **Sola lettura** (*read-only*). From the card:

- **Modifica** (*Edit*) makes the fields editable; **Salva modifiche** (*Save changes*) writes them;
- the bin deletes the record: the card asks **Eliminare questo record? L'operazione è irreversibile.** (*Delete this record? It cannot be undone*) and you confirm with **Elimina**;
- the ✕ closes the card.

## Edit several rows {#modifica}

**Modifica** in the main bar (it becomes **Annulla modifica**, *Cancel editing*) turns the rows into text fields. Change the values you need: for every changed row the button with the floppy disk (**Salva**) lights up and writes only that row (**Record aggiornato.**, *Record updated*). **Annulla modifica** throws away everything you have not saved.

With editing on, **Nuovo** (*New*) also appears: it adds a green row at the top to fill in; the tick creates it (**Record creato.**, *Record created*), the ✕ cancels. The **id** is assigned by the system.

To delete a row press the bin (**Elimina**) and then the tick to confirm, or the ✕ to give up (**Record eliminato.**, *Record deleted*).

The outcome messages appear at the top; errors stay on screen for a few seconds, except the expired-session notice.

## Attachments and ID photos {#allegati}

Every record can have *one attachment*, typically a photo. Rows that have one show a small paperclip. In the record card you see the preview (a click enlarges it) or **Nessun allegato** (*No attachment*). The buttons are:

- *Preview* and *Download attachment*;
- *Delete attachment*, with the question **Eliminare l'allegato?**;
- *Upload attachment* (or *Replace attachment* if there already is one): picks a file;
- *Take a photo with the camera*: opens the device's camera; **Scatta** (*Shoot*) takes the photo, **Annulla** closes. If the camera is not available you see **Fotocamera non disponibile:** with the reason.

When you upload or shoot an **image** the *35×45 mm ID photo* crop opens: drag the frame to the right spot and choose **Ritaglia e carica** (*Crop and upload*); **Carica originale** (*Upload original*) sends the whole photo, **Annulla** gives up (**Fototessera caricata.**, *ID photo uploaded*, when it worked). A file that is not an image is uploaded as it is (**Allegato caricato.**, *Attachment uploaded*).

## If something goes wrong {#problemi}

- If the list of tables cannot be loaded, the drop-down says **Errore caricamento tabelle** (*Error loading tables*);
- if a read or a write fails you see an **Errore** (*Error*) box with the reason;
- if the working session is no longer valid (for example because the same user signed in from another browser), reads and writes fail with the same **Errore** box: sign in again.
