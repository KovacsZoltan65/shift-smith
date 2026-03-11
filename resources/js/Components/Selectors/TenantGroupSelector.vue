<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { trans } from "laravel-vue-i18n";

import TenantGroupService from "@/services/TenantGroupService.js";

const props = defineProps({
    modelValue: [String, Number, null],
    placeholder: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const selectedTenantGroup = ref(null);
const tenantGroups = ref([]);
const isLoading = ref(false);

const options = computed(() => tenantGroups.value);
const effectivePlaceholder = computed(
    () => props.placeholder || trans("companies.form.tenant_group_placeholder"),
);

const syncFromModel = () => {
    const modelId = props.modelValue === null ? null : Number(props.modelValue);

    if (
        modelId &&
        options.value.some((tenantGroup) => Number(tenantGroup.id) === modelId)
    ) {
        selectedTenantGroup.value = modelId;
        return;
    }

    if (!modelId) {
        selectedTenantGroup.value = null;
    }
};

watch(selectedTenantGroup, (value) => {
    emit("update:modelValue", value);
});

watch(
    () => props.modelValue,
    () => {
        syncFromModel();
    },
);

watch(
    options,
    () => {
        syncFromModel();
    },
    { immediate: true },
);

onMounted(async () => {
    isLoading.value = true;

    try {
        const response = await TenantGroupService.fetch({
            active: true,
            sort_field: "name",
            sort_direction: "asc",
            per_page: 100,
        });

        tenantGroups.value = (response?.data?.data ?? []).map((tenantGroup) => ({
            id: Number(tenantGroup.id),
            name: tenantGroup.name,
            code: tenantGroup.code,
            label: tenantGroup.code
                ? `${tenantGroup.name} (${tenantGroup.code})`
                : tenantGroup.name,
        }));
    } finally {
        isLoading.value = false;
    }
});
</script>

<template>
    <Select
        v-model="selectedTenantGroup"
        :options="options"
        optionLabel="label"
        optionValue="id"
        :placeholder="effectivePlaceholder"
        :loading="isLoading"
        class="w-full"
        filter
        showClear
    />
</template>
