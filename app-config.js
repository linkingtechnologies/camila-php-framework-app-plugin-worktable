// app-config.js — plugin configuration: rotate CAMILA_VAR_ROOTDIR
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

// Checks reported by GET /worktable/vardir, in the order they are shown.
const CHECK_KEYS = ["currentExists", "parentWritable", "targetFree", "stubWritable"];

// Quoted on purpose: unquoted, nginx reads the "{" of "{32}" as the start of a
// block, truncates the regex and refuses to start with
// "pcre2_compile() failed: missing closing parenthesis".
const NGINX_SNIPPET = `location ~ "/app/[^/]+/var(-[0-9a-f]{32})?/" {
    deny all;
}`;

// ── State ──────────────────────────────────────────────────────────────────

const state = {
  loading:   true,
  error:     null,    // { status, message, kind } | null
  info:      null,    // payload of GET /vardir
  confirming: false,  // plan shown, waiting for the explicit second click
  running:   false,   // rotation in flight
  result:    null,    // { to } after success
  failure:   null,    // { error, message } after a failed rotation
  exposure:  "unknown", // "unknown" | "checking" | "exposed" | "protected"
  copied:    false,
  copiedCmd: false,
  scheduled: null,      // { to, waitFor } once a background run was launched
  polls:     0,         // how many times we have checked on it
  deferOutcome: null,   // "ok" | "failed" | "timeout" once it settles
};

const POLL_EVERY_MS = 2000;
const MAX_POLLS     = 20;   // ~40 s, comfortably past the script's own 30 s deadline

let cancelled = false;
let copyTimer = null;

// ── Helpers ────────────────────────────────────────────────────────────────

function normalizeApiError(err) {
  const raw = err?.payload ?? err;
  const status  = raw?.status ?? err?.status;
  const message = raw?.message ?? err?.message
    ?? (typeof raw === "string" ? raw : "Unknown error");
  let kind = "unknown";
  if (err?.name === "AbortError")             kind = "timeout";
  else if (status === 401 || status === 403)  kind = "auth";
  else if (status === 404)                    kind = "not_found";
  else if (status >= 500)                     kind = "server";
  return { status, message, kind, payload: raw };
}

function errorText(e) {
  if (e?.payload?.error === "admin_required") return t("config.error.admin");
  switch (e.kind) {
    case "auth":    return t("config.error.admin");
    case "timeout": return t("config.error.network");
    case "server":  return t("config.error.server");
    default:        return t("config.error.generic");
  }
}

function failureText(f) {
  switch (f.error) {
    case "rename_failed":    return t("config.error.locked");
    case "stale_plan":       return t("config.error.stale");
    case "preflight_failed": return t("config.error.preflight");
    case "stub_dir_failed":
    case "stub_write_failed":
    case "verify_failed":    return t("config.error.rolledBack");
    case "admin_required":   return t("config.error.admin");
    case "no_cli_binary":    return t("config.error.noCliBinary");
    case "spawn_failed":     return t("config.error.spawnFailed");
    case "script_missing":   return t("config.error.scriptMissing");
    default:                 return t("config.error.generic");
  }
}

// ── Actions ────────────────────────────────────────────────────────────────

async function load() {
  state.loading = true;
  state.error = null;
  state.confirming = false;
  mount();
  try {
    const res = await client.call("GET", "/worktable/vardir");
    if (cancelled) return;
    state.info = res;
    state.loading = false;
    mount();
    probeExposure(res.currentName, res.probeFile);
  } catch (e) {
    if (cancelled) return;
    state.error = normalizeApiError(e);
    state.info = null;
    state.loading = false;
    mount();
  }
}

// Same-origin probe: is the data directory served over plain HTTP, with no session?
// HEAD, so the body is never transferred. The path is relative to this page, which
// lives in the app directory — the same base the data directory sits in.
//
// The file to ask for comes from the server, because there is no single filename
// that always exists: db/camila.db is only there on a bundled SQLite install, and
// the framework can just as well be pointed at MySQL or PostgreSQL. With nothing
// static to probe we report "cannot tell" rather than a reassuring "protected".
async function probeExposure(dirName, probeFile) {
  if (!dirName) return;
  if (!probeFile) {
    state.exposure = "noprobe";
    mount();
    return;
  }
  state.exposure = "checking";
  mount();
  try {
    const url = `${dirName}/${probeFile.split("/").map(encodeURIComponent).join("/")}`;
    const res = await fetch(url, { method: "HEAD", cache: "no-store" });
    if (cancelled) return;
    state.exposure = res.ok ? "exposed" : "protected";
  } catch {
    if (cancelled) return;
    state.exposure = "protected";
  }
  mount();
}

