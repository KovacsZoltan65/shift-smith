<script setup>
import { computed, ref, watch } from "vue";
import { trans } from "laravel-vue-i18n";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import DatePicker from "primevue/datepicker";
import Message from "primevue/message";
import Select from "primevue/select";
import EmployeeSelector from "@/Components/Selectors/EmployeeSelector.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    visible: { type: Boolean, default: false },
    employee: { type: Object, default: null },
    companyId: { type: Number, default: null },
    defaultEffectiveFrom: { type: String, default: null },
});

const emit = defineEmits(["update:visible", "deleted"]);

const previewLoading = ref(false);
const executeLoading = ref(false);
const previewData = ref(null);
const requestError = ref("");
const strategy = ref("none");
const targetSupervisorEmployeeId = ref(null);
const effectiveFrom = ref(props.defaultEffectiveFrom ? new Date(props.defaultEffectiveFrom) : new Date());
let previewTimer = null;

const strategyOptions = [
    { label: trans("employees.delete.no_reassignment"), value: "none" },
    { label: trans("employees.delete.reassign_to_old_supervisor"), value: "reassign_to_old_supervisor" },
    { label: trans("employees.delete.reassign_to_specific_supervisor"), value: "reassign_to_specific_supervisor" },
];

const ymd = (value) => {
    const date = value instanceof Date ? value : new Date(value ?? Date.now());
    return Number.isNaN(date.getTime()) ? new Date().toISOString().slice(0, 10) : date.toISOString().slice(0, 10);
};

const subordinateCount = computed(() => Number(previewData.value?.subordinate_count ?? 0));
const hasSubordinates = computed(() => subordinateCount.value > 0);
const isCeoWithSubordinates = computed(
    () => props.employee?.org_level === "ceo" && hasSubordinates.value,
);
const needsStrategy = computed(() => hasSubordinates.value && props.employee?.org_level !== "ceo");
const needsTargetSupervisor = computed(() => strategy.value === "reassign_to_specific_supervisor");
const previewErrors = computed(() => previewData.value?.errors ?? []);
const isPreviewPendingInput = computed(
    () => needsTargetSupervisor.value && !targetSupervisorEmployeeId.value,
);
const canPreview = computed(
    () =>
        props.visible &&
        Number(props.employee?.id || 0) > 0 &&
        Number(payload.value.company_id || 0) > 0 &&
        !isPreviewPendingInput.value,
);
const canDelete = computed(
    () =>
        canPreview.value &&
        !previewLoading.value &&
        !executeLoading.value &&
        !isCeoWithSubordinates.value &&
        previewErrors.value.length === 0 &&
        (!needsStrategy.value || strategy.value !== "none"),
);

const resetState = () => {
    previewData.value = null;
    requestError.value = "";
    strategy.value = "none";
    targetSupervisorEmployeeId.value = null;
    effectiveFrom.value = props.defaultEffectiveFrom ? new Date(props.defaultEffectiveFrom) : new Date();
};

const payload = computed(() => ({
    company_id: Number(props.companyId || props.employee?.company_id || 0),
    effective_from: ymd(effectiveFrom.value),
    strategy: hasSubordinates.value ? strategy.value : "none",
    target_supervisor_employee_id: needsTargetSupervisor.value ? targetSupervisorEmployeeId.value : null,
}));

const runPreview = async () => {
    if (!canPreview.value) {
        requestError.value = "";
        return;
    }
    const employeeId = Number(props.employee?.id || 0);

    previewLoading.value = true;
    requestError.value = "";

    try {
        const params = new URLSearchParams();
        Object.entries(payload.value).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== "") {
                params.set(key, String(value));
            }
        });

        const response = await csrfFetch(`${route("employees.delete_preview", employeeId)}?${params.toString()}`, {
            method: "GET",
            headers: { Accept: "application/json" },
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(json?.message || trans("employees.messages.delete_preview_failed"));
        }

        previewData.value = json?.data ?? null;
    } catch (error) {
        previewData.value = null;
        requestError.value = error instanceof Error ? error.message : trans("employees.messages.delete_preview_failed");
    } finally {
        previewLoading.value = false;
    }
};

const queuePreview = () => {
    if (previewTimer !== null) {
        clearTimeout(previewTimer);
    }

    if (!canPreview.value) {
        requestError.value = "";
        return;
    }

    previewTimer = setTimeout(() => {
        runPreview();
    }, 400);
};

const submitDelete = async () => {
    const employeeId = Number(props.employee?.id || 0);
    if (!employeeId || !canDelete.value) {
        return;
    }

    executeLoading.value = true;
    requestError.value = "";

    try {
        const response = await csrfFetch(route("employees.destroy", employeeId), {
            method: "DELETE",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify(payload.value),
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(json?.message || trans("employees.messages.delete_failed"));
        }

        emit("deleted", json?.data ?? null);
        emit("update:visible", false);
    } catch (error) {
        requestError.value = error instanceof Error ? error.message : trans("employees.messages.delete_failed");
    } finally {
        executeLoading.value = false;
    }
};

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            resetState();
            queuePreview();
            return;
        }

        if (previewTimer !== null) {
            clearTimeout(previewTimer);
            previewTimer = null;
        }
    },
);

