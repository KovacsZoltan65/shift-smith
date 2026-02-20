<script setup>
import { computed, ref, watch } from "vue";
import Select from "primevue/select";
import WorkShiftService from "@/services/WorkShiftService";

const props = defineProps({
    modelValue: { type: [Number, String, null], default: null },
    companyId: { type: [Number, String, null], default: null },
    placeholder: { type: String, default: "Műszak..." },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const options = ref([]);
const loading = ref(false);

const selected = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v === null || v === undefined ? null : Number(v)),
});

const load = async () => {
    loading.value = true;
    try {
        const params = {
            only_active: 1,
            ...(props.companyId ? { company_id: Number(props.companyId) } : {}),
        };
        const { data } = await WorkShiftService.getToSelect(params);
        options.value = Array.isArray(data) ? data : [];
    } finally {
        loading.value = false;
    }
};

watch(() => props.companyId, load, { immediate: true });
</script>

<template>
    <Select
        v-model="selected"
        class="w-full"
        :options="options"
        optionLabel="name"
        optionValue="id"
        :placeholder="placeholder"
        :loading="loading"
        :disabled="disabled"
        showClear
    />
</template>
