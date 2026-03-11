<script setup>
import { computed, onMounted, ref, watch } from "vue";
import PositionService from "@/services/PositionService.js";

const props = defineProps({
    modelValue: [String, Number, null],
    companyId: { type: [String, Number, null], default: null },
    onlyActive: { type: Boolean, default: true },
    placeholder: { type: String, default: "Pozíció..." },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const options = ref([]);
const loading = ref(false);

const model = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const load = async () => {
    options.value = [];
    const companyId = Number(props.companyId ?? 0);
    if (!companyId) return;

    loading.value = true;
    try {
        const { data } = await PositionService.getToSelect({
            company_id: companyId,
            only_active: props.onlyActive ? 1 : 0,
        });
        options.value = Array.isArray(data) ? data : [];
    } finally {
        loading.value = false;
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
        :options="options"
        optionLabel="name"
        optionValue="id"
        :placeholder="placeholder"
        class="w-full"
        :loading="loading"
        :disabled="disabled"
        :filter="options.length > 10"
        showClear
    />
</template>
