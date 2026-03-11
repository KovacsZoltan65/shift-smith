<script setup>
import { reactive, ref, watch } from "vue";

import WorkShiftFields from "./Partials/WorkShiftFields.vue";
import WorkShiftService from "@/services/WorkShiftService.js";

const props = defineProps({
    modelValue: Boolean,
    canCreate: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue", "saved"]);

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

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;

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
    }
);

const close = () => emit("update:modelValue", false);

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
        :visible="modelValue"
        modal
        header="Új műszak"
        :style="{ width: '520px' }"
        @update:visible="emit('update:modelValue', $event)"
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
