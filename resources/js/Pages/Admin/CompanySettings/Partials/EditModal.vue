<script setup>
import { reactive, ref, watch } from "vue";
import { trans } from "laravel-vue-i18n";
import Button from "primevue/button";
import Dialog from "primevue/dialog";

import CompanySettingsFields from "./CompanySettingsFields.vue";
import CompanySettingsService from "@/services/CompanySettingsService.js";

const props = defineProps({
    modelValue: Boolean,
    companySettingId: { type: Number, default: null },
});

const emit = defineEmits(["update:modelValue", "saved"]);
const loading = ref(false);
const bootstrapLoading = ref(false);
const errors = reactive({});
const form = ref({});

const hydrate = async () => {
    if (!props.companySettingId) return;

    bootstrapLoading.value = true;
    Object.keys(errors).forEach((key) => delete errors[key]);

    try {
        const { data } = await CompanySettingsService.show(props.companySettingId);
        const setting = data?.data;
        form.value = {
            key: setting?.key ?? "",
            group: setting?.group ?? "leave",
            type: setting?.type ?? "string",
            label: setting?.label ?? "",
            description: setting?.description ?? "",
            value: setting?.type === "json" ? JSON.stringify(setting?.value ?? {}, null, 2) : (setting?.value ?? ""),
        };
    } catch (error) {
        errors._global = error?.response?.data?.message ?? error?.message ?? trans("common.unknown_error");
    } finally {
        bootstrapLoading.value = false;
    }
};

watch(() => [props.modelValue, props.companySettingId], ([open]) => open && hydrate());

const close = () => emit("update:modelValue", false);

const submit = async () => {
    if (!props.companySettingId) return;

    loading.value = true;
    Object.keys(errors).forEach((key) => delete errors[key]);

    try {
        const { data } = await CompanySettingsService.update(props.companySettingId, form.value);
        emit("saved", data?.message ?? trans("company_settings.messages.updated_success"));
        close();
    } catch (error) {
        const bag = CompanySettingsService.extractErrors(error) ?? {};
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
    <Dialog :visible="modelValue" modal :header="$t('company_settings.dialogs.edit_title')" :style="{ width: '44rem' }" @update:visible="emit('update:modelValue', $event)">
        <div class="space-y-4">
            <div v-if="errors._global" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ errors._global }}
            </div>
            <div v-if="bootstrapLoading" class="text-sm text-gray-600">{{ $t("common.loading") }}</div>
            <CompanySettingsFields v-else v-model="form" :errors="errors" :disabled="loading" />
        </div>

        <template #footer>
            <Button :label="$t('common.cancel')" severity="secondary" :disabled="loading || bootstrapLoading" @click="close" />
            <Button :label="$t('common.save')" :loading="loading" :disabled="bootstrapLoading" @click="submit" />
        </template>
    </Dialog>
</template>
