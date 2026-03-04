<script setup>
import { computed, ref, watch } from "vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
import WorkScheduleFields from "@/Pages/Scheduling/WorkSchedules/Partials/WorkScheduleFields.vue";
import WorkScheduleService from "@/services/WorkScheduleService";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    companyId: { type: [Number, String, null], default: null },
    canCreate: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const open = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const saving = ref(false);
const errors = ref({});
const form = ref({
    name: "",
    date_from: "",
    date_to: "",
    status: "draft",
});

const reset = () => {
    errors.value = {};
    form.value = {
        name: "",
        date_from: "",
        date_to: "",
        status: "draft",
    };
};

watch(
    () => open.value,
    (isOpen) => {
        if (isOpen) {
            reset();
        }
    },
);

const close = () => {
    open.value = false;
};

const submit = async () => {
    saving.value = true;
    errors.value = {};

    try {
        const response = await csrfFetch("/work-schedules", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({
                company_id: Number(props.companyId ?? 0),
                ...form.value,
            }),
        });

        if (response.status === 422) {
            const body = await response.json().catch(() => ({}));
            const bag = body?.errors ?? {};
            errors.value = Object.fromEntries(
                Object.entries(bag).map(([key, value]) => [
                    key,
                    Array.isArray(value) ? value[0] : String(value),
                ]),
            );
            return;
        }

        if (!response.ok) {
            throw new Error(`Mentés sikertelen (HTTP ${response.status})`);
        }

        emit("saved", "Munkabeosztás létrehozva.");
        close();
    } catch (error) {
        errors.value._global = error?.message ?? "Mentés sikertelen.";
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Dialog
        v-model:visible="open"
        modal
        header="Új munkabeosztás"
        :style="{ width: '42rem' }"
        :closable="!saving"
        :dismissableMask="!saving"
    >
        <WorkScheduleFields v-model="form" :disabled="saving" :errors="errors" />

        <div v-if="errors?._global" class="mt-2 text-sm text-red-600">
            {{ errors._global }}
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Mégse" severity="secondary" :disabled="saving" @click="close" />
                <Button
                    label="Mentés"
                    icon="pi pi-check"
                    :loading="saving"
                    :disabled="saving || !canCreate"
                    @click="submit"
                />
            </div>
        </template>
    </Dialog>
</template>
