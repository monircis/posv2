import { createApp } from "vue";
import "./assets/css/style.css";
import App from "./App.vue";
import router from "./router";
import { bindRouter } from "./erp/runtime/nav.js";
import { pseudo } from "./erp/runtime/pseudo.js";
import { applyTheme, savedTheme } from "./erp/theme.js";

bindRouter(router);
applyTheme(savedTheme());

createApp(App).use(router).directive("pseudo", pseudo).mount("#app");
