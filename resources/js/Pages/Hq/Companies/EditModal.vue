<script setup>
import SharedEditModal from "@/Pages/Companies/EditModal.vue";

const props = defineProps({
    modelValue: Boolean,
    company: { type: Object, default: null },
    canUpdate: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "saved"]);
</script>

<template>
    <SharedEditModal
        :modelValue="props.modelValue"
        :company="props.company"
        :canUpdate="props.canUpdate"
        endpointBase="/hq/companies"
        :tenantGroupFieldEnabled="true"
        :tenantGroupReadOnly="true"
        @update:modelValue="emit('update:modelValue', $event)"
        @saved="emit('saved', $event)"
    />
</template>
