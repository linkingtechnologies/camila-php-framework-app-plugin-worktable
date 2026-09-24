import requests


class WorkTableClient:
    """
    Python port of worktable-client.js.

    Endpoints:
      /records/<table>                    GET list, POST create
      /records/<table>/<id>               GET read, PUT update, DELETE remove
      /records/<table>/distinct/<column>  GET distinct values
      /columns/<table>                    GET describe columns
      /permissions/<table>                GET permissions
      /sequence/<table>                   GET sequence
      /tables                             GET list of visible tables
      /attachments/<table>/<id>           POST upload, GET serve, HEAD exists, DELETE remove
      /attachments/<table>                GET list of IDs with an attachment
    """

    def __init__(
        self,
        base_url="",
        records_path="/records",
        columns_path="/columns",
        permissions_path="/permissions",
        sequence_path="/sequence",
        tables_path="/tables",
        attachments_path="/attachments",
        login_path="/login",
        api_key_header_name=None,
        api_key_header_value=None,
        timeout_s=20,
        debug=False,
        sys_mode=False,
        replica_mode=False,
    ):
        self._base_url = base_url
        self._base = self._join(base_url, records_path)
        self._base_cols = self._join(base_url, columns_path)
        self._base_perms = self._join(base_url, permissions_path)
        self._base_seq = self._join(base_url, sequence_path)
        self._base_tables = self._join(base_url, tables_path)
        self._base_attachments = self._join(base_url, attachments_path)
        self._login_url = self._join(base_url, login_path)
        self._headers = {}
        if api_key_header_name and api_key_header_value:
            self._headers[api_key_header_name] = api_key_header_value
        self._timeout = timeout_s
        self._debug = debug
        self._sys_mode = sys_mode or replica_mode  # replica implica sys
        self._replica_mode = replica_mode

    def _extra_params(self):
        """Restituisce i parametri _sys/_replica da aggiungere a ogni richiesta."""
        if self._replica_mode:
            return [("_replica", "1")]
        if self._sys_mode:
            return [("_sys", "1")]
        return []

    def _log(self, method, url, params=None, status=None, body_preview=None):
        if not self._debug:
            return
        qs = ""
        if params:
            from urllib.parse import urlencode
            qs = "?" + urlencode(params)
        line = f"[WT] {method} {url}{qs}"
        if status is not None:
            line += f" → {status}"
        if body_preview is not None:
            preview = str(body_preview)
            if len(preview) > 200:
                preview = preview[:200] + "…"
            line += f"\n     {preview}"
        print(line)

    def login(self, username, password, token_field="token", auth_header="X-API-Key", auth_prefix=""):
        """
        POST al login endpoint con username/password.
        Salva il token restituito negli header per le chiamate successive.
        Ritorna il token come stringa.
        """
        self._log("POST", self._login_url)
        resp = requests.post(
            self._login_url,
            json={"username": username, "password": password},
            timeout=self._timeout,
        )
        self._log("POST", self._login_url, status=resp.status_code)
        resp.raise_for_status()
        data = resp.json()
        token = data.get(token_field)
        if not token:
            raise ValueError(f"Login OK ma campo '{token_field}' non trovato nella risposta: {data}")
        self._headers[auth_header] = auth_prefix + token
        return token

    # ------------------------------------------------------------------
    # Utilities
    # ------------------------------------------------------------------

    @staticmethod
    def _join(a, b):
        a = str(a or "")
        b = str(b or "")
        if not a:
            return b
        if not b:
            return a
        if a.endswith("/") and b.startswith("/"):
            return a + b[1:]
        if not a.endswith("/") and not b.startswith("/"):
            return a + "/" + b
        return a + b

    @staticmethod
    def _build_query(params):
        """Build a query-string dict from a ListQuery dict."""
        if not params:
            return []
        qs = []

        for f in params.get("filters") or []:
            qs.append(("filter", ",".join(str(x) for x in f)))

        for key, group in (params.get("orFilters") or {}).items():
            qs.append((key, ",".join(str(x) for x in group)))

        if "include" in params:
            v = params["include"]
            qs.append(("include", ",".join(v) if isinstance(v, list) else v))

        if "exclude" in params:
            v = params["exclude"]
            qs.append(("exclude", ",".join(v) if isinstance(v, list) else v))

        order = params.get("order")
        if order:
            if isinstance(order, list):
                if order and isinstance(order[0], list):
                    for o in order:
                        qs.append(("order", ",".join(str(x) for x in o)))
                else:
                    qs.append(("order", ",".join(str(x) for x in order)))
            else:
                qs.append(("order", order))

        if "size" in params:
            qs.append(("size", params["size"]))

        if "page" in params:
            v = params["page"]
            qs.append(("page", ",".join(str(x) for x in v) if isinstance(v, list) else v))

        return qs

    def _request(self, base, path, method, body=None, query=None):
        url = base + path
        headers = dict(self._headers)
        if body is not None:
            headers["Content-Type"] = "application/json"
        params = self._build_query(query) + self._extra_params()
        self._log(method, url, params=params)
        resp = requests.request(
            method,
            url,
            headers=headers,
            json=body,
            params=params or None,
            timeout=self._timeout,
        )
        ct = resp.headers.get("content-type", "")
        data = resp.json() if "application/json" in ct else resp.text
        self._log(method, url, status=resp.status_code, body_preview=data)
        resp.raise_for_status()
        return data

    def _req(self, path, method, body=None, query=None):
        return self._request(self._base, path, method, body, query)

    def _req_cols(self, path, method, body=None, query=None):
        return self._request(self._base_cols, path, method, body, query)

    def _req_perms(self, path, method, body=None, query=None):
        return self._request(self._base_perms, path, method, body, query)

    def _req_seq(self, path, method, body=None, query=None):
        return self._request(self._base_seq, path, method, body, query)

    # ------------------------------------------------------------------
    # Public API  (flat style)
    # ------------------------------------------------------------------

    def list(self, table, query=None):
        return self._req(f"/{table}", "GET", query=query)

    def create(self, table, data):
        return self._req(f"/{table}", "POST", body=data)

    def bulk_create(self, table, records):
        """POST array of records → list of generated IDs."""
        return self._req(f"/{table}", "POST", body=records)

    def bulk_update(self, table, ids, records):
        """PUT array of records by comma-separated IDs in path (same order)."""
        path = f"/{table}/{','.join(str(i) for i in ids)}"
        return self._req(path, "PUT", body=records)

    def read(self, table, id):
        return self._req(f"/{table}/{id}", "GET")

    def update(self, table, id, patch):
        return self._req(f"/{table}/{id}", "PUT", body=patch)

    def remove(self, table, id):
        return self._req(f"/{table}/{id}", "DELETE")

    def describe(self, table, query=None):
        return self._req_cols(f"/{table}", "GET", query=query)

    def permissions(self, table, query=None):
        return self._req_perms(f"/{table}", "GET", query=query)

    def sequence(self, table, query=None):
        return self._req_seq(f"/{table}", "GET", query=query)

    def distinct(self, table, column, query=None):
        return self._req(f"/{table}/distinct/{column}", "GET", query=query)

    def tables(self):
        """GET /tables → lista tabelle visibili."""
        self._log("GET", self._base_tables)
        resp = requests.get(
            self._base_tables,
            headers=dict(self._headers),
            params=self._extra_params() or None,
            timeout=self._timeout,
        )
        ct = resp.headers.get("content-type", "")
        data = resp.json() if "application/json" in ct else resp.text
        self._log("GET", self._base_tables, status=resp.status_code, body_preview=data)
        resp.raise_for_status()
        return data

    # ------------------------------------------------------------------
    # Templates
    # ------------------------------------------------------------------

    def templates_list(self, lang=None):
        """GET /templates → {lang, templates: [{name, lang, value}, ...]}"""
        query = {"lang": lang} if lang else None
        return self.call("GET", "/templates", query=query)

    def template_get(self, name, lang=None):
        """GET /templates/{name} → {name, lang, value} or {error, name} (always HTTP 200)."""
        query = {"lang": lang} if lang else None
        return self.call("GET", f"/templates/{name}", query=query)

    def template_put(self, name, value, lang=None):
        """PUT /templates/{name} with body {value: ...}. lang as query param."""
        query = {"lang": lang} if lang else None
        return self.call("PUT", f"/templates/{name}", body={"value": value}, query=query)

    def call(self, method, path, body=None, query=None):
        """Chiamata generica a qualsiasi endpoint custom (autenticazione inclusa).

        method: "GET"|"POST"|"PUT"|"DELETE"|"PATCH"
        path:   percorso completo, es. "/templates/welcome"
        body:   dict opzionale (JSON)
        query:  dict opzionale → query string
        """
        url = self._base_url + path
        headers = dict(self._headers)
        if body is not None:
            headers["Content-Type"] = "application/json"
        extra = self._extra_params()
        call_params = list((query or {}).items()) + extra
        self._log(method, url, params=call_params)
        resp = requests.request(
            method,
            url,
            headers=headers,
            json=body,
            params=call_params or None,
            timeout=self._timeout,
        )
        ct = resp.headers.get("content-type", "")
        data = resp.json() if "application/json" in ct else resp.text
        self._log(method, url, status=resp.status_code, body_preview=data)
        resp.raise_for_status()
        return data

    def upload_attachment(self, table, id, file):
        """POST multipart/form-data a /attachments/<table>/<id>.

        file: oggetto file-like aperto in modalità binaria.
        Ritorna dict con la URL dell'allegato.
        """
        url = f"{self._base_attachments}/{table}/{id}"
        self._log("POST", url)
        resp = requests.post(
            url,
            headers=dict(self._headers),
            files={"file": file},
            timeout=self._timeout,
        )
        self._log("POST", url, status=resp.status_code)
        resp.raise_for_status()
        return resp.json()

    def attachment_url(self, table, id):
        """Restituisce la URL stringa dell'allegato (trigger download lato browser)."""
        return f"{self._base_attachments}/{table}/{id}"

    def fetch_attachment(self, table, id):
        """GET allegato → dict {content: bytes, mime: str, ext: str}."""
        url = f"{self._base_attachments}/{table}/{id}"
        self._log("GET", url)
        resp = requests.get(url, headers=dict(self._headers), timeout=self._timeout)
        self._log("GET", url, status=resp.status_code)
        resp.raise_for_status()
        return {
            "content": resp.content,
            "mime": resp.headers.get("Content-Type", ""),
            "ext": resp.headers.get("X-Attachment-Ext", ""),
        }

    def has_attachment(self, table, id):
        """HEAD check allegato → False se assente, {mime, ext} se presente."""
        url = f"{self._base_attachments}/{table}/{id}"
        self._log("HEAD", url)
        resp = requests.head(url, headers=dict(self._headers), timeout=self._timeout)
        self._log("HEAD", url, status=resp.status_code)
        if resp.status_code != 200:
            return False
        return {
            "mime": resp.headers.get("Content-Type", ""),
            "ext": resp.headers.get("X-Attachment-Ext", ""),
        }

    def list_attachments(self, table):
        """GET /attachments/<table> → {ids: [{id, mime, ext}, ...]}."""
        url = f"{self._base_attachments}/{table}"
        self._log("GET", url)
        resp = requests.get(url, headers=dict(self._headers), timeout=self._timeout)
        self._log("GET", url, status=resp.status_code)
        resp.raise_for_status()
        return resp.json()

    def delete_attachment(self, table, id):
        """DELETE /attachments/<table>/<id>."""
        url = f"{self._base_attachments}/{table}/{id}"
        self._log("DELETE", url)
        resp = requests.delete(url, headers=dict(self._headers), timeout=self._timeout)
        self._log("DELETE", url, status=resp.status_code)
        if resp.ok or resp.status_code == 204:
            return
        resp.raise_for_status()

    # ------------------------------------------------------------------
    # Table-bound helper  (table style)
    # ------------------------------------------------------------------

    def table(self, table_name):
        return _TableApi(self, table_name)

    # ------------------------------------------------------------------
    # Filter helpers
    # ------------------------------------------------------------------

    @staticmethod
    def filter(column, operator, *values):
        """Build a filter tuple: filter("id", "gt", 1) -> ["id", "gt", 1]"""
        return [column, operator] + list(values)

    @staticmethod
    def negate(operator):
        """Negate an operator: negate("eq") -> "neq" """
        return "n" + operator


