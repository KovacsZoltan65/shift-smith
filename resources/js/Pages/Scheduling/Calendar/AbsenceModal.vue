<script setup>
import { computed, reactive, ref, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import DatePicker from "primevue/datepicker";
import InputText from "primevue/inputtext";
import EmployeeSelector from "@/Components/Selectors/EmployeeSelector.vue";
import LeaveTypeSelector from "@/Components/Selectors/LeaveTypeSelector.vue";
import SickLeaveCategorySelector from "@/Components/Selectors/SickLeaveCategorySelector.vue";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    absence: { type: Object, default: null },
    companyId: { type: Number, required: true },
    loading: { type: Boolean, default: false },
    defaultRange: { type: Object, default: () => ({ from: null, to: null }) },
});

const emit = defineEmits(["update:modelValue", "submit", "delete"]);

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const form = reactive({
    employee_id: null,
    leave_type_id: null,
    sick_leave_category_id: null,
    date_range: null,
    note: "",
});
const selectedLeaveType = ref(null);

const isEdit = computed(() => Number(props.absence?.extendedProps?.absence_id ?? 0) > 0);
const header = computed(() => (isEdit.value ? "Távollét módosítása" : "Távollét jelölése"));
const isSickLeaveSelected = computed(() => selectedLeaveType.value?.category === "sick_leave");

const toDate = (value) => {
    if (!value) return null;
    const date = value instanceof Date ? value : new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
};

const toYmd = (value) => {
    const date = toDate(value);
    if (!date) return null;
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
};

watch(
    () => [props.modelValue, props.absence, props.defaultRange],
    () => {
        if (!props.modelValue) return;

        if (isEdit.value) {
            form.employee_id = Number(props.absence?.extendedProps?.employee_id ?? 0) || null;
            form.leave_type_id = Number(props.absence?.extendedProps?.leave_type_id ?? 0) || null;
            form.sick_leave_category_id = Number(props.absence?.extendedProps?.sick_leave_category_id ?? 0) || null;
            form.date_range = [
                toDate(props.absence?.start),
                toDate(props.absence?.end ? new Date(new Date(props.absence.end).getTime() - 86400000) : props.absence?.start),
            ];
            form.note = String(props.absence?.extendedProps?.note ?? "");
            selectedLeaveType.value = form.leave_type_id
                ? {
                    id: form.leave_type_id,
                    name: String(props.absence?.extendedProps?.leave_type_name ?? ""),
                    category: String(props.absence?.extendedProps?.category ?? ""),
                }
                : null;
            return;
        }

        form.employee_id = null;
        form.leave_type_id = null;
        form.sick_leave_category_id = null;
        form.date_range = props.defaultRange?.from
            ? [toDate(props.defaultRange.from), toDate(props.defaultRange.to ?? props.defaultRange.from)]
            : null;
        form.note = "";
        selectedLeaveType.value = null;
    },
    { immediate: true, deep: true },
);

watch(isSickLeaveSelected, (value) => {
    if (!value) {
        form.sick_leave_category_id = null;
    }
});

const submit = () => {
    const range = Array.isArray(form.date_range) ? form.date_range : [form.date_range, form.date_range];

    emit("submit", {
        id: Number(props.absence?.extendedProps?.absence_id ?? 0),
        payload: {
            employee_id: Number(form.employee_id ?? 0),
            leave_type_id: Number(form.leave_type_id ?? 0),
            ...(isSickLeaveSelected.value && form.sick_leave_category_id
                ? { sick_leave_category_id: Number(form.sick_leave_category_id) }
                : {}),
            date_from: toYmd(range[0]),
            date_to: toYmd(range[1] ?? range[0]),
            note: form.note?.trim() || null,
        },
    });
};

const submitDelete = () => {
    emit("delete", Number(props.absence?.extendedProps?.absence_id ?? 0));
};
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="header" :style="{ width: '36rem' }">
        <div class="grid grid-cols-1 gap-3">
            <div>
                <label class="mb-1 block text-sm">Dolgozó</label>
                <EmployeeSelector v-model="form.employee_id" :companyId="companyId" />
            </div>

            <div>
                <label class="mb-1 block text-sm">Szabadság típus</label>
                <LeaveTypeSelector
                    v-model="form.leave_type_id"
                    :categories="['leave', 'sick_leave']"
                    @update:selectedOption="selectedLeaveType = $event"
                />
            </div>

            <div v-if="isSickLeaveSelected">
                <label class="mb-1 block text-sm">Betegszabadság kategória</label>
                <SickLeaveCategorySelector
                    v-model="form.sick_leave_category_id"
                    :disabled="loading"
                />
            </div>

            <div>
                <label class="mb-1 block text-sm">Időszak</label>
                <DatePicker v-model="form.date_range" selectionMode="range" dateFormat="yy-mm-dd" showIcon class="w-full" />
            </div>

            <div>
                <label class="mb-1 block text-sm">Megjegyzés</label>
                <InputText v-model="form.note" class="w-full" maxlength="500" />
            </div>
        </div>

        <template #footer>
            <Button v-if="isEdit" label="Törlés" icon="pi pi-trash" severity="danger" text :disabled="loading" @click="submitDelete" />
            <Button label="Mégse" severity="secondary" :disabled="loading" @click="visible = false" />
            <Button label="Mentés" icon="pi pi-check" :loading="loading" @click="submit" />
        </template>
    </Dialog>
</template>
