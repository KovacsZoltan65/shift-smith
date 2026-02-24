<script setup>
import { computed, reactive, ref, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";

import WorkShiftFields from "./Partials/WorkShiftFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";

/**
 * NOTE:
 * - Itt nem kell usePermissions() a komponensben, mert a parent (Index.vue) már átadja a canUpdate boolean-t.
 * - Így tesztbarátabb és nem függ az Inertia page props-tól.
 */
const props = defineProps({
    modelValue: Boolean,
    workShift: { type: Object, default: null },
    canUpdate: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue", "saved"]);

const loading = ref(false);
const errors = reactive({});

// ⚠️ igazítsd a WorkShift mezőkhöz
const form = ref({
    name: "",
    start_time: null,
    end_time: null,
    work_time_minutes: null,
    break_minutes: null,
    active: true,
});

const hasWorkShift = computed(() => !!props.workShift?.id);

const fill = () => {
    form.value = {
        name: props.workShift?.name ?? "",
        start_time: props.workShift?.start_time ? String(props.workShift.start_time).slice(0, 5) : null,
        end_time: props.workShift?.end_time ? String(props.workShift.end_time).slice(0, 5) : null,
        work_time_minutes: props.workShift?.work_time_minutes ?? null,
        break_minutes: props.workShift?.break_minutes ?? null,
        active: Boolean(props.workShift?.active ?? true),
    };

    Object.keys(errors).forEach((k) => delete errors[k]);
};

watch(
    () => props.modelValue,
    (open) => open && fill()
);

watch(
    () => props.workShift,
    () => props.modelValue && fill()
);

const close = () => emit("update:modelValue", false);

const normalizeTime = (t) => {
    if (!t) return null;
    const s = String(t).trim();
    if (!s) return null;
    return s.length === 5 ? `${s}:00` : s;
};

const submit = async () => {
    if (!hasWorkShift.value) return;
    if (!props.canUpdate) return;

    loading.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    try {
        const res = await csrfFetch(`/work-shifts/${props.workShift.id}`, {
            method: "PUT",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({
                ...form.value,
                start_time: normalizeTime(form.value.start_time),
                end_time: normalizeTime(form.value.end_time),
            }),
        });

        if (res.status === 422) {
            const body = await res.json();
            const bag = body?.errors ?? {};
            for (const k of Object.keys(bag)) errors[k] = bag[k]?.[0] ?? "Hiba";
            return;
        }

        if (!res.ok) {
            const body = await res.json().catch(() => null);
            throw new Error(body?.message ?? `HTTP ${res.status}`);
        }

        emit("saved", "Műszak módosítva.");
        close();
    } catch (e) {
        errors._global = e?.message ?? "Ismeretlen hiba";
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Dialog
        :visible="modelValue"
        modal
        header="Műszak szerkesztése"
        :style="{ width: '520px' }"
        @update:visible="emit('update:modelValue', $event)"
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
            <!-- CANCEL -->
            <Button
                label="Mégse"
                severity="secondary"
                :disabled="loading"
                @click="close"
            />

            <!-- SAVE -->
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
