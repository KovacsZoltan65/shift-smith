<!-- resources/js/Pages/WorkSchedules/DeleteModal.vue -->
<script setup>
import { computed, reactive, ref, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    modelValue: Boolean,
    workSchedule: { type: Object, default: null },
    canDelete: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue", "deleted"]);

const loading = ref(false);
const errors = reactive({});

const hasSchedule = computed(() => !!props.workSchedule?.id);
const label = computed(
    () =>
        props.workSchedule?.name ??
        (props.workSchedule?.id ? `#${props.workSchedule.id}` : "")
);
const published = computed(() => (props.workSchedule?.status ?? "") === "published");

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;
        Object.keys(errors).forEach((k) => delete errors[k]);
    }
);

const close = () => emit("update:modelValue", false);

const submit = async () => {
    if (!hasSchedule.value) return;
    if (!props.canDelete) return;

    // UI tiltás
    if (published.value) {
        errors._global = "Published státuszú beosztás nem törölhető.";
        return;
    }

    loading.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    try {
        const res = await csrfFetch(`/work_schedules/${props.workSchedule.id}`, {
            method: "DELETE",
            headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        });

        if (!res.ok) {
            let msg = `Törlés sikertelen (HTTP ${res.status})`;
            try {
                const body = await res.json();
                msg = body?.message || msg;
            } catch (_) {}
            throw new Error(msg);
        }

        emit("deleted", "Beosztás törölve.");
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
        header="Beosztás törlése"
        :style="{ width: '520px' }"
        @update:visible="emit('update:modelValue', $event)"
        data-testid="work_schedules-delete-modal"
    >
        <div v-if="!hasSchedule" class="text-sm text-gray-600">
            Nincs kiválasztott beosztás.
        </div>

        <div v-else class="space-y-3">
            <div v-if="errors._global" class="rounded border p-2 text-sm">
                {{ errors._global }}
            </div>

            <div class="text-sm">
                Biztosan törlöd ezt?
                <div class="mt-1 font-semibold">{{ label }}</div>
            </div>

            <div
                v-if="published"
                class="rounded border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-900"
            >
                Published státuszú beosztás törlése tiltva.
            </div>
        </div>

        <template #footer>
            <Button
                label="Mégse"
                severity="secondary"
                :disabled="loading"
                @click="close"
            />

            <Button
                label="Törlés"
                icon="pi pi-trash"
                severity="danger"
                :loading="loading"
                :disabled="loading || !hasSchedule || !props.canDelete || published"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
