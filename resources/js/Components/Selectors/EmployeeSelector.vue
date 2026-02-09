<script setup>
import { ref, computed, onMounted } from "vue";
import Service from "@/services/EmployeeService.js";
import { Select } from "primevue";

const props = defineProps({
    modelValue: [String, Number, null],
    filter: { type: Boolean, default: null },
    placeholder: { type: String, default: "" },
    inputId: { type: String, default: null },
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

onMounted(async () => {
    isLoading.value = true;
    try {
        const { data } = await Service.getToSelect();
        employees.value = data; // [{id, full_name}]
    } finally {
        isLoading.value = false;
    }
});
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
    />
</template>
