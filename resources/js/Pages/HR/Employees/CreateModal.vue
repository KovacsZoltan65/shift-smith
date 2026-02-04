<script setup>
import { ref, watch, computed } from "vue";

import Dialog from "primevue/dialog";
import Button from "primevue/button";

import EmployeeFields from "@/Pages/HR/Employees/Partials/EmployeeFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    defaultCompanyId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const saving = ref(false);
const errors = ref({});

const form = ref({
    company_id: props.defaultCompanyId ? Number(props.defaultCompanyId) : null,
    first_name: "",
    last_name: "",
    email: "",
    phone: "",
    position: "",
    hired_at: null,
    active: true,
});

const reset = () => {
    errors.value = {};
    saving.value = false;
    form.value = {
        company_id: props.defaultCompanyId ? Number(props.defaultCompanyId) : null,
        first_name: "",
        last_name: "",
        email: "",
        phone: "",
        position: "",
        hired_at: null,
        active: true,
    };
};

watch(
    () => props.modelValue,
    (open) => {
        if (open) reset();
    }
);

const toPayload = () => {
    const hiredAt =
        form.value.hired_at instanceof Date
            ? form.value.hired_at.toISOString().slice(0, 10)
            : null;

    return {
        company_id: form.value.company_id,
        first_name: form.value.first_name?.trim() || "",
        last_name: form.value.last_name?.trim() || "",
        email: form.value.email?.trim() || null,
        phone: form.value.phone?.trim() || null,
        position: form.value.position?.trim() || null,
        hired_at: hiredAt,
        active: !!form.value.active,
    };
};

const submit = async () => {
    saving.value = true;
    errors.value = {};

    try {
        const res = await csrfFetch("/employees", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(toPayload()),
        });

        if (res.status === 422) {
            const body = await res.json().catch(() => ({}));
            errors.value = body?.errors ?? {};
            saving.value = false;
            return;
        }

        if (!res.ok) {
            let msg = `Mentés sikertelen (HTTP ${res.status})`;
            try {
                const body = await res.json();
                msg = body?.message || msg;
            } catch (_) {}
            throw new Error(msg);
        }

        visible.value = false;
        emit("saved", "Dolgozó létrehozva.");
    } catch (e) {
        errors.value = { _general: e?.message || "Ismeretlen hiba" };
    } finally {
        saving.value = false;
    }
};

const close = () => {
    visible.value = false;
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        header="Új dolgozó"
        :style="{ width: '720px' }"
        :closable="!saving"
        :dismissableMask="!saving"
        @hide="reset"
    >
        <div v-if="errors?._general" class="mb-4 border p-3">
            <div class="font-semibold">Hiba</div>
            <div class="text-sm">{{ errors._general }}</div>
        </div>

        <EmployeeFields v-model="form" :errors="errors" :disabled="saving" />

        <template #footer>
            <!-- CANCEL -->
            <Button
                label="Mégse"
                severity="secondary"
                text
                :disabled="saving"
                @click="close"
            />
            <!-- SAVE -->
            <Button
                label="Mentés"
                icon="pi pi-check"
                :loading="saving"
                :disabled="saving"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
