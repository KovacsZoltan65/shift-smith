<script setup>
import { computed, onMounted, ref, watch } from "vue";
import SickLeaveCategoryService from "@/services/SickLeaveCategoryService.js";

const props = defineProps({
    modelValue: [String, Number, null],
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: "Betegszabadság kategória..." },
    onlyActive: { type: Boolean, default: true },
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
        const { data } = await SickLeaveCategoryService.selector({
            only_active: props.onlyActive ? 1 : 0,
        });

        options.value = Array.isArray(data?.data) ? data.data : [];
    } catch (_) {
        options.value = [];
    } finally {
        loading.value = false;
    }
};

onMounted(loadOptions);
watch(() => props.onlyActive, loadOptions);
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
        emptyMessage="Nincs elérhető kategória."
        emptyFilterMessage="Nincs találat."
    />
</template>
