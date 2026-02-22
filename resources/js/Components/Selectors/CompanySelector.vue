<script setup>
import { ref, computed, onMounted, watch } from "vue";
import Service from "@/services/CompanyService.js";
import { Select } from "primevue";

const props = defineProps({
    modelValue: [String, Number, Object, null],
    onlyWithEmployees: {
        type: Boolean,
        default: false,
    },
    filter: {
        type: Boolean,
        default: null,
    },
    placeholder: { type: String, default: "" },
    options: {
        type: Array,
        default: null,
    },
});

const emit = defineEmits(["update:modelValue"]);

const selectedCompany = ref(null);
const companies = ref([]);
const isLoading = ref(false);
const hasStaticOptions = computed(
    () => Array.isArray(props.options) && props.options.length >= 0,
);

const effectiveCompanies = computed(() =>
    hasStaticOptions.value ? props.options ?? [] : companies.value,
);

const shouldUseFilter = computed(() => {
    if (props.filter === true) return true;
    if (props.filter === false) return false;
    return effectiveCompanies.value.length > 10;
});

// ⚡ Választás visszaadása parentnek
watch(selectedCompany, (val) => {
    emit("update:modelValue", val);
});

const syncFromModel = () => {
    const modelId = props.modelValue === null ? null : Number(props.modelValue);

    if (
        modelId &&
        effectiveCompanies.value.some((p) => Number(p.id) === modelId)
    ) {
        selectedCompany.value = modelId;
    } else if (effectiveCompanies.value.length === 1) {
        selectedCompany.value = Number(effectiveCompanies.value[0].id);
    }
};

watch(
    () => props.modelValue,
    () => {
        syncFromModel();
    },
);

watch(
    effectiveCompanies,
    () => {
        syncFromModel();
    },
    { immediate: true },
);

onMounted(async () => {
    if (hasStaticOptions.value) {
        syncFromModel();
        return;
    }

    isLoading.value = true;

    try {
        const params = {};

        if (props.onlyWithEmployees) params.only_with_employees = 1;

        const response = await Service.getToSelect(params);
        companies.value = response.data;
        syncFromModel();
        /*
        const response = await Service.getToSelect();
        companies.value = response.data;

        if (props.onlyWithEmployees) params.only_with_employees = 1;

        // 👇 Itt állítjuk csak be, ha már minden adat megvan
        if (props.modelValue && companies.value.some((p) => p.id === props.modelValue)) {
            selectedCompany.value = props.modelValue;
        } else if (companies.value.length === 1) {
            selectedCompany.value = companies.value[0].id;
        }
        */
    } catch (err) {
        console.error("Nem sikerült a cégek lekérdezése:", err);
    } finally {
        isLoading.value = false;
    }
});
</script>
<template>
    <Select
        v-model="selectedCompany"
        :options="effectiveCompanies"
        optionLabel="name"
        optionValue="id"
        :placeholder="props.placeholder"
        class="mr-2 w-full"
        :loading="isLoading"
        :filter="shouldUseFilter"
        showClear
    />
</template>
