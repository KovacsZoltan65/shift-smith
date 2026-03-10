<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import Message from "primevue/message";

const props = defineProps({
    visible: { type: Boolean, default: false },
    employee: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:visible", "confirm"]);
const page = usePage();

const formattedDeletedAt = computed(() => {
    if (!props.employee?.deleted_at) {
        return "-";
    }

    const date = new Date(props.employee.deleted_at);
    const locale =
        document.documentElement.getAttribute("lang") ??
        page.props?.preferences?.locale ??
        page.props?.locale ??
        "en";

    return Number.isNaN(date.getTime())
        ? props.employee.deleted_at
        : date.toLocaleString(locale);
});
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        :draggable="false"
        :style="{ width: '34rem', maxWidth: '96vw' }"
        :header="$t('employees.dialogs.restore_title')"
        @update:visible="emit('update:visible', $event)"
    >
        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-sm font-semibold text-slate-800">
                    {{ employee?.last_name || "" }} {{ employee?.first_name || "" }}
                </div>
                <div class="mt-1 text-sm text-slate-600">{{ employee?.email || "-" }}</div>
                <div class="mt-2 text-xs text-slate-500">
                    {{ $t("employees.fields.deleted_at") }}: {{ formattedDeletedAt }}
                </div>
            </div>

            <Message severity="info" :closable="false">
                {{ $t("employees.messages.deleted_employee_found") }}
            </Message>

            <Message severity="warn" :closable="false">
                {{ $t("employees.messages.restore_hierarchy_hint") }}
            </Message>
        </div>

        <template #footer>
            <div class="flex items-center justify-end gap-2">
                <Button
                    :label="$t('common.cancel')"
                    severity="secondary"
                    text
                    :disabled="loading"
                    @click="emit('update:visible', false)"
                />
                <Button
                    :label="$t('employees.actions.restore')"
                    icon="pi pi-history"
                    :loading="loading"
                    :disabled="loading"
                    @click="emit('confirm')"
                />
            </div>
        </template>
    </Dialog>
</template>
