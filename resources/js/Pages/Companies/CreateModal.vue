<script setup>
import { reactive, ref, watch } from "vue";
import { trans } from "laravel-vue-i18n";

import CompanyFields from "./Partials/CompanyFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";

//import { usePermissions } from "@/composables/usePermissions";
//const { has } = usePermissions();

const props = defineProps({
    modelValue: Boolean,
    canCreate: { type: Boolean, default: false },
    endpointBase: { type: String, default: "/companies" },
    tenantGroupFieldEnabled: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue", "saved"]);

const loading = ref(false);
const errors = reactive({});
const form = ref({
    tenant_group_id: null,
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
            tenant_group_id: null,
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
        const res = await csrfFetch(props.endpointBase, {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
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

        const body = await res.json().catch(() => null);
        emit("saved", body?.message ?? trans("companies.messages.created_success"));
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
        :header="$t('companies.dialogs.create_title')"
        :style="{ width: '520px' }"
        @update:visible="emit('update:modelValue', $event)"
    >
        <div class="space-y-4">
            <div v-if="errors._global" class="rounded border p-2 text-sm">
                {{ errors._global }}
            </div>

            <CompanyFields
                v-model="form"
                :errors="errors"
                :disabled="loading"
                :tenant-group-field-enabled="props.tenantGroupFieldEnabled"
            />
        </div>

        <template #footer>
            <!-- CANCEL -->
            <Button
                :label="$t('common.cancel')"
                severity="secondary"
                :disabled="loading"
                @click="close"
            />

            <!-- MENTÉS -->
            <Button
                :label="$t('common.save')"
                icon="pi pi-check"
                :loading="loading"
                :disabled="loading || !props.canCreate"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
