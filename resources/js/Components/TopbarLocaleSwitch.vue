<script setup>
import { ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import { usePreferences } from "@/composables/usePreferences";
import LocaleSelector from "@/Components/Selectors/LocaleSelector.vue";

const page = usePage();
const resolveOptions = () => {
    const options = page.props?.available_locales;

    return Array.isArray(options) && options.length > 0
        ? options
        : [
              { label: "English", value: "en" },
              { label: "Magyar", value: "hu" },
          ];
};
const resolveLocale = () =>
    document.documentElement.getAttribute("lang") ??
    page.props?.preferences?.locale ??
    page.props?.locale ??
    "hu";

const locale = ref(resolveLocale());
const localeOptions = ref(resolveOptions());
const { setLocale } = usePreferences();

watch(
    () => [page.props?.preferences?.locale, page.props?.locale, page.props?.available_locales],
    () => {
        locale.value = resolveLocale();
        localeOptions.value = resolveOptions();
    },
);

const change = async (val) => {
    try {
        await setLocale(val);
    } catch (error) {
        locale.value = resolveLocale();
        console.error("Locale switch failed", error);
    }
};
</script>
<template>
    <div class="inline-flex items-center gap-2">
        <span class="text-xs uppercase opacity-70">{{ $t("language") }}</span>

        <div class="w-36">
            <LocaleSelector v-model="locale" :options="localeOptions" @change="change" />
        </div>
    </div>
</template>
