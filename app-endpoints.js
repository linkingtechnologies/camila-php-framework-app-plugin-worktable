// app-endpoints.js
import { html, render } from "../../../../camila/js/lit-html/lit-html.js";

const root = document.getElementById("app");

if (typeof WorkTableClient !== "function") {
  render(html`<div class="notification is-danger">WorkTableClient not available</div>`, root);
  throw Error("WorkTableClient not available");
}

const t = (key, ...args) => {
  let s = window.I18N?.[key] ?? key;
  args.forEach(a => { s = s.replace('%s', a); });
  return s;
};

const state = {
  mcpUrl:     window.APP_CONFIG?.mcpDefaultUrl || "",
  restApiUrl: window.APP_CONFIG?.baseUrl || "",
  copy:       { mcp: null, restApi: null, claudeConfig: null }, // per-field: null | 'copied' | 'error'
};

const copyTimers = {};

function isPublicHttpsUrl(url) {
  try {
    const u = new URL(url);
    if (u.protocol !== "https:") return false;
    return !/^(localhost|127\.|\[?::1\]?|0\.0\.0\.0)/i.test(u.hostname);
  } catch {
    return false;
  }
}

function claudeDesktopConfig() {
  return JSON.stringify({
    mcpServers: {
      camila: {
        type: "http",
        url: state.mcpUrl,
        headers: { "X-API-Key": "<token>" },
      },
    },
  }, null, 2);
}

async function copyField(field, value) {
  try {
    await navigator.clipboard.writeText(value);
    state.copy[field] = "copied";
  } catch {
    state.copy[field] = "error";
  }
  mount();
  clearTimeout(copyTimers[field]);
  copyTimers[field] = setTimeout(() => { state.copy[field] = null; mount(); }, 2000);
}

function EndpointBox(labelKey, url, field) {
  return html`
    <div class="field">
      <label class="label">${t(labelKey)}</label>
      <div class="field has-addons">
        <div class="control is-expanded">
          <input
            class="input"
            type="text"
            readonly
            .value=${url}
            @click=${e => e.target.select()}
          />
        </div>
        <div class="control">
          <button class="button is-info" @click=${() => copyField(field, url)}>
            <span class="icon"><i class="ri-file-copy-line"></i></span>
            <span>${t("endpoints.btn.copy")}</span>
          </button>
        </div>
      </div>
      ${state.copy[field] === "copied" ? html`<p class="help is-success">${t("endpoints.copied")}</p>` : ""}
      ${state.copy[field] === "error" ? html`<p class="help is-danger">${t("endpoints.copyError")}</p>` : ""}
    </div>
  `;
}

function ClaudeDesktopBox() {
  const config = claudeDesktopConfig();
  return html`
    <div class="box mb-4">
      <h4 class="title is-6 mb-3">${t("endpoints.claudeDesktop.title")}</h4>
      <p class="mb-2">${t("endpoints.claudeDesktop.step1")}</p>
      <p class="mb-3">${t("endpoints.claudeDesktop.step2")}</p>
      <p class="mb-1"><strong>${t("endpoints.claudeDesktop.configLabel")}</strong></p>
      <div class="field has-addons">
        <div class="control is-expanded">
          <pre style="margin:0;white-space:pre-wrap;word-break:break-word;">${config}</pre>
        </div>
        <div class="control">
          <button class="button is-info" @click=${() => copyField("claudeConfig", config)}>
            <span class="icon"><i class="ri-file-copy-line"></i></span>
            <span>${t("endpoints.btn.copy")}</span>
          </button>
        </div>
      </div>
      ${state.copy.claudeConfig === "copied" ? html`<p class="help is-success">${t("endpoints.copied")}</p>` : ""}
      ${state.copy.claudeConfig === "error" ? html`<p class="help is-danger">${t("endpoints.copyError")}</p>` : ""}
    </div>
  `;
}

function OpenAiBox() {
  return html`
    <div class="box mb-4">
      <h4 class="title is-6 mb-3">${t("endpoints.openai.title")}</h4>
      <p class="mb-2">${t("endpoints.openai.step1")}</p>
      <p class="mb-2">${t("endpoints.openai.step2")}</p>
      <p class="mb-3">${t("endpoints.openai.step3")}</p>
      <p class="has-text-grey mb-3">${t("endpoints.openai.limits")}</p>
      ${!isPublicHttpsUrl(state.mcpUrl) ? html`
        <article class="message is-warning mb-3">
          <div class="message-body">${t("endpoints.openai.warnNotPublic")}</div>
        </article>
      ` : ""}
      <article class="message is-warning">
        <div class="message-body">${t("endpoints.openai.warnAuth")}</div>
      </article>
    </div>
  `;
}

function App() {
  return html`
    <div class="container pt-0 pb-4">
      <div class="box spa-title-box">
        ${EndpointBox("endpoints.mcpEndpoint.label", state.mcpUrl, "mcp")}
        ${EndpointBox("endpoints.restApi.label", state.restApiUrl, "restApi")}
      </div>
      ${ClaudeDesktopBox()}
      ${OpenAiBox()}
    </div>
  `;
}

function mount() {
  render(App(), root);
}

mount();
