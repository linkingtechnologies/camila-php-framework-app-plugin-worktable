"""worktable_sync.py — Replicate Worktable data from one endpoint to another.

Source credentials (.env):
  WORKTABLE_BASE_URL, WORKTABLE_USERNAME, WORKTABLE_PASSWORD

Destination credentials (.env):
  WORKTABLE_DEST_URL, WORKTABLE_DEST_USERNAME, WORKTABLE_DEST_PASSWORD

Clone strategy: INSERT new records (replica_mode preserves original id and audit
trail), UPDATE existing records (matched by id). Records present in destination
but not in source are left untouched.
"""

import os
import sys

from dotenv import load_dotenv
from worktable_client import WorkTableClient

BATCH_SIZE = 200  # records per bulk request

load_dotenv(".env.sync", override=True)


# ------------------------------------------------------------------
# Client factory
# ------------------------------------------------------------------

def _make_client(url, user, password, replica=False, debug=False):
    print(f"  Connecting to {url} (user={user}) ...")
    client = WorkTableClient(
        base_url=url,
        replica_mode=replica,
        sys_mode=not replica,   # sys_mode for source reads
        debug=debug,
    )
    client.login(user, password)
    return client


def make_source(debug=False):
    return _make_client(
        os.environ["WORKTABLE_BASE_URL"],
        os.environ["WORKTABLE_USERNAME"],
        os.environ["WORKTABLE_PASSWORD"],
        replica=False,
        debug=debug,
    )


def make_dest(debug=False):
    return _make_client(
        os.environ["WORKTABLE_DEST_URL"],
        os.environ["WORKTABLE_DEST_USERNAME"],
        os.environ["WORKTABLE_DEST_PASSWORD"],
        replica=True,
        debug=debug,
    )


# ------------------------------------------------------------------
# Helpers
# ------------------------------------------------------------------

def fetch_all(client, table, page_size=500):
    """Fetch all records from a table (paginated). Returns list of dicts."""
    page, records = 1, []
    while True:
        resp = client.list(table, query={"page": [page, page_size]})
        batch  = resp.get("records", [])
        total  = resp.get("results", 0)
        records.extend(batch)
        print(f"    fetched {len(records)}/{total}", end="\r")
        if not batch or len(records) >= total:
            break
        page += 1
    print()
    return records


def fetch_dest_ids(client, table, page_size=500):
    """Return set of 'id' values already present in destination."""
    page, ids, fetched = 1, set(), 0
    while True:
        resp  = client.list(table, query={"page": [page, page_size]})
        batch = resp.get("records", [])
        total = resp.get("results", 0)
        for r in batch:
            rid = str(r.get("id") or "").strip()
            if rid:
                ids.add(rid)
        fetched += len(batch)
        if not batch or fetched >= total:
            break
        page += 1
    return ids


def pick_table(client):
    """Interactive table picker using client.tables()."""
    try:
        resp = client.tables()
    except Exception as e:
        print(f"  [WARN] Cannot list tables: {e}")
        return input("Table name: ").strip()

    raw = resp if isinstance(resp, list) else (resp.get("tables") or resp.get("records") or [])
    if not raw:
        return input("Table name: ").strip()

    entries = sorted(
        (str(r.get("alias") or r.get("name") or next(iter(r.values()))), str(r.get("name") or ""))
        for r in raw
    ) if raw and isinstance(raw[0], dict) else [(str(t), "") for t in sorted(raw)]

    print("\nAvailable tables:")
    for i, (alias, name) in enumerate(entries, 1):
        suffix = f"  (db: {name})" if name and name != alias else ""
        print(f"  {i}. {alias}{suffix}")
    while True:
        try:
            choice = int(input(f"\nPick a table (1-{len(entries)}): "))
            if 1 <= choice <= len(entries):
                return entries[choice - 1][0]
        except ValueError:
            pass
        print(f"Enter a number between 1 and {len(entries)}.")


# ------------------------------------------------------------------
# Clone logic
# ------------------------------------------------------------------

def clone_table(src, dst, table):
    print(f"\nCloning table '{table}'...")

    print("  Fetching source records...")
    src_records = fetch_all(src, table)
    print(f"  Source: {len(src_records)} records.")

    print("  Fetching destination IDs...")
    dst_ids = fetch_dest_ids(dst, table)
    print(f"  Destination: {len(dst_ids)} records already present.")

    def _src_id(r):
        return str(r.get("id") or "").strip()

    def _build_dest_record(r):
        """Map source fields to destination: source id→uuid, source id2→id."""
        rec = dict(r)
        rec["uuid"] = rec.pop("id", "")
        if "id2" in rec:
            rec["id"] = rec.pop("id2")
        return rec

    to_insert = [r for r in src_records if _src_id(r) not in dst_ids]
    to_update = [r for r in src_records if _src_id(r) in dst_ids]
    total = len(src_records)
    print(f"  to insert: {len(to_insert)}  |  to update: {len(to_update)}")
    inserted, updated, errors, done = 0, 0, 0, 0

    def _progress():
        pct = done * 100 // total if total else 100
        print(f"  {pct:3d}%  {done}/{total} — inserted: {inserted}, updated: {updated}, errors: {errors}   ", end="\r")

    def _api_error(e):
        body = ""
        if hasattr(e, "response") and e.response is not None:
            try:
                body = e.response.text[:300]
            except Exception:
                pass
        return body

    # --- bulk INSERT ---
    for i in range(0, len(to_insert), BATCH_SIZE):
        chunk = [_build_dest_record(r) for r in to_insert[i:i + BATCH_SIZE]]
        try:
            dst.bulk_create(table, chunk)
            inserted += len(chunk)
        except Exception as e:
            print(f"\n  [ERROR] bulk insert chunk {i//BATCH_SIZE + 1}: {e}")
            print(f"          {_api_error(e)}")
            errors += len(chunk)
        done += len(chunk)
        _progress()

    # --- bulk UPDATE ---
    for i in range(0, len(to_update), BATCH_SIZE):
        chunk  = to_update[i:i + BATCH_SIZE]
        ids    = [_src_id(r) for r in chunk]   # source.id == destination.id
        bodies = [_build_dest_record(r) for r in chunk]
        try:
            dst.bulk_update(table, ids, bodies)
            updated += len(chunk)
        except Exception as e:
            print(f"\n  [ERROR] bulk update chunk {i//BATCH_SIZE + 1}: {e}")
            print(f"          {_api_error(e)}")
            errors += len(chunk)
        done += len(chunk)
        _progress()

    print()
    print(f"\nDone '{table}': inserted={inserted}, updated={updated}, errors={errors}")
    return inserted, updated, errors


