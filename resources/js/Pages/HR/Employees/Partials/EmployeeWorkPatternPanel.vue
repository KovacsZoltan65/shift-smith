<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { trans } from "laravel-vue-i18n";
import WorkPatternSelector from "@/Components/Selectors/WorkPatternSelector.vue";
import EmployeeWorkPatternService from "@/services/EmployeeWorkPatternService";
import { useToast } from "primevue/usetoast";
import { toYmd } from "@/helpers/functions.js";

const toast = useToast();

const props = defineProps({
    employeeId: { type: [Number, String, null], default: null },
    companyId: { type: [Number, String, null], default: null },
    canAssign: { type: Boolean, default: false },
    canUnassign: { type: Boolean, default: false },
});

const loading = ref(false);
const rows = ref([]);
const assignOpen = ref(false);
const editingId = ref(null);
const saving = ref(false);
const errors = ref({});

const form = ref({
    work_pattern_id: null,
    date_from: null,
    date_to: null,
});

const employeeIdInt = computed(() => Number(props.employeeId ?? 0));
const companyIdInt = computed(() => Number(props.companyId ?? 0));

const resetForm = () => {
    errors.value = {};
    form.value = {
        work_pattern_id: null,
        date_from: new Date(),
        date_to: null,
    };
};

const load = async () => {
    if (!employeeIdInt.value) {
        rows.value = [];
        return;
    }

    loading.value = true;
    try {
        const { data } = await EmployeeWorkPatternService.getList(employeeIdInt.value);
        rows.value = Array.isArray(data?.data) ? data.data : [];
    } catch (e) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: e?.message || trans("employees.messages.work_pattern_list_failed"),
            life: 3500,
        });
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.employeeId,
    () => {
        load();
    }
);

onMounted(load);

const openAssign = () => {
    resetForm();
    editingId.value = null;
    assignOpen.value = true;
};

const parseDate = (value) => {
    if (!value) return null;
    if (value instanceof Date) return value;
    if (typeof value === "string" && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [y, m, d] = value.split("-").map(Number);
        return new Date(y, m - 1, d);
    }
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? null : d;
};

const openEdit = (row) => {
    errors.value = {};
    editingId.value = Number(row?.id ?? 0) || null;
    form.value = {
        work_pattern_id: row?.work_pattern_id ?? null,
        date_from: parseDate(row?.date_from),
        date_to: parseDate(row?.date_to),
    };
    assignOpen.value = true;
};

const toPayload = () => ({
    work_pattern_id: Number(form.value.work_pattern_id ?? 0),
    date_from: toYmd(form.value.date_from),
    date_to: toYmd(form.value.date_to),
});

const submitAssign = async () => {
    saving.value = true;
    errors.value = {};

    try {
        if (!employeeIdInt.value) {
            errors.value._global = trans("employees.messages.missing_employee_id");
            return;
        }

        const payload = toPayload();
        const isEdit = !!editingId.value;
        const { data } = isEdit
            ? await EmployeeWorkPatternService.update(employeeIdInt.value, editingId.value, payload)
            : await EmployeeWorkPatternService.assign(employeeIdInt.value, payload);
        assignOpen.value = false;
        editingId.value = null;
        await load();
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: data?.message || (isEdit ? trans("employees.messages.work_pattern_updated") : trans("employees.messages.work_pattern_assigned")),
            life: 2500,
        });
    } catch (e) {
        const status = e?.response?.status;
        if (status === 422) {
            const bag = e?.response?.data?.errors ?? {};
            const flat = {};
            Object.keys(bag).forEach((k) => (flat[k] = bag[k]?.[0] ?? String(bag[k])));
            errors.value = flat;
        } else {
            errors.value._global = e?.response?.data?.message || e?.message || trans("common.unknown_error");
        }
    } finally {
        saving.value = false;
    }
};

