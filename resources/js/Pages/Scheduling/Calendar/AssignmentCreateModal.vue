<script setup>
import { computed, reactive, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import DatePicker from "primevue/datepicker";
import EmployeeSelector from "@/Components/Selectors/EmployeeSelector.vue";
import WorkShiftSelector from "@/Components/Selectors/WorkShiftSelector.vue";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    companyId: { type: Number, required: true },
    scheduleId: { type: Number, required: true },
    defaultDate: { type: String, default: null },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "submit"]);

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
    () => props.modelValue,
    (open) => {
        if (!open) return;
        form.employee_id = null;
        form.work_shift_id = null;
        form.date = props.defaultDate ?? null;
    }
);

const toYmd = (d) => {
    if (!d) return null;
    if (typeof d === "string" && /^\d{4}-\d{2}-\d{2}$/.test(d)) return d;
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return null;
    return dt.toISOString().slice(0, 10);
};

const submit = () => {
    emit("submit", {
        work_schedule_id: props.scheduleId,
        employee_id: Number(form.employee_id ?? 0),
        work_shift_id: Number(form.work_shift_id ?? 0),
        date: toYmd(form.date),
    });
};
</script>

<template>
    <Dialog v-model:visible="visible" modal header="Új hozzárendelés" :style="{ width: '34rem' }">
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
            <Button label="Mégse" severity="secondary" :disabled="loading || disabled" @click="visible = false" />
            <Button label="Mentés" icon="pi pi-check" :loading="loading" :disabled="disabled" @click="submit" />
        </template>
    </Dialog>
</template>
