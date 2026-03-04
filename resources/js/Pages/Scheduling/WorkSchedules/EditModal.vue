<script setup>
import { computed, ref, watch } from "vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
import WorkScheduleFields from "@/Pages/Scheduling/WorkSchedules/Partials/WorkScheduleFields.vue";
import WorkScheduleService from "@/services/WorkScheduleService";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    workSchedule: { type: Object, default: null },
    canUpdate: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const open = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const loading = ref(false);
const saving = ref(false);
const errors = ref({});
const form = ref({
    company_id: null,
    name: "",
    date_from: "",
    date_to: "",
    status: "draft",
});

const reset = () => {
    loading.value = false;
    saving.value = false;
    errors.value = {};
    form.value = {
        company_id: null,
        name: "",
        date_from: "",
        date_to: "",
        status: "draft",
    };
};

const close = () => {
    open.value = false;
};

const load = async (id) => {
    loading.value = true;
    errors.value = {};

    try {
        const response = await WorkScheduleService.getWorkSchedule(id, {
            company_id: Number(props.workSchedule?.company_id ?? 0),
        });
        const row = response?.data?.data ?? {};

        form.value = {
            company_id: row.company_id ?? props.workSchedule?.company_id ?? null,
            name: row.name ?? "",
            date_from: row.date_from ?? "",
            date_to: row.date_to ?? "",
            status: row.status ?? "draft",
        };
    } catch (error) {
        errors.value._global = error?.message ?? "Betöltés sikertelen.";
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.modelValue,
    async (isOpen) => {
        if (!isOpen) {
            reset();
            return;
        }

        const id = Number(props.workSchedule?.id ?? 0);
        if (!id) {
            errors.value._global = "Nincs kiválasztott munkabeosztás.";
            return;
        }

        await load(id);
    },
);

const submit = async () => {
    const id = Number(props.workSchedule?.id ?? 0);
    if (!id) {
        errors.value._global = "Nincs kiválasztott munkabeosztás.";
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        const response = await csrfFetch(`/work-schedules/${id}`, {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({
                company_id: Number(form.value.company_id ?? 0),
                name: form.value.name,
                date_from: form.value.date_from,
                date_to: form.value.date_to,
                status: form.value.status,
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

        emit("saved", "Munkabeosztás frissítve.");
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
        header="Munkabeosztás szerkesztése"
        :style="{ width: '42rem' }"
        :closable="!saving"
        :dismissableMask="!saving"
        @hide="reset"
    >
        <div v-if="loading" class="text-sm text-slate-500">Betöltés...</div>

        <template v-else>
            <WorkScheduleFields v-model="form" :disabled="saving" :errors="errors" />
            <div v-if="errors?._global" class="mt-2 text-sm text-red-600">
                {{ errors._global }}
            </div>
        </template>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Mégse" severity="secondary" :disabled="saving" @click="close" />
                <Button
                    label="Mentés"
                    icon="pi pi-check"
                    :loading="saving"
                    :disabled="saving || loading || !canUpdate"
                    @click="submit"
                />
            </div>
        </template>
    </Dialog>
</template>
