// app-boot.js — shared bootstrap for the SPA entry points of this plugin.
//
// Every app-<id>.js was a copy of the same lines: find #app, check that
// WorkTableClient is there, build it, dynamically import the view, render it,
// show an error box if anything fails. That skeleton lives here now. (Same file as
// the one in the segreteria-campo plugin, with the texts in English and Italian.)
//
// WHY THIS FILE IS IMPORTED DYNAMICALLY, AND WITH A VERSION
//
// mountMiniApp() emits `<script type="module" src="./plugins/<p>/app-<id>.js">`
// with no query string, so the browser caches the entry point indefinitely.
// That is why entry points are thin shells and all the real code sits behind
// versioned `import(...?v=VERSION)` calls. A static `import "./app-boot.js"`
// from an entry point would inherit that same cacheless URL and freeze this
// file in the browser too - the exact trap AGENTS.md describes for
// views/shared. Entry points must therefore do:
//
//   import(`./app-boot.js?v=${VERSION}`).then(({ mountSpa }) => mountSpa({...}))
//
import { html, render } from "../../../../camila/js/lit-html/lit-html.js";
import "./no-pull-refresh.js";

const VERSION = window.APP_CONFIG?.version || Date.now();

// The page language comes from the dashboard (APP_CONFIG.lang), then from <html lang>.
const TEXT = {
  en: {
    loading: "Loading…", details: "Technical details", unknown: "Unknown error",
    initError: "Initialization error", noClient: "WorkTableClient is not available",
    startError: "Startup error", wizardError: "Wizard render error",
    sessionTitle: "Session no longer valid",
    session1: "This page is no longer linked to your login, so it cannot read the data.",
    session2a: "It almost always happens when ", session2b: "the same user signs in from another browser or device",
    session2c: ": only the latest sign-in counts, and the tabs opened before stop working.",
    session3a: "To carry on from here you have to ", session3b: "sign in again",
    session3c: ": it is the sign-in that links the session to this tab. Reloading alone is not enough. Note that signing in from here will leave the other device out.",
    relogin: "Sign out and sign in again", reload: "Reload the page",
    reloadNote: "Reloading only helps if the sign-in has been redone in this tab in the meantime."
  },
  it: {
    loading: "Caricamento…", details: "Dettagli tecnici", unknown: "Errore sconosciuto",
    initError: "Errore inizializzazione", noClient: "WorkTableClient non disponibile",
    startError: "Errore avvio", wizardError: "Errore render wizard",
    sessionTitle: "Sessione non più valida",
    session1: "Questa pagina non è più collegata al tuo accesso, quindi non riesce a leggere i dati.",
    session2a: "Succede quasi sempre quando ", session2b: "lo stesso utente entra da un altro browser o da un altro dispositivo",
    session2c: ": vale solo l'accesso più recente, e le schede aperte prima smettono di funzionare.",
    session3a: "Per riprendere da qui devi ", session3b: "rifare l'accesso",
    session3c: ": è l'accesso che riaggancia la sessione a questa scheda. Ricaricare da solo non basta. Tieni presente che rientrando da qui sarà l'altra postazione a restare fuori.",
    relogin: "Esci e rientra", reload: "Ricarica la pagina",
    reloadNote: "Ricaricare serve solo se nel frattempo l'accesso è stato rifatto in questa scheda."
  }
};
const tx = () => {
  const l = String(window.APP_CONFIG?.lang || document.documentElement.lang || "en").slice(0, 2).toLowerCase();
  return TEXT[l] || TEXT.en;
};

const loadingSpinner = () => html`
  <style>
    .spa-boot-spin {
      animation: spa-boot-spin 1s linear infinite;
    }
    @keyframes spa-boot-spin {
      to {
        transform: rotate(360deg);
      }
    }
  </style>
  <div
    style="display:flex;align-items:center;justify-content:center;gap:.75rem;margin-top:2rem"
  >
    <span class="icon spa-boot-spin"><i class="ri-loader-4-line ri-lg"></i></span>
    <span class="has-text-grey">${tx().loading}</span>
  </div>
`;

