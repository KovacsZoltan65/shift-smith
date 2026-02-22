<script setup>
import { computed, reactive, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import EmployeeSelector from "@/Components/Selectors/EmployeeSelector.vue";
import WorkShiftSelector from "@/Components/Selectors/WorkShiftSelector.vue";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    companyId: { type: Number, required: true },
    scheduleId: { type: Number, required: true },
    selectedDates: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "submit"]);

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const form = reactive({
    employee_ids: [],
    work_shift_id: null,
});

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;
        form.employee_ids = [];
        form.work_shift_id = null;
    }
);

const addEmployee = (id) => {
    const value = Number(id ?? 0);
    if (!value) return;
    if (!form.employee_ids.includes(value)) {
        form.employee_ids = [...form.employee_ids, value];
    }
};

const removeEmployee = (id) => {
    form.employee_ids = form.employee_ids.filter((x) => x !== id);
};

const submit = () => {
    emit("submit", {
        work_schedule_id: props.scheduleId,
        work_shift_id: Number(form.work_shift_id ?? 0),
        employee_ids: form.employee_ids.map((x) => Number(x)),
        dates: [...props.selectedDates],
    });
};
</script>

<template>
    <Dialog v-model:visible="visible" modal header="Bulk hozzárendelés" :style="{ width: '40rem' }">
        <div class="space-y-3">
            <div class="rounded bg-slate-50 p-2 text-sm">
                Kijelölt napok: <b>{{ selectedDates.length }}</b>
            </div>

            <div>
                <label class="mb-1 block text-sm">Műszak</label>
                <WorkShiftSelector v-model="form.work_shift_id" :companyId="companyId" />
            </div>

            <div>
                <label class="mb-1 block text-sm">Dolgozók hozzáadása</label>
                <EmployeeSelector :companyId="companyId" @update:modelValue="addEmployee" />
            </div>

            <div class="flex flex-wrap gap-2">
                <span
                    v-for="id in form.employee_ids"
                    :key="id"
                    class="inline-flex items-center gap-1 rounded bg-sky-100 px-2 py-1 text-xs text-sky-800"
                >
                    #{{ id }}
                    <button type="button" class="font-bold" @click="removeEmployee(id)">x</button>
                </span>
            </div>
        </div>

        <template #footer>
            <Button label="Mégse" severity="secondary" :disabled="loading" @click="visible = false" />
            <Button label="Mentés" icon="pi pi-check" :loading="loading" @click="submit" />
        </template>
    </Dialog>
</template>
