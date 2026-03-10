<script setup>
import { reactive, ref, watch } from "vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";

import AppSettingsFields from "./AppSettingsFields.vue";
import AppSettingsService from "@/services/AppSettingsService.js";

const props = defineProps({
    modelValue: Boolean,
});

const emit = defineEmits(["update:modelValue", "saved"]);

const loading = ref(false);
const errors = reactive({});
const form = ref({});

const reset = () => {
    form.value = {
        key: "",
        group: "leave",
        type: "string",
        label: "",
        description: "",
        value: "",
    };

    Object.keys(errors).forEach((key) => delete errors[key]);
};

watch(
    () => props.modelValue,
    (open) => {
        if (open) reset();
    },
);

const close = () => emit("update:modelValue", false);

const submit = async () => {
    loading.value = true;
    Object.keys(errors).forEach((key) => delete errors[key]);

    try {
        const { data } = await AppSettingsService.store(form.value);
        emit("saved", {
            message: data?.message ?? "App setting létrehozva.",
            item: data?.data ?? null,
        });
        close();
    } catch (error) {
        const bag = AppSettingsService.extractErrors(error) ?? {};
        Object.keys(bag).forEach((key) => {
            errors[key] = bag[key]?.[0] ?? "Hiba";
        });
        if (!Object.keys(bag).length) {
            errors._global = error?.response?.data?.message ?? error?.message ?? "Ismeretlen hiba";
        }
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Dialog
        :visible="modelValue"
        modal
        header="Új app setting"
        :style="{ width: '44rem' }"
        @update:visible="emit('update:modelValue', $event)"
    >
        <div class="space-y-4">
            <div v-if="errors._global" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ errors._global }}
            </div>

            <AppSettingsFields v-model="form" :errors="errors" :disabled="loading" />
        </div>

        <template #footer>
            <Button label="Mégse" severity="secondary" :disabled="loading" @click="close" />
            <Button label="Mentés" :loading="loading" @click="submit" />
        </template>
    </Dialog>
</template>
