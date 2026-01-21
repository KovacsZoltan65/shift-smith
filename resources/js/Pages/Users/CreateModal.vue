<script setup>
import { reactive, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import UserFields from "./Partials/UserFields.vue";

import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({ modelValue: Boolean });
const emit = defineEmits(["update:modelValue", "saved"]);

const page = usePage();

const loading = ref(false);
const errors = reactive({});
const form = ref({ name: "", email: "" });

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;
        form.value = { name: "", email: "" };
        Object.keys(errors).forEach((k) => delete errors[k]);
    }
);

const close = () => emit("update:modelValue", false);

//const csrf = () =>
//    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";

const submit = async () => {
    loading.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    try {
        const res = await csrfFetch("/users", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(form.value),
        });
        console.log("res", res);
        /*
        const res = await fetch("/users", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrf(),
                Accept: "application/json",
            },
            body: JSON.stringify(form.value),
        });
        */

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
        header="Új felhasználó"
        :style="{ width: '520px' }"
        @update:visible="emit('update:modelValue', $event)"
    >
        <div class="space-y-4">
            <div v-if="errors._global" class="rounded border p-2 text-sm">
                {{ errors._global }}
            </div>

            <UserFields v-model="form" :errors="errors" :disabled="loading" />

            <div class="rounded border p-3 text-sm text-gray-700">
                Mentés után automatikusan kiküldjük a jelszó beállító emailt.
            </div>
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
