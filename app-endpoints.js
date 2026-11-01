// app-endpoints.js
const VERSION = window.APP_CONFIG?.version || Date.now();

import(`./app-boot.js?v=${VERSION}`).then(({ mountSpa }) =>
  mountSpa({ view: "endpoints", exportName: "Endpoints" })
);
