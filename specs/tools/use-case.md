# UC-TOOLS1 — Download the plugin's Python helper scripts

## System context

SPA of the **worktable** plugin (tab "Tools", dashboard id `tools`). Reads no WorkTable table: it serves files from the plugin's own `tools/` directory through the plugin API.

## Goal

Let an administrator obtain the Python helper scripts that talk to this WorkTable instance (`worktable_client.py`, `worktable_sync.py`) and their `.env` templates, without needing filesystem access to the server, and with the instructions to run them on the same page.

## Primary Actor

CAMILA WorkTable administrator / developer.

## Stakeholders and interests

| Stakeholder | Interest |
|---|---|
| Administrator / developer | Wants the current version of the scripts on their own machine, matching the instance they are pointing them at |
| Plugin maintainer | Wants one obvious source for the scripts, so copies do not drift from what ships with the plugin |
| Security owner | Wants the tab to advertise only files that are public by nature, so no operator-created `.env` is ever listed |

## Preconditions

- User is logged into CAMILA WorkTable with access to the worktable plugin.
- The plugin's `tools/` directory exists and contains at least one `.py` or `.example` file.

## Postconditions — Success

- The chosen file is saved by the browser under its original name, byte-identical to the copy on the server.

## Postconditions — Error / Partial failure

- A failed listing leaves the page usable with an error message and a retry button.
- A failed download is handled by the browser as for any other link; the page state is unaffected.

## Main Success Scenario

### Step 1 — Open the Tools tab

1. User opens the "Tools" tab.
2. The page lists every downloadable file with its name, a one-line description of what it does, and its size — `.py` scripts and `.env` templates together.
3. Below the list the page shows the dependency install command, how to prepare the `.env` file, and how to run the script.

### Step 2 — Download one file

1. User presses "Download" on a row.
2. The browser saves the file under its original name.

### Step 3 — Download every file

1. User presses "Download all".
2. The browser saves each file in turn.

## Extensions

**1a'. A listed file has no description**
The row renders with name and size only. This is the expected state for a script added to `tools/` before its description key exists.

**1a. `tools/` is empty or missing**
The list is replaced by an empty-state message; "Download all" is disabled but still visible.

**1b. Listing fails**
An error message explains the failure and offers a retry button; the rest of the page still renders.

**2a. File disappeared between listing and download**
The web server answers 404 and the browser reports it; the page state is unaffected. Reloading the tab refreshes the list.

**3a. The browser blocks repeated downloads**
Files after the first may require the user to allow multiple downloads for this site. The per-row buttons remain available for the ones that were skipped.
