<script setup>
import { computed, ref } from "vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";

import CompanySettingsService from "@/services/CompanySettingsService.js";

const props = defineProps({
    modelValue: Boolean,
    item: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "deleted"]);
const loading = ref(false);
const error = ref("");
const title = computed(() => props.item?.key ?? "kiválasztott elem");

const close = () => emit("update:modelValue", false);

const submit = async () => {
    if (!props.item?.id) return;
    loading.value = true;
    error.value = "";

    try {
        const { data } = await CompanySettingsService.destroy(props.item.id);
        emit("deleted", data?.message ?? "Company setting törölve.");
        close();
    } catch (err) {
        error.value = err?.response?.data?.message ?? err?.message ?? "Törlési hiba";
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Dialog :visible="modelValue" modal header="Company setting törlése" :style="{ width: '30rem' }" @update:visible="emit('update:modelValue', $event)">
        <div class="space-y-3">
            <p class="text-sm text-gray-700">Biztosan törlöd ezt a beállítást: <strong>{{ title }}</strong>?</p>
            <div v-if="error" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>
        </div>

        <template #footer>
            <Button label="Mégse" severity="secondary" :disabled="loading" @click="close" />
            <Button label="Törlés" severity="danger" :loading="loading" @click="submit" />
        </template>
    </Dialog>
</template>
