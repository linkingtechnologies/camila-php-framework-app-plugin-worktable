# UC-CONFIG1 — Rotate the instance data directory name

## System context

SPA of the **worktable** plugin (tab "Configuration", dashboard id `config`). Operates on `CAMILA_VAR_ROOTDIR` — the directory holding the SQLite database, uploaded files, logs, templates and generated worktable configuration — not on any WorkTable table.

## Goal

Let an administrator rename the instance data directory to an unguessable name (`<base>-<guid>`) and repeat that rename later, so the directory stops sitting at a path anyone can guess. The operation leaves a one-line compatibility stub at the old path, so no other file has to be edited, and it can be undone by renaming the directory back.

## Primary Actor

CAMILA WorkTable administrator.

## Stakeholders and interests

| Stakeholder | Interest |
|---|---|
| Administrator | Wants the data directory off a guessable path without editing framework files or risking an app that no longer boots |
| Security owner | Wants the database to stop being downloadable over plain HTTP, and wants the operation restricted to administrators |
| Plugin maintainer | Wants the rename to be reversible and to leave the framework untouched, so a framework update cannot undo it |

## Preconditions

- User is logged into CAMILA WorkTable as an administrator (`$_CAMILA['adm_user_group'] === CAMILA_ADM_USER_GROUP`).
- The data directory exists and its parent is writable by the web server user.

## Postconditions — Success

- The data directory is named `<base>-<32 hex characters>`.
- A file `<base>/config.php` exists at the old path containing a single `require` of the renamed directory's `config.php`.
- Every `CAMILA_*` path constant resolves into the renamed directory on the next request, because they all derive from `dirname(__FILE__)`.
- The database is no longer reachable at its previous URL.

## Postconditions — Error / Partial failure

- If the rename itself fails, nothing has moved: the directory, the database and the stub are exactly as before.
- If the rename succeeds but a later step fails, the directory is renamed back and any stub directory created by the attempt is removed.
- In both cases the page reloads the current state and shows a fresh plan.

## Main Success Scenario

### Step 1 — Open the Configuration tab

1. Administrator opens the "Configuration" tab.
2. The page shows the current directory name, whether that name already carries a GUID, whether a compatibility stub is present, and the outcome of the most recent background rotation if there was one.
3. The page probes, from the browser and without a session, whether the data directory is served over HTTP, and reports the answer together with the file it asked for and the database in use. The file to probe is chosen server-side, because an instance backed by MySQL or PostgreSQL has no database file in that directory; when nothing static is available the page says the check could not be made rather than implying safety.
4. The page lists the preflight checks with a pass or fail mark for each.
5. When the database lives inside the directory to be renamed, the page warns that the web server is holding that file open and that on Windows this normally blocks the rename outright, so the button is expected to fail and offer the background route.
6. The page shows the web-server rule that actually denies access to the directory, with a copy button, described as the real fix rather than an alternative to it. That box leads with the probe's verdict — served, not reachable, or not checkable — so the operator can see whether the rule is still needed without reading the state table.

### Step 2 — Review the plan

1. Administrator presses "Rotate the name".
2. The page shows the exact rename it is about to perform, old name to new name, and states that a stub will be left behind and that a failure after the rename is rolled back automatically.
3. The primary button is disabled while the plan is on screen, so the plan cannot be re-opened on top of itself.

### Step 3 — Confirm

1. Administrator presses "Yes, rename it".
2. The server re-runs the preflight, renames the directory, creates the stub, and verifies both exist.
3. The page reports the new directory name and asks for a page reload, because the current page still holds the old paths in memory.

### Step 4 — Rotate again later

1. Administrator returns to the tab; the state shows the name is already rotated.
2. The primary action reads "Rotate again" and proposes a new GUID derived from the same base name.

## Extensions

**1a. The caller is not an administrator**
Every endpoint answers 403 and the page shows that the operation is reserved for administrators. No state is disclosed.

**1b. The state cannot be loaded**
An error message with a retry button replaces the state; the web-server rule box still renders.

**1c. The data directory is not reachable over HTTP**
The probe reports "not reachable" and the rule box turns green — the web server already denies it, or the file simply is not there. The wording does not claim the rule is installed, because the probe cannot tell the difference.

**1d. There is no static file to probe**
The probe is skipped, the row reads "cannot tell" and the rule box turns amber, advising to add the rule anyway. This is the normal state on an instance whose data directory holds only PHP files.

**3a. The rename fails because a file is open**
The server answers that the rename failed. The page explains that a file inside — the SQLite database on this instance, held open precisely in order to serve this page — blocks the rename, and that nothing was moved. It then offers two ways out, in order:

- **Without stopping the web server:** a single button hands the rename to a background process that keeps retrying and succeeds as soon as this page's request ends. This is the normal resolution and needs no restart.
- **By hand:** the three steps to finish from a terminal — stop the web server, run `tools/rotate_vardir.php` (the exact command is shown with a copy button), start the web server and reload.

The page never tells the operator to stop the web server and press a button on a page that server has to serve.

**3e. The background run is launched**
The page shows that the rotation is running, names the directory it is waiting for, and checks on its own every couple of seconds. On success it reports the new name, states that no restart was needed, and offers a reload. If it has not reported within about forty seconds, the page says so and leaves both the state reload and the manual command available. The outcome is recorded server-side, so reloading the tab later still shows what happened.

**3b. The plan is stale**
The directory changed between loading the plan and confirming it. The server refuses with a conflict, and the page reloads the state so a fresh plan can be reviewed.

**3c. A preflight check no longer passes**
The server refuses before touching anything and returns the failing checks.

**3d. The stub cannot be created or verified**
The directory is renamed back automatically and the page reports that everything was restored and nothing was lost.