async function rotate() {
  if (!state.info) return;
  state.running = true;
  state.failure = null;
  mount();
  try {
    const res = await client.call("POST", "/worktable/vardir/rotate", {
      targetName: state.info.targetName,
      token:      state.info.token,
    });
    if (cancelled) return;
    state.result = res;
    state.confirming = false;
    state.running = false;
    mount();
  } catch (e) {
    if (cancelled) return;
    const norm = normalizeApiError(e);
    state.failure = {
      error:     norm.payload?.error ?? "unknown",
      message:   norm.payload?.message ?? norm.message,
      cliScript: norm.payload?.cliScript ?? "",
    };
    state.running = false;
    state.confirming = false;
    mount();
    load();   // the plan is spent either way — fetch a fresh one
  }
}

// Hand the rotation to a detached CLI process. It retries until this request ends
// and the database handle is released, so the web server never has to be stopped.
async function rotateDeferred() {
  if (!state.info) return;
  state.running = true;
  state.failure = null;
  mount();
  try {
    const res = await client.call("POST", "/worktable/vardir/rotate", {
      targetName: state.info.targetName,
      token:      state.info.token,
      defer:      true,
    });
    if (cancelled) return;
    state.scheduled = { to: res.to, waitFor: res.waitFor ?? 30 };
    state.polls = 0;
    state.deferOutcome = null;
    state.running = false;
    state.confirming = false;
    mount();
    setTimeout(pollDeferred, POLL_EVERY_MS);
  } catch (e) {
    if (cancelled) return;
    const norm = normalizeApiError(e);
    state.failure = {
      error:     norm.payload?.error ?? "unknown",
      message:   norm.payload?.message ?? norm.message,
      cliScript: norm.payload?.cliScript ?? "",
    };
    state.running = false;
    mount();
  }
}

// Checking costs one request, which briefly reopens the database — harmless next to
// the renamer's 250 ms retry loop, but a reason to poll slowly rather than tightly.
async function pollDeferred() {
  if (cancelled || !state.scheduled) return;
  state.polls++;
  try {
    const res = await client.call("GET", "/worktable/vardir");
    if (cancelled) return;
    state.info = res;

    const done = res.lastRun;
    if (done?.status === "ok") {
      state.deferOutcome = "ok";
      state.scheduled = { ...state.scheduled, to: done.to ?? state.scheduled.to };
      mount();
      probeExposure(res.currentName, res.probeFile);
      return;
    }
    if (done && done.status !== "ok") {
      state.deferOutcome = "failed";
      state.failure = { error: done.status, message: done.message ?? "" };
      mount();
      return;
    }
  } catch {
    // a failed check is not a failed rotation; keep waiting
  }
  if (state.polls >= MAX_POLLS) {
    state.deferOutcome = "timeout";
    mount();
    return;
  }
  mount();
  setTimeout(pollDeferred, POLL_EVERY_MS);
}

async function copyText(value, flag) {
  try {
    await navigator.clipboard.writeText(value);
    state[flag] = true;
  } catch {
    state[flag] = false;
  }
  mount();
  clearTimeout(copyTimer);
  copyTimer = setTimeout(() => { state[flag] = false; mount(); }, 2000);
}

const copySnippet = () => copyText(NGINX_SNIPPET, "copied");
const copyCommand = () => copyText(cliCommand(), "copiedCmd");

// The exact command to run on the server, with the real absolute path.
function cliCommand() {
  const path = state.failure?.cliScript || state.info?.cliScript || "";
  return path ? `php "${path}"` : "";
}

// ── View ───────────────────────────────────────────────────────────────────

function ExposureTag() {
  switch (state.exposure) {
    case "checking":  return html`<span class="tag is-light">${t("config.exposure.checking")}</span>`;
    case "exposed":   return html`<span class="tag is-danger">${t("config.exposure.exposed")}</span>`;
    case "protected": return html`<span class="tag is-success is-light">${t("config.exposure.protected")}</span>`;
    case "noprobe":   return html`<span class="tag is-light">${t("config.exposure.noprobe")}</span>`;
    default:          return "";
  }
}

