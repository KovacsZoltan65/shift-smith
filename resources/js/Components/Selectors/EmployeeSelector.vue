<script setup>
import { ref, computed, onMounted, watch } from "vue";
import Service from "@/services/EmployeeService.js";
import { Select } from "primevue";

const props = defineProps({
    modelValue: [String, Number, null],
    companyId: { type: [String, Number, null], default: null },
    onlyActive: { type: Boolean, default: true },
    filter: { type: Boolean, default: null },
    placeholder: { type: String, default: "" },
    inputId: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const employees = ref([]);
const isLoading = ref(false);

const model = computed({
    get: () => props.modelValue,
    set: (val) => emit("update:modelValue", val),
});

const shouldUseFilter = computed(() => {
    if (props.filter === true) return true;
    if (props.filter === false) return false;
    return employees.value.length > 10;
});

const loadEmployees = async () => {
    isLoading.value = true;
    try {
        const baseParams = {};

        if (props.companyId !== null && props.companyId !== "" && props.companyId !== undefined) {
            baseParams.company_id = Number(props.companyId);
        }

        const { data } = await Service.getToSelect({
            ...baseParams,
            only_active: props.onlyActive ? 1 : 0,
        });

        const list = Array.isArray(data) ? data : [];

        // Ha csak aktívra szűrve üres, fallbackként kérjük le az összeset.
        if (props.onlyActive && list.length === 0) {
            const fallback = await Service.getToSelect({
                ...baseParams,
                only_active: 0,
            });
            employees.value = Array.isArray(fallback?.data) ? fallback.data : [];
            return;
        }

        employees.value = list;
    } catch {
        employees.value = [];
    } finally {
        isLoading.value = false;
    }
};

onMounted(loadEmployees);

watch(
    () => [props.companyId, props.onlyActive],
    () => {
        loadEmployees();
    }
);
</script>
<template>
    <Select
        v-model="model"
        :options="employees"
        optionLabel="name"
        optionValue="id"
        :placeholder="placeholder"
        class="mr-2 w-full"
        :loading="isLoading"
        :filter="shouldUseFilter"
        showClear
        :inputId="inputId"
        :disabled="disabled"
    />
</template>
