<script setup>
import { reactive, ref, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";

import WorkShiftFields from "./Partials/WorkShiftFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    modelValue: Boolean,
    canCreate: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue", "saved"]);

const loading = ref(false);
const errors = reactive({});

// ⚠️ form mezők: igazítsd a WorkShift migration / request mezőihez
// Tipikus: name, start_date, end_date, active
const form = ref({
    name: "",
    start_date: null, // pl. "08:00" (string) vagy null
    end_date: null, // pl. "16:00"
    active: true,
});

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;

        // reset
        form.value = {
            name: "",
            start_date: null,
            end_date: null,
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
        const res = await csrfFetch("/work-shifts", {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify(form.value),
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

        emit("saved", "Műszak létrehozva.");
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
                :disabled="loading || !props.canCreate"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