function StateBox(info) {
  return html`
    <table class="table is-fullwidth is-vcentered">
      <tbody>
        <tr>
          <th style="width:16rem">${t("config.state.current")}</th>
          <td><code>${info.currentName}</code></td>
        </tr>
        <tr>
          <th>${t("config.state.rotated")}</th>
          <td>
            ${info.rotated
              ? html`<span class="tag is-success is-light">${t("config.state.rotated.yes")}</span>`
              : html`<span class="tag is-warning is-light">${t("config.state.rotated.no")}</span>`}
          </td>
        </tr>
        <tr>
          <th>${t("config.state.exposure")}</th>
          <td>
            ${ExposureTag()}
            ${info.probeFile && state.exposure !== "noprobe"
              ? html`<span class="is-size-7 has-text-grey ml-2">${t("config.exposure.probed", info.probeFile)}</span>`
              : ""}
          </td>
        </tr>
        <tr>
          <th>${t("config.state.driver")}</th>
          <td><span class="tag is-light">${info.dbDriver ?? "?"}</span></td>
        </tr>
        ${info.lastRun ? html`
          <tr>
            <th>${t("config.state.lastRun")}</th>
            <td>
              ${info.lastRun.status === "ok"
                ? html`<span class="tag is-success is-light">${t("config.lastRun.ok", info.lastRun.to ?? "", info.lastRun.at ?? "")}</span>`
                : html`<span class="tag is-danger is-light">${t("config.lastRun.failed", info.lastRun.status, info.lastRun.at ?? "")}</span>`}
            </td>
          </tr>
        ` : ""}
        <tr>
          <th>${t("config.state.stub")}</th>
          <td>
            ${info.stubPresent
              ? html`<code>${info.stubFile}</code>`
              : html`<span class="has-text-grey">${t("config.state.stub.none")}</span>`}
          </td>
        </tr>
      </tbody>
    </table>
  `;
}

function ChecksBox(info) {
  return html`
    <h5 class="title is-6 mb-2">${t("config.checks.title")}</h5>
    <table class="table is-fullwidth is-vcentered">
      <tbody>
        ${CHECK_KEYS.map(k => html`
          <tr>
            <th style="width:16rem">${t("config.check." + k)}</th>
            <td>
              ${info.checks[k]
                ? html`<span class="icon has-text-success"><i class="ri-check-line"></i></span>`
                : html`<span class="icon has-text-danger"><i class="ri-close-line"></i></span>`}
            </td>
          </tr>
        `)}
      </tbody>
    </table>
  `;
}

function Warnings(info) {
  if (!info.warnings?.length) return "";
  return html`
    <article class="message is-warning">
      <div class="message-body">
        ${info.warnings.includes("sqlite_inside") ? html`<p>${t("config.warn.sqlite")}</p>` : ""}
        ${info.warnings.includes("windows_open_handle") ? html`<p class="mt-2">${t("config.warn.windows")}</p>` : ""}
      </div>
    </article>
  `;
}

function ConfirmPanel(info) {
  return html`
    <article class="message is-danger">
      <div class="message-header"><p>${t("config.confirm.title")}</p></div>
      <div class="message-body">
        <p class="mb-3">${t("config.confirm.body")}</p>
        <p class="mb-3">
          <code>${info.currentName}</code>
          <span class="icon"><i class="ri-arrow-right-line"></i></span>
          <code>${info.targetName}</code>
        </p>
        <div class="buttons">
          <button
            class="button is-danger ${state.running ? "is-loading" : ""}"
            ?disabled=${state.running}
            @click=${rotate}
          >
            <span class="icon"><i class="ri-refresh-line"></i></span>
            <span>${t("config.btn.confirm")}</span>
          </button>
          <button class="button is-light" ?disabled=${state.running}
            @click=${() => { state.confirming = false; mount(); }}>
            <span>${t("config.btn.cancel")}</span>
          </button>
        </div>
      </div>
    </article>
  `;
}

// The verdict comes from the exposure probe, which tells us whether the data is
// reachable — not whether this particular rule is installed. Worded accordingly.
function NginxStatus() {
  switch (state.exposure) {
    case "exposed":
      return html`<article class="message is-danger mb-3"><div class="message-body">
        ${t("config.nginx.status.exposed")}</div></article>`;
    case "protected":
      return html`<article class="message is-success mb-3"><div class="message-body">
        ${t("config.nginx.status.protected")}</div></article>`;
    case "noprobe":
      return html`<article class="message is-warning mb-3"><div class="message-body">
        ${t("config.nginx.status.unknown")}</div></article>`;
    default:
      return "";
  }
}

function NginxBox() {
  return html`
    <div class="box">
      <h4 class="title is-6 mb-2">${t("config.nginx.title")}</h4>
      ${NginxStatus()}
      <p class="mb-3">${t("config.nginx.body")}</p>
      <div class="field has-addons">
        <div class="control is-expanded">
          <pre style="margin:0">${NGINX_SNIPPET}</pre>
        </div>
        <div class="control">
          <button class="button is-info" @click=${copySnippet}>
            <span class="icon"><i class="ri-file-copy-line"></i></span>
            <span>${t("config.btn.copy")}</span>
          </button>
        </div>
      </div>
      ${state.copied ? html`<p class="help is-success">${t("config.copied")}</p>` : ""}
    </div>
  `;
}

