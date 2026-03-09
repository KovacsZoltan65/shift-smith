<script setup>
import { computed } from "vue";
import Select from "primevue/select";

import { useLocaleSwitcher } from "@/composables/useLocaleSwitcher";

const props = defineProps({
    inputId: {
        type: String,
        default: "locale-switcher",
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const { currentLocale, isSwitching, localeOptions, switchLocale } =
    useLocaleSwitcher();

const selectedLocale = computed({
    get: () => currentLocale.value,
    set: (value) => switchLocale(value),
});
</script>

<template>
    <div
        class="flex items-center gap-2"
        :class="{ 'w-full flex-col items-stretch': compact }"
    >
        <label
            :for="inputId"
            class="text-xs font-medium text-gray-500"
            :class="{ 'sr-only': !compact }"
        >
            {{ $t("common.language") }}
        </label>

        <Select
            :inputId="inputId"
            v-model="selectedLocale"
            :options="localeOptions"
            optionLabel="label"
            optionValue="value"
            :disabled="isSwitching"
            class="min-w-32"
            size="small"
        />
    </div>
</template>
