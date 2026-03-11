<script setup>
import { computed, reactive, ref, watch } from "vue";

import WorkShiftFields from "./Partials/WorkShiftFields.vue";
import WorkShiftService from "@/services/WorkShiftService.js";

const props = defineProps({
    modelValue: Boolean,
    workShift: { type: Object, default: null },
    canUpdate: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue", "saved"]);

const open = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const loading = ref(false);
const errors = reactive({});
const form = ref({
    name: "",
    start_time: null,
    end_time: null,
    work_time_minutes: null,
    break_minutes: null,
    breaks: [],
    active: true,
});

const hasWorkShift = computed(() => !!props.workShift?.id);

const reset = () => {
    loading.value = false;
    form.value = {
        name: "",
        start_time: null,
        end_time: null,
        work_time_minutes: null,
        break_minutes: null,
        breaks: [],
        active: true,
    };
    Object.keys(errors).forEach((k) => delete errors[k]);
};

const fill = () => {
    form.value = {
        name: props.workShift?.name ?? "",
        start_time: props.workShift?.start_time ? String(props.workShift.start_time).slice(0, 5) : null,
        end_time: props.workShift?.end_time ? String(props.workShift.end_time).slice(0, 5) : null,
        work_time_minutes: props.workShift?.work_time_minutes ?? null,
        break_minutes: props.workShift?.break_minutes ?? null,
        breaks: Array.isArray(props.workShift?.breaks)
            ? props.workShift.breaks.map((row) => ({
                  break_start_time: row?.break_start_time ? String(row.break_start_time).slice(0, 5) : null,
                  break_end_time: row?.break_end_time ? String(row.break_end_time).slice(0, 5) : null,
              }))
            : [],
        active: Boolean(props.workShift?.active ?? true),
    };

    Object.keys(errors).forEach((k) => delete errors[k]);
};

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) {
            reset();
            return;
        }

        fill();
    },
);

watch(
    () => props.workShift,
    () => props.modelValue && fill(),
);

const close = () => {
    open.value = false;
};

const submit = async () => {
    if (!hasWorkShift.value || !props.canUpdate) return;

    loading.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    try {
        const { data } = await WorkShiftService.updateWorkShift(props.workShift.id, form.value);
        emit("saved", data?.message ?? "Műszak sikeresen frissítve.");
        close();
    } catch (e) {
        const bag = WorkShiftService.extractErrors(e);
        if (bag) {
            Object.keys(bag).forEach((k) => {
                errors[k] = bag[k]?.[0] ?? "Hiba";
            });
            return;
        }

        errors._global = e?.response?.data?.message || e?.message || "Ismeretlen hiba";
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Dialog
        v-model:visible="open"
        modal
        header="Műszak szerkesztése"
        :style="{ width: '520px' }"
        :closable="!loading"
        :dismissableMask="!loading"
        @hide="reset"
        data-testid="work-shifts-edit-modal"
    >
        <div v-if="!hasWorkShift" class="text-sm text-gray-600">
            Nincs kiválasztott műszak.
        </div>

        <div v-else class="space-y-4">
            <div v-if="errors._global" class="rounded border p-2 text-sm">
                {{ errors._global }}
            </div>

            <WorkShiftFields v-model="form" :errors="errors" :disabled="loading" />
        </div>

        <template #footer>
            <Button
                label="Mégse"
                severity="secondary"
                :disabled="loading"
                @click="close"
            />

            <Button
                label="Mentés"
                icon="pi pi-check"
                :loading="loading"
                :disabled="loading || !hasWorkShift || !props.canUpdate"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
