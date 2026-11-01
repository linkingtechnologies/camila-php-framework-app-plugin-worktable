# Configuration {#top}

The page for the *instance data directory*: the folder that holds the database, the uploaded files, the logs and the generated configuration. By default it sits at a path anyone can guess (`var`). The page renames it to an unguessable name (`var-` followed by 32 letters and digits) and leaves a one-line *stub* at the old path, so no other file has to be changed.

It is **reserved for administrators**: for anyone else every request is refused and the page says so.

## What the page shows {#stato}

The table at the top describes the current situation:

- **Current directory**: the name of the folder now;
- **Name rotated**: whether it already carries the random part (*Yes, it carries a GUID*) or still has the default name;
- **Data directory over HTTP**: the page asks the web server, without a session, for a file of the data directory and reports *served without a session* (bad: anyone who knows the path can download it), *not reachable* (good) or *cannot tell* (there is no static file to try, which is normal when the data is held in a database server);
- **Database in use**: the kind of database;
- **Compatibility stub**: the file left at the old path after a rotation, or *None* if the folder is still where it started;
- **Last background run**: the outcome of the last rotation run in the background, if there was one.

Below, **Preflight checks** lists four conditions with a tick or a cross: the data directory exists, its parent folder is writable, the new name is free, the stub can be written. If one fails, nothing is changed.

A yellow warning appears when the SQLite database lives inside the folder to rename: the web server holds that file open, and on Windows this normally blocks the rename, so the button will most likely fail without moving anything.

## The real fix: the nginx rule {#nginx}

The box **The real fix: deny the directory in nginx** matters more than the rename. A random name only hides the path, it does not control access; and the `.htaccess` file inside the folder only works on Apache. The box shows the rule to add to the nginx server block so that the data is never served, whatever the folder is called. **Copy** copies it. Its first line says what the check above found.

## Rotate the name {#ruotare}

1. Press **Rotate the name**. The page shows **Confirm the rotation**: the old and the new name, and the reminder that a stub is left behind and that, if a later step fails, everything is put back by itself;
2. press **Yes, rename it**, or **Cancel** to give up;
3. on success the page says *Done: the data directory is now …* and asks you to press **Reload the page**: the open page still holds the old paths and must not be used before reloading.

Once the name is rotated the button reads **Rotate again** and proposes a new random name.

## If the rename is blocked {#bloccato}

When a file inside the folder is open, the rename cannot succeed from the page. Nothing is moved, and the page offers two ways out.

*Without stopping the web server*: **Run it in the background** hands the job to a separate process that keeps trying and succeeds as soon as the request of this page ends, usually within a couple of seconds. The box **Rotation running in the background** names the folder it is waiting for, and the page checks on its own: leave it alone for a few seconds. At the end it shows the new name and asks you to reload. If it has not answered after about forty seconds, the page says so: press **Retry** to read the state again, or use the command line.

*From the command line*: the box **How to complete it from the command line** gives the three steps: stop the web server; open a terminal on the server and run the command shown (**Copy** copies it); start the web server again and reload the page. Add `--dry-run` to the command to see the plan without changing anything.

## When something else goes wrong {#problemi}

- *The plan is stale*: the folder changed meanwhile. The page has already reloaded the state: check it and try again;
- *A preflight check no longer passes*: nothing was changed;
- *The rename worked but a later step failed*: everything was put back and nothing was lost;
- a connection or server error comes with **Retry**.

The rotation can be undone by renaming the folder back to its default name.
