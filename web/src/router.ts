import { createRouter, createWebHashHistory } from "vue-router";
import Cookies from "js-cookie";

import page_index from "./pages/index.vue";

Cookies.remove("token");

const routes = [
  { path: "/", component: page_index, meta: { requiresAuth: true } },
  { path: "/login", component: () => import("./pages/account/login.vue") },
  { path: "/:pathMatch(.*)*", redirect: "/" },
];

const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

router.beforeEach(async (to) => {
  const hasUsername = Cookies.get("username") !== undefined;

  if (to.meta.requiresAuth && !hasUsername) {
    return "/login";
  }

  if (to.meta.requiresAuth || to.path === "/login") {
    const isAuthed = await checkSession();

    if (to.meta.requiresAuth && !isAuthed) {
      Cookies.remove("username");
      return "/login";
    }

    if (to.path === "/login" && isAuthed) {
      return "/";
    }
  }
});

async function checkSession() {
  try {
    const response = await fetch("/api/admin/me", {
      credentials: "include",
      headers: { Accept: "application/json" },
    });
    const result = await response.json();

    if (!response.ok || result.code !== 0) {
      return false;
    }

    Cookies.set("username", result.data.username, { expires: 30, path: "/" });
    return true;
  } catch {
    return false;
  }
}

export default router;
