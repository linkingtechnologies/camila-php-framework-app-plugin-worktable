// app-tools.js
const VERSION = window.APP_CONFIG?.version || Date.now();

import(`./app-boot.js?v=${VERSION}`).then(({ mountSpa }) =>
  mountSpa({ view: "tools", exportName: "Tools" })
);
