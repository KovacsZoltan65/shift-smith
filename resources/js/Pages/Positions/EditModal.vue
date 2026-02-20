<script setup>
import { computed, reactive, ref, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";

import PositionFields from "./Partials/PositionFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    modelValue: Boolean,
    position: { type: Object, default: null },
    companyId: { type: [Number, String, null], default: null },
    canUpdate: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue", "saved"]);

const loading = ref(false);
const errors = reactive({});
const form = ref({
    company_id: null,
    name: "",
    description: "",
    active: true,
});

const hasPosition = computed(() => !!props.position?.id);

const fill = () => {
    form.value = {
        company_id: props.position?.company_id ?? (props.companyId ? Number(props.companyId) : null),
        name: props.position?.name ?? "",
        description: props.position?.description ?? "",
        active: !!(props.position?.active ?? true),
    };

    Object.keys(errors).forEach((k) => delete errors[k]);
};

watch(
    () => props.modelValue,
    (open) => open && fill()
);

watch(
    () => props.position,
    () => props.modelValue && fill()
);

const close = () => emit("update:modelValue", false);

const submit = async () => {
    if (!hasPosition.value) return;

    loading.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    try {
        const res = await csrfFetch(`/positions/${props.position.id}`, {
            method: "PUT",
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

        const body = await res.json().catch(() => null);
        const position = body?.data ?? body;
        emit("saved", position);
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
        header="Pozíció szerkesztése"
        :style="{ width: '520px' }"
        @update:visible="emit('update:modelValue', $event)"
    >
        <div v-if="!hasPosition" class="text-sm text-gray-600">
            Nincs kiválasztott pozíció.
        </div>

        <div v-else class="space-y-4">
            <div v-if="errors._global" class="rounded border p-2 text-sm">
                {{ errors._global }}
            </div>

            <PositionFields v-model="form" :errors="errors" :disabled="loading" />
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
                :loading="loading"
                :disabled="!hasPosition || !props.canUpdate"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
