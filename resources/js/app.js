import "../css/app.css";
import "./bootstrap";
import "@primeuix/styled";

import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createApp, h } from "vue";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";
import Tooltip from "primevue/tooltip";

import { i18nVue } from "laravel-vue-i18n";

import PrimeVue from "primevue/config";
import ToastService from "primevue/toastservice";
import { ConfirmationService } from "primevue";
import Aura from "@primevue/themes/aura";

import "primeicons/primeicons.css";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue"),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18nVue, {
                locale:
                    props?.initialPage?.props?.preferences?.locale ||
                    document.documentElement.getAttribute("lang") ||
                    "hu",
                fallbackLocale: "hu",
                resolve: async (lang) => {
                    const messages = import.meta.glob("../../lang/*.json"); // */
                    return await messages[`../../lang/${lang}.json`]();
                },
            })
            .use(PrimeVue, {
                ripple: true,
                theme: {
                    preset: Aura,
                },
            })
            .use(ToastService)
            .use(ConfirmationService)
            .use(ZiggyVue)
            .directive("tooltip", Tooltip)
            .mixin({
                methods: {
                    can: (permissions) => {
                        const all = this.$page.props.auth.can || {};
                        const list = Array.isArray(permissions)
                            ? permissions
                            : typeof permissions === "string" &&
                                permissions.length
                              ? permissions.split(/[|,]/).map((s) => s.trim())
                              : [];
                        if (!list.length) return true;
                        return list.some((p) => !!all[p]);
                    },
                },
            })
            .mount(el);
    },
    progress: {
        color: "#4B5563",
    },
});
