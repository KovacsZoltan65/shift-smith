<script setup>
import { computed, ref } from "vue";
import { trans } from "laravel-vue-i18n";

import AppSettingsService from "@/services/AppSettingsService.js";

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
        const { data } = await AppSettingsService.bulkDestroy(ids);
        emit("deleted", data?.message ?? trans("app_settings.messages.bulk_deleted_success"));
        close();
    } catch (err) {
        error.value = err?.response?.data?.message ?? err?.message ?? trans("common.delete_failed");
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Dialog
        :visible="modelValue"
        modal
        :header="$t('app_settings.dialogs.bulk_delete_title')"
        :style="{ width: '30rem' }"
        @update:visible="emit('update:modelValue', $event)"
    >
        <div class="space-y-3">
            <p class="text-sm text-gray-700">
                {{ $t("app_settings.dialogs.bulk_delete_confirm", { count }) }}
            </p>
            <div v-if="error" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ error }}
            </div>
        </div>

        <template #footer>
            <Button :label="$t('common.cancel')" severity="secondary" :disabled="loading" @click="close" />
            <Button :label="$t('delete')" severity="danger" :loading="loading" :disabled="!count" @click="submit" />
        </template>
    </Dialog>
</template>
