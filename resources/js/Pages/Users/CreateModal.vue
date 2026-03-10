<script setup>
import { reactive, ref, watch } from "vue";
import { trans } from "laravel-vue-i18n";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import UserFields from "./Partials/UserFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    modelValue: Boolean,
    companies: { type: Array, default: () => [] },
    defaultCompanyId: { type: [Number, null], default: null },
});
const emit = defineEmits(["update:modelValue", "saved"]);

const loading = ref(false);
const errors = reactive({});
const form = ref({ name: "", email: "", company_id: props.defaultCompanyId ?? null });

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;
        form.value = { name: "", email: "", company_id: props.defaultCompanyId ?? null };
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

        if (res.status === 422) {
            const body = await res.json();
            const bag = body?.errors ?? {};
            for (const k of Object.keys(bag)) errors[k] = bag[k]?.[0] ?? trans("common.error");
            return;
        }
        if (!res.ok) {
            const body = await res.json().catch(() => null);
            throw new Error(body?.message ?? `HTTP ${res.status}`);
        }

        emit("saved");
        close();
    } catch (e) {
        errors._global = e?.message ?? trans("common.unknown_error");
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Dialog
        :visible="modelValue"
        modal
        :header="trans('users.dialogs.create_title')"
        :style="{ width: '520px' }"
        @update:visible="emit('update:modelValue', $event)"
    >
        <div class="space-y-4">
            <div v-if="errors._global" class="rounded border p-2 text-sm">
                {{ errors._global }}
            </div>

            <UserFields
                v-model="form"
                :errors="errors"
                :disabled="loading"
                :companies="companies"
            />

            <div class="rounded border p-3 text-sm text-gray-700">
                {{ trans("users.messages.setup_email_notice") }}
            </div>
        </div>

        <template #footer>
            <Button
                :label="trans('common.cancel')"
                severity="secondary"
                :disabled="loading"
                @click="close"
            />
            <Button :label="trans('common.save')" :loading="loading" @click="submit" />
        </template>
    </Dialog>
</template>
