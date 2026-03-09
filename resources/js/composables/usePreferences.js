import { router } from "@inertiajs/vue3";
import { loadLanguageAsync } from "laravel-vue-i18n";
import { csrfFetch } from "@/lib/csrfFetch";

export function usePreferences() {
    //
    const setHtmlLang = (lang) =>
        document.documentElement.setAttribute("lang", lang);

    const setThemeClass = (theme) => {
        const root = document.documentElement;
        root.classList.remove("theme-light", "theme-dark");
        if (theme === "light") root.classList.add("theme-light");
        if (theme === "dark") root.classList.add("theme-dark");
        // 'system' esetnél ne adjunk class-t, CSS media query intézi
    };

    const setLocale = async (locale) => {
        const response = await csrfFetch(route("preferences.locale"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({ locale }),
        });

        if (!response.ok) {
            throw new Error(`Locale update failed (${response.status})`);
        }

        await loadLanguageAsync(locale); // fordítások betöltése + váltás
        setHtmlLang(locale);
        await router.reload({ preserveState: true, preserveScroll: true });
    };

    const setTimezone = async (timezone) => {
        const response = await csrfFetch(route("preferences.timezone"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({ timezone }),
        });

        if (!response.ok) {
            throw new Error(`Timezone update failed (${response.status})`);
        }

        await router.reload({ preserveState: true, preserveScroll: true });
    };

    const setTheme = async (theme) => {
        const response = await csrfFetch(route("preferences.theme"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({ theme }),
        });

        if (!response.ok) {
            throw new Error(`Theme update failed (${response.status})`);
        }

        setThemeClass(theme);
        await router.reload({ preserveState: true, preserveScroll: true });
    };

    return { setLocale, setTimezone, setTheme };
}
