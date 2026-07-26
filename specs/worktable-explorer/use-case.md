# UC-WTE1 — Browse and edit any WorkTable table

## System context

Generic administrative tool inside the **worktable** plugin (tab "Worktable Explorer"). Ported verbatim from the `segreteria-campo` plugin's `worktable-explorer` SPA (see `views/worktable-explorer/index.js`), which is itself generic — it does not assume any specific table name or schema.

## Goal

Let an operator pick any WorkTable table exposed by the API and browse, sort, filter, create, edit and delete its records — including image/file attachments — without writing SQL or using the raw REST API directly.

## Primary Actor

CAMILA WorkTable administrator / developer.

## Stakeholders and interests

| Stakeholder | Interest |
|---|---|
| Administrator / developer | Wants a quick, generic way to inspect and fix data in any table during development or support, without a dedicated per-table SPA |
| Plugin maintainer | Wants a single generic explorer instead of one bespoke admin screen per table |

## Preconditions

- User is logged into CAMILA WorkTable with access to the worktable plugin.
- At least one table is visible to the current user via `GET /tables`.

## Postconditions — Success

- Selected table's records are shown, paginated, sortable and filterable.
- Create / edit / delete operations, when performed, are reflected immediately in the visible list.

## Postconditions — Error / Partial failure

- Load/save/delete failures show an inline, auto-dismissing message; the rest of the UI (table selector, pagination, other rows) remains usable.

## Main Success Scenario

### Step 1 — Pick a table

1. User opens the "Worktable Explorer" tab; the list of available tables loads into a selector.
2. User picks a table; its records load (default page size 20, sorted by `id` ascending).

### Step 2 — Browse

1. User can change page size, navigate pages, sort by clicking a column header, and filter by column/operator/value.
2. Rows with an attachment show an attachment icon.

### Step 3 — Inspect / edit a record

1. User double-clicks a row to open a record overlay showing every field.
2. User clicks "Modifica" to edit field values, then saves; only changed fields are sent.
3. If the record has an attachment, the overlay shows a thumbnail/preview, with upload (including a webcam capture + crop-to-ID-photo flow), download and delete actions.

### Step 4 — Inline table edit / create / delete

1. User can toggle inline edit mode on the table itself, editing multiple rows before saving.
2. "Nuovo" adds a new row (green) with inputs for all editable columns.
3. Deleting a row asks for inline confirmation, then removes it from the list without a full reload.

## Extensions

- **1a.** No tables visible / table list fails to load → inline error, no further action possible until retried.
- **1b.** Selected table has zero records → column list falls back to `describe()` (schema) so create/edit still knows the fields.
- **2a.** Sort/filter/pagination request fails → inline error; previous rows stay visible.
- **3a.** Attachment fetch fails → thumbnail area shows the error state; the rest of the overlay stays usable.
- **3b.** Camera permission denied / no camera devices → camera modal reports the failure; user can still upload a file directly.
- **4a.** Create/update/delete request fails → inline error; the row's previous state (or the deleted row, for a failed delete) is preserved — no optimistic removal on failure.

## Known divergence from other worktable SPAs

This SPA has no `window.I18N` / server-injected translations — all UI text is hardcoded Italian, copied as-is from the source plugin. See `design.md` for the reason this was not translated.