watch(
    () => [props.employee?.id ?? null, payload.value.effective_from, strategy.value, targetSupervisorEmployeeId.value, props.visible],
    () => {
        if (props.visible) {
            queuePreview();
        }
    },
);
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        :draggable="false"
        :style="{ width: '42rem', maxWidth: '96vw' }"
        :header="$t('employees.dialogs.delete_title')"
        @update:visible="emit('update:visible', $event)"
    >
        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $t("employees.delete.affected_employee") }}</div>
                <div class="mt-1 text-sm font-medium text-slate-800">
                    {{ employee?.name || `${employee?.first_name || ""} ${employee?.last_name || ""}`.trim() || `#${employee?.id}` }}
                </div>
                <div class="mt-1 text-xs text-slate-500">{{ employee?.position_name || employee?.position || "-" }}</div>
            </div>

            <div class="space-y-2">
                <label class="block text-sm font-medium text-slate-700">{{ $t("employees.delete.effective_from") }}</label>
                <DatePicker
                    v-model="effectiveFrom"
                    dateFormat="yy-mm-dd"
                    showIcon
                    inputClass="w-full"
                    class="w-full [&_.p-datepicker-input]:w-full [&_.p-inputtext]:w-full"
                />
            </div>

            <Message v-if="requestError && !isPreviewPendingInput" severity="error" :closable="false">
                {{ requestError }}
            </Message>

            <div v-if="visible" class="rounded-xl border border-slate-200 p-4">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-800">{{ $t("employees.delete.preview") }}</div>
                        <div class="text-xs text-slate-500">{{ $t("employees.delete.preview_help") }}</div>
                    </div>
                    <Button
                        :label="$t('employees.actions.refresh')"
                        icon="pi pi-refresh"
                        severity="secondary"
                        :loading="previewLoading"
                        :disabled="!canPreview"
                        @click="runPreview"
                    />
                </div>

                <div v-if="previewData" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="text-xs uppercase tracking-wide text-slate-500">{{ $t("employees.delete.active_subordinates") }}</div>
                        <div class="mt-1 text-lg font-semibold text-slate-800">{{ previewData.subordinate_count || 0 }}</div>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="text-xs uppercase tracking-wide text-slate-500">{{ $t("employees.delete.affected_employees") }}</div>
                        <div class="mt-1 text-lg font-semibold text-slate-800">{{ previewData.affected_count || 0 }}</div>
                    </div>
                </div>

                <Message
                    v-if="previewData && hasSubordinates"
                    severity="warn"
                    :closable="false"
                    class="mt-3"
                >
                    {{ $t("employees.messages.employee_has_subordinates", { count: subordinateCount }) }}
                </Message>

                <Message
                    v-if="previewData && isCeoWithSubordinates"
                    severity="error"
                    :closable="false"
                    class="mt-3"
                >
                    {{ $t("employees.messages.ceo_delete_blocked") }}
                </Message>

                <div v-if="needsStrategy" class="mt-3 space-y-3">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-slate-700">{{ $t("employees.delete.reassignment_strategy") }}</label>
                        <Select
                            v-model="strategy"
                            :options="strategyOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                        />
                    </div>

                    <div v-if="needsTargetSupervisor" class="space-y-2">
                        <label class="block text-sm font-medium text-slate-700">{{ $t("employees.delete.target_supervisor") }}</label>
                        <EmployeeSelector
                            v-model="targetSupervisorEmployeeId"
                            :company-id="companyId"
                            :server-search="true"
                            :exclude-employee-ids="[employee?.id]"
                            class="block w-full"
                            :placeholder="$t('employees.form.select_supervisor')"
                        />
                        <div v-if="isPreviewPendingInput" class="text-xs text-slate-500">
                            {{ $t("employees.messages.select_target_supervisor") }}
                        </div>
                    </div>
                </div>

                <div class="mt-3 space-y-2">
                    <Message
                        v-if="isPreviewPendingInput"
                        severity="info"
                        :closable="false"
                    >
                        {{ $t("employees.messages.select_target_supervisor") }}
                    </Message>

                    <Message
                        v-for="warning in previewData?.warnings || []"
                        :key="`warning-${warning}`"
                        severity="warn"
                        :closable="false"
                    >
                        {{ warning }}
                    </Message>

                    <Message
                        v-for="error in previewData?.errors || []"
                        :key="`error-${error}`"
                        severity="error"
                        :closable="false"
                    >
                        {{ error }}
                    </Message>

                    <Message
                        v-if="previewData && !previewLoading && !isPreviewPendingInput && (previewData.errors || []).length === 0 && !isCeoWithSubordinates"
                        severity="success"
                        :closable="false"
                    >
                        {{ $t("employees.messages.delete_allowed") }}
                    </Message>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex items-center justify-end gap-2">
                <Button
                    :label="$t('common.cancel')"
                    severity="secondary"
                    text
                    :disabled="executeLoading"
                    @click="emit('update:visible', false)"
                />
                <Button
                    :label="$t('delete')"
                    icon="pi pi-trash"
                    severity="danger"
                    :loading="executeLoading"
                    :disabled="!canDelete"
                    @click="submitDelete"
                />
            </div>
        </template>
    </Dialog>
</template>
