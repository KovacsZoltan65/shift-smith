<script setup>
import { computed, onMounted, ref, watch } from "vue";
import Select from "primevue/select";
import LeaveCategoryService from "@/services/LeaveCategoryService.js";

const props = defineProps({
    modelValue: [String, null],
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: "Szabadság kategória..." },
    onlyActive: { type: Boolean, default: true },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "update:options", "update:selectedOption"]);

const options = ref([]);
const loading = ref(false);

const model = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const emitSelectedOption = () => {
    const selected = options.value.find((option) => option.code === model.value) ?? null;
    emit("update:selectedOption", selected);
};

const loadOptions = async () => {
    loading.value = true;

    try {
        const { data } = await LeaveCategoryService.selector({
            only_active: props.onlyActive ? 1 : 0,
        });

        options.value = Array.isArray(data?.data) ? data.data : [];
        emit("update:options", options.value);
        emitSelectedOption();
    } catch (_) {
        options.value = [];
        emit("update:options", []);
        emitSelectedOption();
    } finally {
        loading.value = false;
    }
};

onMounted(loadOptions);
watch(() => props.onlyActive, loadOptions);
watch(() => model.value, emitSelectedOption);
</script>

<template>
    <Select
        v-model="model"
        :options="options"
        optionLabel="name"
        optionValue="code"
        :placeholder="placeholder"
        class="w-full"
        :loading="loading"
        :disabled="disabled"
        :invalid="invalid"
        :filter="options.length > 10"
        showClear
        emptyMessage="Nincs elérhető kategória."
        emptyFilterMessage="Nincs találat."
    />
</template>
