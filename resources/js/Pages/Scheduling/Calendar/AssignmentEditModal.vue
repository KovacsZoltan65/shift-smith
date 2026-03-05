<script setup>
import { computed, reactive, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import DatePicker from "primevue/datepicker";
import EmployeeSelector from "@/Components/Selectors/EmployeeSelector.vue";
import WorkShiftSelector from "@/Components/Selectors/WorkShiftSelector.vue";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    assignment: { type: Object, default: null },
    companyId: { type: Number, required: true },
    scheduleId: { type: Number, required: true },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "update", "delete"]);

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const form = reactive({
    employee_id: null,
    work_shift_id: null,
    date: null,
});

watch(
    () => props.assignment,
    (row) => {
        form.employee_id = Number(row?.extendedProps?.employee_id ?? 0) || null;
        form.work_shift_id = Number(row?.extendedProps?.shift_id ?? 0) || null;
        form.date = row?.start ?? null;
    },
    { immediate: true }
);

const toYmd = (d) => {
    if (!d) return null;
    if (typeof d === "string" && /^\d{4}-\d{2}-\d{2}$/.test(d)) return d;
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return null;
    return dt.toISOString().slice(0, 10);
};

const submitUpdate = () => {
    emit("update", {
        id: Number(props.assignment?.id ?? 0),
        payload: {
            work_schedule_id: props.scheduleId,
            employee_id: Number(form.employee_id ?? 0),
            work_shift_id: Number(form.work_shift_id ?? 0),
            date: toYmd(form.date),
        },
    });
};

const submitDelete = () => {
    emit("delete", Number(props.assignment?.id ?? 0));
};
</script>

<template>
    <Dialog v-model:visible="visible" modal header="Hozzárendelés módosítása" :style="{ width: '34rem' }">
        <div class="grid grid-cols-1 gap-3">
            <div>
                <label class="mb-1 block text-sm">Dolgozó</label>
                <EmployeeSelector v-model="form.employee_id" :companyId="companyId" :disabled="disabled" />
            </div>

            <div>
                <label class="mb-1 block text-sm">Műszak</label>
                <WorkShiftSelector v-model="form.work_shift_id" :companyId="companyId" :disabled="disabled" />
            </div>

            <div>
                <label class="mb-1 block text-sm">Dátum</label>
                <DatePicker v-model="form.date" dateFormat="yy-mm-dd" showIcon class="w-full" :disabled="disabled" />
            </div>
        </div>

        <template #footer>
            <Button label="Törlés" icon="pi pi-trash" severity="danger" text :disabled="loading || disabled" @click="submitDelete" />
            <Button label="Mégse" severity="secondary" :disabled="loading || disabled" @click="visible = false" />
            <Button label="Mentés" icon="pi pi-check" :loading="loading" :disabled="disabled" @click="submitUpdate" />
        </template>
    </Dialog>
</template>