// php-crud-api error codes (ErrorCode in api.include.php).
const ERR_AUTH_REQUIRED = 1011;
const ERR_AUTH_FAILED = 1012;

// The client throws Error("HTTP <status>") with .status and .payload, where
// payload is the API's JSON body: { code, message }. Reading .payload with
// String() is what printed "[object Object]" in the box.
function describeApiError(err) {
  const payload = err?.payload;
  const fromPayload =
    typeof payload === "string"
      ? payload
      : typeof payload?.message === "string"
      ? payload.message
      : "";

  return {
    status: err?.status,
    code: typeof payload?.code === "number" ? payload.code : undefined,
    message: fromPayload || err?.message || String(err || tx().unknown),
  };
}

// The session died, rather than something being broken. In this app it has one
// cause above all others: when the request carries an Authorization header the
// API ignores its value, starts the PHP session and looks for a user row whose
// `session_id` column equals session_id() (api.include.php:4805). That column
// holds ONE session per user and is written AT LOGIN (header.php:234), so a
// second login by the same user from another browser or device takes it over
// and every earlier tab stops being recognised.
function isSessionError({ status, code }) {
  // 403 is also "Operation forbidden" (1014), a permission refusal and not a
  // dead session, so when the API gave a code the code decides. The status
  // is only a fallback for answers that carry no JSON body.
  if (typeof code === "number") {
    return code === ERR_AUTH_REQUIRED || code === ERR_AUTH_FAILED;
  }
  return status === 401 || status === 403;
}

function technicalDetails(err) {
  return html`
    <details class="mt-3">
      <summary class="is-size-7">${tx().details}</summary>
      <pre style="white-space:pre-wrap;margin:.5rem 0 0">
${JSON.stringify(err, null, 2)}</pre
      >
    </details>
  `;
}

// Single error box for every SPA. The entry points had two variants of it,
// differing only in the wording of the <details> summary.
function showError(root, title, err) {
  const info = describeApiError(err);

  if (isSessionError(info)) {
    render(
      html`
        <section class="section">
          <div class="container">
            <article class="message is-warning">
              <div class="message-header"><p>${tx().sessionTitle}</p></div>
              <div class="message-body">
                <p>${tx().session1}</p>
                <p class="mt-2">
                  ${tx().session2a}<strong>${tx().session2b}</strong>${tx().session2c}
                </p>
                <p class="mt-2">
                  ${tx().session3a}<strong>${tx().session3b}</strong>${tx().session3c}
                </p>
                <div class="buttons mt-4">
                  <a class="button is-primary" href="cf_logout.php">
                    <span class="icon"><i class="ri-login-box-line"></i></span>
                    <span>${tx().relogin}</span>
                  </a>
                  <button
                    class="button is-light"
                    @click=${() => window.location.reload()}
                  >
                    <span class="icon"><i class="ri-refresh-line"></i></span>
                    <span>${tx().reload}</span>
                  </button>
                </div>
                <p class="is-size-7 has-text-grey">${tx().reloadNote}</p>
                ${technicalDetails(err)}
              </div>
            </article>
          </div>
        </section>
      `,
      root
    );
    return;
  }

  render(
    html`
      <section class="section">
        <div class="container">
          <article class="message is-danger">
            <div class="message-header"><p>${title}</p></div>
            <div class="message-body">
              <p>${info.message}</p>
              ${technicalDetails(err)}
            </div>
          </article>
        </div>
      </section>
    `,
    root
  );
}

