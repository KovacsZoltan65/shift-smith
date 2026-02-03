<script setup>
import { ref, computed, onMounted, watch } from "vue";
import Service from "@/services/PermissionService.js";
import Select from "primevue/select";

const props = defineProps({
    modelValue: { type: [String, Number, Object], default: null },
    filter: { type: Boolean, default: null },
    placeholder: { type: String, default: "" },
    coerceNumber: { type: Boolean, default: true }, // ha a backend string ID-t ad
});

const emit = defineEmits(["update:modelValue"]);

const selectedPermission = ref(null);
const permissions = ref([]);
const isLoading = ref(false);

const shouldUseFilter = computed(() => {
    if (props.filter === true) return true;
    if (props.filter === false) return false;
    return permissions.value.length > 10;
});

// a szülőtől érkező v-model változás tükrözése (pl. clear form)
watch(
    () => props.modelValue,
    (val) => {
        if (val == null) {
            selectedPermission.value = null;
        } else if (permissions.value.some((p) => p.id == val)) {
            selectedPermission.value = val; // laza összehasonlítás a string/number miatt
        }
    }
);

// a gyerekből küldjük vissza a v-model-t
watch(selectedPermission, (val) => emit("update:modelValue", val));

onMounted(async () => {
    isLoading.value = true;
    try {
        const response = await Service.getToSelect(); // elvárt: [{id,name}]
        console.log("response", response);
        permissions.value = (Array.isArray(response.data) ? response.data : []).map(
            (p) => ({
                id: props.coerceNumber ? Number(p.id) : p.id,
                name: p.name ?? p.label ?? String(p.id),
            })
        );

        // kezdeti kiválasztás
        if (
            props.modelValue != null &&
            permissions.value.some((p) => p.id == props.modelValue)
        ) {
            selectedPermission.value = props.modelValue;
        } else if (permissions.value.length === 1) {
            selectedPermission.value = permissions.value[0].id;
        }
    } catch (err) {
        console.error("Nem sikerült a jogosultságok lekérdezése:", err);
        permissions.value = [];
    } finally {
        isLoading.value = false;
    }
});
</script>
<template>
    <Select
        v-model="selectedPermission"
        :options="permissions"
        optionLabel="name"
        optionValue="id"
        :placeholder="props.placeholder"
        class="mr-2 w-full"
        :loading="isLoading"
        :filter="shouldUseFilter"
        :filterFields="['name']"
        showClear
    />
</template>