class _TableApi:
    """Bound to a specific table — mirrors the JS tableApi() return value."""

    def __init__(self, client: WorkTableClient, table_name: str):
        self._c = client
        self._t = table_name

    def list(self, query=None):
        return self._c.list(self._t, query)

    def create(self, data):
        return self._c.create(self._t, data)

    def read(self, id):
        return self._c.read(self._t, id)

    def update(self, id, patch):
        return self._c.update(self._t, id, patch)

    def remove(self, id):
        return self._c.remove(self._t, id)

    def describe(self, query=None):
        return self._c.describe(self._t, query)

    def permissions(self, query=None):
        return self._c.permissions(self._t, query)

    def sequence(self, query=None):
        return self._c.sequence(self._t, query)

    def distinct(self, column, query=None):
        return self._c.distinct(self._t, column, query)

    def upload_attachment(self, id, file):
        return self._c.upload_attachment(self._t, id, file)

    def attachment_url(self, id):
        return self._c.attachment_url(self._t, id)

    def fetch_attachment(self, id):
        return self._c.fetch_attachment(self._t, id)

    def has_attachment(self, id):
        return self._c.has_attachment(self._t, id)

    def list_attachments(self):
        return self._c.list_attachments(self._t)

    def delete_attachment(self, id):
        return self._c.delete_attachment(self._t, id)
