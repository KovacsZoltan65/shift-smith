<script setup>
import { ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import { usePreferences } from "@/composables/usePreferences";
import LocaleSelector from "@/Components/Selectors/LocaleSelector.vue";

const page = usePage();
const resolveLocale = () =>
    document.documentElement.getAttribute("lang") ??
    page.props?.preferences?.locale ??
    page.props?.locale ??
    "hu";

const locale = ref(resolveLocale());
const { setLocale } = usePreferences();

watch(
    () => [page.props?.preferences?.locale, page.props?.locale],
    () => {
        locale.value = resolveLocale();
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
            <LocaleSelector v-model="locale" @change="change" />
        </div>
    </div>
</template>
