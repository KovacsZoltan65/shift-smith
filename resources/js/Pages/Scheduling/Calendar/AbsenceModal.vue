<script setup>
import { computed, reactive, ref, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import DatePicker from "primevue/datepicker";
import InputText from "primevue/inputtext";
import MultiSelect from "primevue/multiselect";
import LeaveTypeSelector from "@/Components/Selectors/LeaveTypeSelector.vue";
import SickLeaveCategorySelector from "@/Components/Selectors/SickLeaveCategorySelector.vue";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    absence: { type: Object, default: null },
    companyId: { type: Number, required: true },
    employeeOptions: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    defaultRange: { type: Object, default: () => ({ from: null, to: null }) },
});

const emit = defineEmits(["update:modelValue", "submit", "delete"]);

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const form = reactive({
    employee_ids: [],
    leave_type_id: null,
    sick_leave_category_id: null,
    date_range: null,
    note: "",
    errors: {},
});
const selectedLeaveType = ref(null);

const isEdit = computed(() => Number(props.absence?.extendedProps?.absence_id ?? 0) > 0);
const header = computed(() => (isEdit.value ? "Távollét módosítása" : "Távollét jelölése"));
const isSickLeaveSelected = computed(() => selectedLeaveType.value?.category === "sick_leave");
const employeeCountLabel = computed(() => `Kijelölve: ${form.employee_ids.length} fő`);
const allEmployeeIds = computed(() =>
    (props.employeeOptions ?? [])
        .map((employee) => Number(employee?.id ?? 0))
        .filter((id) => id > 0),
);

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
            form.employee_ids = [Number(props.absence?.extendedProps?.employee_id ?? 0)].filter(Boolean);
            form.leave_type_id = Number(props.absence?.extendedProps?.leave_type_id ?? 0) || null;
            form.sick_leave_category_id = Number(props.absence?.extendedProps?.sick_leave_category_id ?? 0) || null;
            form.date_range = [
                toDate(props.absence?.start),
                toDate(props.absence?.end ? new Date(new Date(props.absence.end).getTime() - 86400000) : props.absence?.start),
            ];
            form.note = String(props.absence?.extendedProps?.note ?? "");
            form.errors = {};
            selectedLeaveType.value = form.leave_type_id
                ? {
                    id: form.leave_type_id,
                    name: String(props.absence?.extendedProps?.leave_type_name ?? ""),
                    category: String(props.absence?.extendedProps?.category ?? ""),
                }
                : null;
            return;
        }

        form.employee_ids = [];
        form.leave_type_id = null;
        form.sick_leave_category_id = null;
        form.date_range = props.defaultRange?.from
            ? [toDate(props.defaultRange.from), toDate(props.defaultRange.to ?? props.defaultRange.from)]
            : null;
        form.note = "";
        form.errors = {};
        selectedLeaveType.value = null;
    },
    { immediate: true, deep: true },
);

watch(isSickLeaveSelected, (value) => {
    if (!value) {
        form.sick_leave_category_id = null;
    }
});

watch(
    () => form.employee_ids,
    () => {
        if (form.errors.employee_ids && form.employee_ids.length > 0) {
            form.errors = { ...form.errors, employee_ids: null };
        }
    },
    { deep: true },
);

const selectAllEmployees = () => {
    if (isEdit.value) return;
    form.employee_ids = [...allEmployeeIds.value];
};

const submit = () => {
    if (!form.employee_ids.length) {
        form.errors = {
            ...form.errors,
            employee_ids: "Válassz legalább egy dolgozót.",
        };
        return;
    }

    const range = Array.isArray(form.date_range) ? form.date_range : [form.date_range, form.date_range];

    emit("submit", {
        id: Number(props.absence?.extendedProps?.absence_id ?? 0),
        payload: {
            ...(isEdit.value
                ? { employee_id: Number(form.employee_ids[0] ?? 0) }
                : { employee_ids: form.employee_ids.map((id) => Number(id)) }),
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
                    :disabled="loading || isEdit"
                    placeholder="Dolgozók kiválasztása"
                />
                <div v-if="!isEdit" class="mt-2 flex items-center gap-2">
                    <Button
                        type="button"
                        label="Összes kijelölése"
                        size="small"
                        severity="secondary"
                        outlined
                        :disabled="loading || allEmployeeIds.length === 0"
                        @click="selectAllEmployees"
                    />
                </div>
                <div v-if="form.errors.employee_ids" class="mt-2 text-sm text-red-600">
                    {{ form.errors.employee_ids }}
                </div>
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
            <Button label="Mentés" icon="pi pi-check" :loading="loading" :disabled="!form.employee_ids.length" @click="submit" />
        </template>
    </Dialog>
</template>
