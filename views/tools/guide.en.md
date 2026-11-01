# Tools {#top}

The Python helper scripts that talk to this instance, ready to download, with the instructions to run them. You do not need access to the server's files. The page only lists and serves files: it changes no data.

## The list {#elenco}

For every file the page shows its name, a line saying what it is for and its size. There are the scripts (`.py`) and the settings templates (`.env…`):

- `worktable_client.py` is the client library: it wraps the REST API of this instance. The other scripts import it; it is not run on its own;
- `worktable_sync.py` is an interactive script that copies data from one WorkTable instance to another: one table, every visible table, or the templates. It needs `worktable_client.py` in the same folder;
- `.env.locale.example` is the settings template for a single instance, `.env.sync.example` the one for `worktable_sync.py`.

A file without a description simply shows no line: it is a script added to the folder before its description.

## Download {#scarica}

**Download** on a row saves that file under its own name. **Download all** saves every file in turn: the browser may ask you to allow multiple downloads from this site; the buttons on the rows stay available for the files it skipped.

## How to use them {#uso}

The box **How to use them** gives the three steps:

1. install the dependencies: `pip install requests python-dotenv`;
2. copy the `.env` example next to the scripts, rename it (`.env.locale` or `.env.sync`) and fill in the URLs and the credentials. `WORKTABLE_BASE_URL` must point to the `cf_api.php` of this application: the address is on the [Endpoints](guide:endpoints) page;
3. run the script from that folder: `python worktable_sync.py`.

## If something goes wrong {#problemi}

- *No downloadable scripts found*: the plugin's `tools/` folder is empty or missing. **Download all** stays visible but disabled;
- if the list cannot be loaded, a red message says why (session expired or insufficient permissions, connection problem, server error) and **Retry** tries again;
- a file removed from the server after the list was loaded gives a "not found" from the browser: reload the page to get the current list.
