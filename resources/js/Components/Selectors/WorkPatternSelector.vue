<script setup>
import { computed, onMounted, ref, watch } from "vue";
import WorkPatternService from "@/services/WorkPatternService.js";

const props = defineProps({
    modelValue: [String, Number, null],
    companyId: { type: [String, Number, null], default: null },
    onlyActive: { type: Boolean, default: true },
    placeholder: { type: String, default: "Munkarend..." },
    filter: { type: Boolean, default: null },
    inputId: { type: String, default: null },
});

const emit = defineEmits(["update:modelValue"]);

const workPatterns = ref([]);
const isLoading = ref(false);

const model = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const shouldUseFilter = computed(() => {
    if (props.filter === true) return true;
    if (props.filter === false) return false;
    return workPatterns.value.length > 10;
});

const load = async () => {
    workPatterns.value = [];

    const companyId = Number(props.companyId ?? 0);
    if (!companyId) return;

    isLoading.value = true;
    try {
        const { data } = await WorkPatternService.getToSelect({
            company_id: companyId,
            only_active: props.onlyActive ? 1 : 0,
        });
        workPatterns.value = data ?? [];
    } finally {
        isLoading.value = false;
    }
};

watch(
    () => props.companyId,
    async () => {
        model.value = null;
        await load();
    }
);

onMounted(load);
</script>

<template>
    <Select
        v-model="model"
        :options="workPatterns"
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
