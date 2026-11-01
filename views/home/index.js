// views/home/index.js — placeholder welcome page, to be expanded later
// handed over by app-boot.js when the view is mounted
let html, render, root, client;

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

// ── Entry ─────────────────────────────────────────────────────────────────

export async function Home(ctx) {
  ({ html, render, root, client } = ctx);
  return App();
}
