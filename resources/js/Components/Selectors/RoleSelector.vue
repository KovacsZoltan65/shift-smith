<script setup>
import { ref, computed, onMounted, watch } from "vue";
import Service from "../../services/Auth/RoleService.js";

const props = defineProps({
    modelValue: [String, Number, Object, null],
    filter: {
        type: Boolean,
        default: null,
    },
    placeholder: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const selectedRole = ref(null);
const companies = ref([]);
const isLoading = ref(false);

const shouldUseFilter = computed(() => {
    if (props.filter === true) return true;
    if (props.filter === false) return false;
    return companies.value.length > 10;
});

// ⚡ Választás visszaadása parentnek
watch(selectedRole, (val) => {
    emit("update:modelValue", val);
});

onMounted(async () => {
    isLoading.value = true;

    try {
        const response = await Service.getCompaniesToSelect();
        companies.value = response.data;

        // 👇 Itt állítjuk csak be, ha már minden adat megvan
        if (props.modelValue && companies.value.some((p) => p.id === props.modelValue)) {
            selectedRole.value = props.modelValue;
        } else if (companies.value.length === 1) {
            selectedRole.value = companies.value[0].id;
        }
    } catch (err) {
        console.error("Nem sikerült a cégek lekérdezése:", err);
    } finally {
        isLoading.value = false;
    }
});
</script>
<template>
    <Select
        v-model="selectedRole"
        :options="companies"
        optionLabel="name"
        optionValue="id"
        :placeholder="props.placeholder"
        class="mr-2 w-full"
        :loading="isLoading"
        :filter="shouldUseFilter"
        showClear
    />
</template>