const unassign = async (row) => {
    if (!props.canUnassign) return;
    if (!window.confirm(trans("employees.work_pattern.delete_confirm", { name: row.work_pattern_name }))) return;

    try {
        await EmployeeWorkPatternService.unassign(employeeIdInt.value, row.id);
        await load();
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: trans("employees.messages.work_pattern_deleted"),
            life: 2500,
        });
    } catch (e) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: e?.response?.data?.message || e?.message || trans("employees.messages.work_pattern_delete_failed"),
            life: 3500,
        });
    }
};
</script>

<template>
    <div class="mt-6 rounded border p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold">{{ $t("employees.work_pattern.title") }}</h3>
            <div class="flex items-center gap-2">
                <Button
                    :label="$t('employees.actions.refresh')"
                    icon="pi pi-refresh"
                    severity="secondary"
                    size="small"
                    :disabled="loading"
                    :loading="loading"
                    @click="load"
                />
                <Button
                    v-if="canAssign"
                    :label="$t('employees.work_pattern.assign')"
                    icon="pi pi-plus"
                    size="small"
                    :disabled="!employeeIdInt || !companyIdInt"
                    @click="openAssign"
                />
            </div>
        </div>

        <DataTable :value="rows" dataKey="id" :loading="loading" size="small">
            <template #empty>{{ $t("employees.work_pattern.empty") }}</template>

            <Column field="work_pattern_name" :header="$t('employees.work_pattern.title')" />
            <Column field="date_from" :header="$t('columns.date_from')">
                <template #body="{ data }">{{ data.date_from || "-" }}</template>
            </Column>
            <Column field="date_to" :header="$t('columns.date_to')">
                <template #body="{ data }">{{ data.date_to || $t("employees.work_pattern.current") }}</template>
            </Column>
            <Column :header="$t('columns.actions')" style="width: 180px">
                <template #body="{ data }">
                    <div class="flex items-center gap-1">
                        <Button
                            v-if="canAssign"
                            :label="$t('edit')"
                            icon="pi pi-pencil"
                            severity="secondary"
                            text
                            size="small"
                            @click="openEdit(data)"
                        />
                        <Button
                            v-if="canUnassign"
                            :label="$t('delete')"
                            icon="pi pi-trash"
                            severity="danger"
                            text
                            size="small"
                            @click="unassign(data)"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>
    </div>

    <Dialog
        v-model:visible="assignOpen"
        modal
        :header="editingId ? $t('employees.work_pattern.edit_title') : $t('employees.work_pattern.create_title')"
        :style="{ width: '38rem' }"
        :closable="!saving"
        :dismissableMask="!saving"
    >
        <div class="grid grid-cols-1 gap-4">
            <div>
                <label class="mb-1 block text-sm">{{ $t("employees.work_pattern.title") }}</label>
                <WorkPatternSelector
                    v-model="form.work_pattern_id"
                    :companyId="companyIdInt"
                    :placeholder="$t('employees.work_pattern.select')"
                />
                <div v-if="errors?.work_pattern_id" class="mt-1 text-sm text-red-600">
                    {{ errors.work_pattern_id }}
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm">{{ $t("columns.date_from") }}</label>
                <DatePicker v-model="form.date_from" showIcon dateFormat="yy-mm-dd" class="w-full" />
                <div v-if="errors?.date_from" class="mt-1 text-sm text-red-600">
                    {{ errors.date_from }}
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm">{{ $t("columns.date_to") }}</label>
                <DatePicker v-model="form.date_to" showIcon dateFormat="yy-mm-dd" class="w-full" />
                <div v-if="errors?.date_to" class="mt-1 text-sm text-red-600">
                    {{ errors.date_to }}
                </div>
            </div>

            <div v-if="errors?._global" class="text-sm text-red-600">
                {{ errors._global }}
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button
                    :label="$t('common.cancel')"
                    severity="secondary"
                    :disabled="saving"
                    @click="assignOpen = false; editingId = null"
                />
                <Button
                    :label="$t('common.save')"
                    icon="pi pi-check"
                    :loading="saving"
                    :disabled="saving || !canAssign"
                    @click="submitAssign"
                />
            </div>
        </template>
    </Dialog>
</template>
