<!-- resources/js/Pages/WorkSchedules/CreateModal.vue -->
<script setup>
import { reactive, ref, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";

import WorkScheduleFields from "./Partials/WorkScheduleFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    modelValue: Boolean,
    canCreate: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue", "saved"]);

const loading = ref(false);
const errors = reactive({});

const form = ref({
    company_id: null,
    name: "",
    date_from: null,
    date_to: null,
    status: "draft",
    notes: "",
});

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;

        form.value = {
            company_id: null,
            name: "",
            date_from: null,
            date_to: null,
            status: "draft",
            notes: "",
        };

        Object.keys(errors).forEach((k) => delete errors[k]);
    }
);

const close = () => emit("update:modelValue", false);

const toYmd = (d) => {
    if (!d) return null;
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return null;
    return dt.toISOString().slice(0, 10);
};

const submit = async () => {
    if (!props.canCreate) return;

    loading.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    try {
        const payload = {
            ...form.value,
            date_from: toYmd(form.value.date_from),
            date_to: toYmd(form.value.date_to),
            notes: form.value.notes || null,
        };

        const res = await csrfFetch("/work_schedules", {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify(payload),
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

        emit("saved", "Beosztás létrehozva.");
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
        header="Új beosztás"
        :style="{ width: '620px' }"
        @update:visible="emit('update:modelValue', $event)"
        data-testid="work_schedules-create-modal"
    >
        <div class="space-y-4">
            <div v-if="errors._global" class="rounded border p-2 text-sm">
                {{ errors._global }}
            </div>

            <WorkScheduleFields v-model="form" :errors="errors" :disabled="loading" />
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
