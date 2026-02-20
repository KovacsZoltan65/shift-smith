<script setup>
import { computed, onMounted, ref, watch } from "vue";
import Button from "primevue/button";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import DatePicker from "primevue/datepicker";
import Dialog from "primevue/dialog";
import EmployeeSelector from "@/Components/Selectors/EmployeeSelector.vue";
import WorkShiftSelector from "@/Components/Selectors/WorkShiftSelector.vue";
import WorkScheduleAssignmentService from "@/services/WorkScheduleAssignmentService";
import { useToast } from "primevue/usetoast";
import { toYmd } from "@/helpers/functions.js";

const toast = useToast();

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    workSchedule: { type: Object, default: null },
    canAssign: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    canUpdate: { type: Boolean, default: false },
    canBulkDelete: { type: Boolean, default: false },
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
const selected = ref([]);
const editingId = ref(null);

const form = ref({
    employee_id: null,
    work_shift_id: null,
    day: new Date(),
    start_time: null,
    end_time: null,
});

const scheduleId = computed(() => Number(props.workSchedule?.id ?? 0));

const resetForm = () => {
    editingId.value = null;
    form.value = {
        employee_id: null,
        work_shift_id: null,
        day: new Date(),
        start_time: null,
        end_time: null,
    };
    errors.value = {};
};

const load = async () => {
    if (!scheduleId.value) {
        rows.value = [];
        return;
    }

    loading.value = true;
    try {
        const { data } = await WorkScheduleAssignmentService.fetch(scheduleId.value, {
            page: 1,
            per_page: 100,
            order: "desc",
        });
        rows.value = Array.isArray(data?.data) ? data.data : [];
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
        if (open) {
            resetForm();
            load();
        }
    }
);

watch(
    () => props.workSchedule?.id,
    () => {
        if (props.modelValue) {
            resetForm();
            load();
        }
    }
);

onMounted(() => {
    if (props.modelValue) {
        load();
    }
});

const submit = async () => {
    if (!scheduleId.value) return;

    saving.value = true;
    errors.value = {};
    try {
        const payload = {
            employee_id: Number(form.value.employee_id ?? 0),
            work_shift_id: Number(form.value.work_shift_id ?? 0),
            day: toYmd(form.value.day),
            start_time: form.value.start_time || null,
            end_time: form.value.end_time || null,
        };

        if (editingId.value) {
            await WorkScheduleAssignmentService.update(scheduleId.value, editingId.value, payload);
        } else {
            await WorkScheduleAssignmentService.store(scheduleId.value, payload);
        }

        await load();
        toast.add({
            severity: "success",
            summary: "Siker",
            detail: editingId.value ? "Kiosztás frissítve." : "Dolgozó kiosztva.",
            life: 2500,
        });
        resetForm();
    } catch (e) {
        if (e?.response?.status === 422) {
            const bag = e?.response?.data?.errors ?? {};
            const flat = {};
            Object.keys(bag).forEach((k) => (flat[k] = bag[k]?.[0] ?? String(bag[k])));
            errors.value = flat;
            if (!Object.keys(flat).length && e?.response?.data?.message) {
                errors.value._global = e.response.data.message;
            }
        } else {
            errors.value._global = e?.response?.data?.message || e?.message || "Mentés sikertelen.";
        }
    } finally {
        saving.value = false;
    }
};

const startEdit = (row) => {
    if (!props.canUpdate) return;

    editingId.value = row.id;
    form.value.employee_id = row.employee_id;
    form.value.work_shift_id = row.work_shift_id;
    form.value.day = row.day ? new Date(row.day) : new Date();
    form.value.start_time = row.start_time;
    form.value.end_time = row.end_time;
    errors.value = {};
};

