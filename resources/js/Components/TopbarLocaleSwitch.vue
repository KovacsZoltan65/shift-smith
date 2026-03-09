<script setup>
import { ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import { usePreferences } from "@/composables/usePreferences";
import LocaleSelector from "@/Components/Selectors/LocaleSelector.vue";

const { props } = usePage();
const locale = ref(props.preferences?.locale ?? props.locale ?? "hu");
const { setLocale } = usePreferences();
const change = async (val) => {
    try {
        await setLocale(val);
    } catch (error) {
        locale.value = props.preferences?.locale ?? props.locale ?? "hu";
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
