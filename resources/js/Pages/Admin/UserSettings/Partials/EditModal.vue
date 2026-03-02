<script setup>
import { reactive, ref, watch } from "vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";

import UserSettingsFields from "./UserSettingsFields.vue";
import UserSettingsService from "@/services/UserSettingsService.js";

const props = defineProps({
    modelValue: Boolean,
    userSettingId: { type: Number, default: null },
    targetUserId: { type: Number, default: null },
});

const emit = defineEmits(["update:modelValue", "saved"]);
const loading = ref(false);
const bootstrapLoading = ref(false);
const errors = reactive({});
const form = ref({});

const hydrate = async () => {
    if (!props.userSettingId) return;
    bootstrapLoading.value = true;
    Object.keys(errors).forEach((key) => delete errors[key]);

    try {
        const { data } = await UserSettingsService.show(props.userSettingId, { user_id: props.targetUserId });
        const setting = data?.data;
        form.value = {
            user_id: setting?.user_id ?? props.targetUserId,
            key: setting?.key ?? "",
            group: setting?.group ?? "leave",
            type: setting?.type ?? "string",
            label: setting?.label ?? "",
            description: setting?.description ?? "",
            value: setting?.type === "json" ? JSON.stringify(setting?.value ?? {}, null, 2) : (setting?.value ?? ""),
        };
    } catch (error) {
        errors._global = error?.response?.data?.message ?? error?.message ?? "Betöltési hiba";
    } finally {
        bootstrapLoading.value = false;
    }
};

watch(() => [props.modelValue, props.userSettingId, props.targetUserId], ([open]) => open && hydrate());

const close = () => emit("update:modelValue", false);

const submit = async () => {
    if (!props.userSettingId) return;
    loading.value = true;
    Object.keys(errors).forEach((key) => delete errors[key]);

    try {
        const { data } = await UserSettingsService.update(props.userSettingId, form.value);
        emit("saved", data?.message ?? "User setting frissítve.");
        close();
    } catch (error) {
        const bag = UserSettingsService.extractErrors(error) ?? {};
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
    <Dialog :visible="modelValue" modal header="User setting szerkesztése" :style="{ width: '44rem' }" @update:visible="emit('update:modelValue', $event)">
        <div class="space-y-4">
            <div v-if="errors._global" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ errors._global }}</div>
            <div v-if="bootstrapLoading" class="text-sm text-gray-600">Betöltés...</div>
            <UserSettingsFields v-else v-model="form" :errors="errors" :disabled="loading" />
        </div>

        <template #footer>
            <Button label="Mégse" severity="secondary" :disabled="loading || bootstrapLoading" @click="close" />
            <Button label="Mentés" :loading="loading" :disabled="bootstrapLoading" @click="submit" />
        </template>
    </Dialog>
</template>
