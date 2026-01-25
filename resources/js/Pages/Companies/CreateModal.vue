<script setup>
import { reactive, ref, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";

import CompanyFields from "./Partials/CompanyFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({ modelValue: Boolean });
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

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;

        form.value = {
            name: "",
            email: "",
            address: "",
            phone: "",
            active: true,
        };

        Object.keys(errors).forEach((k) => delete errors[k]);
    }
);

const close = () => emit("update:modelValue", false);

const submit = async () => {
    loading.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    try {
        const res = await csrfFetch("/companies", {
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

        emit("saved");
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
        header="Új cég"
        :style="{ width: '520px' }"
        @update:visible="emit('update:modelValue', $event)"
    >
        <div class="space-y-4">
            <div v-if="errors._global" class="rounded border p-2 text-sm">
                {{ errors._global }}
            </div>

            <CompanyFields v-model="form" :errors="errors" :disabled="loading" />
        </div>

        <template #footer>
            <Button
                label="Mégse"
                severity="secondary"
                :disabled="loading"
                @click="close"
            />
            <Button label="Mentés" :loading="loading" @click="submit" />
        </template>
    </Dialog>
</template>
