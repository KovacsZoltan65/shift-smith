<script setup>
import { reactive, ref, watch } from "vue";
import { trans } from "laravel-vue-i18n";
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
        errors._global = error?.response?.data?.message ?? error?.message ?? trans("user_settings.messages.fetch_failed");
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
        emit("saved", data?.message ?? trans("user_settings.messages.updated_success"));
        close();
    } catch (error) {
        const bag = UserSettingsService.extractErrors(error) ?? {};
        Object.keys(bag).forEach((key) => {
            errors[key] = bag[key]?.[0] ?? trans("common.error");
        });
        if (!Object.keys(bag).length) {
            errors._global = error?.response?.data?.message ?? error?.message ?? trans("common.unknown_error");
        }
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Dialog :visible="modelValue" modal :header="trans('user_settings.dialogs.edit_title')" :style="{ width: '44rem' }" @update:visible="emit('update:modelValue', $event)">
        <div class="space-y-4">
            <div v-if="errors._global" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ errors._global }}</div>
            <div v-if="bootstrapLoading" class="text-sm text-gray-600">{{ trans("user_settings.states.loading") }}</div>
            <UserSettingsFields v-else v-model="form" :errors="errors" :disabled="loading" />
        </div>

        <template #footer>
            <Button :label="trans('common.cancel')" severity="secondary" :disabled="loading || bootstrapLoading" @click="close" />
            <Button :label="trans('common.save')" :loading="loading" :disabled="bootstrapLoading" @click="submit" />
        </template>
    </Dialog>
</template>