function App() {
  const info = state.info;
  return html`
    <div class="container pt-0 pb-4">
      <div class="box spa-title-box">
        <div class="level mb-4">
          <div class="level-left">
            <p class="is-size-5">${t("config.intro")}</p>
          </div>
          <div class="level-right">
            <button
              class="button is-primary"
              ?disabled=${state.loading || !info || !info.canRotate || state.confirming || state.running}
              @click=${() => { state.confirming = true; state.result = null; mount(); }}
            >
              <span class="icon"><i class="ri-refresh-line"></i></span>
              <span>${info?.rotated ? t("config.btn.rotateAgain") : t("config.btn.rotate")}</span>
            </button>
          </div>
        </div>

        ${state.error ? html`
          <article class="message is-danger">
            <div class="message-body">
              ${errorText(state.error)}
              <button class="button is-small is-light ml-3" @click=${load}>
                <span class="icon"><i class="ri-refresh-line"></i></span>
                <span>${t("config.btn.retry")}</span>
              </button>
            </div>
          </article>
        ` : ""}

        ${state.failure ? html`
          <article class="message is-danger">
            <div class="message-body">
              <p>${failureText(state.failure)}</p>
              ${state.failure.message ? html`<pre class="mt-2" style="white-space:pre-wrap">${state.failure.message}</pre>` : ""}
              ${state.failure.error === "rename_failed" ? html`
                <p class="mt-3"><strong>${t("config.deferred.offer.title")}</strong></p>
                <p>${t("config.deferred.offer.body")}</p>
                <button
                  class="button is-primary mt-2 ${state.running ? "is-loading" : ""}"
                  ?disabled=${state.running}
                  @click=${rotateDeferred}
                >
                  <span class="icon"><i class="ri-play-circle-line"></i></span>
                  <span>${t("config.btn.deferred")}</span>
                </button>
              ` : ""}
              ${state.failure.error === "rename_failed" && cliCommand() ? html`
                <p class="mt-4"><strong>${t("config.locked.howto.title")}</strong></p>
                <ol class="ml-5">
                  <li>${t("config.locked.step1")}</li>
                  <li>
                    ${t("config.locked.step2")}
                    <div class="field has-addons mt-2">
                      <div class="control is-expanded">
                        <pre class="tools-cmd" style="margin:0">${cliCommand()}</pre>
                      </div>
                      <div class="control">
                        <button class="button is-info" @click=${copyCommand}>
                          <span class="icon"><i class="ri-file-copy-line"></i></span>
                          <span>${t("config.btn.copy")}</span>
                        </button>
                      </div>
                    </div>
                    ${state.copiedCmd ? html`<p class="help is-success">${t("config.copied")}</p>` : ""}
                  </li>
                  <li>${t("config.locked.step3")}</li>
                </ol>
              ` : ""}
            </div>
          </article>
        ` : ""}

        ${state.scheduled && state.deferOutcome === null ? html`
          <article class="message is-info">
            <div class="message-body">
              <p><strong>${t("config.deferred.running.title")}</strong></p>
              <p class="mt-2">${t("config.deferred.running.body", state.scheduled.to)}</p>
              <progress class="progress is-small is-info mt-3"></progress>
            </div>
          </article>
        ` : ""}

        ${state.deferOutcome === "ok" ? html`
          <article class="message is-success">
            <div class="message-body">
              <p>${t("config.deferred.ok", state.scheduled?.to ?? "")}</p>
              <p class="mt-2">${t("config.deferred.ok.note")}</p>
              <button class="button is-small is-success mt-2" @click=${() => location.reload()}>
                <span class="icon"><i class="ri-refresh-line"></i></span>
                <span>${t("config.btn.reload")}</span>
              </button>
            </div>
          </article>
        ` : ""}

        ${state.deferOutcome === "timeout" ? html`
          <article class="message is-warning">
            <div class="message-body">
              <p>${t("config.deferred.timeout")}</p>
              <button class="button is-small is-light mt-2" @click=${load}>
                <span class="icon"><i class="ri-refresh-line"></i></span>
                <span>${t("config.btn.retry")}</span>
              </button>
            </div>
          </article>
        ` : ""}

        ${state.result?.ok ? html`
          <article class="message is-success">
            <div class="message-body">
              <p>${t("config.result.ok", state.result.to)}</p>
              <p class="mt-2">${t("config.result.reload")}</p>
              <button class="button is-small is-success mt-2" @click=${() => location.reload()}>
                <span class="icon"><i class="ri-refresh-line"></i></span>
                <span>${t("config.btn.reload")}</span>
              </button>
            </div>
          </article>
        ` : ""}

        ${state.loading ? html`<progress class="progress is-small is-primary"></progress>` : ""}

        ${info ? html`
          ${StateBox(info)}
          ${Warnings(info)}
          ${state.confirming ? ConfirmPanel(info) : ""}
          ${ChecksBox(info)}
        ` : ""}
      </div>

      ${NginxBox()}
    </div>
  `;
}

function mount() {
  render(App(), root);
}

mount();
load();
