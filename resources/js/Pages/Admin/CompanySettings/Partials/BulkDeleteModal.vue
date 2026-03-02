<script setup>
import { computed, ref } from "vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";

import CompanySettingsService from "@/services/CompanySettingsService.js";

const props = defineProps({
    modelValue: Boolean,
    items: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:modelValue", "deleted"]);
const loading = ref(false);
const error = ref("");
const count = computed(() => props.items?.length ?? 0);

const close = () => emit("update:modelValue", false);

const submit = async () => {
    const ids = (props.items ?? []).map((item) => item.id).filter(Boolean);
    if (!ids.length) return;

    loading.value = true;
    error.value = "";

    try {
        const { data } = await CompanySettingsService.bulkDestroy(ids);
        emit("deleted", data?.message ?? "A kijelölt beállítások törölve.");
        close();
    } catch (err) {
        error.value = err?.response?.data?.message ?? err?.message ?? "Bulk törlési hiba";
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Dialog :visible="modelValue" modal header="Kijelöltek törlése" :style="{ width: '30rem' }" @update:visible="emit('update:modelValue', $event)">
        <div class="space-y-3">
            <p class="text-sm text-gray-700">Biztosan törlöd a kijelölt <strong>{{ count }}</strong> company settinget?</p>
            <div v-if="error" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>
        </div>

        <template #footer>
            <Button label="Mégse" severity="secondary" :disabled="loading" @click="close" />
            <Button label="Törlés" severity="danger" :loading="loading" :disabled="!count" @click="submit" />
        </template>
    </Dialog>
</template>
