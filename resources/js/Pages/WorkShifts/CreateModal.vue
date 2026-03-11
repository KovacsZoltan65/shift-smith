<script setup>
import { computed, reactive, ref, watch } from "vue";

import WorkShiftFields from "./Partials/WorkShiftFields.vue";
import WorkShiftService from "@/services/WorkShiftService.js";

const props = defineProps({
    modelValue: Boolean,
    canCreate: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue", "saved"]);

const open = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const loading = ref(false);
const errors = reactive({});
const createEmptyForm = () => ({
    name: "",
    start_time: null,
    end_time: null,
    work_time_minutes: null,
    break_minutes: null,
    breaks: [],
    active: true,
});
const form = ref(createEmptyForm());

const reset = () => {
    loading.value = false;
    form.value = createEmptyForm();
    Object.keys(errors).forEach((k) => delete errors[k]);
};

watch(
    () => props.modelValue,
    (open) => {
        if (!open) {
            reset();
            return;
        }

        reset();
    },
);

const close = () => {
    open.value = false;
};

const submit = async () => {
    if (!props.canCreate) return;

    loading.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    try {
        const { data } = await WorkShiftService.storeWorkShift(form.value);
        emit("saved", data?.message ?? "Műszak sikeresen létrehozva.");
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
        header="Új műszak"
        :style="{ width: '520px' }"
        :closable="!loading"
        :dismissableMask="!loading"
        @hide="reset"
        data-testid="work-shifts-create-modal"
    >
        <div class="space-y-4">
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
                :disabled="loading || !props.canCreate"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
