<script setup>
import { computed } from "vue";

const props = defineProps({
    modelValue: { type: String, default: "" },
    options: {
        type: Array,
        default: () => [
            { label: "English", value: "en" },
            { label: "Magyar", value: "hu" },
        ],
    },
    // FONTOS: kulcs legyen, ne lefordított szöveg
    placeholderKey: { type: String, default: "select_language" },

    /* ↓ ezek az űrlapgenerátorból jönnek kényelmi okból */
    id: { type: String, default: "" }, // label for-hoz
    invalid: { type: Boolean, default: false },
    inputClass: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue", "change"]);

const selectedLocale = computed({
    get: () => props.modelValue,
    set: (value) => {
        emit("update:modelValue", value);
        emit("change", value);
    },
});
</script>

<template>
    <Select
        v-model="selectedLocale"
        :inputId="id || undefined"
        :options="options"
        optionLabel="label"
        optionValue="value"
        :placeholder="$t(placeholderKey)"
        :class="[inputClass, { 'p-invalid': invalid }]"
        class="w-full"
    />
</template>
