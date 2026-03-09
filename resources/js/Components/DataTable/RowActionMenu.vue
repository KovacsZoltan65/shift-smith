<script setup>
import { computed, ref } from "vue";

import Button from "primevue/button";
import Menu from "primevue/menu";

const props = defineProps({
    items: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    buttonIcon: { type: String, default: "pi pi-ellipsis-v" },
    buttonSeverity: { type: String, default: "secondary" },
    buttonSize: { type: String, default: "small" },
    buttonTitle: { type: String, default: "" },
    menuAppendTo: { type: String, default: "body" },
});

const menu = ref(null);

const hasItems = computed(
    () => Array.isArray(props.items) && props.items.length > 0,
);

const openMenu = (event) => {
    if (!hasItems.value || props.disabled) {
        return;
    }

    menu.value?.toggle(event);
};
</script>

<template>
    <div class="inline-flex">
        <Menu
            ref="menu"
            :model="items"
            popup
            :appendTo="menuAppendTo"
        />

        <Button
            :icon="buttonIcon"
            :severity="buttonSeverity"
            :size="buttonSize"
            :disabled="disabled || !hasItems"
            :title="buttonTitle"
            text
            rounded
            @click="openMenu"
        />
    </div>
</template>
