<script setup>
import { computed, reactive, ref, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";

import CompanyFields from "./Partials/CompanyFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";

import { usePermissions } from "@/composables/usePermissions";
const { has } = usePermissions();

const props = defineProps({
    modelValue: Boolean,
    company: { type: Object, default: null },
    canUpdate: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue", "saved"]);

const loading = ref(false);
const errors = reactive({});
const form = ref({
    name: "",
    email: "",
    address: "",
    phone: "",
    active: true,
});

const hasCompany = computed(() => !!props.company?.id);

const fill = () => {
    form.value = {
        name: props.company?.name ?? "",
        email: props.company?.email ?? "",
        address: props.company?.address ?? "",
        phone: props.company?.phone ?? "",
        active: Boolean(props.company?.active ?? true),
    };

    Object.keys(errors).forEach((k) => delete errors[k]);
};

watch(
    () => props.modelValue,
    (open) => open && fill()
);

watch(
    () => props.company,
    () => props.modelValue && fill()
);

const close = () => emit("update:modelValue", false);

const submit = async () => {
    if (!hasCompany.value) return;

    loading.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    try {
        const res = await csrfFetch(`/companies/${props.company.id}`, {
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

        // Backend adapter:
        // - régi: response lehetett { id, ... }
        // - új: { message, data: { ... } }
        const body = await res.json().catch(() => null);
        const company = body?.data ?? body;

        emit("saved", company);
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
        header="Cég szerkesztése"
        :style="{ width: '520px' }"
        @update:visible="emit('update:modelValue', $event)"
    >
        <div v-if="!hasCompany" class="text-sm text-gray-600">
            Nincs kiválasztott cég.
        </div>

        <div v-else class="space-y-4">
            <div v-if="errors._global" class="rounded border p-2 text-sm">
                {{ errors._global }}
            </div>

            <CompanyFields v-model="form" :errors="errors" :disabled="loading" />
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
                :loading="loading"
                :disabled="!hasCompany || !props.canUpdate"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
