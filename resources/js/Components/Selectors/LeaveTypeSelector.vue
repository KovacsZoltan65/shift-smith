<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { Select } from "primevue";
import LeaveTypeService from "@/services/LeaveTypeService.js";

const props = defineProps({
    modelValue: [String, Number, null],
    activeOnly: { type: Boolean, default: true },
    categories: { type: Array, default: () => ["leave", "sick_leave"] },
    placeholder: { type: String, default: "Szabadság típus..." },
});

const emit = defineEmits(["update:modelValue"]);

const options = ref([]);
const loading = ref(false);

const model = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const loadOptions = async () => {
    loading.value = true;
    try {
        const { data } = await LeaveTypeService.selector({
            active: props.activeOnly ? 1 : undefined,
            category: props.categories,
        });

        options.value = Array.isArray(data?.data) ? data.data : [];
    } catch (_) {
        options.value = [];
    } finally {
        loading.value = false;
    }
};

onMounted(loadOptions);

watch(() => [props.activeOnly, props.categories], loadOptions, { deep: true });
</script>

<template>
    <Select
        v-model="model"
        :options="options"
        optionLabel="name"
        optionValue="id"
        :placeholder="placeholder"
        class="w-full"
        :loading="loading"
        filter
        showClear
    />
</template>
