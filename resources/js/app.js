import "../css/app.css";
import "./bootstrap";
import "@primeuix/styled";

import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createApp, h } from "vue";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";
import Tooltip from "primevue/tooltip";

import PrimeVue from "primevue/config";
import ToastService from "primevue/toastservice";
import { ConfirmationService } from "primevue";
import Aura from "@primevue/themes/aura";

import "primeicons/primeicons.css";
import Button from "primevue/button";
import Card from "primevue/card";
import InputText from "primevue/inputtext";
import Password from "primevue/password";
import Checkbox from "primevue/checkbox";
import Toast from "primevue/toast";
import Divider from "primevue/divider";
import Message from "primevue/message";

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
            .component("Button", Button)
            .component("Card", Card)
            .component("InputText", InputText)
            .component("Password", Password)
            .component("Checkbox", Checkbox)
            .component("Toast", Toast)
            .component("Divider", Divider)
            .component("Message", Message)
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
