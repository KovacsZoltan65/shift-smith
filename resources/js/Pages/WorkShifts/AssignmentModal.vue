<script setup>
import { computed, onMounted, ref, watch } from "vue";
import Button from "primevue/button";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import DatePicker from "primevue/datepicker";
import Dialog from "primevue/dialog";
import { Select } from "primevue";
import EmployeeSelector from "@/Components/Selectors/EmployeeSelector.vue";
import WorkShiftAssignmentService from "@/services/WorkShiftAssignmentService";
import { useToast } from "primevue/usetoast";
import { toYmd } from "@/helpers/functions.js";

const toast = useToast();

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    workShift: { type: Object, default: null },
    canAssign: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const loading = ref(false);
const saving = ref(false);
const errors = ref({});
const rows = ref([]);
const scheduleOptions = ref([]);

const form = ref({
    employee_id: null,
    work_schedule_id: null,
    date: new Date(),
});

const workShiftId = computed(() => Number(props.workShift?.id ?? 0));
const companyId = computed(() => Number(props.workShift?.company_id ?? 0) || null);

const formatScheduleLabel = (schedule) => {
    if (!schedule) return "";
    return `${schedule.name} (${schedule.date_from} - ${schedule.date_to})`;
};

const syncScheduleForDate = () => {
    const date = toYmd(form.value.date);
    if (!date || !Array.isArray(scheduleOptions.value) || scheduleOptions.value.length === 0) {
        return;
    }

    const selectedId = Number(form.value.work_schedule_id ?? 0);
    const selected = scheduleOptions.value.find((item) => Number(item.id) === selectedId);
    if (selected && date >= selected.date_from && date <= selected.date_to) {
        return;
    }

    const match = scheduleOptions.value.find((item) => date >= item.date_from && date <= item.date_to);
    form.value.work_schedule_id = match ? Number(match.id) : null;
};

const load = async () => {
    if (!workShiftId.value) {
        rows.value = [];
        scheduleOptions.value = [];
        return;
    }

    loading.value = true;
    try {
        const [assignmentsResponse, schedulesResponse] = await Promise.all([
            WorkShiftAssignmentService.list(workShiftId.value),
            WorkShiftAssignmentService.listSchedules(workShiftId.value),
        ]);

        rows.value = Array.isArray(assignmentsResponse?.data?.data) ? assignmentsResponse.data.data : [];
        const schedules = Array.isArray(schedulesResponse?.data?.data) ? schedulesResponse.data.data : [];
        scheduleOptions.value = schedules.map((item) => ({
            ...item,
            id: Number(item.id),
            label: formatScheduleLabel(item),
        }));
        syncScheduleForDate();
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.response?.data?.message || e?.message || "Lista nem tölthető.",
            life: 3500,
        });
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.modelValue,
    (open) => {
        if (open) load();
    }
);

watch(
    () => props.workShift?.id,
    () => {
        if (props.modelValue) load();
    }
);

watch(
    () => form.value.date,
    () => {
        syncScheduleForDate();
    }
);

onMounted(() => {
    if (props.modelValue) load();
});

const submit = async () => {
    if (!workShiftId.value) return;

    saving.value = true;
    errors.value = {};
    try {
        await WorkShiftAssignmentService.assign(workShiftId.value, {
            employee_id: Number(form.value.employee_id ?? 0),
            work_schedule_id: Number(form.value.work_schedule_id ?? 0) || null,
            date: toYmd(form.value.date),
        });

        form.value.employee_id = null;
        form.value.work_schedule_id = null;
        form.value.date = new Date();

        await load();
        toast.add({
            severity: "success",
            summary: "Siker",
            detail: "Dolgozó hozzárendelve.",
            life: 2500,
        });
    } catch (e) {
        if (e?.response?.status === 422) {
            const bag = e?.response?.data?.errors ?? {};
            const flat = {};
            Object.keys(bag).forEach((k) => (flat[k] = bag[k]?.[0] ?? String(bag[k])));
            errors.value = flat;
        } else {
            errors.value._global = e?.response?.data?.message || e?.message || "Mentés sikertelen.";
        }
    } finally {
        saving.value = false;
    }
};

const remove = async (row) => {
    if (!props.canDelete) return;
    if (!window.confirm(`Biztos törlöd a hozzárendelést: ${row.employee_name} (${row.date})?`)) return;

    try {
        await WorkShiftAssignmentService.unassign(workShiftId.value, row.id);
        await load();
        toast.add({
            severity: "success",
            summary: "Siker",
            detail: "Hozzárendelés törölve.",
            life: 2500,
        });
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.response?.data?.message || e?.message || "Törlés sikertelen.",
            life: 3500,
        });
    }
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        header="Dolgozó hozzárendelése műszakhoz"
        :style="{ width: '56rem' }"
    >
        <div class="mb-4 text-sm text-gray-600">
            Műszak: <b>{{ props.workShift?.name || "-" }}</b>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <div>
                <label class="mb-1 block text-sm">Dolgozó</label>
                <EmployeeSelector
                    v-model="form.employee_id"
                    :companyId="companyId"
                    :onlyActive="false"
                    placeholder="Dolgozó..."
                />
                <div v-if="errors?.employee_id" class="mt-1 text-sm text-red-600">
                    {{ errors.employee_id }}
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm">Nap</label>
                <DatePicker v-model="form.date" dateFormat="yy-mm-dd" showIcon class="w-full" />
                <div v-if="errors?.date" class="mt-1 text-sm text-red-600">{{ errors.date }}</div>
            </div>

            <div>
                <label class="mb-1 block text-sm">Munkabeosztás</label>
                <Select
                    v-model="form.work_schedule_id"
                    :options="scheduleOptions"
                    optionLabel="label"
                    optionValue="id"
                    placeholder="Munkabeosztás..."
                    class="w-full"
                    filter
                    showClear
                />
                <div v-if="errors?.work_schedule_id" class="mt-1 text-sm text-red-600">
                    {{ errors.work_schedule_id }}
                </div>
            </div>

            <div class="flex items-end md:col-span-3">
                <Button
                    label="Hozzárendelés"
                    icon="pi pi-plus"
                    :loading="saving"
                    :disabled="saving || !canAssign"
                    @click="submit"
                />
            </div>
        </div>

        <div v-if="errors?._global" class="mb-3 text-sm text-red-600">
            {{ errors._global }}
        </div>

        <DataTable :value="rows" dataKey="id" :loading="loading" size="small">
            <template #empty>Nincs hozzárendelés.</template>

            <Column field="employee_name" header="Dolgozó" />
            <Column field="work_schedule_name" header="Beosztás" />
            <Column field="date" header="Nap" />
            <Column header="Művelet" style="width: 120px">
                <template #body="{ data }">
                    <Button
                        v-if="canDelete"
                        label="Törlés"
                        icon="pi pi-trash"
                        severity="danger"
                        text
                        size="small"
                        @click="remove(data)"
                    />
                </template>
            </Column>
        </DataTable>
    </Dialog>
</template>
