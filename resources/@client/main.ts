import { createApp } from "vue";
import { createPinia } from "pinia";
import App from "./App.vue";
import router from "./router";
import "./assets/css/app.css";
import { useAuthStore } from "@/stores/auth";

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);

const authStore = useAuthStore(pinia);

router.beforeEach(async (to) => {
	if (!authStore.initialized) {
		await authStore.initialize();
	}

	if (to.meta.requiresAuth && !authStore.isAuthenticated) {
		return {
			name: "login",
			query: { redirect: to.fullPath },
		};
	}

	if (to.name === "login" && authStore.isAuthenticated) {
		const redirectPath = (to.query.redirect as string) || "/";
		return { path: redirectPath };
	}

	return true;
});

app.use(router);

app.mount("#app");
