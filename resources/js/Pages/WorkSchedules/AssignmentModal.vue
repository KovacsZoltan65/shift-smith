<script setup>
import { computed, reactive, ref, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import DatePicker from "primevue/datepicker";
import { useToast } from "primevue/usetoast";
import EmployeeSelector from "@/Components/Selectors/EmployeeSelector.vue";
import WorkShiftSelector from "@/Components/Selectors/WorkShiftSelector.vue";
import WorkShiftAssignmentService from "@/services/WorkShiftAssignmentService";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    workSchedule: { type: Object, default: null },
    canAssign: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "saved"]);
const toast = useToast();

const saving = ref(false);
const errors = reactive({});
const form = ref({
    employee_id: null,
    work_shift_id: null,
    date: null,
});

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const scheduleId = computed(() => Number(props.workSchedule?.id ?? 0));
const companyId = computed(() => Number(props.workSchedule?.company_id ?? 0));
const hasSchedule = computed(() => scheduleId.value > 0);

const reset = () => {
    form.value = {
        employee_id: null,
        work_shift_id: null,
        date: props.workSchedule?.date_from ?? null,
    };
    Object.keys(errors).forEach((k) => delete errors[k]);
};

watch(
    () => props.modelValue,
    (open) => {
        if (open) reset();
    }
);

const toYmd = (d) => {
    if (!d) return null;
    if (typeof d === "string" && /^\d{4}-\d{2}-\d{2}$/.test(d)) return d;
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return null;
    return dt.toISOString().slice(0, 10);
};

const submit = async () => {
    if (!hasSchedule.value || !props.canAssign) return;
    const shiftId = Number(form.value.work_shift_id ?? 0);
    if (!shiftId) {
        errors.work_shift_id = "A műszak kiválasztása kötelező.";
        return;
    }

    saving.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    try {
        await WorkShiftAssignmentService.assign(shiftId, {
            employee_id: Number(form.value.employee_id ?? 0),
            work_schedule_id: scheduleId.value,
            date: toYmd(form.value.date),
        });

        toast.add({
            severity: "success",
            summary: "Siker",
            detail: "Hozzárendelés mentve.",
            life: 2500,
        });
        emit("saved");
        visible.value = false;
    } catch (e) {
        const bag = e?.response?.data?.errors ?? null;
        if (bag && typeof bag === "object") {
            for (const key of Object.keys(bag)) {
                errors[key] = bag[key]?.[0] ?? "Hiba";
            }
            toast.add({
                severity: "warn",
                summary: "Validációs hiba",
                detail: Object.values(errors).join(" | "),
                life: 4500,
            });
            return;
        }

        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.response?.data?.message || e?.message || "Mentés sikertelen.",
            life: 4000,
        });
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        header="Beosztás hozzárendelés"
        :style="{ width: '48rem' }"
    >
        <div v-if="!hasSchedule" class="text-sm text-gray-600">
            Nincs kiválasztott beosztás.
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="mb-1 block text-sm">Dolgozó</label>
                <EmployeeSelector
                    v-model="form.employee_id"
                    :companyId="companyId"
                    placeholder="Dolgozó..."
                />
                <small v-if="errors.employee_id" class="text-red-600">{{ errors.employee_id }}</small>
            </div>

            <div>
                <label class="mb-1 block text-sm">Műszak</label>
                <WorkShiftSelector
                    v-model="form.work_shift_id"
                    :companyId="companyId"
                    placeholder="Műszak..."
                />
                <small v-if="errors.work_shift_id" class="text-red-600">{{ errors.work_shift_id }}</small>
            </div>

            <div>
                <label class="mb-1 block text-sm">Dátum</label>
                <DatePicker v-model="form.date" dateFormat="yy-mm-dd" showIcon class="w-full" />
                <small v-if="errors.date" class="text-red-600">{{ errors.date }}</small>
            </div>
        </div>

        <template #footer>
            <Button label="Mégse" severity="secondary" :disabled="saving" @click="visible = false" />
            <Button
                label="Hozzárendelés"
                icon="pi pi-check"
                :loading="saving"
                :disabled="saving || !canAssign"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
