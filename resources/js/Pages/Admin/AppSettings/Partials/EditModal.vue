<script setup>
import { reactive, ref, watch } from "vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";

import AppSettingsFields from "./AppSettingsFields.vue";
import AppSettingsService from "@/services/AppSettingsService.js";

const props = defineProps({
    modelValue: Boolean,
    appSettingId: { type: Number, default: null },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const loading = ref(false);
const bootstrapLoading = ref(false);
const errors = reactive({});
const form = ref({
    key: "",
    group: "leave",
    type: "string",
    label: "",
    description: "",
    value: "",
});

const hydrate = async () => {
    if (!props.appSettingId) return;

    bootstrapLoading.value = true;
    Object.keys(errors).forEach((key) => delete errors[key]);

    try {
        const { data } = await AppSettingsService.show(props.appSettingId);
        const setting = data?.data;
        form.value = {
            key: setting?.key ?? "",
            group: setting?.group ?? "leave",
            type: setting?.type ?? "string",
            label: setting?.label ?? "",
            description: setting?.description ?? "",
            value:
                setting?.type === "json"
                    ? JSON.stringify(setting?.value ?? {}, null, 2)
                    : (setting?.value ?? (setting?.type === "bool" ? false : "")),
        };
    } catch (error) {
        errors._global = error?.response?.data?.message ?? error?.message ?? "Betöltési hiba";
    } finally {
        bootstrapLoading.value = false;
    }
};

watch(
    () => [props.modelValue, props.appSettingId],
    ([open]) => {
        if (open) hydrate();
    },
    { immediate: false },
);

const close = () => emit("update:modelValue", false);

const submit = async () => {
    if (!props.appSettingId) return;

    loading.value = true;
    Object.keys(errors).forEach((key) => delete errors[key]);

    try {
        const { data } = await AppSettingsService.update(props.appSettingId, form.value);
        emit("saved", {
            message: data?.message ?? "App setting frissítve.",
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
        header="App setting szerkesztése"
        :style="{ width: '44rem' }"
        @update:visible="emit('update:modelValue', $event)"
    >
        <div class="space-y-4">
            <div v-if="errors._global" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ errors._global }}
            </div>

            <div v-if="bootstrapLoading" class="text-sm text-gray-600">Betöltés...</div>
            <AppSettingsFields v-else v-model="form" :errors="errors" :disabled="loading" />
        </div>

        <template #footer>
            <Button label="Mégse" severity="secondary" :disabled="loading || bootstrapLoading" @click="close" />
            <Button label="Mentés" :loading="loading" :disabled="bootstrapLoading" @click="submit" />
        </template>
    </Dialog>
</template>
