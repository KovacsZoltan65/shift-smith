import { router, usePage } from "@inertiajs/vue3";
import {
    getActiveLanguage,
    loadLanguageAsync,
    trans,
} from "laravel-vue-i18n";
import { computed, ref, watch } from "vue";

/**
 * A backend settings alapú locale és a frontend i18n állapot közötti szinkront kezeli.
 */
export function useLocaleSwitcher() {
    const page = usePage();
    const isSwitching = ref(false);
    const currentLocale = ref(
        page.props?.locale || getActiveLanguage() || "en",
    );

    const supportedLocales = computed(() => page.props?.supported_locales ?? []);

    const availableLocales = computed(() =>
        (page.props?.available_locales ?? []).filter((locale) =>
            supportedLocales.value.includes(locale.code),
        ),
    );

    const localeOptions = computed(() =>
        availableLocales.value.map((locale) => {
            const key = `locales.${locale.code}`;
            const translated = trans(key);

            return {
                label: translated !== key ? translated : locale.name,
                value: locale.code,
            };
        }),
    );

    watch(
        () => page.props?.locale,
        async (locale) => {
            if (!locale || locale === currentLocale.value) {
                currentLocale.value = locale || currentLocale.value;
                return;
            }

            currentLocale.value = locale;

            if (locale !== getActiveLanguage()) {
                await loadLanguageAsync(locale);
            }
        },
        { immediate: true },
    );

    const switchLocale = (locale) => {
        if (
            isSwitching.value ||
            !supportedLocales.value.includes(locale) ||
            locale === currentLocale.value
        ) {
            return;
        }

        isSwitching.value = true;

        router.post(
            route("locale.update"),
            { locale },
            {
                preserveState: false,
                preserveScroll: true,
                onSuccess: async (nextPage) => {
                    const nextLocale = nextPage.props?.locale || locale;
                    currentLocale.value = nextLocale;
                    await loadLanguageAsync(nextLocale);
                },
                onError: () => {
                    currentLocale.value = page.props?.locale || "en";
                },
                onFinish: () => {
                    isSwitching.value = false;
                },
            },
        );
    };

    return {
        currentLocale,
        isSwitching,
        localeOptions,
        switchLocale,
    };
}
