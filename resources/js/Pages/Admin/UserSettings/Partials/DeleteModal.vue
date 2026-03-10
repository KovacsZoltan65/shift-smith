<script setup>
import { computed, ref } from "vue";
import { trans } from "laravel-vue-i18n";
import Button from "primevue/button";
import Dialog from "primevue/dialog";

import UserSettingsService from "@/services/UserSettingsService.js";

const props = defineProps({
    modelValue: Boolean,
    item: { type: Object, default: null },
    targetUserId: { type: Number, default: null },
});

const emit = defineEmits(["update:modelValue", "deleted"]);
const loading = ref(false);
const error = ref("");
const title = computed(() => props.item?.key ?? trans("user_settings.dialogs.item_fallback"));

const close = () => emit("update:modelValue", false);

const submit = async () => {
    if (!props.item?.id) return;
    loading.value = true;
    error.value = "";

    try {
        const { data } = await UserSettingsService.destroy(props.item.id, { user_id: props.targetUserId });
        emit("deleted", data?.message ?? trans("user_settings.messages.deleted_success"));
        close();
    } catch (err) {
        error.value = err?.response?.data?.message ?? err?.message ?? trans("user_settings.messages.delete_failed");
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Dialog :visible="modelValue" modal :header="trans('user_settings.dialogs.delete_title')" :style="{ width: '30rem' }" @update:visible="emit('update:modelValue', $event)">
        <div class="space-y-3">
            <p class="text-sm text-gray-700">{{ trans("user_settings.dialogs.delete_confirm", { name: title }) }}</p>
            <div v-if="error" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>
        </div>

        <template #footer>
            <Button :label="trans('common.cancel')" severity="secondary" :disabled="loading" @click="close" />
            <Button :label="trans('delete')" severity="danger" :loading="loading" @click="submit" />
        </template>
    </Dialog>
</template>
