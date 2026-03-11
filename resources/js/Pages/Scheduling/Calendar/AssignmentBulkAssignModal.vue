<script setup>
import { computed, reactive, watch } from "vue";
import WorkShiftSelector from "@/Components/Selectors/WorkShiftSelector.vue";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    companyId: { type: Number, required: true },
    scheduleId: { type: Number, required: true },
    selectedDates: { type: Array, default: () => [] },
    employeeOptions: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "submit"]);

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const form = reactive({
    employee_ids: [],
    work_shift_id: null,
    errors: {},
});

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;
        form.employee_ids = [];
        form.work_shift_id = null;
        form.errors = {};
    }
);

watch(
    () => form.employee_ids,
    () => {
        if (form.errors.employee_ids && form.employee_ids.length > 0) {
            form.errors = { ...form.errors, employee_ids: null };
        }
    },
    { deep: true },
);

const employeeCountLabel = computed(() => `Kijelölve: ${form.employee_ids.length} fő`);

const allEmployeeIds = computed(() =>
    (props.employeeOptions ?? [])
        .map((employee) => Number(employee?.id ?? 0))
        .filter((id) => id > 0),
);

const selectAllEmployees = () => {
    form.employee_ids = [...allEmployeeIds.value];
};

const clearEmployees = () => {
    form.employee_ids = [];
};

const submit = () => {
    if (!form.employee_ids.length) {
        form.errors = {
            ...form.errors,
            employee_ids: "Válassz legalább egy dolgozót.",
        };
        return;
    }

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
                <WorkShiftSelector v-model="form.work_shift_id" :companyId="companyId" :disabled="disabled" />
            </div>

            <div>
                <div class="mb-1 flex items-center justify-between gap-3">
                    <label class="block text-sm">Dolgozók</label>
                    <span class="text-xs text-slate-500">{{ employeeCountLabel }}</span>
                </div>
                <MultiSelect
                    v-model="form.employee_ids"
                    :options="employeeOptions"
                    optionLabel="name"
                    optionValue="id"
                    class="w-full"
                    display="chip"
                    filter
                    :maxSelectedLabels="3"
                    selectedItemsLabel="{0} dolgozó kiválasztva"
                    :disabled="disabled"
                    placeholder="Dolgozók kiválasztása"
                />
                <div class="mt-2 flex items-center gap-2">
                    <Button
                        type="button"
                        label="Összes kijelölése"
                        size="small"
                        severity="secondary"
                        outlined
                        :disabled="disabled || allEmployeeIds.length === 0"
                        @click="selectAllEmployees"
                    />
                    <Button
                        type="button"
                        label="Kijelölés törlése"
                        size="small"
                        severity="secondary"
                        text
                        :disabled="disabled || form.employee_ids.length === 0"
                        @click="clearEmployees"
                    />
                </div>
                <div v-if="form.errors.employee_ids" class="mt-2 text-sm text-red-600">
                    {{ form.errors.employee_ids }}
                </div>
            </div>
        </div>

        <template #footer>
            <Button label="Mégse" severity="secondary" :disabled="loading || disabled" @click="visible = false" />
            <Button
                label="Mentés"
                icon="pi pi-check"
                :loading="loading"
                :disabled="disabled || form.employee_ids.length === 0"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
