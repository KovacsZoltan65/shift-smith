<script setup>
import { computed, ref, watch } from "vue";
import Select from "primevue/select";
import EmployeeService from "@/services/EmployeeService.js";

const props = defineProps({
    modelValue: [Number, String, null],
    companyId: { type: [Number, String, null], default: null },
    employeeId: { type: [Number, String, null], default: null },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: "Válassz felettest..." },
});

const emit = defineEmits(["update:modelValue"]);
const options = ref([]);
const loading = ref(false);

const model = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const load = async () => {
    if (!props.companyId) {
        options.value = [];
        return;
    }

    loading.value = true;
    try {
        const { data } = await EmployeeService.getToSelect({
            company_id: Number(props.companyId),
            only_active: 1,
        });

        const list = Array.isArray(data) ? data : [];
        options.value = list.filter((row) => Number(row.id) !== Number(props.employeeId || 0));
    } catch {
        options.value = [];
    } finally {
        loading.value = false;
    }
};

watch(() => [props.companyId, props.employeeId], load, { immediate: true });
</script>

<template>
    <Select
        v-model="model"
        :options="options"
        optionLabel="name"
        optionValue="id"
        :loading="loading"
        :placeholder="placeholder"
        :disabled="disabled"
        class="w-full"
        filter
        showClear
    />
</template>

