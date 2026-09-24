// app-tools.js — download the plugin's Python helper scripts
import { html, render } from "../../../../camila/js/lit-html/lit-html.js";

const root = document.getElementById("app");

if (typeof WorkTableClient !== "function") {
  render(html`<div class="notification is-danger">WorkTableClient not available</div>`, root);
  throw Error("WorkTableClient not available");
}

const client = WorkTableClient(window.APP_CONFIG || {});

const t = (key, ...args) => {
  let s = window.I18N?.[key] ?? key;
  args.forEach(a => { s = s.replace('%s', a); });
  return s;
};

// Static path of the tools/ directory, relative to the page (cf_app.php lives in
// the app directory). Relative on purpose: it resolves correctly when the app is
// served under a URL prefix. These files are public — the plugin ships them in a
// public repository — so they are linked directly rather than proxied through the
// API.
const TOOLS_PATH = "plugins/worktable/tools/";

// Explicit filename -> i18n key map for the one-line descriptions. A file with no
// entry here simply renders without a description, so dropping a new script into
// tools/ never breaks the tab — it just shows up undescribed until a key is added.
const DESCRIPTIONS = {
  "worktable_client.py": "tools.desc.client",
  "worktable_sync.py":   "tools.desc.sync",
  ".env.locale.example": "tools.desc.envLocale",
  ".env.sync.example":   "tools.desc.envSync",
};

// ── State ──────────────────────────────────────────────────────────────────

const state = {
  loading: true,
  error:   null,   // { status, message, kind } | null
  files:   [],     // [{ name, size, lang }]
};

let cancelled = false;

// ── Helpers ────────────────────────────────────────────────────────────────

function normalizeApiError(err) {
  const raw = err?.payload ?? err;
  const status  = raw?.status ?? err?.status;
  const message = raw?.message ?? err?.message
    ?? (typeof raw === "string" ? raw : "Unknown error");
  let kind = "unknown";
  if (err?.name === "AbortError")            kind = "timeout";
  else if (status === 401 || status === 403) kind = "auth";
  else if (status === 404)                   kind = "not_found";
  else if (status >= 500)                    kind = "server";
  return { status, message, kind };
}

function userFriendlyErrorText(e) {
  switch (e.kind) {
    case "auth":      return t("tools.error.auth");
    case "timeout":   return t("tools.error.network");
    case "server":    return t("tools.error.server");
    case "not_found": return t("tools.error.notFound");
    default:          return t("tools.error.generic");
  }
}

function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} B`;
  return `${(bytes / 1024).toFixed(1)} kB`;
}

function fileHref(name) {
  return TOOLS_PATH + encodeURIComponent(name);
}

// ── Actions ────────────────────────────────────────────────────────────────

async function loadFiles() {
  state.loading = true;
  state.error = null;
  mount();
  try {
    const res = await client.call("GET", "/worktable/tools");
    if (cancelled) return;
    state.files = Array.isArray(res?.files) ? res.files : [];
  } catch (e) {
    if (cancelled) return;
    state.error = normalizeApiError(e);
    state.files = [];
  }
  state.loading = false;
  mount();
}

// "Download all": click each link in turn. The browser may ask the user to allow
// multiple downloads for this site; individual buttons always remain available.
function downloadAll() {
  for (const f of state.files) {
    const a = document.createElement("a");
    a.href = fileHref(f.name);
    a.download = f.name;
    document.body.appendChild(a);
    a.click();
    a.remove();
  }
}

// ── View ───────────────────────────────────────────────────────────────────

function Row(f) {
  const descKey = DESCRIPTIONS[f.name];
  return html`
    <tr>
      <td class="tools-icon-cell">
        <span class="icon is-medium has-text-grey">
          <i class="${f.lang === "python" ? "ri-file-code-line" : "ri-file-settings-line"} ri-xl"></i>
        </span>
      </td>
      <td>
        <p class="tools-name"><code>${f.name}</code></p>
        ${descKey ? html`<p class="has-text-grey">${t(descKey)}</p>` : ""}
      </td>
      <td class="has-text-right has-text-grey tools-size">${formatSize(f.size)}</td>
      <td class="has-text-right tools-action">
        <a class="button is-primary" href=${fileHref(f.name)} download=${f.name}>
          <span class="icon"><i class="ri-download-2-line"></i></span>
          <span>${t("tools.btn.download")}</span>
        </a>
      </td>
    </tr>
  `;
}

function App() {
  return html`
    <div class="container pt-0 pb-4">
      <div class="box spa-title-box">
        <div class="level mb-4">
          <div class="level-left">
            <p class="is-size-5">${t("tools.intro")}</p>
          </div>
          <div class="level-right">
            <button
              class="button is-primary"
              ?disabled=${state.loading || state.files.length === 0}
              @click=${downloadAll}
            >
              <span class="icon"><i class="ri-download-2-line"></i></span>
              <span>${t("tools.btn.downloadAll")}</span>
            </button>
          </div>
        </div>

        ${state.error ? html`
          <article class="message is-danger">
            <div class="message-body">
              ${userFriendlyErrorText(state.error)}
              <button class="button is-small is-light ml-3" @click=${loadFiles}>
                <span class="icon"><i class="ri-refresh-line"></i></span>
                <span>${t("tools.btn.retry")}</span>
              </button>
            </div>
          </article>
        ` : ""}

        ${state.loading ? html`<progress class="progress is-small is-primary"></progress>` : ""}

        ${!state.loading && state.files.length === 0 && !state.error ? html`
          <p class="has-text-grey is-size-5">${t("tools.empty")}</p>
        ` : ""}

        ${state.files.length ? html`
          <table class="table is-fullwidth is-hoverable is-striped is-vcentered">
            <tbody>
              ${state.files.map(Row)}
            </tbody>
          </table>
        ` : ""}
      </div>

      <div class="box">
        <h4 class="title is-6 mb-2">${t("tools.usage.title")}</h4>
        <p class="mb-2">${t("tools.usage.step1")}</p>
        <pre style="margin:0 0 .75rem 0">pip install requests python-dotenv</pre>
        <p class="mb-2">${t("tools.usage.step2")}</p>
        <p class="mb-2">${t("tools.usage.step3")}</p>
        <pre style="margin:0">python worktable_sync.py</pre>
      </div>
    </div>
  `;
}

function mount() {
  render(App(), root);
}

mount();
loadFiles();