# ------------------------------------------------------------------
# Template sync
# ------------------------------------------------------------------

def sync_templates(src, dst, langs=None):
    """Sync templates from source to destination.

    langs: list of language codes to sync, e.g. ['it', 'en'].
           Pass [None] (default) to sync only the default language.
    """
    if langs is None:
        langs = [None]

    total_created, total_updated, total_skipped = 0, 0, 0

    for lang in langs:
        label = lang or "default"
        print(f"\n  Templates (lang={label})...")
        resp = src.templates_list(lang=lang)
        src_templates = resp.get("templates", [])
        print(f"  Source: {len(src_templates)} templates.")

        for tmpl in src_templates:
            name      = tmpl.get("name", "")
            value     = tmpl.get("value", "")
            tmpl_lang = tmpl.get("lang") or lang

            dst_resp = dst.template_get(name, lang=tmpl_lang)
            if dst_resp.get("error"):
                dst.template_put(name, value, lang=tmpl_lang)
                print(f"    + {name}")
                total_created += 1
            elif dst_resp.get("value") != value:
                dst.template_put(name, value, lang=tmpl_lang)
                print(f"    ~ {name}")
                total_updated += 1
            else:
                total_skipped += 1

    print(f"\n  Templates: created={total_created}, updated={total_updated}, unchanged={total_skipped}")
    return total_created, total_updated, total_skipped


# ------------------------------------------------------------------
# Commands
# ------------------------------------------------------------------

def cmd_clone_table(debug=False):
    src = make_source(debug)
    dst = make_dest(debug)
    print(f"Source: {os.environ['WORKTABLE_BASE_URL']}")
    print(f"Dest:   {os.environ['WORKTABLE_DEST_URL']}")
    table = pick_table(src)
    clone_table(src, dst, table)


def cmd_sync_templates(debug=False):
    src = make_source(debug)
    dst = make_dest(debug)
    print(f"Source: {os.environ['WORKTABLE_BASE_URL']}")
    print(f"Dest:   {os.environ['WORKTABLE_DEST_URL']}")
    raw = input("Languages to sync (comma-separated, e.g. 'it,en'; leave blank for default): ").strip()
    langs = [l.strip() for l in raw.split(",") if l.strip()] if raw else [None]
    sync_templates(src, dst, langs=langs)


def cmd_clone_all(debug=False):
    src = make_source(debug)
    dst = make_dest(debug)
    print(f"Source: {os.environ['WORKTABLE_BASE_URL']}")
    print(f"Dest:   {os.environ['WORKTABLE_DEST_URL']}")

    try:
        resp = src.tables()
        raw = resp if isinstance(resp, list) else (resp.get("tables") or resp.get("records") or [])
    except Exception as e:
        print(f"Cannot list tables: {e}")
        sys.exit(1)

    tables = sorted(
        str(r.get("alias") or r.get("name") or next(iter(r.values())))
        for r in raw
    ) if raw and isinstance(raw[0], dict) else sorted(str(t) for t in raw)

    print(f"\n{len(tables)} tables to clone: {', '.join(tables)}")
    confirm = input("Proceed? (y/N): ").strip().lower()
    if confirm != "y":
        print("Aborted.")
        return

    total_ins, total_upd, total_err = 0, 0, 0
    for table in tables:
        ins, upd, err = clone_table(src, dst, table)
        total_ins += ins
        total_upd += upd
        total_err += err

    print(f"\n=== Clone complete ===")
    print(f"Tables: {len(tables)}  |  Inserted: {total_ins}  |  Updated: {total_upd}  |  Errors: {total_err}")


# ------------------------------------------------------------------
# Menu
# ------------------------------------------------------------------

DEBUG = "--debug" in sys.argv

print("=== Worktable Sync ===")
print(f"  Source: {os.environ.get('WORKTABLE_BASE_URL', '(not set)')}")
print(f"  Dest:   {os.environ.get('WORKTABLE_DEST_URL', '(not set)')}")
print()
print("  1. Clone a single table")
print("  2. Clone all visible tables")
print("  3. Sync templates")
print()

while True:
    try:
        cmd = int(input("Choose an option (1-3): "))
        if cmd in (1, 2, 3):
            break
        print("Enter 1, 2 or 3.")
    except ValueError:
        print("Invalid input.")

if cmd == 1:
    cmd_clone_table(debug=DEBUG)
elif cmd == 2:
    cmd_clone_all(debug=DEBUG)
elif cmd == 3:
    cmd_sync_templates(debug=DEBUG)