const remove = async (row) => {
    if (!props.canDelete) return;
    if (!window.confirm(`Biztos törlöd a kiosztást: ${row.employee_name} (${row.day})?`)) return;

    try {
        await WorkScheduleAssignmentService.destroy(scheduleId.value, row.id);
        await load();
        selected.value = selected.value.filter((x) => x.id !== row.id);
        toast.add({
            severity: "success",
            summary: "Siker",
            detail: "Kiosztás törölve.",
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

const bulkDelete = async () => {
    if (!props.canBulkDelete || !selected.value.length) return;
    if (!window.confirm(`Biztos törlöd a kijelölt ${selected.value.length} kiosztást?`)) return;

    try {
        await WorkScheduleAssignmentService.bulkDestroy(
            scheduleId.value,
            selected.value.map((x) => x.id)
        );

        selected.value = [];
        await load();
        toast.add({
            severity: "success",
            summary: "Siker",
            detail: "Kijelölt kiosztások törölve.",
            life: 2500,
        });
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.response?.data?.message || e?.message || "Bulk törlés sikertelen.",
            life: 3500,
        });
    }
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :header="`Dolgozó kiosztása beosztáshoz: ${props.workSchedule?.name || '-'}`"
        :style="{ width: '70rem' }"
    >
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3 mb-4">
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm">Dolgozó</label>
                <EmployeeSelector v-model="form.employee_id" placeholder="Dolgozó..." />
                <div v-if="errors?.employee_id" class="mt-1 text-sm text-red-600">{{ errors.employee_id }}</div>
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm">Műszak</label>
                <WorkShiftSelector v-model="form.work_shift_id" placeholder="Műszak..." />
                <div v-if="errors?.work_shift_id" class="mt-1 text-sm text-red-600">{{ errors.work_shift_id }}</div>
            </div>

            <div>
                <label class="mb-1 block text-sm">Nap</label>
                <DatePicker v-model="form.day" dateFormat="yy-mm-dd" showIcon class="w-full" />
                <div v-if="errors?.day" class="mt-1 text-sm text-red-600">{{ errors.day }}</div>
            </div>

            <div class="flex items-end gap-2">
                <Button
                    :label="editingId ? 'Mentés' : 'Hozzárendelés'"
                    icon="pi pi-save"
                    :loading="saving"
                    :disabled="saving || (!canAssign && !editingId) || (editingId && !canUpdate)"
                    @click="submit"
                />
                <Button
                    v-if="editingId"
                    label="Mégse"
                    severity="secondary"
                    text
                    @click="resetForm"
                />
            </div>
        </div>

        <div v-if="errors?._global" class="mb-3 text-sm text-red-600">{{ errors._global }}</div>

        <div class="mb-2 flex justify-end">
            <Button
                v-if="canBulkDelete"
                label="Kijelöltek törlése"
                icon="pi pi-trash"
                severity="danger"
                text
                :disabled="!selected.length"
                @click="bulkDelete"
            />
        </div>

        <DataTable
            v-model:selection="selected"
            :value="rows"
            dataKey="id"
            :loading="loading"
            size="small"
            selectionMode="multiple"
        >
            <template #empty>Nincs kiosztás.</template>

            <Column selectionMode="multiple" headerStyle="width: 3rem" />
            <Column field="employee_name" header="Dolgozó" />
            <Column field="work_shift_name" header="Műszak" />
            <Column field="day" header="Nap" />
            <Column field="start_time" header="Kezdés (override)" />
            <Column field="end_time" header="Vége (override)" />
            <Column header="Művelet" style="width: 190px">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button
                            v-if="canUpdate"
                            label="Szerk."
                            icon="pi pi-pencil"
                            text
                            size="small"
                            @click="startEdit(data)"
                        />
                        <Button
                            v-if="canDelete"
                            label="Törlés"
                            icon="pi pi-trash"
                            severity="danger"
                            text
                            size="small"
                            @click="remove(data)"
                        />
                    </div>
                </template>
            </Column>
        </DataTable>
    </Dialog>
</template>
