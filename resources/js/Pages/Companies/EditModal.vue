<script setup>
import { computed, reactive, ref, watch } from "vue";
import { trans } from "laravel-vue-i18n";

import CompanyFields from "./Partials/CompanyFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";

import { usePermissions } from "@/composables/usePermissions";
const { has } = usePermissions();

const props = defineProps({
    modelValue: Boolean,
    company: { type: Object, default: null },
    canUpdate: { type: Boolean, default: false },
    endpointBase: { type: String, default: "/companies" },
    tenantGroupFieldEnabled: { type: Boolean, default: false },
    tenantGroupReadOnly: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue", "saved"]);

const loading = ref(false);
const errors = reactive({});
const form = ref({
    tenant_group_id: null,
    tenant_group_name: "",
    tenant_group_code: "",
    name: "",
    email: "",
    address: "",
    phone: "",
    active: true,
});

const hasCompany = computed(() => !!props.company?.id);

const fill = () => {
    form.value = {
        tenant_group_id: props.company?.tenantGroupId ?? props.company?.tenant_group_id ?? null,
        tenant_group_name: props.company?.tenantGroupName ?? props.company?.tenant_group_name ?? "",
        tenant_group_code: props.company?.tenantGroupCode ?? props.company?.tenant_group_code ?? "",
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
        const res = await csrfFetch(`${props.endpointBase}/${props.company.id}`, {
            method: "PUT",
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
        emit("saved", body?.message ?? trans("companies.messages.updated_success"));
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
        :header="$t('companies.dialogs.edit_title')"
        :style="{ width: '520px' }"
        @update:visible="emit('update:modelValue', $event)"
    >
        <div v-if="!hasCompany" class="text-sm text-gray-600">
            {{ $t("companies.dialogs.none_selected") }}
        </div>

        <div v-else class="space-y-4">
            <div v-if="errors._global" class="rounded border p-2 text-sm">
                {{ errors._global }}
            </div>

            <CompanyFields
                v-model="form"
                :errors="errors"
                :disabled="loading"
                :tenant-group-field-enabled="props.tenantGroupFieldEnabled"
                :tenant-group-read-only="props.tenantGroupReadOnly"
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

            <!-- SAVE -->
            <Button
                :label="$t('common.save')"
                :loading="loading"
                :disabled="!hasCompany || !props.canUpdate"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
