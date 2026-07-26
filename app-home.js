// app-home.js — placeholder welcome page, to be expanded later
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

function App() {
  return html`
    <div class="container pt-0 pb-4">
      <div class="box spa-title-box">
        <h4 class="title is-6 mb-2">${t("home.welcome.title")}</h4>
        <p>${t("home.welcome.message")}</p>
      </div>
    </div>
  `;
}

render(App(), root);
