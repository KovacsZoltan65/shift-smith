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
const roles = ref([]);
const isLoading = ref(false);

const shouldUseFilter = computed(() => {
    if (props.filter === true) return true;
    if (props.filter === false) return false;
    return roles.value.length > 10;
});

// ⚡ Választás visszaadása parentnek
watch(selectedRole, (val) => {
    emit("update:modelValue", val);
});

onMounted(async () => {
    isLoading.value = true;

    try {
        const response = await Service.getToSelect();
        const items = Array.isArray(response?.data) ? response.data : response?.data?.data ?? [];
        roles.value = items;

        // 👇 Itt állítjuk csak be, ha már minden adat megvan
        if (props.modelValue && roles.value.some((p) => p.id === props.modelValue)) {
            selectedRole.value = props.modelValue;
        } else if (roles.value.length === 1) {
            selectedRole.value = roles.value[0].id;
        }
    } catch (err) {
        console.error("Nem sikerült a role-ok lekérdezése:", err);
    } finally {
        isLoading.value = false;
    }
});
</script>
<template>
    <Select
        v-model="selectedRole"
        :options="roles"
        optionLabel="name"
        optionValue="id"
        :placeholder="props.placeholder"
        class="mr-2 w-full"
        :loading="isLoading"
        :filter="shouldUseFilter"
        showClear
    />
</template>
