<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { Select } from "primevue";
import Service from "@/services/WorkShiftService.js";

const props = defineProps({
    modelValue: [String, Number, null],
    placeholder: { type: String, default: "" },
    inputId: { type: String, default: null },
    filter: { type: Boolean, default: null },
});

const emit = defineEmits(["update:modelValue"]);

const shifts = ref([]);
const isLoading = ref(false);

const model = computed({
    get: () => props.modelValue,
    set: (val) => emit("update:modelValue", val),
});

const shouldUseFilter = computed(() => {
    if (props.filter === true) return true;
    if (props.filter === false) return false;
    return shifts.value.length > 10;
});

const loadShifts = async () => {
    isLoading.value = true;

    try {
        const response = await Service.getToSelect();
        shifts.value = Array.isArray(response?.data) ? response.data : [];
    } catch {
        shifts.value = [];
    } finally {
        isLoading.value = false;
    }
};

onMounted(loadShifts);
watch(
    () => props.modelValue,
    () => {
        if (shifts.value.length === 0) {
            loadShifts();
        }
    }
);
</script>

<template>
    <Select
        v-model="model"
        :options="shifts"
        optionLabel="name"
        optionValue="id"
        :placeholder="placeholder"
        class="w-full"
        :loading="isLoading"
        :filter="shouldUseFilter"
        showClear
        :inputId="inputId"
    />
</template>