/**
 * Mounts a SPA of this plugin into #app.
 *
 * Single view:
 *   mountSpa({ view: "comms-feed", exportName: "CommsFeed" })
 *
 * Wizard (views/<view>/step1.js … stepN.js, each exporting Step<n>):
 *   mountSpa({
 *     view: "massive-check-out",
 *     steps: 4,
 *     state: { org: null },
 *     guards: { 2: (s) => !!s.org?.name },
 *   })
 *
 * @param {Object}   o
 * @param {string}   o.view        directory under views/
 * @param {string}  [o.exportName] named export of the view module (single view)
 * @param {string}  [o.module]     file inside that directory, default index.js
 * @param {number}  [o.steps]      number of wizard steps; 0 = single view
 * @param {Object}  [o.state]      initial state, merged over { step: 1 }
 * @param {Object}  [o.guards]     { step: (state) => boolean }; false sends back to step 1
 * @param {string}  [o.loading]    "blank" (default) or "spinner"
 */
export function mountSpa({
  view,
  exportName,
  module = "index.js",
  steps = 0,
  state: initialState = {},
  guards = {},
  loading = "blank",
} = {}) {
  const root = document.getElementById("app");

  if (typeof WorkTableClient !== "function") {
    showError(root, tx().initError, tx().noClient);
    throw new Error(tx().noClient);
  }

  const client = WorkTableClient(window.APP_CONFIG || {});
  const state = { step: 1, ...initialState };
  let guide = null;

  // Wizard steps are guarded on every render, not only on the transition that
  // leads to them: a reload or a stale state must not land on a step whose
  // preconditions are gone (AGENTS.md §SPA Entry Points).
  function resolveStep() {
    let n = Number(state.step) || 1;
    if (n < 1 || n > steps) n = 1;
    const guard = guards[n];
    if (typeof guard === "function" && !guard(state)) n = 1;
    return n;
  }

  async function mountWizard() {
    const n = resolveStep();
    state.step = n;
    const mod = await import(`./views/${view}/step${n}.js?v=${VERSION}`);
    const Step = mod[`Step${n}`];
    if (typeof Step !== "function") {
      throw new Error(`views/${view}/step${n}.js does not export Step${n}`);
    }
    render(await Step({ state, client, goTo, html, render, root }), root);
    guide?.sync();   // an open guide follows the step
  }

  async function mountView() {
    const mod = await import(`./views/${view}/${module}?v=${VERSION}`);
    const View = mod[exportName];
    if (typeof View !== "function") {
      throw new Error(`views/${view}/${module} does not export ${exportName}`);
    }
    render(await View({ state, client, html, render, root }), root);
  }

  async function mount() {
    try {
      await (steps > 0 ? mountWizard() : mountView());
    } catch (e) {
      showError(root, steps > 0 ? tx().wizardError : tx().startError, e);
    }
  }

  function goTo(step) {
    state.step = step;
    mount();
  }

  // The guide is optional: a SPA gets the "?" button only if docs/guide/guide.json
  // lists it, the kiosk (totem) never gets it, and nothing here may break the SPA.
  async function setupGuide() {
    try {
      if (new URLSearchParams(window.location.search).get("totem") === "1") return;
      const res = await fetch(new URL(`./docs/guide/guide.json?v=${VERSION}`, import.meta.url));
      if (!res.ok) return;
      const manifest = await res.json();
      // "floating": false keeps the guide in the manual and behind links, without the
      // button on this page (the Smart Assistant lives in the home: a "?" there would
      // read as help for the whole home).
      const entry = manifest.guides?.find(g => g.view === view);
      if (!entry || entry.floating === false) return;
      const mod = await import(`./views/shared/guide.js?v=${VERSION}`);
      guide = mod.createGuideDrawer({
        html,
        render,
        view,
        manifest,
        version: VERSION,
        getAnchor: () => (steps > 0 ? `passo-${state.step}` : null)
      });
    } catch (e) {
      console.warn("guide:", e);
    }
  }

  render(loading === "spinner" ? loadingSpinner() : html`<div></div>`, root);
  mount();
  setupGuide();

  return { state, goTo, mount };
}
